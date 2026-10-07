<?php

class AuditLogRepo {
    public function __construct(private PDO $pdo) {}

    public function add(?int $actorUserId, string $action, ?string $target=null, ?array $metadata=null): void {
        $stmt = $this->pdo->prepare("INSERT INTO audit_log(actor_user_id, action, target, metadata, created_at)
                                     VALUES(?, ?, ?, ?, datetime('now'))");
        $metaJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null;
        $stmt->execute([$actorUserId, $action, $target, $metaJson]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listRecent(int $limit=200): array {
        $limit = max(1, min($limit, 500));
        $stmt = $this->pdo->prepare("SELECT log_id, actor_user_id, action, target, metadata, created_at
                                     FROM audit_log
                                     ORDER BY log_id DESC
                                     LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
