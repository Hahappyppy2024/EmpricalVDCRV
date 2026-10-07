<?php

declare(strict_types=1);

final class CommentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO comments (course_id, post_id, author_id, body, created_at, updated_at)
             VALUES (:course_id, :post_id, :author_id, :body, :created_at, :updated_at)'
        );
        $now = date('c');
        $stmt->execute([
            ':course_id' => $data['course_id'],
            ':post_id' => $data['post_id'],
            ':author_id' => $data['author_id'],
            ':body' => $data['body'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listByPost(int $courseId, int $postId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.comment_id, c.course_id, c.post_id, c.author_id, c.body, c.created_at, c.updated_at,
                    u.username AS author_username, u.display_name AS author_display_name
             FROM comments c
             JOIN users u ON u.user_id = c.author_id
             WHERE c.course_id = :course_id AND c.post_id = :post_id
             ORDER BY c.comment_id ASC'
        );
        $stmt->execute([
            ':course_id' => $courseId,
            ':post_id' => $postId,
        ]);
        return $stmt->fetchAll();
    }

    public function findById(int $courseId, int $postId, int $commentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.comment_id, c.course_id, c.post_id, c.author_id, c.body, c.created_at, c.updated_at,
                    u.username AS author_username, u.display_name AS author_display_name
             FROM comments c
             JOIN users u ON u.user_id = c.author_id
             WHERE c.course_id = :course_id AND c.post_id = :post_id AND c.comment_id = :comment_id
             LIMIT 1'
        );
        $stmt->execute([
            ':course_id' => $courseId,
            ':post_id' => $postId,
            ':comment_id' => $commentId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, int $postId, int $commentId, string $body): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE comments SET body = :body, updated_at = :updated_at
             WHERE course_id = :course_id AND post_id = :post_id AND comment_id = :comment_id'
        );
        return $stmt->execute([
            ':body' => $body,
            ':updated_at' => date('c'),
            ':course_id' => $courseId,
            ':post_id' => $postId,
            ':comment_id' => $commentId,
        ]);
    }

    public function updateOwned(int $courseId, int $postId, int $commentId, int $authorId, string $body): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE comments SET body = :body, updated_at = :updated_at
             WHERE course_id = :course_id AND post_id = :post_id AND comment_id = :comment_id AND author_id = :author_id'
        );
        return $stmt->execute([
            ':body' => $body,
            ':updated_at' => date('c'),
            ':course_id' => $courseId,
            ':post_id' => $postId,
            ':comment_id' => $commentId,
            ':author_id' => $authorId,
        ]);
    }

    public function delete(int $courseId, int $postId, int $commentId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM comments WHERE course_id = :course_id AND post_id = :post_id AND comment_id = :comment_id'
        );
        return $stmt->execute([
            ':course_id' => $courseId,
            ':post_id' => $postId,
            ':comment_id' => $commentId,
        ]);
    }

    public function deleteOwned(int $courseId, int $postId, int $commentId, int $authorId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM comments WHERE course_id = :course_id AND post_id = :post_id AND comment_id = :comment_id AND author_id = :author_id'
        );
        return $stmt->execute([
            ':course_id' => $courseId,
            ':post_id' => $postId,
            ':comment_id' => $commentId,
            ':author_id' => $authorId,
        ]);
    }
}