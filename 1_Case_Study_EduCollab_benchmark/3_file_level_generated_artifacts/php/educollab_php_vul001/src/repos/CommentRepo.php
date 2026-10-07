<?php

class CommentRepo {
    public function __construct(private PDO $pdo) {}

    public function create(int $courseId, int $postId, int $authorId, string $body): array {
        $now = now_iso();
        $stmt = $this->pdo->prepare('INSERT INTO comments(post_id,course_id,author_id,body,created_at,updated_at) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$postId, $courseId, $authorId, $body, $now, $now]);
        return $this->getById($courseId, $postId, (int)$this->pdo->lastInsertId());
    }

    public function listByPost(int $courseId, int $postId): array {
        $stmt = $this->pdo->prepare('SELECT c.*, u.username AS author_username FROM comments c JOIN users u ON u.user_id=c.author_id WHERE c.course_id=? AND c.post_id=? ORDER BY c.comment_id ASC');
        $stmt->execute([$courseId, $postId]);
        return $stmt->fetchAll();
    }

    public function getById(int $courseId, int $postId, int $commentId): ?array {
        $stmt = $this->pdo->prepare('SELECT c.*, u.username AS author_username FROM comments c JOIN users u ON u.user_id=c.author_id WHERE c.course_id=? AND c.post_id=? AND c.comment_id=?');
        $stmt->execute([$courseId, $postId, $commentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, int $postId, int $commentId, string $body): ?array {
        $stmt = $this->pdo->prepare('UPDATE comments SET body=?, updated_at=? WHERE course_id=? AND post_id=? AND comment_id=?');
        $stmt->execute([$body, now_iso(), $courseId, $postId, $commentId]);
        return $this->getById($courseId, $postId, $commentId);
    }

    public function delete(int $courseId, int $postId, int $commentId): void {
        $stmt = $this->pdo->prepare('DELETE FROM comments WHERE course_id=? AND post_id=? AND comment_id=?');
        $stmt->execute([$courseId, $postId, $commentId]);
    }

    public function searchComments(int $courseId, string $keyword): array {
        $kw = '%' . $keyword . '%';
        $stmt = $this->pdo->prepare('SELECT c.*, u.username AS author_username FROM comments c JOIN users u ON u.user_id=c.author_id WHERE c.course_id=? AND c.body LIKE ? ORDER BY c.comment_id DESC');
        $stmt->execute([$courseId, $kw]);
        return $stmt->fetchAll();
    }
}

