<?php

declare(strict_types=1);

final class PostRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO posts (course_id, author_id, title, body, created_at, updated_at)
             VALUES (:course_id, :author_id, :title, :body, :created_at, :updated_at)'
        );
        $now = date('c');
        $stmt->execute([
            ':course_id' => $data['course_id'],
            ':author_id' => $data['author_id'],
            ':title' => $data['title'],
            ':body' => $data['body'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listByCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.post_id, p.course_id, p.author_id, p.title, p.body, p.created_at, p.updated_at,
                    u.username AS author_username, u.display_name AS author_display_name
             FROM posts p
             JOIN users u ON u.user_id = p.author_id
             WHERE p.course_id = :course_id
             ORDER BY p.post_id DESC'
        );
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetchAll();
    }

    public function findById(int $courseId, int $postId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.post_id, p.course_id, p.author_id, p.title, p.body, p.created_at, p.updated_at,
                    u.username AS author_username, u.display_name AS author_display_name
             FROM posts p
             JOIN users u ON u.user_id = p.author_id
             WHERE p.course_id = :course_id AND p.post_id = :post_id
             LIMIT 1'
        );
        $stmt->execute([
            ':course_id' => $courseId,
            ':post_id' => $postId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, int $postId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE posts SET title = :title, body = :body, updated_at = :updated_at
             WHERE course_id = :course_id AND post_id = :post_id'
        );
        return $stmt->execute([
            ':title' => $data['title'],
            ':body' => $data['body'],
            ':updated_at' => date('c'),
            ':course_id' => $courseId,
            ':post_id' => $postId,
        ]);
    }

    public function updateOwned(int $courseId, int $postId, int $authorId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE posts SET title = :title, body = :body, updated_at = :updated_at
             WHERE course_id = :course_id AND post_id = :post_id AND author_id = :author_id'
        );
        return $stmt->execute([
            ':title' => $data['title'],
            ':body' => $data['body'],
            ':updated_at' => date('c'),
            ':course_id' => $courseId,
            ':post_id' => $postId,
            ':author_id' => $authorId,
        ]);
    }

    public function delete(int $courseId, int $postId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM posts WHERE course_id = :course_id AND post_id = :post_id');
        return $stmt->execute([
            ':course_id' => $courseId,
            ':post_id' => $postId,
        ]);
    }

    public function deleteOwned(int $courseId, int $postId, int $authorId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM posts WHERE course_id = :course_id AND post_id = :post_id AND author_id = :author_id'
        );
        return $stmt->execute([
            ':course_id' => $courseId,
            ':post_id' => $postId,
            ':author_id' => $authorId,
        ]);
    }
}