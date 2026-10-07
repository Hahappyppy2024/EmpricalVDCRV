from models.db import get_db


def create_quiz(course_id, created_by, title, description='', open_at='', due_at=''):
    db = get_db()
    cur = db.execute(
        'INSERT INTO quizzes (course_id, created_by, title, description, open_at, due_at) VALUES (?, ?, ?, ?, ?, ?)',
        (course_id, created_by, title, description, open_at, due_at),
    )
    db.commit()
    return get_quiz_by_id(course_id, cur.lastrowid)


def list_quizzes(course_id):
    db = get_db()
    return db.execute(
        'SELECT q.quiz_id, q.course_id, q.created_by, q.title, q.description, q.open_at, q.due_at, q.created_at, q.updated_at, u.username, u.display_name '
        'FROM quizzes q JOIN users u ON u.user_id = q.created_by WHERE q.course_id = ? ORDER BY q.quiz_id DESC',
        (course_id,),
    ).fetchall()


def get_quiz_by_id(course_id, quiz_id):
    db = get_db()
    return db.execute(
        'SELECT q.quiz_id, q.course_id, q.created_by, q.title, q.description, q.open_at, q.due_at, q.created_at, q.updated_at, u.username, u.display_name '
        'FROM quizzes q JOIN users u ON u.user_id = q.created_by WHERE q.course_id = ? AND q.quiz_id = ?',
        (course_id, quiz_id),
    ).fetchone()


def update_quiz(course_id, quiz_id, title, description='', open_at='', due_at=''):
    db = get_db()
    db.execute(
        'UPDATE quizzes SET title = ?, description = ?, open_at = ?, due_at = ?, updated_at = CURRENT_TIMESTAMP WHERE course_id = ? AND quiz_id = ?',
        (title, description, open_at, due_at, course_id, quiz_id),
    )
    db.commit()
    return get_quiz_by_id(course_id, quiz_id)


def delete_quiz(course_id, quiz_id):
    db = get_db()
    attempt_rows = db.execute('SELECT attempt_id FROM quiz_attempts WHERE course_id = ? AND quiz_id = ?', (course_id, quiz_id)).fetchall()
    attempt_ids = [row['attempt_id'] for row in attempt_rows]
    for attempt_id in attempt_ids:
        db.execute('DELETE FROM quiz_answers WHERE attempt_id = ?', (attempt_id,))
    db.execute('DELETE FROM quiz_attempts WHERE course_id = ? AND quiz_id = ?', (course_id, quiz_id))
    db.execute('DELETE FROM quiz_questions WHERE quiz_id = ?', (quiz_id,))
    db.execute('DELETE FROM quizzes WHERE course_id = ? AND quiz_id = ?', (course_id, quiz_id))
    db.commit()


def list_quiz_questions(course_id, quiz_id):
    db = get_db()
    return db.execute(
        'SELECT qq.quiz_id, qq.question_id, qq.points, qq.position, q.prompt, q.qtype '
        'FROM quiz_questions qq JOIN quizzes z ON z.quiz_id = qq.quiz_id JOIN questions q ON q.question_id = qq.question_id '
        'WHERE z.course_id = ? AND qq.quiz_id = ? ORDER BY qq.position ASC, qq.question_id ASC',
        (course_id, quiz_id),
    ).fetchall()


def add_quiz_question(course_id, quiz_id, question_id, points, position):
    db = get_db()
    exists = db.execute('SELECT 1 FROM questions WHERE course_id = ? AND question_id = ?', (course_id, question_id)).fetchone()
    if exists is None:
        return None
    db.execute(
        'INSERT INTO quiz_questions (quiz_id, question_id, points, position) VALUES (?, ?, ?, ?) '
        'ON CONFLICT(quiz_id, question_id) DO UPDATE SET points = excluded.points, position = excluded.position',
        (quiz_id, question_id, points, position),
    )
    db.commit()
    return db.execute(
        'SELECT qq.quiz_id, qq.question_id, qq.points, qq.position, q.prompt, q.qtype '
        'FROM quiz_questions qq JOIN questions q ON q.question_id = qq.question_id WHERE qq.quiz_id = ? AND qq.question_id = ?',
        (quiz_id, question_id),
    ).fetchone()


def remove_quiz_question(quiz_id, question_id):
    db = get_db()
    db.execute('DELETE FROM quiz_questions WHERE quiz_id = ? AND question_id = ?', (quiz_id, question_id))
    db.commit()


def create_quiz_attempt(quiz_id, course_id, student_id):
    db = get_db()
    cur = db.execute('INSERT INTO quiz_attempts (quiz_id, course_id, student_id) VALUES (?, ?, ?)', (quiz_id, course_id, student_id))
    db.commit()
    return get_attempt_by_id(course_id, quiz_id, cur.lastrowid)


def get_attempt_by_id(course_id, quiz_id, attempt_id):
    db = get_db()
    return db.execute(
        'SELECT a.attempt_id, a.quiz_id, a.course_id, a.student_id, a.started_at, a.submitted_at, u.username, u.display_name, z.title AS quiz_title '
        'FROM quiz_attempts a JOIN users u ON u.user_id = a.student_id JOIN quizzes z ON z.quiz_id = a.quiz_id '
        'WHERE a.course_id = ? AND a.quiz_id = ? AND a.attempt_id = ?',
        (course_id, quiz_id, attempt_id),
    ).fetchone()


def upsert_quiz_answer(attempt_id, question_id, answer_json):
    db = get_db()
    db.execute(
        'INSERT INTO quiz_answers (attempt_id, question_id, answer_json) VALUES (?, ?, ?) '
        'ON CONFLICT(attempt_id, question_id) DO UPDATE SET answer_json = excluded.answer_json, created_at = CURRENT_TIMESTAMP',
        (attempt_id, question_id, answer_json),
    )
    db.commit()
    return db.execute('SELECT answer_id, attempt_id, question_id, answer_json, created_at FROM quiz_answers WHERE attempt_id = ? AND question_id = ?', (attempt_id, question_id)).fetchone()


def submit_quiz_attempt(course_id, quiz_id, attempt_id):
    db = get_db()
    db.execute('UPDATE quiz_attempts SET submitted_at = CURRENT_TIMESTAMP WHERE course_id = ? AND quiz_id = ? AND attempt_id = ?', (course_id, quiz_id, attempt_id))
    db.commit()
    return get_attempt_by_id(course_id, quiz_id, attempt_id)


def list_attempts_for_student(student_id):
    db = get_db()
    return db.execute(
        'SELECT a.attempt_id, a.quiz_id, a.course_id, a.student_id, a.started_at, a.submitted_at, z.title AS quiz_title, c.title AS course_title '
        'FROM quiz_attempts a JOIN quizzes z ON z.quiz_id = a.quiz_id JOIN courses c ON c.course_id = a.course_id '
        'WHERE a.student_id = ? ORDER BY a.attempt_id DESC',
        (student_id,),
    ).fetchall()


def list_answers_for_attempt(attempt_id):
    db = get_db()
    return db.execute(
        'SELECT qa.answer_id, qa.attempt_id, qa.question_id, qa.answer_json, qa.created_at, q.prompt, q.qtype '
        'FROM quiz_answers qa JOIN questions q ON q.question_id = qa.question_id WHERE qa.attempt_id = ? ORDER BY qa.answer_id ASC',
        (attempt_id,),
    ).fetchall()
