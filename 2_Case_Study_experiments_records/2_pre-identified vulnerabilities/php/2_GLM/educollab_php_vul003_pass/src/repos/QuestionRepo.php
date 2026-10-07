<?php

class QuestionRepo {
    public function __construct(private PDO $pdo) {}

    public function create(int $courseId, int $createdBy, string $qtype, string $prompt, ?string $optionsJson, ?string $answerJson): array {
        $now = now_iso();
        $stmt = $this->pdo->prepare('INSERT INTO questions(course_id,created_by,qtype,prompt,options_json,answer_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$courseId, $createdBy, $qtype, $prompt, $optionsJson, $answerJson, $now, $now]);
        return $this->getById($courseId, (int)$this->pdo->lastInsertId());
    }

    public function listByCourse(int $courseId): array {
        $stmt = $this->pdo->prepare('SELECT q.*, u.username AS creator_username FROM questions q JOIN users u ON u.user_id=q.created_by WHERE q.course_id=? ORDER BY q.question_id DESC');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function getById(int $courseId, int $questionId): ?array {
        $stmt = $this->pdo->prepare('SELECT q.*, u.username AS creator_username FROM questions q JOIN users u ON u.user_id=q.created_by WHERE q.course_id=? AND q.question_id=?');
        $stmt->execute([$courseId, $questionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, int $questionId, string $qtype, string $prompt, ?string $optionsJson, ?string $answerJson): ?array {
        $stmt = $this->pdo->prepare('UPDATE questions SET qtype=?, prompt=?, options_json=?, answer_json=?, updated_at=? WHERE course_id=? AND question_id=?');
        $stmt->execute([$qtype, $prompt, $optionsJson, $answerJson, now_iso(), $courseId, $questionId]);
        return $this->getById($courseId, $questionId);
    }

    public function delete(int $courseId, int $questionId): void {
        $stmt = $this->pdo->prepare('DELETE FROM questions WHERE course_id=? AND question_id=?');
        $stmt->execute([$courseId, $questionId]);
    }
}

