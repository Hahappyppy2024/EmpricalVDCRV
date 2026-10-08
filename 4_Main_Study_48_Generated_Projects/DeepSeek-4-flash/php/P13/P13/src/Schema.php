<?php

declare(strict_types=1);

namespace P13;

use PDO;

/**
 * SQLite schema definition. `migrate()` is idempotent and runs on every
 * app bootstrap; `seed()` creates the deterministic fixtures required by
 * the acceptance criteria of every use case.
 */
final class Schema
{
    public function migrate(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT NOT NULL UNIQUE,
                email         TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                display_name  TEXT NOT NULL DEFAULT '',
                role          TEXT NOT NULL CHECK (role IN ('mail_user','domain_admin','system_admin')),
                domain_id     INTEGER REFERENCES domains(id),
                status        TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','disabled')),
                created_at    TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS domains (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                name          TEXT NOT NULL UNIQUE,
                status        TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','suspended')),
                quota_mb      INTEGER NOT NULL DEFAULT 1024,
                mailbox_limit INTEGER NOT NULL DEFAULT 25,
                aliases       TEXT NOT NULL DEFAULT '[]',
                created_at    TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS sessions (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                token      TEXT NOT NULL UNIQUE,
                csrf_token TEXT NOT NULL,
                user_id    INTEGER NOT NULL REFERENCES users(id),
                ip_address TEXT NOT NULL DEFAULT '',
                user_agent TEXT NOT NULL DEFAULT '',
                status     TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','expired')),
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                last_seen_at TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS account_access (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER REFERENCES users(id),
                type       TEXT NOT NULL,
                status     TEXT NOT NULL,
                message    TEXT NOT NULL DEFAULT '',
                ip_address TEXT NOT NULL DEFAULT '',
                user_agent TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS folders (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL REFERENCES users(id),
                name       TEXT NOT NULL,
                is_system  INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS messages (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id        INTEGER NOT NULL REFERENCES users(id),
                folder_id      INTEGER NOT NULL REFERENCES folders(id),
                from_address   TEXT NOT NULL,
                from_name      TEXT NOT NULL DEFAULT '',
                to_address     TEXT NOT NULL,
                subject        TEXT NOT NULL,
                body           TEXT NOT NULL DEFAULT '',
                headers        TEXT NOT NULL DEFAULT '{}',
                thread_id      INTEGER,
                priority       TEXT NOT NULL DEFAULT 'normal',
                status         TEXT NOT NULL DEFAULT 'unread' CHECK (status IN ('unread','read')),
                created_at     TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS message_compose (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL REFERENCES users(id),
                recipient  TEXT NOT NULL DEFAULT '',
                subject    TEXT NOT NULL DEFAULT '',
                body       TEXT NOT NULL DEFAULT '',
                status     TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','sent')),
                message_id INTEGER REFERENCES messages(id),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS message_reading (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL REFERENCES users(id),
                message_id INTEGER NOT NULL REFERENCES messages(id),
                opened_at  TEXT NOT NULL,
                starred    INTEGER NOT NULL DEFAULT 0
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS stored_files (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_user_id INTEGER NOT NULL REFERENCES users(id),
                message_id    INTEGER REFERENCES messages(id),
                purpose       TEXT NOT NULL DEFAULT 'attachment',
                original_name TEXT NOT NULL,
                stored_name   TEXT NOT NULL,
                mime_type     TEXT NOT NULL DEFAULT 'application/octet-stream',
                size          INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS attachment_handling (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL REFERENCES users(id),
                message_id    INTEGER REFERENCES messages(id),
                stored_file_id INTEGER NOT NULL REFERENCES stored_files(id),
                status        TEXT NOT NULL DEFAULT 'linked' CHECK (status IN ('pending','linked','deleted')),
                created_at    TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS contacts (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_user_id INTEGER NOT NULL REFERENCES users(id),
                first_name   TEXT NOT NULL DEFAULT '',
                last_name    TEXT NOT NULL DEFAULT '',
                email        TEXT NOT NULL,
                phone        TEXT NOT NULL DEFAULT '',
                organization TEXT NOT NULL DEFAULT '',
                notes        TEXT NOT NULL DEFAULT '',
                created_at   TEXT NOT NULL,
                updated_at   TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS mail_rules (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL REFERENCES users(id),
                name          TEXT NOT NULL,
                match_field   TEXT NOT NULL CHECK (match_field IN ('from','to','subject','body')),
                match_operator TEXT NOT NULL DEFAULT 'contains' CHECK (match_operator IN ('contains','equals')),
                match_value   TEXT NOT NULL,
                action_type   TEXT NOT NULL CHECK (action_type IN ('folder','label','forward','delete')),
                action_value  TEXT NOT NULL DEFAULT '',
                enabled       INTEGER NOT NULL DEFAULT 1,
                position      INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT NOT NULL,
                updated_at    TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS quarantine (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                domain_id     INTEGER NOT NULL REFERENCES domains(id),
                from_address  TEXT NOT NULL,
                to_address    TEXT NOT NULL,
                subject       TEXT NOT NULL,
                reason        TEXT NOT NULL,
                score         REAL NOT NULL DEFAULT 0,
                status        TEXT NOT NULL DEFAULT 'quarantined' CHECK (status IN ('quarantined','released','deleted')),
                reviewed_by   INTEGER REFERENCES users(id),
                created_at    TEXT NOT NULL,
                updated_at    TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS audit_events (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER REFERENCES users(id),
                actor_username TEXT,
                actor_role    TEXT NOT NULL DEFAULT 'guest',
                action        TEXT NOT NULL,
                entity_type   TEXT NOT NULL,
                entity_id     TEXT,
                details       TEXT NOT NULL DEFAULT '{}',
                ip_address    TEXT NOT NULL DEFAULT '',
                created_at    TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS import_export (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL REFERENCES users(id),
                kind          TEXT NOT NULL CHECK (kind IN ('import','export')),
                entity_type   TEXT NOT NULL,
                status        TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','done','failed')),
                stored_file_id INTEGER REFERENCES stored_files(id),
                counts        INTEGER NOT NULL DEFAULT 0,
                error         TEXT,
                created_at    TEXT NOT NULL,
                updated_at    TEXT NOT NULL
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS frontend_api_errors (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id         INTEGER NOT NULL REFERENCES users(id),
                scenario        TEXT NOT NULL,
                request_payload TEXT NOT NULL DEFAULT '{}',
                response_status INTEGER NOT NULL DEFAULT 0,
                response_message TEXT NOT NULL DEFAULT '',
                resolved        INTEGER NOT NULL DEFAULT 0,
                created_at      TEXT NOT NULL
            )"
        );
    }

    /**
     * Deterministic seed fixtures for every actor, role, domain, workflow
     * state and dependency required by the acceptance criteria.
     */
    public function seed(Database $db, Config $config): void
    {
        $pdo = $db->pdo();
        $pwd = (string) $config->get('seed.password', 'Passw0rd!');
        $hash = password_hash($pwd, PASSWORD_DEFAULT);

        $domainIds = [];
        $domainDefs = [
            ['example.com', 'active', 1024, 25, ['postmaster@example.com', 'abuse@example.com']],
            ['corporate.org', 'active', 2048, 50, ['postmaster@corporate.org']],
        ];
        foreach ($domainDefs as $d) {
            $pdo->prepare('INSERT INTO domains (name, status, quota_mb, mailbox_limit, aliases, created_at) VALUES (?, ?, ?, ?, ?, datetime(\'now\'))')
                ->execute([$d[0], $d[1], $d[2], $d[3], json_encode($d[4])]);
            $domainIds[$d[0]] = (int) $pdo->lastInsertId();
        }

        $userId = [];
        $userDefs = [
            ['alice', 'alice@example.com', 'Alice Anderson', 'mail_user', 'example.com'],
            ['bob', 'bob@example.com', 'Bob Becker', 'mail_user', 'example.com'],
            ['carol', 'carol@corporate.org', 'Carol Chen', 'mail_user', 'corporate.org'],
            ['dan', 'dan@example.com', 'Dan Diaz', 'domain_admin', 'example.com'],
            ['eva', 'eva@corporate.org', 'Eva Evans', 'domain_admin', 'corporate.org'],
            ['sysadmin', 'admin@mail.example', 'System Admin', 'system_admin', null],
        ];
        foreach ($userDefs as $i => $u) {
            $pdo->prepare('INSERT INTO users (username, email, password_hash, display_name, role, domain_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, \'active\', datetime(\'now\'))')
                ->execute([$u[0], $u[1], $hash, $u[2], $u[3], $u[4] !== null ? $domainIds[$u[4]] : null]);
            $userId[$u[0]] = (int) $pdo->lastInsertId();
        }

        $folderId = [];
        $systemFolders = ['Inbox', 'Sent', 'Drafts', 'Archive'];
        foreach ($userDefs as $u) {
            $uid = $userId[$u[0]];
            foreach ($systemFolders as $f) {
                $pdo->prepare('INSERT INTO folders (user_id, name, is_system, created_at) VALUES (?, ?, 1, datetime(\'now\'))')
                    ->execute([$uid, $f]);
                $folderId[$u[0]][$f] = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('INSERT INTO folders (user_id, name, is_system, created_at) VALUES (?, ?, 0, datetime(\'now\'))')
                ->execute([$uid, 'Work']);
            $folderId[$u[0]]['Work'] = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO folders (user_id, name, is_system, created_at) VALUES (?, ?, 0, datetime(\'now\'))')
                ->execute([$uid, 'Personal']);
            $folderId[$u[0]]['Personal'] = (int) $pdo->lastInsertId();
        }

        $insertMessage = function (array $m) use ($pdo, $userId, $folderId): int {
            $pdo->prepare(
                'INSERT INTO messages (user_id, folder_id, from_address, from_name, to_address, subject, body, headers, thread_id, priority, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))'
            )->execute([
                $userId[$m['user']],
                $folderId[$m['user']][$m['folder']],
                $m['from_address'],
                $m['from_name'],
                $m['to_address'],
                $m['subject'],
                $m['body'],
                json_encode($m['headers'] ?? ['x-synthetic' => 'true']),
                $m['thread_id'] ?? null,
                $m['priority'] ?? 'normal',
                $m['status'] ?? 'unread',
            ]);
            return (int) $pdo->lastInsertId();
        };

        $m1 = $insertMessage([
            'user' => 'alice', 'folder' => 'Inbox',
            'from_address' => 'bob@example.com', 'from_name' => 'Bob Becker',
            'to_address' => 'alice@example.com', 'subject' => 'Project kickoff notes',
            'body' => "Hi Alice,\n\nPlease review the attached notes before Friday.\n\nBest,\nBob",
            'status' => 'unread',
        ]);
        $insertMessage([
            'user' => 'alice', 'folder' => 'Inbox',
            'from_address' => 'carol@corporate.org', 'from_name' => 'Carol Chen',
            'to_address' => 'alice@example.com', 'subject' => 'Quarterly report',
            'body' => "Hi Alice,\n\nThe quarterly report draft is ready for your review.\n\nThanks,\nCarol",
            'thread_id' => 9001, 'status' => 'read',
        ]);
        $insertMessage([
            'user' => 'alice', 'folder' => 'Inbox',
            'from_address' => 'carol@corporate.org', 'from_name' => 'Carol Chen',
            'to_address' => 'alice@example.com', 'subject' => 'Re: Quarterly report',
            'body' => "Hi Alice,\n\nOne more update on section three.\n\nBest,\nCarol",
            'thread_id' => 9001, 'status' => 'read',
        ]);
        $insertMessage([
            'user' => 'alice', 'folder' => 'Work',
            'from_address' => 'dan@example.com', 'from_name' => 'Dan Diaz',
            'to_address' => 'alice@example.com', 'subject' => 'Domain migration schedule',
            'body' => "Alice,\n\nThe domain migration is scheduled for next week.\n\nDan",
            'status' => 'unread', 'priority' => 'high',
        ]);
        $insertMessage([
            'user' => 'alice', 'folder' => 'Sent',
            'from_address' => 'alice@example.com', 'from_name' => 'Alice Anderson',
            'to_address' => 'bob@example.com', 'subject' => 'Meeting confirmation',
            'body' => 'Hi Bob, see you on Monday at 10am.',
            'status' => 'read',
        ]);

        $insertMessage([
            'user' => 'bob', 'folder' => 'Inbox',
            'from_address' => 'alice@example.com', 'from_name' => 'Alice Anderson',
            'to_address' => 'bob@example.com', 'subject' => 'Meeting confirmation',
            'body' => 'Hi Bob, see you on Monday at 10am.',
            'status' => 'unread',
        ]);
        $insertMessage([
            'user' => 'bob', 'folder' => 'Inbox',
            'from_address' => 'dan@example.com', 'from_name' => 'Dan Diaz',
            'to_address' => 'bob@example.com', 'subject' => 'Invoice attached',
            'body' => 'Bob, please find the invoice attached.',
            'status' => 'read',
        ]);

        $insertMessage([
            'user' => 'carol', 'folder' => 'Inbox',
            'from_address' => 'alice@example.com', 'from_name' => 'Alice Anderson',
            'to_address' => 'carol@corporate.org', 'subject' => 'Quarterly report feedback',
            'body' => 'Carol, the report looks great. Two small edits requested.',
            'status' => 'read',
        ]);

        // Seed attachments (deterministic local files) for MAIL-05.
        $uploadsDir = storage_path('uploads');
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }
        $attachDefs = [
            ['alice', $m1, 'meeting_notes.txt', 'text/plain', "Meeting notes\n1. Review milestones\n2. Confirm budget\n"],
            ['alice', $m1, 'roadmap.pdf', 'application/pdf', "%PDF-1.4 synthetic attachment body for roadmap.pdf\n"],
            ['bob', null, 'invoice.csv', 'text/csv', "item,amount\nconsulting,1200\n"],
        ];
        $fileId = [];
        foreach ($attachDefs as $a) {
            $stored = date('Ymd') . '_seed_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $a[2]);
            $path = $uploadsDir . DIRECTORY_SEPARATOR . $stored;
            file_put_contents($path, $a[4]);
            $pdo->prepare(
                'INSERT INTO stored_files (owner_user_id, message_id, purpose, original_name, stored_name, mime_type, size, created_at)
                 VALUES (?, ?, \'attachment\', ?, ?, ?, ?, datetime(\'now\'))'
            )->execute([$userId[$a[0]], $a[1], $a[2], $stored, $a[3], strlen($a[4])]);
            $fid = (int) $pdo->lastInsertId();
            $fileId[] = $fid;
            if ($a[1] !== null) {
                $pdo->prepare(
                    'INSERT INTO attachment_handling (user_id, message_id, stored_file_id, status, created_at) VALUES (?, ?, ?, \'linked\', datetime(\'now\'))'
                )->execute([$userId[$a[0]], $a[1], $fid]);
            }
        }

        // Draft compose for MAIL-03.
        $pdo->prepare(
            'INSERT INTO message_compose (user_id, recipient, subject, body, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, \'draft\', datetime(\'now\'), datetime(\'now\'))'
        )->execute([$userId['alice'], 'bob@example.com', 'Draft: next sprint plan', 'Hi Bob,\n\nHere is the draft plan...', ]);

        // Contacts for MAIL-06.
        $contactDefs = [
            ['alice', 'Bob', 'Becker', 'bob@example.com', '+1-555-0102', 'Example Corp', 'Primary contact'],
            ['alice', 'Carol', 'Chen', 'carol@corporate.org', '+1-555-0103', 'Corporate', 'Quarterly reports'],
            ['alice', 'Dan', 'Diaz', 'dan@example.com', '+1-555-0104', 'Example Corp', 'Domain admin'],
            ['bob', 'Alice', 'Anderson', 'alice@example.com', '+1-555-0101', 'Example Corp', 'Colleague'],
            ['carol', 'Alice', 'Anderson', 'alice@example.com', '+1-555-0101', 'Example Corp', 'Reviewer'],
        ];
        foreach ($contactDefs as $c) {
            $pdo->prepare(
                'INSERT INTO contacts (owner_user_id, first_name, last_name, email, phone, organization, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\'), datetime(\'now\'))'
            )->execute([$userId[$c[0]], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6]]);
        }

        // Rules for MAIL-07.
        $ruleDefs = [
            ['alice', 'Newsletter to archive', 'subject', 'contains', 'newsletter', 'folder', 'Archive', 1, 1],
            ['alice', 'Forward urgent', 'from', 'equals', 'dan@example.com', 'forward', 'carol@corporate.org', 1, 2],
            ['bob', 'Delete spam', 'subject', 'contains', 'lottery', 'delete', '', 0, 1],
        ];
        foreach ($ruleDefs as $r) {
            $pdo->prepare(
                'INSERT INTO mail_rules (user_id, name, match_field, match_operator, match_value, action_type, action_value, enabled, position, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'), datetime(\'now\'))'
            )->execute($r);
        }

        // Quarantine for MAIL-09.
        $quarantineDefs = [
            ['example.com', 'spammer@example.net', 'alice@example.com', 'Cheap pills offer', 'Spam score 8.5', 8.5],
            ['example.com', 'unknown@example.net', 'bob@example.com', 'Lottery winner', 'Spam score 9.1', 9.1],
            ['corporate.org', 'promo@example.org', 'carol@corporate.org', 'Marketing blast', 'Spam score 6.2', 6.2],
        ];
        foreach ($quarantineDefs as $q) {
            $pdo->prepare(
                'INSERT INTO quarantine (domain_id, from_address, to_address, subject, reason, score, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, \'quarantined\', datetime(\'now\'), datetime(\'now\'))'
            )->execute([$domainIds[$q[0]], $q[1], $q[2], $q[3], $q[4], $q[5]]);
        }

        // Audit events for MAIL-10.
        $auditDefs = [
            [null, null, 'guest', 'auth.login_failed', 'User', 'wrong@example.com', ['reason' => 'bad credentials']],
            [$userId['alice'], 'alice', 'mail_user', 'auth.login', 'User', (string) $userId['alice'], []],
            [$userId['alice'], 'alice', 'mail_user', 'mail.send', 'Message', 'seed', ['to' => 'bob@example.com']],
            [$userId['dan'], 'dan', 'domain_admin', 'domain.update', 'Domain', (string) $domainIds['example.com'], ['quota_mb' => 1024]],
            [$userId['sysadmin'], 'sysadmin', 'system_admin', 'auth.login', 'User', (string) $userId['sysadmin'], []],
        ];
        foreach ($auditDefs as $a) {
            $pdo->prepare(
                'INSERT INTO audit_events (user_id, actor_username, actor_role, action, entity_type, entity_id, details, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'127.0.0.1\', datetime(\'now\'))'
            )->execute([$a[0], $a[1], $a[2], $a[3], $a[4], $a[5], json_encode($a[6] ?? [])]);
        }

        // Account access history for MAIL-01.
        $pdo->prepare(
            'INSERT INTO account_access (user_id, type, status, message, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))'
        )->execute([$userId['alice'], 'login', 'success', 'Signed in', '127.0.0.1', 'seed']);

        // Import/export jobs for MAIL-11.
        $pdo->prepare(
            'INSERT INTO import_export (user_id, kind, entity_type, status, stored_file_id, counts, created_at, updated_at)
             VALUES (?, \'export\', \'contacts\', \'done\', NULL, 3, datetime(\'now\'), datetime(\'now\'))'
        )->execute([$userId['alice']]);

        // Frontend API error scenarios for MAIL-12.
        $feDefs = [
            [$userId['alice'], 'delivery_failure', ['to' => 'bob@example.com'], 502, 'Delivery temporarily failed', 0],
            [$userId['alice'], 'invalid_recipient', ['to' => 'nobody@missing.tld'], 422, 'Invalid recipient address', 0],
            [$userId['alice'], 'permission_denied', ['action' => 'domain.update'], 403, 'Permission denied for this role', 1],
        ];
        foreach ($feDefs as $f) {
            $pdo->prepare(
                'INSERT INTO frontend_api_errors (user_id, scenario, request_payload, response_status, response_message, resolved, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))'
            )->execute([$f[0], $f[1], json_encode($f[2]), $f[3], $f[4], $f[5]]);
        }
    }
}
