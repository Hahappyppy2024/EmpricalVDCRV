<?php
declare(strict_types=1);

namespace MailServer\Database;

use PDO;

class Seed
{
    public static function run(): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            $pdo->commit();
            return;
        }

        $domains = [
            ['example.com', 'Primary demo domain', 100, 5120],
            ['acme.test', 'Secondary test domain', 25, 2048],
        ];
        $domainIds = [];
        foreach ($domains as $d) {
            $stmt = $pdo->prepare('INSERT INTO domains (name, description, max_mailboxes, max_quota_mb) VALUES (?, ?, ?, ?)');
            $stmt->execute($d);
            $domainIds[$d[0]] = (int) $pdo->lastInsertId();
        }

        $password = password_hash('Password123!', PASSWORD_BCRYPT);

        $users = [
            ['alice', 'alice@example.com', 'Alice Mailer', 'mail_user', 'example.com', 1024],
            ['bob', 'bob@example.com', 'Bob Sender', 'mail_user', 'example.com', 1024],
            ['carol', 'carol@acme.test', 'Carol Tester', 'mail_user', 'acme.test', 512],
            ['dadm', 'dadm@example.com', 'Diana Domain', 'domain_admin', 'example.com', 1024],
            ['sadm', 'sadm@example.com', 'Sam System', 'system_admin', null, 0],
        ];
        $userIds = [];
        foreach ($users as $u) {
            $stmt = $pdo->prepare('INSERT INTO users (username, email, full_name, role, domain_id, mailbox_quota_mb, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$u[0], $u[1], $u[2], $u[3], $u[4] !== null ? $domainIds[$u[4]] : null, $u[5], $password]);
            $userIds[$u[0]] = (int) $pdo->lastInsertId();
        }

        $defaultFolders = ['Inbox', 'Sent', 'Drafts', 'Trash', 'Spam', 'Archive'];
        $folderIds = [];
        foreach ($userIds as $uname => $uid) {
            foreach ($defaultFolders as $i => $name) {
                $type = match ($name) {
                    'Inbox' => 'inbox',
                    'Sent' => 'sent',
                    'Drafts' => 'drafts',
                    'Trash' => 'trash',
                    'Spam' => 'spam',
                    'Archive' => 'archive',
                    default => 'custom',
                };
                $stmt = $pdo->prepare('INSERT INTO folders (user_id, name, folder_type) VALUES (?, ?, ?)');
                $stmt->execute([$uid, $name, $type]);
                $folderIds[$uname][$name] = (int) $pdo->lastInsertId();
            }
        }

        $messages = [
            ['alice', 'Inbox', 'bob@example.com', 'Bob Sender', 'alice@example.com', 'Welcome to the mail server', 'Hello Alice, welcome to the demo mail server.', 'received', 0],
            ['alice', 'Inbox', 'newsletter@example.com', 'Newsletter Bot', 'alice@example.com', 'Your weekly digest', 'Here are this week updates.', 'received', 1],
            ['alice', 'Inbox', 'carol@acme.test', 'Carol Tester', 'alice@example.com', 'Project update', 'The latest status update is attached.', 'received', 0],
            ['alice', 'Sent', 'alice@example.com', 'Alice Mailer', 'bob@example.com', 'Re: Welcome', 'Thanks Bob, glad to be here.', 'sent', 1],
            ['bob', 'Inbox', 'alice@example.com', 'Alice Mailer', 'bob@example.com', 'Re: Welcome', 'Thanks Bob, glad to be here.', 'received', 1],
            ['carol', 'Inbox', 'admin@example.com', 'System Admin', 'carol@acme.test', 'Verify your account', 'Please confirm your email.', 'received', 0],
        ];

        foreach ($messages as $m) {
            $stmt = $pdo->prepare('INSERT INTO messages (user_id, folder_id, from_address, from_name, to_addresses, subject, body_text, is_read, status, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$userIds[$m[0]], $folderIds[$m[0]][$m[1]], $m[2], $m[3], $m[4], $m[5], $m[6], $m[8], $m[7]]);
        }

        $contacts = [
            ['alice', 'Bob Sender', 'bob@example.com', 'Example Corp', '+1-555-0001', 'Frequent recipient'],
            ['alice', 'Carol Tester', 'carol@acme.test', 'Acme Inc', '+1-555-0002', null],
            ['alice', 'Newsletter', 'newsletter@example.com', 'NoCorp', null, null],
            ['bob', 'Alice Mailer', 'alice@example.com', null, null, 'Friend'],
        ];
        foreach ($contacts as $c) {
            $stmt = $pdo->prepare('INSERT INTO contacts (user_id, name, email, organization, phone, notes) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userIds[$c[0]], $c[1], $c[2], $c[3], $c[4], $c[5]]);
        }

        $rules = [
            ['alice', 'Move newsletter', 'from_address:newsletter@example.com', 'move_to:Junk', 10],
            ['alice', 'Star Acme', 'from_address:acme.test', 'flag:starred', 20],
            ['bob', 'Forward to archive', 'subject:archive', 'forward:bob@example.com', 30],
        ];
        foreach ($rules as $r) {
            $stmt = $pdo->prepare('INSERT INTO rules (user_id, name, conditions, actions, priority) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userIds[$r[0]], $r[1], $r[2], $r[3], $r[4]]);
        }

        $aliases = [
            ['example.com', 'info@example.com', 'alice@example.com'],
            ['example.com', 'support@example.com', 'bob@example.com'],
            ['acme.test', 'hello@acme.test', 'carol@acme.test'],
        ];
        foreach ($aliases as $a) {
            $stmt = $pdo->prepare('INSERT INTO aliases (domain_id, source, destination) VALUES (?, ?, ?)');
            $stmt->execute([$domainIds[$a[0]], $a[1], $a[2]]);
        }

        $quarantine = [
            ['example.com', 'bob@example.com', 'phisher@evil.test', 'Suspicious link', 'url_malware', 'quarantined'],
            ['example.com', 'alice@example.com', 'spammer@spam.test', 'Win a free iPhone', 'spam', 'quarantined'],
            ['acme.test', 'carol@acme.test', 'unknown@dark.test', 'Account locked', 'phishing', 'quarantined'],
        ];
        foreach ($quarantine as $q) {
            $stmt = $pdo->prepare('INSERT INTO quarantined_messages (domain_id, recipient, sender, subject, reason, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$domainIds[$q[0]], $q[1], $q[2], $q[3], $q[4], $q[5]]);
        }

        $audit = [
            ['sadm', 'system_admin', 'user.login', 'user', 'alice', 'successful login', '127.0.0.1'],
            ['sadm', 'system_admin', 'domain.create', 'domain', 'example.com', 'domain created', '127.0.0.1'],
            ['dadm', 'domain_admin', 'alias.create', 'alias', 'support@example.com', 'alias created', '127.0.0.1'],
            ['sadm', 'system_admin', 'quarantine.review', 'quarantine', '1', 'reviewed', '127.0.0.1'],
            ['alice', 'mail_user', 'message.send', 'message', '5', 'message sent', '127.0.0.1'],
            ['sadm', 'system_admin', 'user.login.failed', 'user', 'bob', 'invalid password', '127.0.0.1'],
        ];
        foreach ($audit as $a) {
            $stmt = $pdo->prepare('INSERT INTO audit_events (actor_user_id, actor_role, action, target_type, target_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userIds[$a[0]], $a[1], $a[2], $a[3], $a[4], $a[5], $a[6]]);
        }

        $apiErrors = [
            ['alice', '/api/mail/message_compose', 422, 'invalid_recipient', 'Recipient missing domain'],
            ['bob', '/api/mail/message_compose', 403, 'permission_denied', 'Quota exceeded'],
            ['carol', '/api/mail/attachment_handling', 413, 'payload_too_large', 'File exceeds 25 MB'],
            ['dadm', '/api/mail/quarantine', 404, 'not_found', 'Quarantine entry missing'],
        ];
        foreach ($apiErrors as $e) {
            $stmt = $pdo->prepare('INSERT INTO api_errors (user_id, endpoint, error_code, error_state, message) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userIds[$e[0]], $e[1], $e[2], $e[3], $e[4]]);
        }

        $pdo->commit();
    }
}