import express from 'express';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import env from './config/env.js';
import { getDb } from './db/index.js';
import { loadSession, requireAuth, requireRole } from './middleware/auth.js';
import { errorHandler, notFound } from './middleware/errors.js';
import { makeUploader } from './middleware/upload.js';
import { registerPageRoutes } from './controllers/pageController.js';
import { flushScheduled } from './services/publishing.js';
import { listAudit } from './services/audit.js';
import { apiError, ok } from './lib/http.js';

// Controllers
import * as authController from './controllers/authController.js';
import * as accountAccessController from './controllers/accountAccessController.js';
import * as contentAuthoringController from './controllers/contentAuthoringController.js';
import * as richTextEditorController from './controllers/richTextEditorController.js';
import * as mediaLibraryController from './controllers/mediaLibraryController.js';
import * as publishingWorkflowController from './controllers/publishingWorkflowController.js';
import * as publicSiteController from './controllers/publicSiteController.js';
import * as commentsController from './controllers/commentsController.js';
import * as pageTemplatesController from './controllers/pageTemplatesController.js';
import * as userRoleController from './controllers/userRoleController.js';
import * as pluginSettingsController from './controllers/pluginSettingsController.js';
import * as importExportController from './controllers/importExportController.js';
import * as frontendApiController from './controllers/frontendApiController.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const uploader = makeUploader(env.uploadDir, env.maxUploadMb * 1024 * 1024);

export function createApp() {
  const app = express();
  app.disable('x-powered-by');
  app.use(express.json({ limit: '5mb' }));
  app.use(express.urlencoded({ extended: false }));
  app.use(loadSession);

  const testShutdownToken = process.env.P05_TEST_SHUTDOWN_TOKEN;
  if (testShutdownToken) {
    app.post('/__p05_test_shutdown', (req, res) => {
      if (req.body?.token !== testShutdownToken) {
        return res.status(403).json({ ok: false });
      }
      res.json({ ok: true });
      setImmediate(() => {
        // eslint-disable-next-line no-console
        console.log('[server] Test shutdown requested');
        process.exit(0);
      });
    });
  }

  // Static assets (CSS/JS only; HTML pages are served by pageController)
  app.use('/css', express.static(path.resolve(__dirname, '..', 'public', 'css')));
  app.use('/js', express.static(path.resolve(__dirname, '..', 'public', 'js')));

  // ---- Auth ----
  app.post('/api/auth/register', authController.register);
  app.post('/api/auth/login', authController.login);
  app.post('/api/auth/logout', authController.logout);
  app.get('/api/auth/me', authController.me);

  // ---- CMS-01 Account access ----
  app.get('/api/cms/account_access', requireAuth, accountAccessController.listAccountAccess);
  app.post('/api/cms/account_access', requireAuth, accountAccessController.createAccountAccess);
  app.patch('/api/cms/account_access/:id', requireAuth, accountAccessController.patchAccountAccess);

  // ---- CMS-02 Content authoring ----
  app.get('/api/cms/content_authoring', requireAuth, contentAuthoringController.listContentAuthoring);
  app.post('/api/cms/content_authoring', requireAuth, requireRole('author', 'editor', 'admin'), contentAuthoringController.createContentAuthoring);
  app.get('/api/cms/content_authoring/:id', requireAuth, contentAuthoringController.getContentAuthoring);
  app.patch('/api/cms/content_authoring/:id', requireAuth, contentAuthoringController.patchContentAuthoring);
  app.delete('/api/cms/content_authoring/:id', requireAuth, contentAuthoringController.deleteContentAuthoring);

  // ---- CMS-03 Rich text editor ----
  app.get('/api/cms/rich_text_editor', requireAuth, richTextEditorController.listRichTextEditor);
  app.post('/api/cms/rich_text_editor', requireAuth, requireRole('author', 'editor', 'admin'), richTextEditorController.createRichTextEditor);
  app.patch('/api/cms/rich_text_editor/:id', requireAuth, richTextEditorController.patchRichTextEditor);

  // ---- CMS-04 Media library ----
  app.get('/api/cms/media_library', requireAuth, mediaLibraryController.listMediaLibrary);
  app.post('/api/cms/media_library', requireAuth, requireRole('author', 'editor', 'admin'), uploader, mediaLibraryController.createMediaLibrary);
  app.patch('/api/cms/media_library/:id', requireAuth, mediaLibraryController.patchMediaLibrary);
  app.delete('/api/cms/media_library/:id', requireAuth, mediaLibraryController.deleteMediaLibrary);
  app.get('/api/cms/media_library/:id/file', mediaLibraryController.streamMediaFile);
  app.get('/api/cms/media_library/:id/download', mediaLibraryController.streamMediaFile);

  // ---- CMS-05 Publishing workflow ----
  app.get('/api/cms/publishing_workflow', requireAuth, publishingWorkflowController.listPublishingWorkflow);
  app.get('/api/cms/publishing_workflow/queue', requireAuth, requireRole('editor', 'admin'), publishingWorkflowController.publishingQueue);
  app.post('/api/cms/publishing_workflow', requireAuth, requireRole('author', 'editor', 'admin'), publishingWorkflowController.createPublishingWorkflow);
  app.patch('/api/cms/publishing_workflow/:id', requireAuth, publishingWorkflowController.patchPublishingWorkflow);

  // ---- CMS-06 Public site ----
  app.get('/api/cms/public_site', publicSiteController.getPublicSite);
  app.post('/api/cms/public_site', publicSiteController.createPublicSite);
  app.patch('/api/cms/public_site/:id', requireAuth, publicSiteController.patchPublicSite);

  // Public visitor API
  app.get('/api/public/articles', publicSiteController.publicListArticles);
  app.get('/api/public/articles/:slug', publicSiteController.publicGetArticle);
  app.get('/api/public/categories', publicSiteController.publicListCategories);
  app.get('/api/public/search', publicSiteController.publicSearch);
  app.get('/api/public/settings', publicSiteController.publicSettingsRoute);

  // ---- CMS-07 Comments ----
  app.get('/api/cms/comments', commentsController.listComments);
  app.post('/api/cms/comments', commentsController.createComment);
  app.patch('/api/cms/comments/:id', requireAuth, commentsController.patchComment);
  app.delete('/api/cms/comments/:id', requireAuth, commentsController.deleteComment);

  // ---- CMS-08 Page templates ----
  app.get('/api/cms/page_templates', requireAuth, requireRole('editor', 'admin'), pageTemplatesController.getPageTemplates);
  app.post('/api/cms/page_templates', requireAuth, requireRole('editor', 'admin'), pageTemplatesController.createPageTemplate);
  app.patch('/api/cms/page_templates/:id', requireAuth, requireRole('editor', 'admin'), pageTemplatesController.patchPageTemplate);
  app.delete('/api/cms/page_templates/:id', requireAuth, requireRole('admin'), pageTemplatesController.deletePageTemplate);
  app.post('/api/cms/page_templates/menus', requireAuth, requireRole('editor', 'admin'), pageTemplatesController.createNavMenu);
  app.patch('/api/cms/page_templates/menus/:id', requireAuth, requireRole('editor', 'admin'), pageTemplatesController.patchNavMenu);
  app.delete('/api/cms/page_templates/menus/:id', requireAuth, requireRole('admin'), pageTemplatesController.deleteNavMenu);

  // ---- CMS-09 User and role management ----
  app.get('/api/cms/user_and_role_management', requireAuth, requireRole('admin'), userRoleController.listUserAndRoleManagement);
  app.get('/api/cms/user_and_role_management/roles', requireAuth, requireRole('admin'), userRoleController.listRoles);
  app.post('/api/cms/user_and_role_management', requireAuth, requireRole('admin'), userRoleController.createUserAndRoleManagement);
  app.patch('/api/cms/user_and_role_management/:id', requireAuth, requireRole('admin'), userRoleController.patchUserAndRoleManagement);
  app.get('/api/audit', requireAuth, requireRole('admin'), (req, res, next) => {
    try {
      const rows = listAudit(getDb(), { limit: req.query.limit });
      return ok(res, { data: rows }, 'Audit events.');
    } catch (err) {
      return next(err);
    }
  });

  // ---- CMS-10 Plugin/settings panel ----
  app.get('/api/cms/plugin_settings_panel', requireAuth, requireRole('admin'), pluginSettingsController.listPluginSettingsPanel);
  app.post('/api/cms/plugin_settings_panel', requireAuth, requireRole('admin'), pluginSettingsController.createPluginSetting);
  app.patch('/api/cms/plugin_settings_panel/:id', requireAuth, requireRole('admin'), pluginSettingsController.patchPluginSetting);
  app.delete('/api/cms/plugin_settings_panel/:id', requireAuth, requireRole('admin'), pluginSettingsController.deletePluginSetting);

  // ---- CMS-11 Import/export ----
  app.get('/api/cms/import_export', requireAuth, requireRole('admin'), importExportController.listImportExport);
  app.post('/api/cms/import_export', requireAuth, requireRole('admin'), uploader, importExportController.createImportExport);
  app.patch('/api/cms/import_export/:id', requireAuth, requireRole('admin'), importExportController.patchImportExport);
  app.get('/api/cms/import_export/:id/download', requireAuth, requireRole('admin'), importExportController.downloadImportExport);

  // ---- CMS-12 Frontend API integration and errors ----
  app.get('/api/cms/frontend_api_integration_and_errors', requireAuth, frontendApiController.listFrontendApi);
  app.post('/api/cms/frontend_api_integration_and_errors', requireAuth, frontendApiController.createFrontendApi);
  app.patch('/api/cms/frontend_api_integration_and_errors/:id', requireAuth, frontendApiController.patchFrontendApi);

  // Diagnostics (deterministic controlled error states)
  app.get('/api/diagnostics/validation', frontendApiController.diagnosticValidation);
  app.get('/api/diagnostics/not-found', frontendApiController.diagnosticNotFound);
  app.get('/api/diagnostics/forbidden', frontendApiController.diagnosticForbidden);
  app.get('/api/diagnostics/server-error', frontendApiController.diagnosticServerError);
  app.get('/api/diagnostics/preview', frontendApiController.diagnosticPreview);

  // Health check
  app.get('/api/health', (_req, res) => {
    const db = getDb();
    const counts = {
      users: db.prepare('SELECT COUNT(*) AS c FROM users').get().c,
      articles: db.prepare('SELECT COUNT(*) AS c FROM articles').get().c,
      comments: db.prepare('SELECT COUNT(*) AS c FROM comments').get().c
    };
    return res.json({ ok: true, service: 'p05-content-management-system', status: 'healthy', counts });
  });

  // Browser pages
  registerPageRoutes(app);

  // 404 + error handling
  app.use(notFound);
  app.use(errorHandler);

  return app;
}

export function startScheduledPublishing() {
  const db = getDb();
  flushScheduled(db);
  const interval = setInterval(() => {
    try {
      flushScheduled(db);
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error('[publishing] auto-publish tick failed:', err.message);
    }
  }, 60 * 1000);
  return interval;
}
