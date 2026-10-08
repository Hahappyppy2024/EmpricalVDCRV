<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * Grade items and individual grades.
 */
final class GradeRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsForCourse(int $courseId): array
    {
        return $this->db->select('SELECT * FROM grade_items WHERE course_id = ? ORDER BY id', [$courseId]);
    }

    public function item(int $id): ?array
    {
        return $this->db->first('SELECT * FROM grade_items WHERE id = ?', [$id]);
    }

    public function addItem(array $data): int
    {
        return $this->db->insert('grade_items', $data);
    }

    public function updateItem(int $id, array $data): void
    {
        $this->db->update('grade_items', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Grades for every student in a course gradebook, keyed by item.
     *
     * @return list<array<string, mixed>>
     */
    public function gradebookForCourse(int $courseId): array
    {
        return $this->db->select(
            'SELECT gi.id AS item_id, gi.name AS item_name, gi.max_points, gi.weight, s.id AS student_id,
                    s.username, s.display_name, g.id AS grade_id, g.score, g.feedback, g.graded_at
             FROM grade_items gi
             JOIN enrollments e ON e.course_id = gi.course_id AND e.status = "enrolled"
             JOIN users s ON s.id = e.user_id
             LEFT JOIN grades g ON g.grade_item_id = gi.id AND g.student_id = s.id
             WHERE gi.course_id = ?
             ORDER BY s.display_name, gi.id',
            [$courseId]
        );
    }

    /**
     * Grades for one student across their enrolled courses.
     *
     * @return list<array<string, mixed>>
     */
    public function gradesForStudent(int $studentId): array
    {
        return $this->db->select(
            'SELECT g.*, gi.name AS item_name, gi.max_points, c.title AS course_title, c.id AS course_id
             FROM grades g
             JOIN grade_items gi ON gi.id = g.grade_item_id
             JOIN courses c ON c.id = gi.course_id
             WHERE g.student_id = ? ORDER BY c.title, gi.id',
            [$studentId]
        );
    }

    public function gradeFor(int $itemId, int $studentId): ?array
    {
        return $this->db->first('SELECT * FROM grades WHERE grade_item_id = ? AND student_id = ?', [$itemId, $studentId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveGrade(array $data): int
    {
        $existing = $this->db->first('SELECT id FROM grades WHERE grade_item_id = ? AND student_id = ?', [$data['grade_item_id'], $data['student_id']]);
        if ($existing !== null) {
            $this->db->update('grades', $data, 'id = :id', ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }
        return $this->db->insert('grades', $data);
    }

    /**
     * Weighted course average per student, only counting graded items.
     *
     * @return list<array<string, mixed>>
     */
    public function averagesForCourse(int $courseId): array
    {
        $rows = $this->db->select(
            'SELECT s.username, s.display_name,
                    ROUND(SUM((COALESCE(g.score, 0) / NULLIF(gi.max_points, 0)) * gi.weight) * 100.0, 1) AS weighted_pct,
                    COUNT(g.id) AS graded_items
             FROM enrollments e
             JOIN users s ON s.id = e.user_id
             JOIN grade_items gi ON gi.course_id = e.course_id
             LEFT JOIN grades g ON g.grade_item_id = gi.id AND g.student_id = s.id
             WHERE e.course_id = ? AND e.status = "enrolled"
             GROUP BY s.id ORDER BY s.display_name',
            [$courseId]
        );
        return $rows;
    }
}
