<?php

class PostRepo {
    public function __construct(private PDO $pdo) {}

    public function create(int $courseId, int $authorId, string $title, string $body): array {
        $now = now_iso();
        $stmt = $this->pdo->prepare('INSERT INTO posts(course_id,author_id,title,body,created_at,updated_at) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$courseId, $authorId, $title, $body, $now, $now]);
        return $this->getById($courseId, (int)$this->pdo->lastInsertId());
    }

    public function listByCourse(int $courseId): array {
        $stmt = $this->pdo->prepare('SELECT p.*, u.username AS author_username FROM posts p JOIN users u ON u.user_id=p.author_id WHERE p.course_id=? ORDER BY p.post_id DESC');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function getById(int $courseId, int $postId): ?array {
        $stmt = $this->pdo->prepare('SELECT p.*, u.username AS author_username FROM posts p JOIN users u ON u.user_id=p.author_id WHERE p.course_id=? AND p.post_id=?');
        $stmt->execute([$courseId, $postId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, int $postId, string $title, string $body): ?array {
        $stmt = $this->pdo->prepare('UPDATE posts SET title=?, body=?, updated_at=? WHERE course_id=? AND post_id=?');
        $stmt->execute([$title, $body, now_iso(), $courseId, $postId]);
        return $this->getById($courseId, $postId);
    }

    public function delete(int $courseId, int $postId): void {
        $stmt = $this->pdo->prepare('DELETE FROM posts WHERE course_id=? AND post_id=?');
        $stmt->execute([$courseId, $postId]);
    }


    public function searchPosts(int $courseId, string $keyword): array {
        $sql = "SELECT p.*, u.username AS author_username
            FROM posts p JOIN users u ON u.user_id=p.author_id
            WHERE p.course_id=$courseId
              AND (p.title LIKE '%$keyword%' OR p.body LIKE '%$keyword%')
            ORDER BY p.post_id DESC";
        return $this->pdo->query($sql)->fetchAll();
    }
}

