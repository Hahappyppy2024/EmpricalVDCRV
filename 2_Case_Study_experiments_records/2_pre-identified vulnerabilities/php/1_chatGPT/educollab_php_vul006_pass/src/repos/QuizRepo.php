<?php

class QuizRepo {
    public function __construct(private PDO $pdo) {}

    public function create(int $courseId, int $createdBy, string $title, string $description, ?string $openAt, ?string $dueAt): array {
        $now = now_iso();
        $stmt = $this->pdo->prepare('INSERT INTO quizzes(course_id,created_by,title,description,open_at,due_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$courseId, $createdBy, $title, $description, $openAt, $dueAt, $now, $now]);
        return $this->getById($courseId, (int)$this->pdo->lastInsertId());
    }

    public function listByCourse(int $courseId): array {
        $stmt = $this->pdo->prepare('SELECT z.*, u.username AS creator_username FROM quizzes z JOIN users u ON u.user_id=z.created_by WHERE z.course_id=? ORDER BY z.quiz_id DESC');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function getById(int $courseId, int $quizId): ?array {
        $stmt = $this->pdo->prepare('SELECT z.*, u.username AS creator_username FROM quizzes z JOIN users u ON u.user_id=z.created_by WHERE z.course_id=? AND z.quiz_id=?');
        $stmt->execute([$courseId, $quizId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, int $quizId, string $title, string $description, ?string $openAt, ?string $dueAt): ?array {
        $stmt = $this->pdo->prepare('UPDATE quizzes SET title=?, description=?, open_at=?, due_at=?, updated_at=? WHERE course_id=? AND quiz_id=?');
        $stmt->execute([$title, $description, $openAt, $dueAt, now_iso(), $courseId, $quizId]);
        return $this->getById($courseId, $quizId);
    }

    public function delete(int $courseId, int $quizId): void {
        $stmt = $this->pdo->prepare('DELETE FROM quizzes WHERE course_id=? AND quiz_id=?');
        $stmt->execute([$courseId, $quizId]);
    }

    public function addQuizQuestion(int $quizId, int $questionId, int $points, int $position): void {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO quiz_questions(quiz_id,question_id,points,position) VALUES (?,?,?,?)');
        $stmt->execute([$quizId, $questionId, $points, $position]);
    }

    public function removeQuizQuestion(int $quizId, int $questionId): void {
        $stmt = $this->pdo->prepare('DELETE FROM quiz_questions WHERE quiz_id=? AND question_id=?');
        $stmt->execute([$quizId, $questionId]);
    }

    public function listQuizQuestions(int $quizId): array {
        $stmt = $this->pdo->prepare('SELECT qq.*, q.prompt, q.qtype FROM quiz_questions qq JOIN questions q ON q.question_id=qq.question_id WHERE qq.quiz_id=? ORDER BY qq.position ASC');
        $stmt->execute([$quizId]);
        return $stmt->fetchAll();
    }

    public function startAttempt(int $courseId, int $quizId, int $studentId): array {
        $stmt = $this->pdo->prepare('INSERT INTO quiz_attempts(quiz_id,course_id,student_id,started_at) VALUES (?,?,?,?)');
        $stmt->execute([$quizId, $courseId, $studentId, now_iso()]);
        return $this->getAttemptById((int)$this->pdo->lastInsertId());
    }

    public function getAttemptById(int $attemptId): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM quiz_attempts WHERE attempt_id=?');
        $stmt->execute([$attemptId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsertAnswer(int $attemptId, int $questionId, string $answerJson): void {
        $stmt = $this->pdo->prepare('INSERT INTO quiz_answers(attempt_id,question_id,answer_json,created_at) VALUES (?,?,?,?)
            ON CONFLICT(attempt_id,question_id) DO UPDATE SET answer_json=excluded.answer_json');
        $stmt->execute([$attemptId, $questionId, $answerJson, now_iso()]);
    }

    public function submitAttempt(int $attemptId): void {
        $stmt = $this->pdo->prepare('UPDATE quiz_attempts SET submitted_at=? WHERE attempt_id=?');
        $stmt->execute([now_iso(), $attemptId]);
    }

    public function listMyAttempts(int $studentId): array {
        $stmt = $this->pdo->prepare('SELECT a.*, z.title AS quiz_title, c.title AS course_title FROM quiz_attempts a JOIN quizzes z ON z.quiz_id=a.quiz_id JOIN courses c ON c.course_id=a.course_id WHERE a.student_id=? ORDER BY a.started_at DESC');
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }

    public function listAnswers(int $attemptId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM quiz_answers WHERE attempt_id=? ORDER BY answer_id ASC');
        $stmt->execute([$attemptId]);
        return $stmt->fetchAll();
    }
}

