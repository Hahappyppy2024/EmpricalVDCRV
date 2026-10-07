const fs = require('fs');
const path = require('path');
const courseRepository = require('../repositories/courseRepository');
const uploadRepository = require('../repositories/uploadRepository');

const uploadsDir = path.join(__dirname, '..', '..', 'storage', 'uploads');

function resolveUploadPath(storageName) {
  const base = path.resolve(uploadsDir);
  const target = path.resolve(base, String(storageName || ''));
  if (target === base || !target.startsWith(base + path.sep)) return null;
  return target;
}

function showUploadForm(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('upload_new', { course, error: null });
}

function createUpload(req, res) {
  const courseId = Number(req.params.course_id);
  const course = courseRepository.findById(courseId);
  if (!course) return res.status(404).send('Course not found');
  if (!req.file) {
    return res.status(400).render('upload_new', { course, error: 'file is required' });
  }
  const upload = uploadRepository.createUpload({
    course_id: courseId,
    uploaded_by: req.currentUser.user_id,
    original_name: req.file.originalname,
    // Store only a safe relative filename. Absolute paths are rebuilt from uploadsDir.
    storage_path: req.file.filename
  });
  return res.redirect(`/courses/${courseId}/uploads`);
}

function apiCreateUpload(req, res) {
  const courseId = Number(req.params.course_id);
  if (!req.file) {
    return res.status(400).json({ success: false, error: 'file is required' });
  }
  const upload = uploadRepository.createUpload({
    course_id: courseId,
    uploaded_by: req.currentUser.user_id,
    original_name: req.file.originalname,
    storage_path: req.file.filename
  });
  return res.status(201).json({ success: true, upload });
}

function listUploads(req, res) {
  const course = courseRepository.findById(Number(req.params.course_id));
  if (!course) return res.status(404).send('Course not found');
  res.render('uploads_list', { course, uploads: uploadRepository.listByCourse(course.course_id) });
}

function apiListUploads(req, res) {
  res.json({ success: true, uploads: uploadRepository.listByCourse(Number(req.params.course_id)) });
}

function downloadUpload(req, res) {
  const courseId = Number(req.params.course_id);
  const uploadId = Number(req.params.upload_id);
  const upload = uploadRepository.findById(courseId, uploadId);
  if (!upload) return res.status(404).send('Upload not found');
  const abs = resolveUploadPath(upload.storage_path);
  if (!abs) return res.status(404).send('Upload not found');
  return res.download(abs, upload.original_name);
}

function apiDownloadUpload(req, res) {
  const courseId = Number(req.params.course_id);
  const uploadId = Number(req.params.upload_id);
  const upload = uploadRepository.findById(courseId, uploadId);
  if (!upload) return res.status(404).json({ success: false, error: 'Upload not found' });
  const abs = resolveUploadPath(upload.storage_path);
  if (!abs) return res.status(404).json({ success: false, error: 'Upload not found' });
  return res.download(abs, upload.original_name);
}

function deleteUpload(req, res) {
  const courseId = Number(req.params.course_id);
  const uploadId = Number(req.params.upload_id);
  const upload = uploadRepository.findById(courseId, uploadId);
  if (!upload) return res.status(404).send('Upload not found');
  const abs = resolveUploadPath(upload.storage_path);
  if (abs && fs.existsSync(abs)) fs.unlinkSync(abs);
  uploadRepository.deleteUpload(courseId, uploadId);
  return res.redirect(`/courses/${courseId}/uploads`);
}

function apiDeleteUpload(req, res) {
  const courseId = Number(req.params.course_id);
  const uploadId = Number(req.params.upload_id);
  const upload = uploadRepository.findById(courseId, uploadId);
  if (!upload) return res.status(404).json({ success: false, error: 'Upload not found' });
  const abs = resolveUploadPath(upload.storage_path);
  if (abs && fs.existsSync(abs)) fs.unlinkSync(abs);
  uploadRepository.deleteUpload(courseId, uploadId);
  return res.json({ success: true });
}

module.exports = {
  showUploadForm, createUpload, apiCreateUpload,
  listUploads, apiListUploads,
  downloadUpload, apiDownloadUpload,
  deleteUpload, apiDeleteUpload
};