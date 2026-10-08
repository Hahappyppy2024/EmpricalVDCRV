-- P04 Hotel Booking System schema
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  email         TEXT    NOT NULL UNIQUE,
  password_hash TEXT    NOT NULL,
  name          TEXT    NOT NULL,
  role          TEXT    NOT NULL CHECK (role IN ('guest','staff','admin','moderator')),
  phone         TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sessions (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  token      TEXT    NOT NULL UNIQUE,
  user_id    INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT    NOT NULL DEFAULT (datetime('now')),
  expires_at TEXT    NOT NULL,
  revoked_at TEXT
);

CREATE TABLE IF NOT EXISTS password_resets (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id),
  token      TEXT    NOT NULL UNIQUE,
  created_at TEXT    NOT NULL DEFAULT (datetime('now')),
  expires_at TEXT    NOT NULL,
  used_at    TEXT
);

CREATE TABLE IF NOT EXISTS rooms (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  name           TEXT    NOT NULL UNIQUE,
  type           TEXT    NOT NULL,
  description    TEXT    NOT NULL,
  price_per_night INTEGER NOT NULL,
  capacity       INTEGER NOT NULL,
  amenities      TEXT    NOT NULL DEFAULT '[]',
  image_url      TEXT,
  status         TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active','maintenance','retired')),
  created_at     TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at     TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS availability_blocks (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  room_id    INTEGER NOT NULL REFERENCES rooms(id),
  start_date TEXT    NOT NULL,
  end_date   TEXT    NOT NULL,
  reason     TEXT    NOT NULL DEFAULT 'maintenance',
  created_by INTEGER REFERENCES users(id),
  created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS bookings (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  code            TEXT    NOT NULL UNIQUE,
  guest_id        INTEGER NOT NULL REFERENCES users(id),
  room_id         INTEGER NOT NULL REFERENCES rooms(id),
  check_in_date   TEXT    NOT NULL,
  check_out_date  TEXT    NOT NULL,
  guests          INTEGER NOT NULL DEFAULT 1,
  contact_name    TEXT    NOT NULL,
  contact_email   TEXT    NOT NULL,
  contact_phone   TEXT    NOT NULL,
  total_price     INTEGER NOT NULL,
  status          TEXT    NOT NULL DEFAULT 'confirmed' CHECK (status IN ('confirmed','checked_in','checked_out','cancelled')),
  idempotency_key TEXT    UNIQUE,
  created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS payments (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  booking_id       INTEGER NOT NULL UNIQUE REFERENCES bookings(id),
  amount           INTEGER NOT NULL,
  method           TEXT    NOT NULL DEFAULT 'card',
  card_masked      TEXT    NOT NULL,
  status           TEXT    NOT NULL DEFAULT 'paid',
  transaction_code TEXT    NOT NULL UNIQUE,
  created_at       TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS messages (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  booking_id  INTEGER NOT NULL REFERENCES bookings(id),
  user_id     INTEGER NOT NULL REFERENCES users(id),
  body        TEXT    NOT NULL,
  sender_role TEXT    NOT NULL,
  created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS reviews (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  booking_id   INTEGER NOT NULL UNIQUE REFERENCES bookings(id),
  user_id      INTEGER NOT NULL REFERENCES users(id),
  rating       INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
  text         TEXT    NOT NULL,
  status       TEXT    NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','published','rejected')),
  moderated_by INTEGER REFERENCES users(id),
  moderated_at TEXT,
  created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS invoices (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  booking_id INTEGER NOT NULL UNIQUE REFERENCES bookings(id),
  number     TEXT    NOT NULL UNIQUE,
  total      INTEGER NOT NULL,
  status     TEXT    NOT NULL DEFAULT 'issued' CHECK (status IN ('issued','paid','void')),
  issued_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_events (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_id    INTEGER REFERENCES users(id),
  action      TEXT    NOT NULL,
  target_type TEXT    NOT NULL,
  target_id   TEXT,
  details     TEXT,
  created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS outbound_notifications (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  recipient  TEXT    NOT NULL,
  channel    TEXT    NOT NULL DEFAULT 'email',
  subject    TEXT    NOT NULL,
  body       TEXT    NOT NULL,
  created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- Workflow records for each use-case contract entity

CREATE TABLE IF NOT EXISTS account_access_log (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER REFERENCES users(id),
  action     TEXT    NOT NULL,
  details    TEXT,
  created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS room_search_records (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id       INTEGER REFERENCES users(id),
  query         TEXT    NOT NULL,
  results_count INTEGER NOT NULL,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS room_details_views (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER REFERENCES users(id),
  room_id    INTEGER NOT NULL REFERENCES rooms(id),
  note       TEXT,
  created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS check_in_out_events (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  booking_id INTEGER NOT NULL REFERENCES bookings(id),
  actor_id   INTEGER REFERENCES users(id),
  action     TEXT    NOT NULL CHECK (action IN ('check_in','check_out')),
  created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS report_exports (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_id    INTEGER REFERENCES users(id),
  report_type TEXT    NOT NULL,
  filters     TEXT,
  note        TEXT,
  row_count   INTEGER NOT NULL,
  exported_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS frontend_error_reports (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER REFERENCES users(id),
  context    TEXT    NOT NULL,
  payload    TEXT,
  created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);
CREATE INDEX IF NOT EXISTS idx_bookings_guest ON bookings(guest_id);
CREATE INDEX IF NOT EXISTS idx_bookings_room ON bookings(room_id);
CREATE INDEX IF NOT EXISTS idx_bookings_dates ON bookings(check_in_date, check_out_date);
CREATE INDEX IF NOT EXISTS idx_messages_booking ON messages(booking_id);
CREATE INDEX IF NOT EXISTS idx_reviews_booking ON reviews(booking_id);
CREATE INDEX IF NOT EXISTS idx_invoices_booking ON invoices(booking_id);
CREATE INDEX IF NOT EXISTS idx_blocks_room ON availability_blocks(room_id);
