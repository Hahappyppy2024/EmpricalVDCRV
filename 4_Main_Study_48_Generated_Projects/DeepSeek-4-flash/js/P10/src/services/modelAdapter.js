import crypto from 'node:crypto';

function normalizeTerm(term) {
  return String(term).trim().toLowerCase();
}

export function findBlockedTerm(prompt, blockedTerms) {
  const normalized = normalizeTerm(prompt);
  for (const term of blockedTerms || []) {
    if (normalized.includes(normalizeTerm(term))) {
      return term;
    }
  }
  return null;
}

function safeEvaluate(expression) {
  let pos = 0;
  const text = String(expression);

  function skipSpaces() {
    while (pos < text.length && /\s/.test(text[pos])) pos += 1;
  }
  function peek() {
    skipSpaces();
    return text[pos];
  }
  function parseNumber() {
    skipSpaces();
    const match = /^[-+]?(\d+\.?\d*|\.\d+)/.exec(text.slice(pos));
    if (!match) throw new Error('not-a-number');
    pos += match[0].length;
    return parseFloat(match[0]);
  }
  function parseFactor() {
    const ch = peek();
    if (ch === '(') {
      pos += 1;
      const value = parseExpression();
      if (peek() !== ')') throw new Error('paren-mismatch');
      pos += 1;
      return value;
    }
    return parseNumber();
  }
  function parseTerm() {
    let value = parseFactor();
    for (;;) {
      const ch = peek();
      if (ch === '*') {
        pos += 1;
        value *= parseFactor();
      } else if (ch === '/') {
        pos += 1;
        const divisor = parseFactor();
        if (divisor === 0) throw new Error('divide-by-zero');
        value /= divisor;
      } else {
        break;
      }
    }
    return value;
  }
  function parseExpression() {
    let value = parseTerm();
    for (;;) {
      const ch = peek();
      if (ch === '+') {
        pos += 1;
        value += parseTerm();
      } else if (ch === '-') {
        pos += 1;
        value -= parseTerm();
      } else {
        break;
      }
    }
    return value;
  }

  const result = parseExpression();
  skipSpaces();
  if (pos < text.length) throw new Error('trailing-input');
  return result;
}

const ARITHMETIC_PATTERN = /(?:calculate|compute|evaluate|what is|what's)?\s*\(?\s*(\d+(?:\.\d+)?(?:\s*[-+*/()]\s*\d+(?:\.\d+)?)+)\s*\)?\s*\??/i;

function tryCalculate(prompt) {
  const match = ARITHMETIC_PATTERN.exec(prompt);
  if (!match) return null;
  try {
    const value = safeEvaluate(match[1]);
    return Number.isInteger(value) ? value : Math.round(value * 1e6) / 1e6;
  } catch {
    return null;
  }
}

const TEMPLATES = [
  (meta) =>
    `Under the "${meta.model}" model (provider: ${meta.provider}, temperature ${meta.temperature}, context length ${meta.contextLength}, safety mode "${meta.safetyMode}"), here is a deterministic assistant response for your prompt.\n\n${meta.prompt}\n\nThis is the verified output produced by the local adapter. ${meta.citationNote}`,
  (meta) =>
    `Assistant summary (${meta.model}):\n${meta.prompt}\n\nDraft answer: this synthetic response captures the main topic, offers the next step, and flags open questions. ${meta.citationNote}`,
  (meta) =>
    `Based on the configured profile (${meta.provider}/${meta.model}), the assistant generated the following answer:\n\n${meta.prompt}\n\nRecommendation: proceed with the outlined approach and validate against the attached knowledge if citations were requested. ${meta.citationNote}`,
];

export function generateResponse({ prompt, model, provider, temperature, contextLength, safetyMode, citations = [], toolResults = [], calculatorEnabled = false }) {
  const hash = crypto.createHash('sha256').update(`${prompt}|${model}|${provider}`).digest('hex');
  const templateIndex = Number.parseInt(hash.slice(0, 4), 16) % TEMPLATES.length;

  let extra = '';
  if (calculatorEnabled) {
    const value = tryCalculate(prompt);
    if (value !== null) {
      extra += `\n[calculator] The expression evaluates to ${value}.`;
    }
  }
  for (const tool of toolResults || []) {
    extra += `\n[${tool.name}] ${tool.output}`;
  }

  let citationNote = '';
  if (citations && citations.length > 0) {
    citationNote = `Citations:\n${citations.map((c, i) => `[${i + 1}] ${c.file} — ${c.snippet}`).join('\n')}`;
  }

  const base = TEMPLATES[templateIndex]({
    prompt,
    model,
    provider,
    temperature,
    contextLength,
    safetyMode,
    citationNote: citationNote || 'No citations were attached to this response.',
  });

  return (base + extra).trim();
}
