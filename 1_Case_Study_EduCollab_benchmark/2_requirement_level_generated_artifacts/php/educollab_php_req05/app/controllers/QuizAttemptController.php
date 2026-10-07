<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class QuizAttemptController
{
    public function __construct(
        private QuizAttemptRepository $attemptRepo,
        private QuizRepository $quizRepo,
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
    }

    public function startHtml(int $courseId, int $quizId): void
    {
        $userId = require_student($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->startAttempt($courseId, $quizId, $userId);
        if (!$result['success']) {
            http_response_code($result['error'] === 'Quiz not found' ? 404 : 422);
        }
        redirect_to('/courses/' . $courseId . '/quizzes/' . $quizId);
    }

    public function startApi(int $courseId, int $quizId): void
    {
        $userId = require_student($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->startAttempt($courseId, $quizId, $userId);
        if (!$result['success']) {
            json_response($result, $result['error'] === 'Quiz not found' ? 404 : 422);
        }
        json_response($result, 201);
    }

    private function startAttempt(int $courseId, int $quizId, int $userId): array
    {
        if (!$this->quizRepo->findById($courseId, $quizId)) {
            return ['success' => false, 'error' => 'Quiz not found'];
        }
        $attemptId = $this->attemptRepo->create($quizId, $courseId, $userId);
        return ['success' => true, 'attempt' => $this->buildAttemptPayload($courseId, $quizId, $attemptId)];
    }

    public function answerHtml(int $courseId, int $quizId, int $attemptId): void
    {
        $userId = require_student($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->answer($courseId, $quizId, $attemptId, $userId, request_data());
        redirect_to('/courses/' . $courseId . '/quizzes/' . $quizId);
    }

    public function answerApi(int $courseId, int $quizId, int $attemptId): void
    {
        $userId = require_student($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->answer($courseId, $quizId, $attemptId, $userId, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Attempt not found', 'Question not found in quiz'], true) ? 404 : ($result['error'] === 'Forbidden' ? 403 : 422);
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function answer(int $courseId, int $quizId, int $attemptId, int $userId, array $data): array
    {
        $attempt = $this->attemptRepo->findById($courseId, $quizId, $attemptId);
        if (!$attempt) {
            return ['success' => false, 'error' => 'Attempt not found'];
        }
        if ((int) $attempt['student_id'] !== $userId) {
            return ['success' => false, 'error' => 'Forbidden'];
        }
        if ($attempt['submitted_at'] !== null) {
            return ['success' => false, 'error' => 'Attempt already submitted'];
        }
        $questionId = (int) ($data['question_id'] ?? 0);
        if ($questionId <= 0 || !$this->quizRepo->findQuizQuestion($courseId, $quizId, $questionId)) {
            return ['success' => false, 'error' => 'Question not found in quiz'];
        }
        $answerJson = $this->normalizeJsonText($data['answer_json'] ?? null);
        if ($answerJson === null) {
            return ['success' => false, 'error' => 'answer_json is required'];
        }
        $this->attemptRepo->upsertAnswer($attemptId, $questionId, $answerJson);
        return ['success' => true, 'attempt' => $this->buildAttemptPayload($courseId, $quizId, $attemptId)];
    }

    public function submitHtml(int $courseId, int $quizId, int $attemptId): void
    {
        $userId = require_student($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->submit($courseId, $quizId, $attemptId, $userId);
        redirect_to('/courses/' . $courseId . '/quizzes/' . $quizId);
    }

    public function submitApi(int $courseId, int $quizId, int $attemptId): void
    {
        $userId = require_student($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->submit($courseId, $quizId, $attemptId, $userId);
        if (!$result['success']) {
            $status = $result['error'] === 'Attempt not found' ? 404 : ($result['error'] === 'Forbidden' ? 403 : 422);
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function submit(int $courseId, int $quizId, int $attemptId, int $userId): array
    {
        $attempt = $this->attemptRepo->findById($courseId, $quizId, $attemptId);
        if (!$attempt) {
            return ['success' => false, 'error' => 'Attempt not found'];
        }
        if ((int) $attempt['student_id'] !== $userId) {
            return ['success' => false, 'error' => 'Forbidden'];
        }
        if ($attempt['submitted_at'] !== null) {
            return ['success' => false, 'error' => 'Attempt already submitted'];
        }
        $this->attemptRepo->submit($courseId, $quizId, $attemptId);
        return ['success' => true, 'attempt' => $this->buildAttemptPayload($courseId, $quizId, $attemptId)];
    }

    public function listMyHtml(): void
    {
        $userId = require_login($this->config, false);
        render('my/quizzes', ['title' => 'My Quiz Attempts', 'attempts' => $this->attemptRepo->listByStudent($userId)]);
    }

    public function listMyApi(): void
    {
        $userId = require_login($this->config, true);
        json_response(['success' => true, 'attempts' => $this->attemptRepo->listByStudent($userId)]);
    }

    private function buildAttemptPayload(int $courseId, int $quizId, int $attemptId): array
    {
        $attempt = $this->attemptRepo->findById($courseId, $quizId, $attemptId);
        return [
            'attempt' => $attempt,
            'answers' => $this->attemptRepo->listAnswers($attemptId),
        ];
    }

    private function normalizeJsonText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
