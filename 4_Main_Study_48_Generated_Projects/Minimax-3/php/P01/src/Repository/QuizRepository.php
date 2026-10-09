<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class QuizRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findWithQuestions(int $id): ?array
    {
        $quiz = $this->find($id);
        if (!$quiz) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY position, id');
        $stmt->execute([$id]);
        $quiz['questions'] = $stmt->fetchAll();
        return $quiz;
    }

    public function listForCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count
             FROM quizzes q WHERE q.course_id = ? ORDER BY q.closes_at DESC'
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function createQuiz(array $data, array $questions): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO quizzes (course_id, title, description, created_by, opens_at, closes_at, max_score)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['course_id'],
                $data['title'],
                $data['description'] ?? '',
                $data['created_by'],
                $data['opens_at'] ?? date('Y-m-d H:i:s'),
                $data['closes_at'],
                (float)($data['max_score'] ?? 100),
            ]);
            $quizId = (int)$this->pdo->lastInsertId();
            $qStmt = $this->pdo->prepare(
                'INSERT INTO questions (quiz_id, prompt, kind, options_json, correct_json, points, position)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $pos = 1;
            foreach ($questions as $q) {
                $qStmt->execute([
                    $quizId,
                    $q['prompt'],
                    $q['kind'],
                    json_encode($q['options'] ?? []),
                    json_encode($q['correct'] ?? []),
                    (float)($q['points'] ?? 1),
                    $pos++,
                ]);
            }
            $this->pdo->commit();
            return $quizId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM quizzes WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function startAttempt(int $quizId, int $studentId): array
    {
        $existing = $this->latestAttempt($quizId, $studentId);
        if ($existing && $existing['status'] === 'in_progress') {
            return ['attempt' => $existing, 'created' => false];
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO quiz_attempts (quiz_id, student_id) VALUES (?, ?)"
        );
        $stmt->execute([$quizId, $studentId]);
        return ['attempt' => $this->latestAttempt($quizId, $studentId), 'created' => true];
    }

    public function latestAttempt(int $quizId, int $studentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$quizId, $studentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveAnswers(int $attemptId, array $answers): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO quiz_answers (attempt_id, question_id, response_json) VALUES (?, ?, ?)'
        );
        foreach ($answers as $questionId => $response) {
            $stmt->execute([$attemptId, (int)$questionId, json_encode($response)]);
        }
    }

    public function submitAttempt(int $attemptId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM quiz_attempts WHERE id = ?');
        $stmt->execute([$attemptId]);
        $attempt = $stmt->fetch();
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            return $attempt ?: null;
        }
        $qStmt = $this->pdo->prepare(
            'SELECT q.*, a.response_json
             FROM questions q
             LEFT JOIN quiz_answers a ON a.question_id = q.id AND a.attempt_id = ?
             WHERE q.quiz_id = ?'
        );
        $qStmt->execute([$attemptId, $attempt['quiz_id']]);
        $questions = $qStmt->fetchAll();
        $totalScore = 0.0;
        $maxScore = 0.0;
        foreach ($questions as $q) {
            $maxScore += (float)$q['points'];
            $correct = json_decode($q['correct_json'], true) ?: [];
            $response = json_decode($q['response_json'] ?? '[]', true);
            if (!is_array($response)) {
                $response = [];
            }
            $awarded = $this->gradeQuestion($q['kind'], $response, $correct) ? (float)$q['points'] : 0.0;
            $totalScore += $awarded;
            $u = $this->pdo->prepare('UPDATE quiz_answers SET awarded = ? WHERE attempt_id = ? AND question_id = ?');
            $u->execute([$awarded, $attemptId, $q['id']]);
        }
        $u = $this->pdo->prepare(
            "UPDATE quiz_attempts SET status='submitted', submitted_at=datetime('now'),
              score = ?, max_score = ? WHERE id = ?"
        );
        $u->execute([$totalScore, $maxScore, $attemptId]);
        $attempt['score'] = $totalScore;
        $attempt['max_score'] = $maxScore;
        $attempt['status'] = 'submitted';
        return $attempt;
    }

    private function gradeQuestion(string $kind, array $response, array $correct): bool
    {
        $correct = array_values(array_unique(array_map('strval', $correct)));
        sort($correct);
        $response = array_values(array_unique(array_map('strval', $response)));
        sort($response);
        return $correct === $response;
    }

    public function listAttempts(int $quizId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.full_name AS student_name, u.username
             FROM quiz_attempts a JOIN users u ON u.id = a.student_id
             WHERE a.quiz_id = ? ORDER BY a.id DESC'
        );
        $stmt->execute([$quizId]);
        return $stmt->fetchAll();
    }
}
