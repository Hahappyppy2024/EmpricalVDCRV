PRAGMA foreign_keys = ON;

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password_hash TEXT NOT NULL,
  display_name TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('student','instructor','admin')),
  bio TEXT NOT NULL DEFAULT '',
  timezone TEXT NOT NULL DEFAULT 'UTC',
  email_verified INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE user_preferences (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  email_announcements INTEGER NOT NULL DEFAULT 1,
  email_assignments INTEGER NOT NULL DEFAULT 1,
  browser_notifications INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE sessions (
  id TEXT PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE password_reset_tokens (
  token_hash TEXT PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at TEXT NOT NULL,
  used_at TEXT
);
CREATE TABLE categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE COLLATE NOCASE,
  slug TEXT NOT NULL UNIQUE
);
CREATE TABLE courses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  description TEXT NOT NULL,
  category_id INTEGER NOT NULL REFERENCES categories(id),
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published','archived')),
  capacity INTEGER NOT NULL CHECK (capacity > 0),
  enrollment_opens_at TEXT,
  enrollment_closes_at TEXT,
  enrollment_key_hash TEXT,
  version INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE course_instructors (
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  PRIMARY KEY (course_id,user_id)
);
CREATE TABLE enrollments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','withdrawn','completed')),
  enrolled_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(course_id,student_id)
);
CREATE TABLE stored_files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  original_name TEXT NOT NULL,
  stored_name TEXT NOT NULL UNIQUE,
  mime_type TEXT NOT NULL,
  size_bytes INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE materials (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  visibility TEXT NOT NULL DEFAULT 'visible' CHECK (visibility IN ('visible','hidden')),
  external_url TEXT,
  file_id INTEGER REFERENCES stored_files(id),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE announcements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  publish_at TEXT NOT NULL,
  author_id INTEGER NOT NULL REFERENCES users(id),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE discussion_topics (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE discussion_posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  topic_id INTEGER NOT NULL REFERENCES discussion_topics(id) ON DELETE CASCADE,
  author_id INTEGER NOT NULL REFERENCES users(id),
  body TEXT NOT NULL,
  is_topic_post INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE assignments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  instructions TEXT NOT NULL,
  due_at TEXT NOT NULL,
  max_score REAL NOT NULL CHECK (max_score > 0),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE submissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  assignment_id INTEGER NOT NULL REFERENCES assignments(id) ON DELETE CASCADE,
  student_id INTEGER NOT NULL REFERENCES users(id),
  version INTEGER NOT NULL,
  text_content TEXT,
  file_id INTEGER REFERENCES stored_files(id),
  submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_late INTEGER NOT NULL,
  is_current INTEGER NOT NULL DEFAULT 1,
  score REAL,
  feedback TEXT,
  released INTEGER NOT NULL DEFAULT 0,
  UNIQUE(assignment_id,student_id,version)
);
CREATE TABLE quizzes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  time_limit_minutes INTEGER NOT NULL CHECK (time_limit_minutes > 0),
  attempt_limit INTEGER NOT NULL CHECK (attempt_limit > 0),
  max_score REAL NOT NULL CHECK (max_score > 0),
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published')),
  version INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE questions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
  prompt TEXT NOT NULL,
  choices_json TEXT NOT NULL,
  correct_answer TEXT NOT NULL,
  points REAL NOT NULL CHECK (points > 0),
  position INTEGER NOT NULL
);
CREATE TABLE quiz_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
  student_id INTEGER NOT NULL REFERENCES users(id),
  started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at TEXT,
  status TEXT NOT NULL DEFAULT 'in_progress' CHECK (status IN ('in_progress','submitted')),
  score REAL,
  feedback TEXT,
  released INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE quiz_answers (
  attempt_id INTEGER NOT NULL REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
  answer TEXT NOT NULL,
  PRIMARY KEY(attempt_id,question_id)
);
CREATE TABLE notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type TEXT NOT NULL,
  message TEXT NOT NULL,
  link TEXT NOT NULL DEFAULT '',
  read_at TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE audit_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_id INTEGER REFERENCES users(id),
  action TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id INTEGER,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sessions_expiry ON sessions(expires_at);
CREATE INDEX idx_courses_status ON courses(status);
CREATE INDEX idx_enrollments_course_status ON enrollments(course_id,status);
CREATE INDEX idx_notifications_user_read ON notifications(user_id,read_at);
