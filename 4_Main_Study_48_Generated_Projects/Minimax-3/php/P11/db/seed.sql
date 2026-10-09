-- P11 Hosting Control Panel deterministic seed data
PRAGMA foreign_keys = ON;

-- Plans
INSERT INTO plans (id, code, name, disk_quota_mb, bandwidth_quota_mb, max_domains, max_databases, price_cents) VALUES
    (1, 'starter', 'Starter', 1024, 5120, 1, 1, 500),
    (2, 'pro', 'Pro', 5120, 20480, 5, 5, 1500),
    (3, 'business', 'Business', 20480, 102400, 25, 25, 4900);

-- Default settings
INSERT INTO settings (key, value) VALUES
    ('panel.name', 'AetherPanel'),
    ('panel.theme', 'blue'),
    ('panel.support_email', 'support@aetherpanel.local'),
    ('default_php_version', '8.3');

-- Users: 1 admin, 1 support, 3 customers
-- Passwords are all "Password123!" hashed with PASSWORD_BCRYPT
-- The hash below corresponds to "Password123!" (cost 10) computed offline
INSERT INTO users (id, username, email, password_hash, full_name, role, plan_id, is_active) VALUES
    (1, 'admin',    'admin@aetherpanel.local',    '$2y$10$VUDv7zGYq7t.ZM0cO8D9COyD7lCuA9y/OUHrNsj4q.cRW6B5rEXi.', 'Alice Admin',   'admin',    NULL, 1),
    (2, 'support',  'support@aetherpanel.local',  '$2y$10$VUDv7zGYq7t.ZM0cO8D9COyD7lCuA9y/OUHrNsj4q.cRW6B5rEXi.', 'Sam Support',   'support',  NULL, 1),
    (3, 'alice',    'alice@example.com',          '$2y$10$VUDv7zGYq7t.ZM0cO8D9COyD7lCuA9y/OUHrNsj4q.cRW6B5rEXi.', 'Alice Customer','customer', 2, 1),
    (4, 'bob',      'bob@example.com',            '$2y$10$VUDv7zGYq7t.ZM0cO8D9COyD7lCuA9y/OUHrNsj4q.cRW6B5rEXi.', 'Bob Customer',  'customer', 1, 1),
    (5, 'charlie',  'charlie@example.com',        '$2y$10$VUDv7zGYq7t.ZM0cO8D9COyD7lCuA9y/OUHrNsj4q.cRW6B5rEXi.', 'Charlie C.',    'customer', 3, 1);

-- Domains
INSERT INTO domains (id, user_id, domain, type, document_root, status) VALUES
    (1, 3, 'alice-site.test', 'domain', '/home/alice/public_html', 'active'),
    (2, 3, 'blog.alice-site.test', 'subdomain', '/home/alice/blog', 'active'),
    (3, 4, 'bob-site.test', 'domain', '/home/bob/public_html', 'active'),
    (4, 5, 'charlie-corp.test', 'domain', '/home/charlie/public_html', 'active'),
    (5, 5, 'shop.charlie-corp.test', 'subdomain', '/home/charlie/shop', 'active');

INSERT INTO dns_records (domain_id, name, type, value, ttl) VALUES
    (1, '@',   'A',    '192.0.2.10', 3600),
    (1, 'www', 'CNAME','alice-site.test.', 3600),
    (1, '@',   'MX',   'mail.alice-site.test.', 3600),
    (3, '@',   'A',    '192.0.2.40', 3600),
    (4, '@',   'A',    '192.0.2.50', 3600);

-- Sites
INSERT INTO sites (id, user_id, domain_id, site_name, document_root, php_version, status) VALUES
    (1, 3, 1, 'alice main', '/home/alice/public_html', '8.3', 'deployed'),
    (2, 3, 2, 'alice blog', '/home/alice/blog', '8.3', 'deployed'),
    (3, 4, 3, 'bob main',   '/home/bob/public_html', '8.3', 'deployed'),
    (4, 5, 4, 'charlie main','/home/charlie/public_html', '8.3', 'deployed');

-- Files
INSERT INTO files (user_id, site_id, parent_path, name, path, size_bytes, mime_type) VALUES
    (3, 1, '/', 'index.php', '/home/alice/public_html/index.php', 532, 'text/x-php'),
    (3, 1, '/', 'README.md', '/home/alice/public_html/README.md', 410, 'text/markdown'),
    (3, 2, '/', 'index.php', '/home/alice/blog/index.php', 220, 'text/x-php'),
    (4, 3, '/', 'index.php', '/home/bob/public_html/index.php', 180, 'text/x-php'),
    (5, 4, '/', 'index.php', '/home/charlie/public_html/index.php', 980, 'text/x-php');

-- Databases
INSERT INTO databases (user_id, db_name, db_user, db_pass_hash, size_mb) VALUES
    (3, 'alice_app',  'alice_app',  '$2y$10$VUDv7zGYq7t.ZM0cO8D9COyD7lCuA9y/OUHrNsj4q.cRW6B5rEXi.', 14),
    (3, 'alice_blog', 'alice_blog', '$2y$10$VUDv7zGYq7t.ZM0cO8D9COyD7lCuA9y/OUHrNsj4q.cRW6B5rEXi.',  3),
    (4, 'bob_app',    'bob_app',    '$2y$10$VUDv7zGYq7t.ZM0cO8D9COyD7lCuA9y/OUHrNsj4q.cRW6B5rEXi.',  6),
    (5, 'charlie_db', 'charlie_db', '$2y$10$VUDv7zGYq7t.ZM0cO8D9COyD7lCuA9y/OUHrNsj4q.cRW6B5rEXi.', 25);

-- Backups
INSERT INTO backups (user_id, scope, target_id, filename, size_bytes, status, note) VALUES
    (3, 'site', 1, 'alice_site_20260801.tar.gz', 524288, 'available', 'Weekly full backup'),
    (3, 'database', 1, 'alice_app_20260802.tar.gz', 65536, 'available', NULL),
    (4, 'site', 3, 'bob_site_20260801.tar.gz', 131072, 'available', NULL);

-- Certificates
INSERT INTO certificates (user_id, domain_id, common_name, issuer, cert_pem, key_pem, valid_from, valid_to, status) VALUES
    (3, 1, 'alice-site.test', 'AetherPanel CA (test)', '-----BEGIN CERTIFICATE-----\nMIIB...test...\n-----END CERTIFICATE-----', '-----BEGIN PRIVATE KEY-----\nMIIE...test...\n-----END PRIVATE KEY-----', '2026-07-01', '2027-07-01', 'active'),
    (5, 4, 'charlie-corp.test', 'AetherPanel CA (test)', '-----BEGIN CERTIFICATE-----\nMIIB...test...\n-----END CERTIFICATE-----', '-----BEGIN PRIVATE KEY-----\nMIIE...test...\n-----END PRIVATE KEY-----', '2026-05-01', '2026-09-01', 'active');

-- Cron jobs
INSERT INTO cron_jobs (user_id, name, schedule, command, last_run, last_status, is_active) VALUES
    (3, 'Daily backup',     '0 2 * * *',  '/usr/local/bin/aether-panel backup site 1', datetime('now','-1 day'), 'ok', 1),
    (3, 'Clear cache',      '*/15 * * * *', '/usr/local/bin/aether-panel cache clear', datetime('now','-1 hour'), 'ok', 1),
    (5, 'Hourly report',    '0 * * * *',   '/usr/local/bin/aether-panel report hourly',  datetime('now','-2 hours'),'ok', 1);

-- Resource usage (last 14 days per user, sampled)
INSERT INTO resource_usage (user_id, period, cpu_percent, disk_used_mb, bandwidth_used_mb, emails_sent, recorded_at) VALUES
    (3, '2026-08-15', 12.4,  610,  340,  18, '2026-08-15 00:00:00'),
    (3, '2026-08-16', 14.0,  640,  402,  20, '2026-08-16 00:00:00'),
    (3, '2026-08-17', 11.2,  655,  380,  15, '2026-08-17 00:00:00'),
    (3, '2026-08-18', 18.5,  680,  510,  22, '2026-08-18 00:00:00'),
    (3, '2026-08-19',  9.3,  690,  330,  12, '2026-08-19 00:00:00'),
    (3, '2026-08-20', 15.6,  702,  455,  19, '2026-08-20 00:00:00'),
    (3, '2026-08-21', 17.0,  720,  600,  25, '2026-08-21 00:00:00'),
    (4, '2026-08-18',  6.0,  120,   90,   2, '2026-08-18 00:00:00'),
    (4, '2026-08-19',  7.4,  130,  110,   3, '2026-08-19 00:00:00'),
    (4, '2026-08-20',  5.8,  140,  100,   2, '2026-08-20 00:00:00'),
    (5, '2026-08-18', 22.1, 4200, 2400,  90, '2026-08-18 00:00:00'),
    (5, '2026-08-19', 25.0, 4280, 2600, 110, '2026-08-19 00:00:00'),
    (5, '2026-08-20', 28.4, 4350, 2900, 120, '2026-08-20 00:00:00');

-- Tickets
INSERT INTO tickets (id, user_id, assignee_id, subject, body, priority, status, created_at, updated_at) VALUES
    (1, 3, 2, 'Cannot reach site after DNS change', 'Hi support, after I updated the A record my site stopped responding. Please advise.', 'high',   'answered', '2026-08-19 09:00:00', '2026-08-19 12:00:00'),
    (2, 4, 2, 'Database quota exceeded',             'My application reports MySQL quota exceeded.',                                  'normal', 'open',     '2026-08-21 14:30:00', '2026-08-21 14:30:00'),
    (3, 5, 2, 'Need TLS for new subdomain',          'How do I enable TLS for shop.charlie-corp.test?',                              'normal', 'pending',  '2026-08-22 10:00:00', '2026-08-22 10:00:00');

INSERT INTO ticket_replies (ticket_id, user_id, body) VALUES
    (1, 2, 'Hi Alice, please allow up to 24h for DNS propagation and verify with dig.'),
    (2, 2, 'Hi Bob, I see your disk usage at 96%. Consider deleting old backups.'),
    (3, 2, 'Hi Charlie, please attach a CSR from the SSL page and we will sign it.');

-- Audit events
INSERT INTO audit_events (actor_id, actor_role, action, target_type, target_id, details, ip) VALUES
    (1, 'admin',   'settings.update', 'settings', NULL, 'Updated panel.name', '127.0.0.1'),
    (3, 'customer','domain.create',    'domain',   1,    'Added alice-site.test', '203.0.113.5'),
    (3, 'customer','file.upload',      'file',     1,    'index.php', '203.0.113.5'),
    (5, 'customer','certificate.request','cert', 2,    'Requested TLS for charlie-corp.test', '198.51.100.10'),
    (2, 'support', 'ticket.reply',     'ticket',   1,    'Replied to ticket 1', '127.0.0.1');