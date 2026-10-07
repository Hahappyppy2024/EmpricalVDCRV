import requests

from helpers import (
    api_url,
    json_of,
    register,
    login,
    create_course,
    add_member,
    get_user_id,
    create_question,
    create_quiz,
    configure_quiz_question,
)


def test_r5_question_bank_quiz_attempt_flow(app_instance):
    app = app_instance
    admin = app['session']

    # Older requirements are only setup steps here.
    assert login(admin, app, 'admin', 'admin123').status_code == 200
    course_res = create_course(admin, app, title='R5 Course', description='Quiz module')
    assert course_res.status_code == 201
    course_id = json_of(course_res)['course']['course_id']

    student = requests.Session()
    assert register(student, app, 'student_r5').status_code == 201
    student_id = get_user_id(app, 'student_r5')
    add_res = add_member(admin, app, course_id, student_id, 'student')
    assert add_res.status_code == 201

    # Staff creates question-bank entries.
    q1_res = create_question(admin, app, course_id, prompt='2+2=?', options=['3', '4', '5'], answer={'correct': '4'})
    q2_res = create_question(admin, app, course_id, prompt='3+3=?', options=['5', '6', '7'], answer={'correct': '6'})
    assert q1_res.status_code == 201
    assert q2_res.status_code == 201
    q1_id = json_of(q1_res)['question']['question_id']
    q2_id = json_of(q2_res)['question']['question_id']

    list_q = admin.get(api_url(app, f'/api/courses/{course_id}/questions'), timeout=5)
    assert list_q.status_code == 200
    question_ids = {q['question_id'] for q in json_of(list_q)['questions']}
    assert q1_id in question_ids and q2_id in question_ids

    get_q = admin.get(api_url(app, f'/api/courses/{course_id}/questions/{q1_id}'), timeout=5)
    assert get_q.status_code == 200
    assert json_of(get_q)['question']['prompt'] == '2+2=?'

    upd_q = admin.put(api_url(app, f'/api/courses/{course_id}/questions/{q2_id}'), json={
        'qtype': 'single_choice',
        'prompt': '3+3 still equals?',
        'options_json': ['5', '6', '8'],
        'answer_json': {'correct': '6'},
    }, timeout=5)
    assert upd_q.status_code == 200
    assert json_of(upd_q)['question']['prompt'] == '3+3 still equals?'

    # Staff creates a quiz and attaches questions.
    quiz_res = create_quiz(admin, app, course_id, title='Quiz Alpha', description='chapter 1')
    assert quiz_res.status_code == 201
    quiz_id = json_of(quiz_res)['quiz']['quiz_id']

    cfg1 = configure_quiz_question(admin, app, course_id, quiz_id, q1_id, points=5, position=1)
    cfg2 = configure_quiz_question(admin, app, course_id, quiz_id, q2_id, points=10, position=2)
    assert cfg1.status_code == 201
    assert cfg2.status_code == 201

    list_quiz = admin.get(api_url(app, f'/api/courses/{course_id}/quizzes'), timeout=5)
    assert list_quiz.status_code == 200
    assert any(q['quiz_id'] == quiz_id for q in json_of(list_quiz)['quizzes'])

    # Student can view quiz and start an attempt.
    assert login(student, app, 'student_r5', 'pass123').status_code == 200
    get_quiz = student.get(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}'), timeout=5)
    assert get_quiz.status_code == 200
    payload = json_of(get_quiz)
    assert payload['quiz']['title'] == 'Quiz Alpha'
    assert len(payload['questions']) == 2

    start_res = student.post(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start'), timeout=5)
    assert start_res.status_code == 201
    attempt_payload = json_of(start_res)['attempt']
    attempt_id = attempt_payload['attempt']['attempt_id']
    assert attempt_payload['attempt']['student_id'] == student_id
    assert attempt_payload['answers'] == []

    ans1 = student.post(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers'), json={
        'question_id': q1_id,
        'answer_json': {'selected': '4'},
    }, timeout=5)
    assert ans1.status_code == 200
    assert len(json_of(ans1)['attempt']['answers']) == 1

    ans2 = student.post(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers'), json={
        'question_id': q2_id,
        'answer_json': {'selected': '6'},
    }, timeout=5)
    assert ans2.status_code == 200
    assert len(json_of(ans2)['attempt']['answers']) == 2

    submit_res = student.post(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/submit'), timeout=5)
    assert submit_res.status_code == 200
    assert json_of(submit_res)['attempt']['attempt']['submitted_at'] is not None

    my_attempts = student.get(api_url(app, '/api/my/quizzes/attempts'), timeout=5)
    assert my_attempts.status_code == 200
    assert any(a['attempt_id'] == attempt_id for a in json_of(my_attempts)['attempts'])

    # After submission, further answer changes should be rejected.
    late_answer = student.post(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers'), json={
        'question_id': q1_id,
        'answer_json': {'selected': '3'},
    }, timeout=5)
    assert late_answer.status_code == 422

    # Staff can remove a configured question and delete quiz/question.
    del_cfg = admin.delete(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}/questions/{q2_id}'), timeout=5)
    assert del_cfg.status_code == 200
    quiz_after = admin.get(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}'), timeout=5)
    assert quiz_after.status_code == 200
    assert len(json_of(quiz_after)['questions']) == 1

    del_quiz = admin.delete(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}'), timeout=5)
    assert del_quiz.status_code == 200
    del_q2 = admin.delete(api_url(app, f'/api/courses/{course_id}/questions/{q2_id}'), timeout=5)
    assert del_q2.status_code == 200


def test_r5_authorization_boundaries(app_instance):
    app = app_instance
    admin = app['session']
    assert login(admin, app, 'admin', 'admin123').status_code == 200
    course_id = json_of(create_course(admin, app, title='R5 AuthZ'))['course']['course_id']

    student = requests.Session()
    outsider = requests.Session()
    assistant = requests.Session()

    assert register(student, app, 'student_r5_auth').status_code == 201
    assert register(outsider, app, 'outsider_r5').status_code == 201
    assert register(assistant, app, 'assistant_r5').status_code == 201

    student_id = get_user_id(app, 'student_r5_auth')
    assistant_id = get_user_id(app, 'assistant_r5')
    assert add_member(admin, app, course_id, student_id, 'student').status_code == 201
    assert add_member(admin, app, course_id, assistant_id, 'assistant').status_code == 201

    q_res = create_question(admin, app, course_id, prompt='capital of QC?')
    qid = json_of(q_res)['question']['question_id']
    quiz_id = json_of(create_quiz(admin, app, course_id, title='Boundary Quiz'))['quiz']['quiz_id']
    assert configure_quiz_question(admin, app, course_id, quiz_id, qid).status_code == 201

    assert login(student, app, 'student_r5_auth', 'pass123').status_code == 200
    assert login(outsider, app, 'outsider_r5', 'pass123').status_code == 200
    assert login(assistant, app, 'assistant_r5', 'pass123').status_code == 200

    # Student is not allowed to manage question bank or quizzes.
    stu_q_create = student.post(api_url(app, f'/api/courses/{course_id}/questions'), json={
        'qtype': 'single_choice', 'prompt': 'x', 'options_json': ['a'], 'answer_json': {'correct': 'a'}
    }, timeout=5)
    assert stu_q_create.status_code == 403

    stu_quiz_create = student.post(api_url(app, f'/api/courses/{course_id}/quizzes'), json={'title': 'bad'}, timeout=5)
    assert stu_quiz_create.status_code == 403

    # Outsider cannot access R5 course resources.
    outsider_get = outsider.get(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}'), timeout=5)
    assert outsider_get.status_code == 403

    outsider_start = outsider.post(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start'), timeout=5)
    assert outsider_start.status_code == 403

    # Assistant counts as course staff for question/quiz management.
    asst_list_q = assistant.get(api_url(app, f'/api/courses/{course_id}/questions'), timeout=5)
    assert asst_list_q.status_code == 200

    asst_upd_quiz = assistant.put(api_url(app, f'/api/courses/{course_id}/quizzes/{quiz_id}'), json={
        'title': 'Boundary Quiz Updated',
        'description': 'ok',
        'open_at': '',
        'due_at': '',
    }, timeout=5)
    assert asst_upd_quiz.status_code == 200
