// src/controllers/submissionController.js
const submissionRepo = require('../repos/submissionRepo');




function submitForm(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const me = req.session.user;
  const existing = submissionRepo.getMySubmission(course_id, assignment_id, me.user_id);
  res.render('submissions/submit', { course_id, assignment_id, existing });
}

function createSubmission(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const me = req.session.user;
  const { content_text, attachment_upload_id } = req.body;

  const existing = submissionRepo.getMySubmission(course_id, assignment_id, me.user_id);
  if (existing) {
    // if already exists, update it
    const updated = submissionRepo.updateSubmission(course_id, assignment_id, existing.submission_id, {
      content_text,
      attachment_upload_id
    });
    if ((req.originalUrl || '').startsWith('/api')) return res.json({ submission: updated });
    return res.redirect(`/courses/${course_id}/assignments/${assignment_id}`);
  }

  const s = submissionRepo.createSubmission({
    assignment_id,
    course_id,
    student_id: me.user_id,
    content_text: content_text || '',
    attachment_upload_id
  });

  if ((req.originalUrl || '').startsWith('/api')) return res.status(201).json({ submission: s });
  return res.redirect(`/courses/${course_id}/assignments/${assignment_id}`);
}

function updateMySubmission(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const submission_id = Number(req.params.submission_id);
  const { content_text, attachment_upload_id } = req.body;

  const submission = submissionRepo.updateSubmission(course_id, assignment_id, submission_id, {
    content_text,
    attachment_upload_id
  });

  if ((req.originalUrl || '').startsWith('/api')) return res.json({ submission });
  return res.redirect(`/courses/${course_id}/assignments/${assignment_id}`);
}

function listMySubmissions(req, res) {
  const me = req.session.user;
  const submissions = submissionRepo.listMySubmissions(me.user_id);
  if ((req.originalUrl || '').startsWith('/api')) return res.json({ submissions });
  return res.render('submissions/my', { submissions });
}

function listForAssignment(req, res) {
  const course_id = Number(req.params.course_id);
  const assignment_id = Number(req.params.assignment_id);
  const submissions = submissionRepo.listForAssignment(course_id, assignment_id);
  if ((req.originalUrl || '').startsWith('/api')) return res.json({ submissions });
  return res.render('submissions/list', { course_id, assignment_id, submissions });
}

// src/controllers/submissionZipController.js

async function unzipAndList(req, res, next) {
  try {
    const { course_id, assignment_id, submission_id } = req.params;

    // ... 你的权限检查、路径计算、找到 zip 文件等

    // 这里假设你解压函数能返回“解压出来的文件列表”
    // 如果你现在没有返回列表，也没关系，至少给 extractedCount 一个确定值
    const extractedFiles = await safeUnzip(zipPath, destDir); // 建议返回 array
    // const extractedCount = Array.isArray(extractedFiles) ? extractedFiles.length : 0;
    const extractedCount = 0;
    if (req.originalUrl.startsWith('/api')) {
      return res.json({ ok: true, extractedCount });
    }

    return res.redirect(
      `/courses/${course_id}/assignments/${assignment_id}/submissions/${submission_id}/files`
    );
  } catch (err) {
    return next(err);
  }
}



// 你可以按你项目结构改这个根目录（只要一致即可）
function _extractRoot() {
  return path.join(process.cwd(), 'uploads', 'extracted');
}

function _submissionExtractDir(submission_id) {
  return path.join(_extractRoot(), String(submission_id));
}

function _isPathInside(baseDir, targetPath) {
  const rel = path.relative(baseDir, targetPath);
  return rel && !rel.startsWith('..') && !path.isAbsolute(rel);
}

// Zip Slip-safe unzip：只解压“正常相对路径”，任何越界/绝对路径直接跳过
async function safeUnzip(zipPath, destDir) {
  await fsp.mkdir(destDir, { recursive: true });

  const directory = await unzipper.Open.file(zipPath);
  let extractedCount = 0;

  for (const entry of directory.files) {
    // skip directories
    if (entry.type === 'Directory') continue;

    const entryPath = entry.path.replace(/\\/g, '/'); // normalize
    // reject absolute paths
    if (entryPath.startsWith('/') || /^[A-Za-z]:\//.test(entryPath)) continue;

    const outPath = path.join(destDir, entryPath);
    const outDir = path.dirname(outPath);

    // zip slip check
    if (!_isPathInside(destDir, outPath)) continue;

    await fsp.mkdir(outDir, { recursive: true });

    await new Promise((resolve, reject) => {
      entry
        .stream()
        .pipe(fs.createWriteStream(outPath))
        .on('finish', resolve)
        .on('error', reject);
    });

    extractedCount += 1;
  }

  return extractedCount;
}

async function listFilesRecursive(baseDir) {
  const out = [];
  async function walk(dir) {
    const items = await fsp.readdir(dir, { withFileTypes: true });
    for (const it of items) {
      const abs = path.join(dir, it.name);
      if (it.isDirectory()) {
        await walk(abs);
      } else if (it.isFile()) {
        const rel = path.relative(baseDir, abs).replace(/\\/g, '/');
        out.push({ path: rel });
      }
    }
  }

  // 如果没解压过，返回空列表即可（不要 500）
  try {
    await walk(baseDir);
  } catch (e) {
    if (e && e.code === 'ENOENT') return [];
    throw e;
  }

  out.sort((a, b) => a.path.localeCompare(b.path));
  return out;
}

async function unzipSubmission(req, res, next) {
  try {
    const course_id = Number(req.params.course_id);
    const assignment_id = Number(req.params.assignment_id);
    const submission_id = Number(req.params.submission_id);

    // 1) 找 submission -> 找到它的 attachment_upload_id
    // 你 repo 函数名可能不同：按你的实际改
    const submission =
      submissionRepo.getById?.(submission_id) ||
      submissionRepo.getSubmissionById?.(submission_id);

    if (!submission) {
      return res.status(404).json({ error: 'submission not found' });
    }

    // 额外一致性检查（可选，但对稳定更好）
    if (
      Number(submission.course_id) !== course_id ||
      Number(submission.assignment_id) !== assignment_id
    ) {
      return res.status(404).json({ error: 'submission not in this course/assignment' });
    }

    if (!submission.attachment_upload_id) {
      return res.status(400).json({ error: 'no attachment_upload_id on submission' });
    }

    // 2) 找 upload -> 得到 zip 的实际路径
    const upload =
      uploadRepo.getById?.(Number(submission.attachment_upload_id)) ||
      uploadRepo.getUploadById?.(Number(submission.attachment_upload_id));

    if (!upload) {
      return res.status(404).json({ error: 'upload not found' });
    }

    const zipPath =
      upload.path || upload.file_path || upload.abs_path || upload.disk_path;

    if (!zipPath) {
      return res.status(500).json({ error: 'upload has no file path field' });
    }

    // 3) 解压到固定目录
    const destDir = _submissionExtractDir(submission_id);
    const extractedCount = await safeUnzip(zipPath, destDir);

    // 4) 返回
    if ((req.originalUrl || '').startsWith('/api')) {
      // 这里 extractedCount 一定存在，不会再 ReferenceError
      return res.status(200).json({ ok: true, extractedCount });
    }
    return res.redirect(
      `/courses/${course_id}/assignments/${assignment_id}/submissions/${submission_id}/files`
    );
  } catch (err) {
    return next(err);
  }
}

async function listExtractedFiles(req, res, next) {
  try {
    const submission_id = Number(req.params.submission_id);
    const dir = _submissionExtractDir(submission_id);
    const files = await listFilesRecursive(dir);
    return res.json({ files });
  } catch (err) {
    return next(err);
  }
}


module.exports = { submitForm, unzipSubmission, listExtractedFiles,createSubmission, updateMySubmission, listMySubmissions, listForAssignment };