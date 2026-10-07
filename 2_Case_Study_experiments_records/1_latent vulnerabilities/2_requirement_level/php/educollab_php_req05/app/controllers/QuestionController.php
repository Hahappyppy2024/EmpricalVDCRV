<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/View.php';
require_once dirname(__DIR__) . '/core/Auth.php';

final class QuestionController
{
    public function __construct(
        private QuestionRepository $questionRepo,
        private CourseRepository $courseRepo,
        private MembershipRepository $membershipRepo,
        private array $config
    ) {
    }

    public function showCreateForm(int $courseId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/questions/new', ['title' => 'New Question', 'course' => $this->courseRepo->findById($courseId), 'error' => null]);
    }

    public function createHtml(int $courseId): void
    {
        $userId = require_login($this->config, false);
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->create($courseId, $userId, request_data());
        if (!$result['success']) {
            render('courses/questions/new', ['title' => 'New Question', 'course' => $this->courseRepo->findById($courseId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/questions/' . $result['question']['question_id']);
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
        $qtype = trim((string) ($data['qtype'] ?? ''));
        $prompt = trim((string) ($data['prompt'] ?? ''));
        $optionsJson = $this->normalizeNullableJson($data['options_json'] ?? null);
        $answerJson = $this->normalizeNullableJson($data['answer_json'] ?? null);
        if ($qtype === '' || $prompt === '') {
            return ['success' => false, 'error' => 'qtype and prompt are required'];
        }
        $questionId = $this->questionRepo->create([
            'course_id' => $courseId,
            'created_by' => $userId,
            'qtype' => $qtype,
            'prompt' => $prompt,
            'options_json' => $optionsJson,
            'answer_json' => $answerJson,
        ]);
        return ['success' => true, 'question' => $this->questionRepo->findById($courseId, $questionId)];
    }

    public function listHtml(int $courseId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        render('courses/questions/index', [
            'title' => 'Questions',
            'course' => $this->courseRepo->findById($courseId),
            'questions' => $this->questionRepo->listByCourse($courseId),
        ]);
    }

    public function listApi(int $courseId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        json_response(['success' => true, 'questions' => $this->questionRepo->listByCourse($courseId)]);
    }

    public function getHtml(int $courseId, int $questionId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $question = $this->questionRepo->findById($courseId, $questionId);
        if (!$question) {
            http_response_code(404);
        }
        render('courses/questions/show', [
            'title' => $question ? $question['prompt'] : 'Question Not Found',
            'course' => $this->courseRepo->findById($courseId),
            'question' => $question,
        ]);
    }

    public function getApi(int $courseId, int $questionId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $question = $this->questionRepo->findById($courseId, $questionId);
        if (!$question) {
            json_response(['success' => false, 'error' => 'Question not found'], 404);
        }
        json_response(['success' => true, 'question' => $question]);
    }

    public function showEditForm(int $courseId, int $questionId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $question = $this->questionRepo->findById($courseId, $questionId);
        if (!$question) {
            http_response_code(404);
            render('courses/questions/show', ['title' => 'Question Not Found', 'course' => $this->courseRepo->findById($courseId), 'question' => null]);
            return;
        }
        render('courses/questions/edit', ['title' => 'Edit Question', 'course' => $this->courseRepo->findById($courseId), 'question' => $question, 'error' => null]);
    }

    public function updateHtml(int $courseId, int $questionId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $result = $this->update($courseId, $questionId, request_data());
        if (!$result['success']) {
            render('courses/questions/edit', ['title' => 'Edit Question', 'course' => $this->courseRepo->findById($courseId), 'question' => $this->questionRepo->findById($courseId, $questionId), 'error' => $result['error']]);
            return;
        }
        redirect_to('/courses/' . $courseId . '/questions/' . $questionId);
    }

    public function updateApi(int $courseId, int $questionId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->update($courseId, $questionId, request_data());
        if (!$result['success']) {
            $status = in_array($result['error'], ['Course not found', 'Question not found'], true) ? 404 : 422;
            json_response($result, $status);
        }
        json_response($result, 200);
    }

    private function update(int $courseId, int $questionId, array $data): array
    {
        if (!$this->courseRepo->findById($courseId)) {
            return ['success' => false, 'error' => 'Course not found'];
        }
        if (!$this->questionRepo->findById($courseId, $questionId)) {
            return ['success' => false, 'error' => 'Question not found'];
        }
        $qtype = trim((string) ($data['qtype'] ?? ''));
        $prompt = trim((string) ($data['prompt'] ?? ''));
        $optionsJson = $this->normalizeNullableJson($data['options_json'] ?? null);
        $answerJson = $this->normalizeNullableJson($data['answer_json'] ?? null);
        if ($qtype === '' || $prompt === '') {
            return ['success' => false, 'error' => 'qtype and prompt are required'];
        }
        $this->questionRepo->update($courseId, $questionId, [
            'qtype' => $qtype,
            'prompt' => $prompt,
            'options_json' => $optionsJson,
            'answer_json' => $answerJson,
        ]);
        return ['success' => true, 'question' => $this->questionRepo->findById($courseId, $questionId)];
    }

    public function deleteHtml(int $courseId, int $questionId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, false);
        $this->delete($courseId, $questionId);
        redirect_to('/courses/' . $courseId . '/questions');
    }

    public function deleteApi(int $courseId, int $questionId): void
    {
        require_course_staff($this->config, $this->membershipRepo, $this->courseRepo, $courseId, true);
        $result = $this->delete($courseId, $questionId);
        if (!$result['success']) {
            json_response($result, 404);
        }
        json_response($result, 200);
    }

    private function delete(int $courseId, int $questionId): array
    {
        if (!$this->questionRepo->findById($courseId, $questionId)) {
            return ['success' => false, 'error' => 'Question not found'];
        }
        $this->questionRepo->delete($courseId, $questionId);
        return ['success' => true];
    }

    private function normalizeNullableJson(mixed $value): ?string
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
