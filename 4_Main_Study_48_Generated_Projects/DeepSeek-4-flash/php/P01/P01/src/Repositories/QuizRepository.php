<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * Quizzes, questions, attempts and answers.
 */
final class QuizRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function quizzesForCourse(int $courseId, bool $includeUnpublished = false): array
    {
        $sql = 'SELECT q.*, u.display_name AS instructor_name,
                       (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
                FROM quizzes q JOIN users u ON u.id = q.instructor_id WHERE q.course_id = ?';
        $params = [$courseId];
        if (!$includeUnpublished) {
            $sql .= ' AND q.published = 1';
        }
        $sql .= ' ORDER BY q.id';
        return $this->db->select($sql, $params);
    }

    public function quiz(int $id): ?array
    {
        return $this->db->first('SELECT q.*, u.display_name AS instructor_name, c.title AS course_title FROM quizzes q JOIN users u ON u.id = q.instructor_id JOIN courses c ON c.id = q.course_id WHERE q.id = ?', [$id]);
    }

    public function createQuiz(array $data): int
    {
        return $this->db->insert('quizzes', $data);
    }

    public function updateQuiz(int $id, array $data): void
    {
        $this->db->update('quizzes', $data, 'id = :id', ['id' => $id]);
    }

    public function addQuestion(array $data): int
    {
        return $this->db->insert('quiz_questions', $data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questionsForQuiz(int $quizId): array
    {
        return $this->db->select('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id', [$quizId]);
    }

    public function question(int $id): ?array
    {
        return $this->db->first('SELECT * FROM quiz_questions WHERE id = ?', [$id]);
    }

    public function startAttempt(int $quizId, int $studentId): int
    {
        return $this->db->insert('quiz_attempts', [
            'quiz_id' => $quizId,
            'student_id' => $studentId,
            'status' => 'in_progress',
            'score' => 0,
        ]);
    }

    public function activeAttempt(int $quizId, int $studentId): ?array
    {
        return $this->db->first('SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? AND status = "in_progress" ORDER BY id DESC LIMIT 1', [$quizId, $studentId]);
    }

    public function latestAttempt(int $quizId, int $studentId): ?array
    {
        return $this->db->first('SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1', [$quizId, $studentId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attemptsForQuiz(int $quizId): array
    {
        return $this->db->select(
            'SELECT a.*, u.username, u.display_name FROM quiz_attempts a JOIN users u ON u.id = a.student_id WHERE a.quiz_id = ? ORDER BY a.id',
            [$quizId]
        );
    }

    public function attempt(int $id): ?array
    {
        return $this->db->first('SELECT a.*, u.display_name AS student_name, q.title AS quiz_title, q.course_id FROM quiz_attempts a JOIN users u ON u.id = a.student_id JOIN quizzes q ON q.id = a.quiz_id WHERE a.id = ?', [$id]);
    }

    public function saveAnswer(int $attemptId, int $questionId, string $answer, bool $correct, float $points): void
    {
        $existing = $this->db->first('SELECT id FROM quiz_answers WHERE attempt_id = ? AND question_id = ?', [$attemptId, $questionId]);
        if ($existing !== null) {
            $this->db->update('quiz_answers', [
                'answer_text' => $answer,
                'is_correct' => $correct ? 1 : 0,
                'points_earned' => $points,
            ], 'id = :id', ['id' => (int) $existing['id']]);
            return;
        }
        $this->db->insert('quiz_answers', [
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'answer_text' => $answer,
            'is_correct' => $correct ? 1 : 0,
            'points_earned' => $points,
        ]);
    }

    public function answersForAttempt(int $attemptId): array
    {
        return $this->db->select('SELECT * FROM quiz_answers WHERE attempt_id = ? ORDER BY id', [$attemptId]);
    }

    public function completeAttempt(int $attemptId, float $score): void
    {
        $this->db->update('quiz_attempts', ['status' => 'completed', 'score' => $score, 'submitted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $attemptId]);
    }

    public function maxPossibleScore(int $quizId): float
    {
        $row = $this->db->first('SELECT COALESCE(SUM(points), 0) AS total FROM quiz_questions WHERE quiz_id = ?', [$quizId]);
        return (float) $row['total'];
    }
}
