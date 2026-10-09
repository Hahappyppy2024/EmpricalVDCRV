// Deterministic local "model" adapter. Synthesises a stable response for any
// prompt so the application can run offline without external LLM credentials.
import crypto from 'node:crypto';
import { config } from '../config.js';
import { getDb } from '../db/connection.js';

const RETRIEVAL_KEYWORDS = [
  'rag', 'retrieval', 'document', 'knowledge', 'collection', 'cite', 'citation',
  'search', 'vector', 'embedding', 'index'
];

function approxTokens(text) {
  if (!text) return 0;
  return Math.max(1, Math.ceil(text.length / 4));
}

function deterministicHash(input) {
  return crypto.createHash('sha256').update(String(input)).digest('hex').slice(0, 12);
}

function buildCitations(context) {
  if (!context || !context.chunks || context.chunks.length === 0) return [];
  return context.chunks.slice(0, 3).map((c, idx) => ({
    kind: 'retrieval_chunk',
    ref: `chunk:${c.id}`,
    snippet: c.content.slice(0, 160),
    rank: idx + 1
  }));
}

function lookupRetrievedChunks(collectionId, query) {
  if (!collectionId) return [];
  const db = getDb();
  const coll = db.prepare(
    `SELECT id, owner_id, name FROM retrieval_collections WHERE id = ?`
  ).get(collectionId);
  if (!coll) return [];
  const terms = String(query || '').toLowerCase().split(/\W+/).filter(t => t.length >= 3);
  if (terms.length === 0) {
    return db.prepare(`
      SELECT id, content FROM retrieval_chunks
      WHERE collection_id = ?
      ORDER BY chunk_index ASC LIMIT 3
    `).all(coll.id);
  }
  const rows = db.prepare(`
    SELECT id, content FROM retrieval_chunks WHERE collection_id = ?
  `).all(coll.id);
  const scored = rows.map(row => {
    const lower = row.content.toLowerCase();
    let score = 0;
    for (const t of terms) if (lower.includes(t)) score += 1;
    return { ...row, _score: score };
  }).filter(r => r._score > 0).sort((a, b) => b._score - a._score);
  return scored.slice(0, 3);
}

function synthesizeResponse({ prompt, systemPrompt, context, modelId, temperature, safetyMode }) {
  const retrieved = context?.chunks && context.chunks.length > 0
    ? context.chunks
    : (context?.collectionId ? lookupRetrievedChunks(context.collectionId, prompt) : []);

  const citations = buildCitations({ chunks: retrieved });
  const lower = String(prompt || '').toLowerCase();
  const usesRetrieval = RETRIEVAL_KEYWORDS.some(k => lower.includes(k));

  let body;
  if (retrieved.length > 0) {
    body = `Here is what I found in your retrieval collection:\n\n` +
      retrieved.map((c, i) => `${i + 1}. ${c.content}`).join('\n') +
      `\n\nLet me know if you would like more detail on any of these.`;
  } else if (usesRetrieval) {
    body = `No matching chunks were found for your query. Add documents to a retrieval collection to ground responses.`;
  } else {
    const seed = deterministicHash(`${modelId}|${prompt}|${temperature}|${safetyMode}|${systemPrompt || ''}`);
    body = `Local model ${modelId} response (seed ${seed}):\n` +
      `Echoing your prompt: "${String(prompt).slice(0, 240)}"\n` +
      `Safety mode: ${safetyMode}. Temperature: ${temperature}.`;
  }

  return {
    content: body,
    citations,
    prompt_tokens: approxTokens(prompt) + approxTokens(systemPrompt),
    completion_tokens: approxTokens(body)
  };
}

export async function runCompletion({
  prompt,
  systemPrompt = '',
  modelId = config.defaultModelId,
  temperature = config.defaultTemperature,
  contextLength = config.defaultContextLength,
  safetyMode = 'standard',
  retrievalCollectionId = null
}) {
  const chunks = retrievalCollectionId ? lookupRetrievedChunks(retrievalCollectionId, prompt) : [];
  const result = synthesizeResponse({
    prompt, systemPrompt,
    context: { collectionId: retrievalCollectionId, chunks },
    modelId, temperature, safetyMode
  });
  return {
    model_id: modelId,
    temperature,
    context_length: contextLength,
    safety_mode: safetyMode,
    ...result
  };
}

export function streamCompletion(result, write) {
  // Splits the response content into tokens and writes them out with a small
  // delay so the browser UI can show streaming behaviour.
  const text = result.content;
  const tokens = text.split(/(\s+)/);
  let i = 0;
  return new Promise((resolve) => {
    const step = () => {
      if (i >= tokens.length) {
        write({ event: 'done', citations: result.citations,
          prompt_tokens: result.prompt_tokens, completion_tokens: result.completion_tokens });
        return resolve();
      }
      write({ event: 'token', delta: tokens[i] });
      i += 1;
      setTimeout(step, 12);
    };
    step();
  });
}
