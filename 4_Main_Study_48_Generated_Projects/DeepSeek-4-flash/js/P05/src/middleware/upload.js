import multer from 'multer';
import crypto from 'node:crypto';
import path from 'node:path';

export const ALLOWED_MIME_TYPES = new Set([
  'image/png',
  'image/jpeg',
  'image/gif',
  'image/svg+xml',
  'image/webp',
  'application/pdf',
  'text/plain',
  'text/markdown',
  'application/json',
  'text/csv'
]);

export function makeUploader(uploadDir, maxBytes, fieldName = 'file') {
  const storage = multer.diskStorage({
    destination: (_req, _file, cb) => cb(null, uploadDir),
    filename: (_req, file, cb) => {
      const ext = path.extname(file.originalname || '').toLowerCase().slice(0, 10);
      cb(null, `${Date.now()}-${crypto.randomBytes(6).toString('hex')}${ext}`);
    }
  });

  const upload = multer({
    storage,
    limits: { fileSize: maxBytes, files: 1 },
    fileFilter: (_req, file, cb) => {
      const mime = String(file.mimetype || '').toLowerCase();
      if (!ALLOWED_MIME_TYPES.has(mime)) {
        const err = new Error('File type not allowed.');
        err.status = 400;
        err.code = 'INVALID_FILE_TYPE';
        return cb(err);
      }
      return cb(null, true);
    }
  });

  return (req, res, next) => {
    upload.single(fieldName)(req, res, (err) => {
      if (!err) return next();
      if (err.code === 'LIMIT_FILE_SIZE') {
        err.status = 400;
        err.code = 'FILE_TOO_LARGE';
        err.message = `File exceeds the maximum allowed size of ${Math.round(maxBytes / 1024 / 1024)} MB.`;
      } else if (err.code === 'LIMIT_UNEXPECTED_FILE' || err.code === 'LIMIT_FILE_COUNT') {
        err.status = 400;
        err.code = 'INVALID_FILE_TYPE';
        err.message = 'A single file upload is required.';
      } else if (!err.status) {
        err.status = 400;
        err.code = 'UPLOAD_FAILED';
        err.message = 'File upload failed.';
      }
      return next(err);
    });
  };
}
