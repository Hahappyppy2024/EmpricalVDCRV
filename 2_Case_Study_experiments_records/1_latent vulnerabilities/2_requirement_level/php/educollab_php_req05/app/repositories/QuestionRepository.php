<?php

declare(strict_types=1);

final class QuestionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $now = date('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO questions (course_id, created_by, qtype, prompt, options_json, answer_json, created_at, updated_at)
             VALUES (:course_id, :created_by, :qtype, :prompt, :options_json, :answer_json, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':course_id' => $data['course_id'],
            ':created_by' => $data['created_by'],
            ':qtype' => $data['qtype'],
            ':prompt' => $data['prompt'],
            ':options_json' => $data['options_json'],
            ':answer_json' => $data['answer_json'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listByCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.*, u.username AS creator_username, u.display_name AS creator_display_name
             FROM questions q
             JOIN users u ON u.user_id = q.created_by
             WHERE q.course_id = :course_id
             ORDER BY q.question_id DESC'
        );
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetchAll();
    }

    public function findById(int $courseId, int $questionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.*, u.username AS creator_username, u.display_name AS creator_display_name
             FROM questions q
             JOIN users u ON u.user_id = q.created_by
             WHERE q.course_id = :course_id AND q.question_id = :question_id
             LIMIT 1'
        );
        $stmt->execute([':course_id' => $courseId, ':question_id' => $questionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, int $questionId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE questions
             SET qtype = :qtype, prompt = :prompt, options_json = :options_json, answer_json = :answer_json, updated_at = :updated_at
             WHERE course_id = :course_id AND question_id = :question_id'
        );
        return $stmt->execute([
            ':qtype' => $data['qtype'],
            ':prompt' => $data['prompt'],
            ':options_json' => $data['options_json'],
            ':answer_json' => $data['answer_json'],
            ':updated_at' => date('c'),
            ':course_id' => $courseId,
            ':question_id' => $questionId,
        ]);
    }

    public function delete(int $courseId, int $questionId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM questions WHERE course_id = :course_id AND question_id = :question_id');
        return $stmt->execute([':course_id' => $courseId, ':question_id' => $questionId]);
    }
}
