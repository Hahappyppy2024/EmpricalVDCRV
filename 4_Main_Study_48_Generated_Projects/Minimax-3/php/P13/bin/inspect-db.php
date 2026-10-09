<?php
$db = new PDO('sqlite:D:\000_phd_graudaiton\EMSE\MiniMax3\php\P13\storage\app.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "=== Users ===\n";
foreach ($db->query('SELECT id, username, role, domain_id FROM users') as $r) {
    echo sprintf("  %d %s [%s] domain=%s\n", $r['id'], $r['username'], $r['role'], $r['domain_id'] ?? '-');
}
echo "=== Folders for alice ===\n";
foreach ($db->query('SELECT id, name, folder_type, total_count, unread_count FROM folders WHERE user_id = 1 ORDER BY id') as $r) {
    echo sprintf("  %d %s [%s] total=%d unread=%d\n", $r['id'], $r['name'], $r['folder_type'], $r['total_count'], $r['unread_count']);
}
echo "=== Messages (latest 8) ===\n";
foreach ($db->query('SELECT id, user_id, folder_id, subject, status FROM messages ORDER BY id DESC LIMIT 8') as $r) {
    echo sprintf("  #%d user=%d folder=%d status=%s subject=%s\n", $r['id'], $r['user_id'], $r['folder_id'], $r['status'], $r['subject']);
}
echo "=== Contacts (latest 6) ===\n";
foreach ($db->query('SELECT id, user_id, name, email FROM contacts ORDER BY id DESC LIMIT 6') as $r) {
    echo sprintf("  #%d user=%d %s <%s>\n", $r['id'], $r['user_id'], $r['name'], $r['email']);
}
echo "=== Rules ===\n";
foreach ($db->query('SELECT id, user_id, name FROM rules ORDER BY id') as $r) {
    echo sprintf("  #%d user=%d %s\n", $r['id'], $r['user_id'], $r['name']);
}
echo "=== Audit events (latest 12) ===\n";
foreach ($db->query('SELECT id, actor_user_id, actor_role, action, target_type, target_id FROM audit_events ORDER BY id DESC LIMIT 12') as $r) {
    echo sprintf("  #%d user=%d [%s] %s %s/%s\n", $r['id'], $r['actor_user_id'] ?? '-', $r['actor_role'] ?? '-', $r['action'], $r['target_type'] ?? '-', $r['target_id'] ?? '-');
}
echo "=== Quarantine ===\n";
foreach ($db->query('SELECT id, domain_id, recipient, sender, subject, status FROM quarantined_messages') as $r) {
    echo sprintf("  #%d dom=%d %s->%s [%s] %s\n", $r['id'], $r['domain_id'], $r['sender'], $r['recipient'], $r['status'], $r['subject'] ?? '');
}
echo "=== Attachments ===\n";
foreach ($db->query('SELECT id, user_id, filename, size_bytes, mime_type FROM attachments ORDER BY id') as $r) {
    echo sprintf("  #%d user=%d %s (%d bytes, %s)\n", $r['id'], $r['user_id'], $r['filename'], $r['size_bytes'], $r['mime_type']);
}
echo "=== API errors ===\n";
foreach ($db->query('SELECT id, user_id, endpoint, error_code, error_state FROM api_errors ORDER BY id') as $r) {
    echo sprintf("  #%d user=%d %s -> %d %s\n", $r['id'], $r['user_id'] ?? '-', $r['endpoint'], $r['error_code'], $r['error_state']);
}
echo "=== Import/Export jobs ===\n";
foreach ($db->query('SELECT id, user_id, kind, filename, row_count, status FROM import_export_jobs ORDER BY id') as $r) {
    echo sprintf("  #%d user=%d %s file=%s rows=%d status=%s\n", $r['id'], $r['user_id'], $r['kind'], $r['filename'] ?? '-', $r['row_count'], $r['status']);
}
