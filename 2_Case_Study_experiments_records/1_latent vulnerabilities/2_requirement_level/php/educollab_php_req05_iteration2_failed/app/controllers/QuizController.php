<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class QuizController
{
    public function __construct(
        private QuizRepository $quizRepo,
        private QuizAttemptRepository $attemptRepo,
        private QuizQuestionRepository $quizQuestionRepo,
        private QuestionRepository $questionRepo,
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
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

    public function showCreateForm(int $courseId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/quizzes/new', [
            'title' => 'New Quiz',
            'course' => $this->courseRepo->findById($courseId),
            'error' => null,
        ]);
    }

    public function createHtml(int $courseId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->createQuiz($courseId, request_data());
        if (!$result['success']) {
            render('courses/quizzes/new', [
                'title' => 'New Quiz',
                'course' => $this->courseRepo->findById($courseId),
                'error' => $result['error'],
            ]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/quizzes/' . $result['quiz']['quiz_id']);
    }

    public function createApi(int $courseId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->createQuiz($courseId, request_data());
        if (!$result['success']) {
            json_response($result, 422);
        }
        json_response($result, 201);
    }

    private function createQuiz(int $courseId, array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $openAt = trim((string) ($data['open_at'] ?? ''));
        $dueAt = trim((string) ($data['due_at'] ?? ''));

        if ($title === '') {
            return ['success' => false, 'error' => 'title is required'];
        }

        $quizId = $this->quizRepo->create([
            'course_id' => $courseId,
            'title' => $title,
            'description' => $description,
            'open_at' => $openAt !== '' ? $openAt : null,
            'due_at' => $dueAt !== '' ? $dueAt : null,
        ]);

        return ['success' => true, 'quiz' => $this->quizRepo->findById($courseId, $quizId)];
    }

    public function getHtml(int $courseId, int $quizId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $quiz = $this->quizRepo->findById($courseId, $quizId);
        if (!$quiz) {
            http_response_code(404);
            echo 'Quiz not found';
            return;
        }

        $questions = $this->quizQuestionRepo->listQuestionsForQuiz($courseId, $quizId);
        $membership = current_course_membership($this->config, $this->membershipRepo, $this->courseRepo, $courseId);
        $isStaff = $membership ? is_course_staff_role((string) $membership['role_in_course']) : false;

        if (!$isStaff) {
            foreach ($questions as &$question) {
                unset($question['answer_json']);
            }
            unset($question);
        }

        render('courses/quizzes/show', [
            'title' => $quiz['title'],
            'course' => $this->courseRepo->findById($courseId),
            'quiz' => $quiz,
            'questions' => $questions,
            'is_staff' => $isStaff,
        ]);
    }

    public function getApi(int $courseId, int $quizId): void
    {
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $quiz = $this->quizRepo->findById($courseId, $quizId);
        if (!$quiz) {
            json_response(['success' => false, 'error' => 'Quiz not found'], 404);
        }

        $questions = $this->quizQuestionRepo->listQuestionsForQuiz($courseId, $quizId);
        $membership = current_course_membership($this->config, $this->membershipRepo, $this->courseRepo, $courseId);
        $isStaff = $membership ? is_course_staff_role((string) $membership['role_in_course']) : false;

        if (!$isStaff) {
            foreach ($questions as &$question) {
                unset($question['answer_json']);
            }
            unset($question);
        }

        json_response(['success' => true, 'quiz' => $quiz, 'questions' => $questions]);
    }

    public function updateHtml(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->updateQuiz($courseId, $quizId, request_data());
        if (!$result['success']) {
            http_response_code($result['error'] === 'Quiz not found' ? 404 : 422);
            echo $result['error'];
            return;
        }
        redirect_to('/courses/' . $courseId . '/quizzes/' . $quizId);
    }

    public function updateApi(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->updateQuiz($courseId, $quizId, request_data());
        if (!$result['success']) {
            $status = $result['error'] === 'Quiz not found' ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function updateQuiz(int $courseId, int $quizId, array $data): array
    {
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
            'open_at' => $openAt !== '' ? $openAt : null,
            'due_at' => $dueAt !== '' ? $dueAt : null,
        ]);

        return ['success' => true, 'quiz' => $this->quizRepo->findById($courseId, $quizId)];
    }

    public function deleteHtml(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->deleteQuiz($courseId, $quizId);
        redirect_to('/courses/' . $courseId . '/quizzes');
    }

    public function deleteApi(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->deleteQuiz($courseId, $quizId);
        if (!$result['success']) {
            json_response($result, 404);
        }
        json_response($result, 200);
    }

    private function deleteQuiz(int $courseId, int $quizId): array
    {
        if (!$this->quizRepo->findById($courseId, $quizId)) {
            return ['success' => false, 'error' => 'Quiz not found'];
        }
        $this->quizRepo->delete($courseId, $quizId);
        return ['success' => true];
    }

    public function configureQuestionsApi(int $courseId, int $quizId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $data = request_data();
        $result = $this->configureQuestion($courseId, $quizId, $data);
        if (!$result['success']) {
            $status = in_array($result['error'], ['Quiz not found', 'Question not found'], true) ? 404 : 422;
            json_response($result, $status);
        }
        $status = (($data['action'] ?? 'add') === 'remove') ? 200 : 201;
        json_response($result, $status);
    }

    private function configureQuestion(int $courseId, int $quizId, array $data): array
    {
        $quiz = $this->quizRepo->findById($courseId, $quizId);
        if (!$quiz) {
            return ['success' => false, 'error' => 'Quiz not found'];
        }

        $questionId = (int) ($data['question_id'] ?? 0);
        if ($questionId <= 0) {
            return ['success' => false, 'error' => 'question_id is required'];
        }

        $question = $this->questionRepo->findById($courseId, $questionId);
        if (!$question) {
            return ['success' => false, 'error' => 'Question not found'];
        }

        $action = (string) ($data['action'] ?? 'add');
        if ($action === 'remove') {
            $this->quizQuestionRepo->removeQuestion($courseId, $quizId, $questionId);
            return ['success' => true];
        }

        $points = isset($data['points']) ? (int) $data['points'] : 1;
        $position = isset($data['position']) ? (int) $data['position'] : 1;
        if ($points < 0) {
            return ['success' => false, 'error' => 'points must be non-negative'];
        }
        if ($position < 1) {
            return ['success' => false, 'error' => 'position must be positive'];
        }

        $this->quizQuestionRepo->upsertQuestion($courseId, $quizId, $questionId, $points, $position);

        return [
            'success' => true,
            'quiz_question' => [
                'quiz_id' => $quizId,
                'question_id' => $questionId,
                'points' => $points,
                'position' => $position,
            ],
        ];
    }

    public function startAttemptApi(int $courseId, int $quizId): void
    {
        $userId = require_login($this->config, true);
        $membership = require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        if (is_course_staff_role((string) $membership['role_in_course'])) {
            json_response(['success' => false, 'error' => 'Students only'], 403);
        }

        $quiz = $this->quizRepo->findById($courseId, $quizId);
        if (!$quiz) {
            json_response(['success' => false, 'error' => 'Quiz not found'], 404);
        }

        $attemptId = $this->attemptRepo->createAttempt($courseId, $quizId, $userId);
        $attempt = $this->attemptRepo->findAttemptById($courseId, $quizId, $attemptId, $userId);
        json_response(['success' => true, 'attempt' => ['attempt' => $attempt, 'answers' => []]], 201);
    }

    public function saveAnswerApi(int $courseId, int $quizId, int $attemptId): void
    {
        $userId = require_login($this->config, true);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);

        $attempt = $this->attemptRepo->findAttemptById($courseId, $quizId, $attemptId, $userId);
        if (!$attempt) {
            json_response(['success' => false, 'error' => 'Attempt not found'], 404);
        }
        if (!empty($attempt['submitted_at'])) {
            json_response(['success' => false, 'error' => 'Attempt already submitted'], 422);
        }

        $data = request_data();
        $questionId = (int) ($data['question_id'] ?? 0);
        $answer = $data['answer_json'] ?? null;
        if ($questionId <= 0 || $answer === null) {
            json_response(['success' => false, 'error' => 'question_id and answer_json are required'], 422);
        }

        if (!$this->quizQuestionRepo->hasQuestion($courseId, $quizId, $questionId)) {
            json_response(['success' => false, 'error' => 'Question not part of quiz'], 422);
        }

        $this->attemptRepo->saveAnswer($attemptId, $questionId, $answer);
        $updatedAttempt = $this->attemptRepo->findAttemptById($courseId, $quizId, $attemptId, $userId);
        $answers = $this->attemptRepo->listAnswers($attemptId);

        json_response(['success' => true, 'attempt' => ['attempt' => $updatedAttempt, 'answers' => $answers]], 200);
    }

    public function submitAttemptApi(int $courseId, int $quizId, int $attemptId): void
    {
        $userId = require_login($this->config, true);
        require_course_member($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);

        $attempt = $this->attemptRepo->findAttemptById($courseId, $quizId, $attemptId, $userId);
        if (!$attempt) {
            json_response(['success' => false, 'error' => 'Attempt not found'], 404);
        }
        if (!empty($attempt['submitted_at'])) {
            json_response(['success' => false, 'error' => 'Attempt already submitted'], 422);
        }

        $this->attemptRepo->markSubmitted($attemptId);
        $updatedAttempt = $this->attemptRepo->findAttemptById($courseId, $quizId, $attemptId, $userId);
        $answers = $this->attemptRepo->listAnswers($attemptId);

        json_response(['success' => true, 'attempt' => ['attempt' => $updatedAttempt, 'answers' => $answers]], 200);
    }

    public function listMyAttemptsApi(): void
    {
        $userId = require_login($this->config, true);
        json_response(['success' => true, 'attempts' => $this->attemptRepo->listAttemptsByUser($userId)]);
    }
}