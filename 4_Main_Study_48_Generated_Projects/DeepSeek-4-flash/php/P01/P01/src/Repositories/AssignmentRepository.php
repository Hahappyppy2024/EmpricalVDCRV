<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * Assignments and student submissions.
 */
final class AssignmentRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function assignmentsForCourse(int $courseId): array
    {
        return $this->db->select(
            'SELECT a.*, u.display_name AS instructor_name,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submission_count
             FROM assignments a JOIN users u ON u.id = a.instructor_id
             WHERE a.course_id = ? ORDER BY a.due_at, a.id',
            [$courseId]
        );
    }

    public function assignment(int $id): ?array
    {
        return $this->db->first('SELECT a.*, u.display_name AS instructor_name, c.title AS course_title FROM assignments a JOIN users u ON u.id = a.instructor_id JOIN courses c ON c.id = a.course_id WHERE a.id = ?', [$id]);
    }

    public function create(array $data): int
    {
        return $this->db->insert('assignments', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('assignments', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function submissionsForAssignment(int $assignmentId): array
    {
        return $this->db->select(
            'SELECT s.*, u.username, u.display_name,
                    (SELECT score FROM grades g JOIN grade_items gi ON gi.id = g.grade_item_id
                      WHERE gi.course_id = (SELECT course_id FROM assignments WHERE id = ?) AND g.student_id = s.student_id LIMIT 1) AS linked_score
             FROM submissions s JOIN users u ON u.id = s.student_id
             WHERE s.assignment_id = ? ORDER BY s.submitted_at',
            [$assignmentId, $assignmentId]
        );
    }

    public function submissionForStudent(int $assignmentId, int $studentId): ?array
    {
        return $this->db->first('SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?', [$assignmentId, $studentId]);
    }

    public function submission(int $id): ?array
    {
        return $this->db->first('SELECT s.*, a.title AS assignment_title, a.course_id, u.username, u.display_name FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN users u ON u.id = s.student_id WHERE s.id = ?', [$id]);
    }

    public function submit(array $data): int
    {
        $existing = $this->db->first('SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?', [$data['assignment_id'], $data['student_id']]);
        if ($existing !== null) {
            $this->db->update('submissions', array_merge($data, ['status' => 'submitted', 'submitted_at' => date('Y-m-d H:i:s')]), 'id = :id', ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }
        return $this->db->insert('submissions', $data);
    }

    public function grade(int $submissionId, string $status = 'graded'): void
    {
        $this->db->update('submissions', ['status' => $status], 'id = :id', ['id' => $submissionId]);
    }
}
