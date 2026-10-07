from models.db import get_db


def create_question(course_id, created_by, qtype, prompt, options_json='', answer_json=''):
    db = get_db()
    cur = db.execute(
        'INSERT INTO questions (course_id, created_by, qtype, prompt, options_json, answer_json) VALUES (?, ?, ?, ?, ?, ?)',
        (course_id, created_by, qtype, prompt, options_json, answer_json),
    )
    db.commit()
    return get_question_by_id(course_id, cur.lastrowid)


def list_questions(course_id):
    db = get_db()
    return db.execute(
        'SELECT q.question_id, q.course_id, q.created_by, q.qtype, q.prompt, q.options_json, q.answer_json, q.created_at, q.updated_at, u.username, u.display_name '
        'FROM questions q JOIN users u ON u.user_id = q.created_by WHERE q.course_id = ? ORDER BY q.question_id DESC',
        (course_id,),
    ).fetchall()


def get_question_by_id(course_id, question_id):
    db = get_db()
    return db.execute(
        'SELECT q.question_id, q.course_id, q.created_by, q.qtype, q.prompt, q.options_json, q.answer_json, q.created_at, q.updated_at, u.username, u.display_name '
        'FROM questions q JOIN users u ON u.user_id = q.created_by WHERE q.course_id = ? AND q.question_id = ?',
        (course_id, question_id),
    ).fetchone()


def update_question(course_id, question_id, qtype, prompt, options_json='', answer_json=''):
    db = get_db()
    db.execute(
        'UPDATE questions SET qtype = ?, prompt = ?, options_json = ?, answer_json = ?, updated_at = CURRENT_TIMESTAMP WHERE course_id = ? AND question_id = ?',
        (qtype, prompt, options_json, answer_json, course_id, question_id),
    )
    db.commit()
    return get_question_by_id(course_id, question_id)


def delete_question(course_id, question_id):
    db = get_db()
    db.execute('DELETE FROM quiz_questions WHERE question_id = ?', (question_id,))
    db.execute('DELETE FROM quiz_answers WHERE question_id = ?', (question_id,))
    db.execute('DELETE FROM questions WHERE course_id = ? AND question_id = ?', (course_id, question_id))
    db.commit()
