<?php

declare(strict_types=1);

final class QuizRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $now = date('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO quizzes (course_id, created_by, title, description, open_at, due_at, created_at, updated_at)
             VALUES (:course_id, :created_by, :title, :description, :open_at, :due_at, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':course_id' => $data['course_id'],
            ':created_by' => $data['created_by'],
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':open_at' => $data['open_at'],
            ':due_at' => $data['due_at'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listByCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.*, u.username AS creator_username, u.display_name AS creator_display_name
             FROM quizzes q
             JOIN users u ON u.user_id = q.created_by
             WHERE q.course_id = :course_id
             ORDER BY q.quiz_id DESC'
        );
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetchAll();
    }

    public function findById(int $courseId, int $quizId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.*, u.username AS creator_username, u.display_name AS creator_display_name
             FROM quizzes q
             JOIN users u ON u.user_id = q.created_by
             WHERE q.course_id = :course_id AND q.quiz_id = :quiz_id
             LIMIT 1'
        );
        $stmt->execute([':course_id' => $courseId, ':quiz_id' => $quizId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, int $quizId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE quizzes
             SET title = :title, description = :description, open_at = :open_at, due_at = :due_at, updated_at = :updated_at
             WHERE course_id = :course_id AND quiz_id = :quiz_id'
        );
        return $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':open_at' => $data['open_at'],
            ':due_at' => $data['due_at'],
            ':updated_at' => date('c'),
            ':course_id' => $courseId,
            ':quiz_id' => $quizId,
        ]);
    }

    public function delete(int $courseId, int $quizId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM quizzes WHERE course_id = :course_id AND quiz_id = :quiz_id');
        return $stmt->execute([':course_id' => $courseId, ':quiz_id' => $quizId]);
    }

    public function listQuizQuestions(int $courseId, int $quizId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT qq.quiz_id, qq.question_id, qq.points, qq.position,
                    q.qtype, q.prompt, q.options_json, q.answer_json
             FROM quiz_questions qq
             JOIN quizzes z ON z.quiz_id = qq.quiz_id AND z.course_id = :course_id
             JOIN questions q ON q.question_id = qq.question_id AND q.course_id = :course_id
             WHERE qq.quiz_id = :quiz_id
             ORDER BY qq.position ASC, qq.question_id ASC'
        );
        $stmt->execute([':course_id' => $courseId, ':quiz_id' => $quizId]);
        return $stmt->fetchAll();
    }

    public function findQuizQuestion(int $courseId, int $quizId, int $questionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT qq.quiz_id, qq.question_id, qq.points, qq.position,
                    q.qtype, q.prompt, q.options_json, q.answer_json
             FROM quiz_questions qq
             JOIN quizzes z ON z.quiz_id = qq.quiz_id AND z.course_id = :course_id
             JOIN questions q ON q.question_id = qq.question_id AND q.course_id = :course_id
             WHERE qq.quiz_id = :quiz_id AND qq.question_id = :question_id
             LIMIT 1'
        );
        $stmt->execute([':course_id' => $courseId, ':quiz_id' => $quizId, ':question_id' => $questionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function addQuizQuestion(int $quizId, int $questionId, int $points, int $position): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO quiz_questions (quiz_id, question_id, points, position)
             VALUES (:quiz_id, :question_id, :points, :position)
             ON CONFLICT(quiz_id, question_id) DO UPDATE SET points = excluded.points, position = excluded.position'
        );
        return $stmt->execute([
            ':quiz_id' => $quizId,
            ':question_id' => $questionId,
            ':points' => $points,
            ':position' => $position,
        ]);
    }

    public function deleteQuizQuestion(int $quizId, int $questionId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM quiz_questions WHERE quiz_id = :quiz_id AND question_id = :question_id');
        return $stmt->execute([':quiz_id' => $quizId, ':question_id' => $questionId]);
    }
}
