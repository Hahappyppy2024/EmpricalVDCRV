<?php

declare(strict_types=1);

namespace App\Repositories;

final class PaperSubmissionRepository extends BaseRepository
{
    public function table(): string
    {
        return 'paper_submissions';
    }

    public function search(array $filters = [], ?int $limit = 100): array
    {
        $sql = 'SELECT p.*, u.name AS author_name, u.email AS author_email'
            . ' FROM paper_submissions p JOIN users u ON u.id = p.author_id';
        $conditions = [];
        $params = [];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $conditions[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }
        if (isset($filters['author_id']) && $filters['author_id'] !== '') {
            $conditions[] = 'p.author_id = :author_id';
            $params['author_id'] = (int) $filters['author_id'];
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $conditions[] = '(p.title LIKE :q OR p.abstract LIKE :q OR p.keywords LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (isset($filters['submission_ids']) && is_array($filters['submission_ids']) && $filters['submission_ids'] !== []) {
            $in = implode(',', array_map(static fn ($id) => (int) $id, $filters['submission_ids']));
            $conditions[] = 'p.id IN (' . $in . ')';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY p.id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function withAuthor(int $id): ?array
    {
        $st = $this->db->prepare(
            'SELECT p.*, u.name AS author_name, u.email AS author_email'
            . ' FROM paper_submissions p JOIN users u ON u.id = p.author_id WHERE p.id = :id'
        );
        $st->execute(['id' => $id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
