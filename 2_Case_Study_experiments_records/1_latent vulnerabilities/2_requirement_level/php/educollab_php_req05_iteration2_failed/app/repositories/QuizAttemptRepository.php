<?php

declare(strict_types=1);

final class QuizAttemptRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $quizId, int $courseId, int $studentId): int
    {
        $startedAt = date('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO quiz_attempts (quiz_id, course_id, student_id, started_at, submitted_at)
             VALUES (:quiz_id, :course_id, :student_id, :started_at, NULL)'
        );
        $stmt->execute([
            ':quiz_id' => $quizId,
            ':course_id' => $courseId,
            ':student_id' => $studentId,
            ':started_at' => $startedAt,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $courseId, int $quizId, int $attemptId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT qa.*, u.username AS student_username, u.display_name AS student_display_name,
                    q.title AS quiz_title
             FROM quiz_attempts qa
             JOIN users u ON u.user_id = qa.student_id
             JOIN quizzes q ON q.quiz_id = qa.quiz_id AND q.course_id = qa.course_id
             WHERE qa.course_id = :course_id AND qa.quiz_id = :quiz_id AND qa.attempt_id = :attempt_id
             LIMIT 1'
        );
        $stmt->execute([
            ':course_id' => $courseId,
            ':quiz_id' => $quizId,
            ':attempt_id' => $attemptId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listByStudent(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT qa.*, c.title AS course_title, q.title AS quiz_title
             FROM quiz_attempts qa
             JOIN courses c ON c.course_id = qa.course_id
             JOIN quizzes q ON q.quiz_id = qa.quiz_id AND q.course_id = qa.course_id
             WHERE qa.student_id = :student_id
             ORDER BY qa.attempt_id DESC'
        );
        $stmt->execute([':student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public function submit(int $courseId, int $quizId, int $attemptId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE quiz_attempts SET submitted_at = :submitted_at
             WHERE course_id = :course_id AND quiz_id = :quiz_id AND attempt_id = :attempt_id'
        );
        return $stmt->execute([
            ':submitted_at' => date('c'),
            ':course_id' => $courseId,
            ':quiz_id' => $quizId,
            ':attempt_id' => $attemptId,
        ]);
    }

    public function upsertAnswer(int $attemptId, int $questionId, string $answerJson): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO quiz_answers (attempt_id, question_id, answer_json, created_at)
             VALUES (:attempt_id, :question_id, :answer_json, :created_at)
             ON CONFLICT(attempt_id, question_id) DO UPDATE SET answer_json = excluded.answer_json, created_at = excluded.created_at'
        );
        return $stmt->execute([
            ':attempt_id' => $attemptId,
            ':question_id' => $questionId,
            ':answer_json' => $answerJson,
            ':created_at' => date('c'),
        ]);
    }

    public function listAnswers(int $attemptId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT qa.answer_id, qa.attempt_id, qa.question_id, qa.answer_json, qa.created_at,
                    q.prompt, q.qtype
             FROM quiz_answers qa
             JOIN questions q ON q.question_id = qa.question_id
             WHERE qa.attempt_id = :attempt_id
             ORDER BY qa.answer_id ASC'
        );
        $stmt->execute([':attempt_id' => $attemptId]);
        return $stmt->fetchAll();
    }

    public function findAnswer(int $attemptId, int $questionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT answer_id, attempt_id, question_id, answer_json, created_at
             FROM quiz_answers
             WHERE attempt_id = :attempt_id AND question_id = :question_id
             LIMIT 1'
        );
        $stmt->execute([':attempt_id' => $attemptId, ':question_id' => $questionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
