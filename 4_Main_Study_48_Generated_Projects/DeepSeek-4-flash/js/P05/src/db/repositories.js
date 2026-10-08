import { getDb } from './index.js';
import { createRepo } from './repo.js';

export const usersRepo = createRepo(getDb(), 'users', {
  fields: ['username', 'email', 'password_hash', 'role_id', 'display_name', 'status'],
  orderBy: 'users.id'
});

export const accountAccessRepo = createRepo(getDb(), 'account_access', {
  fields: ['user_id', 'action', 'details', 'ip'],
  orderBy: 'account_access.id DESC'
});

export const articleRepo = createRepo(getDb(), 'articles', {
  fields: ['title', 'slug', 'body', 'body_html', 'tags', 'status', 'author_id', 'editor_id', 'category_id', 'template_id', 'featured_media_id', 'publish_at', 'published_at'],
  orderBy: 'articles.id DESC',
  jsonFields: ['tags']
});

export const richTextRepo = createRepo(getDb(), 'rich_text_editor', {
  fields: ['article_id', 'content', 'content_html', 'version', 'updated_by'],
  orderBy: 'rich_text_editor.id DESC'
});

export const storedFileRepo = createRepo(getDb(), 'stored_file', {
  fields: ['original_name', 'storage_name', 'mime_type', 'size_bytes', 'owner_id', 'kind'],
  orderBy: 'stored_file.id DESC'
});

export const mediaRepo = createRepo(getDb(), 'media_library', {
  fields: ['stored_file_id', 'alt_text', 'visibility', 'uploaded_by'],
  orderBy: 'media_library.id DESC'
});

export const workflowRepo = createRepo(getDb(), 'publishing_workflow', {
  fields: ['article_id', 'from_status', 'to_status', 'actor_id', 'note'],
  orderBy: 'publishing_workflow.id DESC'
});

export const publicSiteRepo = createRepo(getDb(), 'public_site', {
  fields: ['visitor_name', 'visitor_ip', 'action', 'article_id', 'referrer', 'details'],
  orderBy: 'public_site.id DESC'
});

export const commentsRepo = createRepo(getDb(), 'comments', {
  fields: ['article_id', 'author_name', 'author_email', 'body', 'status', 'moderated_by', 'moderated_at'],
  orderBy: 'comments.id DESC'
});

export const userRoleLogRepo = createRepo(getDb(), 'user_and_role_management', {
  fields: ['target_user_id', 'previous_role_id', 'new_role_id', 'action', 'actor_id', 'note'],
  orderBy: 'user_and_role_management.id DESC'
});

export const settingsRepo = createRepo(getDb(), 'plugin_settings_panel', {
  fields: ['key', 'value', 'description', 'updated_by'],
  orderBy: 'plugin_settings_panel.id'
});

export const importExportRepo = createRepo(getDb(), 'import_export', {
  fields: ['kind', 'format', 'status', 'file_id', 'record_count', 'scope', 'error_message', 'requested_by'],
  orderBy: 'import_export.id DESC'
});

export const frontendApiRepo = createRepo(getDb(), 'frontend_api_integration_and_errors', {
  fields: ['kind', 'status_code', 'context', 'message', 'url', 'user_id', 'resolved'],
  orderBy: 'frontend_api_integration_and_errors.id DESC'
});
