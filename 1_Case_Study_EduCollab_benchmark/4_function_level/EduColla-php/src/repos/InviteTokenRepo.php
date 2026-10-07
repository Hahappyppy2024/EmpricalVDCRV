<?php

class InviteTokenRepo {
    public function __construct(private PDO $pdo) {}

    private function base64url(string $bin): string {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /**
     * Create a new invite token for a course.
     * Returns: ['invite' => row, 'token' => raw_token]
     */
    public function create(int $courseId, string $roleInCourse, int $ttlMinutes, int $createdBy): array {
        $ttlMinutes = max(1, min($ttlMinutes, 60 * 24 * 30)); // clamp to 30 days
        $raw = $this->base64url(random_bytes(24));
        $hash = hash('sha256', $raw);
        $expiresAt = gmdate('c', time() + ($ttlMinutes * 60));
        $now = now_iso();

        $st = $this->pdo->prepare(
            'INSERT INTO invite_tokens(token_hash, course_id, role_in_course, expires_at, created_by, created_at) VALUES (?,?,?,?,?,?)'
        );
        $st->execute([$hash, $courseId, $roleInCourse, $expiresAt, $createdBy, $now]);

        $id = (int)$this->pdo->lastInsertId();
        return ['invite' => $this->getById($id), 'token' => $raw];
    }

    public function getById(int $inviteId): ?array {
        $st = $this->pdo->prepare('SELECT * FROM invite_tokens WHERE invite_id=?');
        $st->execute([$inviteId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Find a valid invite by raw token.
     */
    public function findValidByToken(string $token): ?array {
        $token = trim($token);
        if ($token === '') return null;
        $hash = hash('sha256', $token);
        $st = $this->pdo->prepare(
            "SELECT * FROM invite_tokens
             WHERE token_hash=?
               AND used_at IS NULL
               AND expires_at > ?"
        );
        $st->execute([$hash, now_iso()]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function markUsed(int $inviteId, int $usedBy): void {
        $st = $this->pdo->prepare('UPDATE invite_tokens SET used_at=?, used_by=? WHERE invite_id=?');
        $st->execute([now_iso(), $usedBy, $inviteId]);
    }
}
