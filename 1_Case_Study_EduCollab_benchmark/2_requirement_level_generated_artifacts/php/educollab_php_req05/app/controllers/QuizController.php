<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class QuizController
{
    public function __construct(
        private QuizRepository $quizRepo,
        private QuestionRepository $questionRepo,
        private QuizAttemptRepository $attemptRepo,
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
    }

    public function showCreateForm(int $courseId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/quizzes/new', ['title' => 'New Quiz', 'course' => $this->courseRepo->findById($courseId), 'error' => null]);
    }

    public function createHtml(int $courseId): void
    {
        $userId = require_login($this->config, false);
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->create($courseId, $userId, request_data());
        if (!$result['success']) {
            render('courses/quizzes/new', ['title' => 'New Quiz', 'course' => $this->courseRepo->findById($courseId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/quizzes/' . $result['quiz']['quiz_id']);
    }

    public function createApi(int $courseId): void
    {
        $userId = require_login($this->config, true);
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->create($courseId, $userId, request_data());
        if (!$result['success']) {
            json_response($result, $result['error'] === 'Course not found' ? 404 : 422);
        }
        json_response($result, 201);
    }

    private function create(int $courseId, int $userId, array $data): array
    {
        if (!$this->courseRepo->findById($courseId)) {
            return ['success' => false, 'error' => 'Course not found'];
        }
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $openAt = trim((string) ($data['open_at'] ?? ''));
        $dueAt = trim((string) ($data['due_at'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'error' => 'title is required'];
        }
        $quizId = $this->quizRepo->create([
            'course_id' => $courseId,
            'created_by' => $userId,
            'title' => $title,
            'description' => $description,
            'open_at' => $openAt === '' ? null : $openAt,
            'due_at' => $dueAt === '' ? null : $dueAt,
        ]);
        return ['success' => true, 'quiz' => $this->quizRepo->findById($courseId, $quizId)];
    }

    public function listHtml(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/quizzes/index', [
            'title' => 'Quizzes',
            'course' => $this->courseRepo->findById($courseId),
            'quizzes' => $this->quizRepo->listByCourse($courseId),
            'is_staff' => current_user_is_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId),
        ]);
    }

    public function listApi(int $courseId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        json_response(['success' => true, 'quizzes' => $this->quizRepo->listByCourse($courseId)]);
    }

    public function getHtml(int $courseId, int $quizId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $quiz = $this->quizRepo->findById($courseId, $quizId);
        if (!$quiz) {
            http_response_code(404);
        }
        $userId = current_user_id($this->config);
        render('courses/quizzes/show', [
            'title' => $quiz ? $quiz['title'] : 'Quiz Not Found',
            'course' => $this->courseRepo->findById($courseId),
            'quiz' => $quiz,
            'questions' => $quiz ? $this->quizRepo->listQuizQuestions($courseId, $quizId) : [],
            'question_bank' => $quiz && current_user_is_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId) ? $this->questionRepo->listByCourse($courseId) : [],
            'attempts' => $userId && $quiz ? array_values(array_filter($this->attemptRepo->listByStudent($userId), static fn(array $a): bool => (int) $a['quiz_id'] === $quizId && (int) $a['course_id'] === $courseId)) : [],
            'is_staff' => current_user_is_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId),
            'is_student' => current_course_role($this->config, $this->membershipRepo, $this->courseRepo, $courseId) === 'student',
        ]);
    }

    public function getApi(int $courseId, int $quizId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $quiz = $this->quizRepo->findById($courseId, $quizId);
        if (!$quiz) {
            json_response(['success' => false, 'error' => 'Quiz not found'], 404);
        }
        json_response(['success' => true, 'quiz' => $quiz, 'questions' => $this->quizRepo->listQuizQuestions($courseId, $quizId)]);
    }

    public function showEditForm(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $quiz = $this->quizRepo->findById($courseId, $quizId);
        if (!$quiz) {
            http_response_code(404);
            render('courses/quizzes/show', ['title' => 'Quiz Not Found', 'course' => $this->courseRepo->findById($courseId), 'quiz' => null, 'questions' => [], 'question_bank' => [], 'attempts' => [], 'is_staff' => true, 'is_student' => false]);
            return;
        }
        render('courses/quizzes/edit', ['title' => 'Edit Quiz', 'course' => $this->courseRepo->findById($courseId), 'quiz' => $quiz, 'error' => null]);
    }

    public function updateHtml(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->update($courseId, $quizId, request_data());
        if (!$result['success']) {
            render('courses/quizzes/edit', ['title' => 'Edit Quiz', 'course' => $this->courseRepo->findById($courseId), 'quiz' => $this->quizRepo->findById($courseId, $quizId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/quizzes/' . $quizId);
    }

    public function updateApi(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->update($courseId, $quizId, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Course not found', 'Quiz not found'], true) ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function update(int $courseId, int $quizId, array $data): array
    {
        if (!$this->courseRepo->findById($courseId)) {
            return ['success' => false, 'error' => 'Course not found'];
        }
        if (!$this->quizRepo->findById($courseId, $quizId)) {
            return ['success' => false, 'error' => 'Quiz not found'];
        }
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $openAt = trim((string) ($data['open_at'] ?? ''));
        $dueAt = trim((string) ($data['due_at'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'error' => 'title is required'];
        }
        $this->quizRepo->update($courseId, $quizId, [
            'title' => $title,
            'description' => $description,
            'open_at' => $openAt === '' ? null : $openAt,
            'due_at' => $dueAt === '' ? null : $dueAt,
        ]);
        return ['success' => true, 'quiz' => $this->quizRepo->findById($courseId, $quizId)];
    }

    public function deleteHtml(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->delete($courseId, $quizId);
        redirect_to('/courses/' . $courseId . '/quizzes');
    }

    public function deleteApi(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->delete($courseId, $quizId);
        if (!$result['success']) {
            json_response($result, 404);
        }
        json_response($result, 200);
    }

    private function delete(int $courseId, int $quizId): array
    {
        if (!$this->quizRepo->findById($courseId, $quizId)) {
            return ['success' => false, 'error' => 'Quiz not found'];
        }
        $this->quizRepo->delete($courseId, $quizId);
        return ['success' => true];
    }

    public function configureQuestionsHtml(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->configureQuestion($courseId, $quizId, request_data());
        redirect_to('/courses/' . $courseId . '/quizzes/' . $quizId);
    }

    public function configureQuestionsApi(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->configureQuestion($courseId, $quizId, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Quiz not found', 'Question not found'], true) ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 201);
    }

    private function configureQuestion(int $courseId, int $quizId, array $data): array
    {
        if (!$this->quizRepo->findById($courseId, $quizId)) {
            return ['success' => false, 'error' => 'Quiz not found'];
        }
        $questionId = (int) ($data['question_id'] ?? 0);
        $points = (int) ($data['points'] ?? 0);
        $position = (int) ($data['position'] ?? 0);
        if ($questionId <= 0) {
            return ['success' => false, 'error' => 'question_id is required'];
        }
        if (!$this->questionRepo->findById($courseId, $questionId)) {
            return ['success' => false, 'error' => 'Question not found'];
        }
        $this->quizRepo->addQuizQuestion($quizId, $questionId, $points, $position);
        return ['success' => true, 'quiz_questions' => $this->quizRepo->listQuizQuestions($courseId, $quizId)];
    }

    public function deleteConfiguredQuestionApi(int $courseId, int $quizId, int $questionId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        if (!$this->quizRepo->findQuizQuestion($courseId, $quizId, $questionId)) {
            json_response(['success' => false, 'error' => 'Quiz question not found'], 404);
        }
        $this->quizRepo->deleteQuizQuestion($quizId, $questionId);
        json_response(['success' => true]);
    }
}
