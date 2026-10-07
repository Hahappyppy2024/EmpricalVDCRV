from conftest import api_login, api_register, create_course, get_membership_id, get_user_id_by_username


def test_r5_question_bank_quiz_attempt_flow(app_instance):
    app = app_instance["app"]

    teacher = app.test_client()
    student = app.test_client()

    login_res = api_login(teacher, 'admin', 'admin123')
    assert login_res.status_code == 200

    course_res = create_course(teacher, 'Quiz Course', 'R5 only')
    assert course_res.status_code == 201
    course_id = course_res.get_json()['course']['course_id']

    reg_res = api_register(student, 'student_r5', 'pass123', 'Student R5')
    assert reg_res.status_code == 201
    student_id = get_user_id_by_username(app, 'student_r5')
    assert student_id is not None

    add_member_res = teacher.post(
        f'/api/courses/{course_id}/members',
        json={'user_id': student_id, 'role_in_course': 'student'},
    )
    assert add_member_res.status_code == 201
    membership_id = add_member_res.get_json()['membership']['membership_id']

    question_res = teacher.post(
        f'/api/courses/{course_id}/questions',
        json={
            'qtype': 'single_choice',
            'prompt': '2+2=?',
            'options_json': ['3', '4', '5'],
            'answer_json': {'correct': '4'},
        },
    )
    assert question_res.status_code == 201
    question = question_res.get_json()['question']
    question_id = question['question_id']
    assert question['prompt'] == '2+2=?'

    list_questions_res = teacher.get(f'/api/courses/{course_id}/questions')
    assert list_questions_res.status_code == 200
    questions = list_questions_res.get_json()['questions']
    assert any(q['question_id'] == question_id for q in questions)

    get_question_res = teacher.get(f'/api/courses/{course_id}/questions/{question_id}')
    assert get_question_res.status_code == 200
    assert get_question_res.get_json()['question']['question_id'] == question_id

    update_question_res = teacher.put(
        f'/api/courses/{course_id}/questions/{question_id}',
        json={
            'qtype': 'single_choice',
            'prompt': '3+3=?',
            'options_json': ['5', '6', '7'],
            'answer_json': {'correct': '6'},
        },
    )
    assert update_question_res.status_code == 200
    assert update_question_res.get_json()['question']['prompt'] == '3+3=?'

    quiz_res = teacher.post(
        f'/api/courses/{course_id}/quizzes',
        json={'title': 'Week 1 Quiz', 'description': 'Basics', 'open_at': '', 'due_at': ''},
    )
    assert quiz_res.status_code == 201
    quiz_id = quiz_res.get_json()['quiz']['quiz_id']

    add_question_to_quiz_res = teacher.post(
        f'/api/courses/{course_id}/quizzes/{quiz_id}/questions',
        json={'question_id': question_id, 'points': 5, 'position': 1},
    )
    assert add_question_to_quiz_res.status_code == 200
    assert add_question_to_quiz_res.get_json()['quiz_question']['question_id'] == question_id

    list_quizzes_res = student.get(f'/api/courses/{course_id}/quizzes')
    assert list_quizzes_res.status_code == 200
    assert any(q['quiz_id'] == quiz_id for q in list_quizzes_res.get_json()['quizzes'])

    get_quiz_res = student.get(f'/api/courses/{course_id}/quizzes/{quiz_id}')
    assert get_quiz_res.status_code == 200
    body = get_quiz_res.get_json()
    assert body['quiz']['quiz_id'] == quiz_id
    assert body['quiz_questions'][0]['question_id'] == question_id

    start_attempt_res = student.post(f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start')
    assert start_attempt_res.status_code == 201
    attempt_id = start_attempt_res.get_json()['attempt']['attempt_id']

    answer_res = student.post(
        f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/answers',
        json={'question_id': question_id, 'answer_json': {'selected': '6'}},
    )
    assert answer_res.status_code == 200
    assert answer_res.get_json()['answer']['question_id'] == question_id

    submit_res = student.post(f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/{attempt_id}/submit')
    assert submit_res.status_code == 200
    submit_body = submit_res.get_json()['attempt']
    assert submit_body['attempt_id'] == attempt_id
    assert submit_body['submitted_at'] is not None
    assert len(submit_body['answers']) == 1
    assert submit_body['answers'][0]['question_id'] == question_id

    my_attempts_res = student.get('/api/my/quizzes/attempts')
    assert my_attempts_res.status_code == 200
    attempts = my_attempts_res.get_json()['attempts']
    assert any(a['attempt_id'] == attempt_id for a in attempts)

    forbidden_question_create = student.post(
        f'/api/courses/{course_id}/questions',
        json={'qtype': 'single_choice', 'prompt': 'forbidden', 'options_json': [], 'answer_json': {}},
    )
    assert forbidden_question_create.status_code == 403

    forbidden_quiz_create = student.post(
        f'/api/courses/{course_id}/quizzes',
        json={'title': 'Nope', 'description': ''},
    )
    assert forbidden_quiz_create.status_code == 403

    forbidden_question_list = student.get(f'/api/courses/{course_id}/questions')
    assert forbidden_question_list.status_code == 403

    teacher_start_attempt = teacher.post(f'/api/courses/{course_id}/quizzes/{quiz_id}/attempts/start')
    assert teacher_start_attempt.status_code == 403

    remove_question_from_quiz_res = teacher.delete(
        f'/api/courses/{course_id}/quizzes/{quiz_id}/questions/{question_id}'
    )
    assert remove_question_from_quiz_res.status_code == 200

    delete_quiz_res = teacher.delete(f'/api/courses/{course_id}/quizzes/{quiz_id}')
    assert delete_quiz_res.status_code == 200

    delete_question_res = teacher.delete(f'/api/courses/{course_id}/questions/{question_id}')
    assert delete_question_res.status_code == 200
