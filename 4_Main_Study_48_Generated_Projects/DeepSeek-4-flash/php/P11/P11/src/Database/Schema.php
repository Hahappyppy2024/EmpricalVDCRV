<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Idempotent schema bootstrap. Every table the application uses is created
 * here with CREATE TABLE IF NOT EXISTS so the app can run right after install.
 */
final class Schema
{
    public static function ensure(): void
    {
        $db = Connection::db();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL DEFAULT '',
                role TEXT NOT NULL DEFAULT 'customer',
                plan_id INTEGER,
                created_at TEXT NOT NULL,
                FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                disk_quota INTEGER NOT NULL DEFAULT 1024,
                bandwidth_quota INTEGER NOT NULL DEFAULT 1024,
                max_domains INTEGER NOT NULL DEFAULT 5,
                max_sites INTEGER NOT NULL DEFAULT 5,
                max_databases INTEGER NOT NULL DEFAULT 5,
                monthly_price REAL NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS sessions (
                id TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                data TEXT NOT NULL DEFAULT '{}',
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS account_access (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                username TEXT NOT NULL DEFAULT '',
                action TEXT NOT NULL,
                ip TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS domains (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                kind TEXT NOT NULL DEFAULT 'domain',
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE (user_id, name)
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS dns_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                domain_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                name TEXT NOT NULL,
                value TEXT NOT NULL,
                ttl INTEGER NOT NULL DEFAULT 3600,
                FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS sites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                domain_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                document_root TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'deployed',
                deployed_at TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
                UNIQUE (user_id, name)
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS stored_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                site_id INTEGER,
                file_path TEXT NOT NULL,
                file_name TEXT NOT NULL,
                file_size INTEGER NOT NULL DEFAULT 0,
                mime_type TEXT NOT NULL DEFAULT 'application/octet-stream',
                is_dir INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS databases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE (user_id, name)
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS database_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                database_id INTEGER NOT NULL,
                username TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                host TEXT NOT NULL DEFAULT 'localhost',
                privileges TEXT NOT NULL DEFAULT 'ALL',
                FOREIGN KEY (database_id) REFERENCES databases(id) ON DELETE CASCADE
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS backups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                kind TEXT NOT NULL DEFAULT 'full',
                source_type TEXT NOT NULL DEFAULT 'site',
                source_id INTEGER,
                stored_path TEXT NOT NULL,
                file_size INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'ready',
                created_at TEXT NOT NULL,
                restored_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS certificates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                domain_id INTEGER NOT NULL,
                provider TEXT NOT NULL DEFAULT 'letsencrypt',
                status TEXT NOT NULL DEFAULT 'requested',
                certificate_text TEXT NOT NULL DEFAULT '',
                private_key_text TEXT NOT NULL DEFAULT '',
                not_before TEXT,
                not_after TEXT,
                created_at TEXT NOT NULL,
                renewed_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS scheduled_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                command TEXT NOT NULL,
                schedule TEXT NOT NULL DEFAULT '0 * * * *',
                enabled INTEGER NOT NULL DEFAULT 1,
                status TEXT NOT NULL DEFAULT 'idle',
                last_run TEXT,
                next_run TEXT,
                last_output TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS resource_usage (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                recorded_at TEXT NOT NULL,
                cpu_usage REAL NOT NULL DEFAULT 0,
                disk_used INTEGER NOT NULL DEFAULT 0,
                traffic_used INTEGER NOT NULL DEFAULT 0,
                quota_percent REAL NOT NULL DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                subject TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'open',
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS ticket_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_id INTEGER NOT NULL,
                author_id INTEGER NOT NULL,
                author_role TEXT NOT NULL,
                body TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                username TEXT NOT NULL DEFAULT '',
                action TEXT NOT NULL,
                module TEXT NOT NULL,
                entity_type TEXT NOT NULL DEFAULT '',
                entity_id TEXT NOT NULL DEFAULT '',
                details TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            );"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );"
        );
    }
}
