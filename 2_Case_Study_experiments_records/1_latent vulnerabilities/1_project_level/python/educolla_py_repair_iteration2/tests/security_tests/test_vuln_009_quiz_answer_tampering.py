import sqlite3
from datetime import datetime, timezone

import requests

from helpers import (
    login,
    logout,
    register,
    create_course,
    start_attempt,
    answer_question,
    submit_attempt,
    force_add_member_db,
    get_row,
)


def _now_iso():
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


def _table_cols(conn, table: str):
    return [r[1] for r in conn.execute(f"PRAGMA table_info({table})").fetchall()]


def _pick_table(conn, preferred: str, like_pat: str):
    names = [r[0] for r in conn.execute(
        "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE ?",
        (like_pat,),
    ).fetchall()]
    if preferred in names:
        return preferred
    return names[0] if names else None


def _insert_row(conn, table: str, data: dict):
    cols = _table_cols(conn, table)
    insert_cols = []
    insert_vals = []
    for k, v in data.items():
        if k in cols:
            insert_cols.append(k)
            insert_vals.append(v)
    assert insert_cols, f"no matching columns for insert into {table}. table cols={cols}"
    ph = ",".join(["?"] * len(insert_cols))
    sql = f"INSERT INTO {table}({','.join(insert_cols)}) VALUES({ph})"
    cur = conn.execute(sql, tuple(insert_vals))
    return cur.lastrowid


def force_create_question_db(db_path, course_id: int, created_by_username: str, qtype: str, prompt: str,
                            options_json_text: str, answer_json_text: str):
    conn = sqlite3.connect(str(db_path))
    try:
        # find creator id
        row = conn.execute("SELECT user_id FROM users WHERE username=?", (created_by_username,)).fetchone()
        assert row is not None, f"user not found: {created_by_username}"
        created_by = int(row[0])

        table = _pick_table(conn, "questions", "%question%")
        assert table is not None, "questions table not found"

        now = _now_iso()
        data = {
            "course_id": course_id,
            "created_by": created_by,
            "creator_id": created_by,      # some schemas use creator_id
            "created_by_id": created_by,   # fallback
            "qtype": qtype,
            "prompt": prompt,
            "options_json": options_json_text,
            "answer_json": answer_json_text,
            "created_at": now,
            "updated_at": now,
        }
        qid = _insert_row(conn, table, data)
        conn.commit()
        return qid
    finally:
        conn.close()


def force_create_quiz_db(db_path, course_id: int, created_by_username: str, title: str, description: str):
    conn = sqlite3.connect(str(db_path))
    try:
        row = conn.execute("SELECT user_id FROM users WHERE username=?", (created_by_username,)).fetchone()
        assert row is not None, f"user not found: {created_by_username}"
        created_by = int(row[0])

        # pick quiz table (usually quizzes)
        table = _pick_table(conn, "quizzes", "%quiz%")
        assert table is not None, "quiz table not found"

        # but sometimes like %quiz% returns quiz_attempts first; enforce likely table by columns
        # choose table that has title + course_id
        candidates = [r[0] for r in conn.execute(
            "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '%quiz%'"
        ).fetchall()]
        def score(t):
            c = set(_table_cols(conn, t))
            return int("course_id" in c) + int("title" in c) + int("description" in c)
        candidates = sorted(candidates, key=score, reverse=True)
        table = candidates[0]
        assert score(table) >= 2, f"no suitable quiz table found, candidates={candidates}"

        now = _now_iso()
        data = {
            "course_id": course_id,
            "created_by": created_by,
            "creator_id": created_by,
            "title": title,
            "description": description,
            "open_at": None,
            "due_at": None,
            "created_at": now,
            "updated_at": now,
        }
        quiz_id = _insert_row(conn, table, data)
        conn.commit()
        return quiz_id, table
    finally:
        conn.close()


def force_map_quiz_question_db(db_path, course_id: int, quiz_id: int, question_id: int, points: int = 1, position: int = 1):
    conn = sqlite3.connect(str(db_path))
    try:
        # find mapping table: prefer quiz_questions; else anything with quiz+question
        names = [r[0] for r in conn.execute(
            "SELECT name FROM sqlite_master WHERE type='table'"
        ).fetchall()]

        mapping = None
        for cand in ("quiz_questions", "quiz_question", "quiz_question_map", "quiz_question_mapping", "quiz_items"):
            if cand in names:
                mapping = cand
                break

        if mapping is None:
            # heuristic: table has both quiz_id and question_id
            for t in names:
                cols = set(_table_cols(conn, t))
                if "quiz_id" in cols and "question_id" in cols:
                    mapping = t
                    break

        assert mapping is not None, "quiz-question mapping table not found (needs columns quiz_id and question_id)"

        now = _now_iso()
        data = {
            "course_id": course_id,   # some schemas store course_id on mapping
            "quiz_id": quiz_id,
            "question_id": question_id,
            "points": points,
            "position": position,
            "created_at": now,
            "updated_at": now,
        }
        _insert_row(conn, mapping, data)
        conn.commit()
    finally:
        conn.close()


def test_exploit_student_can_change_answers_after_submit_and_answer_non_quiz_question(app_instance):
    base_url = app_instance["base_url"]
    db_path = app_instance["db_path"]

    s = requests.Session()
    s.headers.update({"Accept": "application/json"})

    # 1) admin login + create course (API)
    assert login(s, base_url, "admin", "admin123").status_code == 200
    course_res = create_course(s, base_url, "Quiz Tamper", "demo")
    assert course_res.status_code in (200, 201), course_res.text
    course_id = course_res.json()["course_id"]

    # 2) register student + add as course member via DB (avoid /members API)
    reg = register(s, base_url, "quiz_student", "pass123", "Quiz Student")
    assert reg.status_code in (200, 201), reg.text
    force_add_member_db(db_path, course_id, "quiz_student", "student")

    # 3) create questions + quiz + mapping via DB (avoid staff_role_required)
    # q1 will be in quiz, q2 will NOT be in quiz
    q1 = force_create_question_db(
        db_path, course_id, "admin",
        qtype="single_choice",
        prompt="Q1",
        options_json_text='["A","B"]',
        answer_json_text='{"correct":"A"}',
    )
    q2 = force_create_question_db(
        db_path, course_id, "admin",
        qtype="single_choice",
        prompt="Q2",
        options_json_text='["X","Y"]',
        answer_json_text='{"correct":"Y"}',
    )

    quiz_id, _quiz_table = force_create_quiz_db(db_path, course_id, "admin", "Quiz 1", "desc")
    force_map_quiz_question_db(db_path, course_id, quiz_id, q1, points=1, position=1)

    # 4) login as student
    logout(s, base_url)
    assert login(s, base_url, "quiz_student", "pass123").status_code == 200

    # 5) start attempt (API)
    attempt_res = start_attempt(s, base_url, course_id, quiz_id)
    assert attempt_res.status_code in (200, 201), attempt_res.text
    attempt_id = attempt_res.json()["attempt_id"]

    # 6) answer q1 then submit (API)
    a1 = answer_question(s, base_url, course_id, quiz_id, attempt_id, q1, {"answer": "A"})
    assert a1.status_code in (200, 201), a1.text

    sub = submit_attempt(s, base_url, course_id, quiz_id, attempt_id)
    assert sub.status_code in (200, 201), sub.text

    # 7) exploit 1: change answer after submit (should be rejected if fixed)
    after_submit = answer_question(s, base_url, course_id, quiz_id, attempt_id, q1, {"answer": "B"})
    assert after_submit.status_code in (200, 201), after_submit.text

    # 8) exploit 2: answer a question not mapped to quiz (should be rejected if fixed)
    foreign_q = answer_question(s, base_url, course_id, quiz_id, attempt_id, q2, {"answer": "Y"})
    assert foreign_q.status_code in (200, 201), foreign_q.text

    # 9) verify DB state
    row1 = get_row(db_path, "SELECT answer_json FROM quiz_answers WHERE attempt_id=? AND question_id=?", (attempt_id, q1))
    row2 = get_row(db_path, "SELECT answer_json FROM quiz_answers WHERE attempt_id=? AND question_id=?", (attempt_id, q2))
    attempt_row = get_row(db_path, "SELECT submitted_at FROM quiz_attempts WHERE attempt_id=?", (attempt_id,))

    assert attempt_row is not None and attempt_row["submitted_at"] is not None
    assert row1 is not None and "B" in row1["answer_json"]
    assert row2 is not None and "Y" in row2["answer_json"]