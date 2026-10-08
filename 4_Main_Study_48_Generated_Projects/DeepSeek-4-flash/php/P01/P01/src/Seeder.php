<?php

declare(strict_types=1);

namespace App;

/**
 * Deterministic seed fixtures. The same user accounts, courses, enrollments,
 * materials, discussions, assignments, quizzes, grades, exports, reports and
 * settings are created on every reset so acceptance criteria are reproducible.
 *
 * Seeded accounts (password shown in README):
 *   admin     / AdminPass123!  (admin)
 *   alice     / InstructorPass123!  (instructor, "Dr. Alice Chen")
 *   bob       / InstructorPass123!  (instructor, "Prof. Bob Martin")
 *   student1  / StudentPass123!
 *   student2  / StudentPass123!
 *   student3  / StudentPass123!
 */
final class Seeder
{
    private Database $db;
    private Config $config;
    /** @var array<string, int> */
    private array $ids = [];

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function seed(): void
    {
        $this->seedRoles();
        $this->seedUsers();
        $this->seedCourses();
        $this->seedEnrollments();
        $this->seedMaterials();
        $this->seedAnnouncements();
        $this->seedDiscussions();
        $this->seedAssignments();
        $this->seedQuizzes();
        $this->seedGrades();
        $this->seedSettings();
        $this->seedExports();
        $this->seedReports();
        $this->seedAudit();
    }

    private function seedRoles(): void
    {
        $this->db->insert('roles', ['name' => 'visitor', 'label' => 'Visitor']);
        $this->db->insert('roles', ['name' => 'student', 'label' => 'Student']);
        $this->db->insert('roles', ['name' => 'instructor', 'label' => 'Instructor']);
        $this->db->insert('roles', ['name' => 'admin', 'label' => 'Administrator']);
        foreach (['visitor', 'student', 'instructor', 'admin'] as $role) {
            $row = $this->db->first('SELECT id FROM roles WHERE name = ?', [$role]);
            $this->ids['role_' . $role] = (int) $row['id'];
        }
    }

    private function seedUsers(): void
    {
        $users = [
            ['admin', 'admin@example.com', 'AdminPass123!', 'System Administrator', 'admin'],
            ['alice', 'alice@example.com', 'InstructorPass123!', 'Dr. Alice Chen', 'instructor'],
            ['bob', 'bob@example.com', 'InstructorPass123!', 'Prof. Bob Martin', 'instructor'],
            ['student1', 'student1@example.com', 'StudentPass123!', 'Anna Student', 'student'],
            ['student2', 'student2@example.com', 'StudentPass123!', 'Ben Student', 'student'],
            ['student3', 'student3@example.com', 'StudentPass123!', 'Cara Student', 'student'],
            ['guest', 'guest@example.com', 'GuestPass123!', 'Guest Visitor', 'visitor'],
        ];
        foreach ($users as [$username, $email, $password, $display, $role]) {
            $id = $this->db->insert('users', [
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'display_name' => $display,
                'role_id' => $this->ids['role_' . $role],
                'active' => 1,
            ]);
            $this->ids['user_' . $username] = (int) $id;
        }
    }

    private function seedCourses(): void
    {
        $courses = [
            ['Intro to Computer Science', 'Computer Science', 'Fall 2026', 'alice', 'public', 'open', 'Foundations of programming, algorithms, and computer systems.'],
            ['Data Structures & Algorithms', 'Computer Science', 'Fall 2026', 'alice', 'public', 'open', 'Arrays, linked lists, trees, graphs, and complexity analysis.'],
            ['Calculus I', 'Mathematics', 'Fall 2026', 'bob', 'public', 'open', 'Limits, derivatives, and integrals of single-variable functions.'],
            ['Spanish for Beginners', 'Languages', 'Spring 2027', 'bob', 'public', 'closed', 'Introductory conversational Spanish and basic grammar.'],
            ['Business Finance', 'Business', 'Fall 2026', 'alice', 'private', 'open', 'Corporate finance, budgeting, and investment analysis.'],
            ['Digital Photography', 'Arts', 'Spring 2027', 'bob', 'public', 'closed', 'Composition, exposure, and post-processing fundamentals.'],
        ];
        foreach ($courses as [$title, $category, $semester, $instructor, $visibility, $status, $description]) {
            $id = $this->db->insert('courses', [
                'title' => $title,
                'category' => $category,
                'semester' => $semester,
                'instructor_id' => $this->ids['user_' . $instructor],
                'description' => $description,
                'visibility' => $visibility,
                'status' => $status,
            ]);
            $this->ids['course_' . $this->slug($title)] = (int) $id;
        }
    }

    private function seedEnrollments(): void
    {
        $map = [
            'student1' => ['intro-to-computer-science', 'calculus-i', 'spanish-for-beginners', 'business-finance'],
            'student2' => ['intro-to-computer-science', 'data-structures-algorithms', 'calculus-i', 'digital-photography'],
            'student3' => ['data-structures-algorithms', 'spanish-for-beginners', 'digital-photography'],
        ];
        foreach ($map as $student => $courses) {
            foreach ($courses as $course) {
                $status = ($student === 'student3' && $course === 'digital-photography') ? 'dropped' : 'enrolled';
                $this->db->insert('enrollments', [
                    'course_id' => $this->ids['course_' . $course],
                    'user_id' => $this->ids['user_' . $student],
                    'status' => $status,
                ]);
            }
        }
    }

    private function seedMaterials(): void
    {
        $materialSeeds = [
            ['intro-to-computer-science', 'alice', 'Course Syllabus', 'Read before the first lecture.', 'syllabus-intro-cs.txt', "P01 LMS - Intro to Computer Science\nFall 2026 - Instructor: Dr. Alice Chen\nOffice hours: Tuesday 14:00-15:00\n"],
            ['intro-to-computer-science', 'alice', 'Lecture 01 Slides', 'Basics of programming.', 'lecture-01-intro.txt', "Lecture 01 - Introduction to Programming\nTopics: variables, control flow, functions\n"],
            ['calculus-i', 'bob', 'Derivatives Cheat Sheet', 'Quick reference for derivative rules.', 'derivatives-cheat-sheet.txt', "Derivative Rules\n1. Power: d/dx x^n = n x^(n-1)\n2. Sum: (f+g)' = f' + g'\n3. Product: (fg)' = f'g + fg'\n"],
            ['business-finance', 'alice', 'Time Value of Money', 'Required reading for week 3.', 'tvm-reading.txt', "Time Value of Money\nA dollar today is worth more than a dollar tomorrow.\n"],
        ];
        $dir = $this->config->uploadDir();
        foreach ($materialSeeds as [$course, $instructor, $title, $desc, $filename, $content]) {
            $stored = 'seed-material-' . $this->slug($title) . '-' . bin2hex(random_bytes(3)) . '.txt';
            file_put_contents($dir . DIRECTORY_SEPARATOR . $stored, $content);
            $this->db->insert('materials', [
                'course_id' => $this->ids['course_' . $course],
                'uploader_id' => $this->ids['user_' . $instructor],
                'title' => $title,
                'description' => $desc,
                'filename' => $filename,
                'stored_path' => $stored,
                'mime_type' => 'text/plain',
                'size_bytes' => strlen($content),
            ]);
        }
    }

    private function seedAnnouncements(): void
    {
        $seeds = [
            ['intro-to-computer-science', 'alice', 'Welcome to Intro to Computer Science', 'Welcome everyone! The syllabus and lecture 1 materials are now available.'],
            ['intro-to-computer-science', 'alice', 'Assignment 1 posted', 'Assignment 1 is live. It is due at the end of week 3.'],
            ['calculus-i', 'bob', 'Midterm date announced', 'The midterm will take place in week 6. Review sessions start next Monday.'],
            ['business-finance', 'alice', 'Case study released', 'The week 4 case study has been published in the materials section.'],
        ];
        foreach ($seeds as [$course, $instructor, $title, $body]) {
            $this->db->insert('announcements', [
                'course_id' => $this->ids['course_' . $course],
                'instructor_id' => $this->ids['user_' . $instructor],
                'title' => $title,
                'body' => $body,
            ]);
        }
    }

    private function seedDiscussions(): void
    {
        $threads = [
            ['intro-to-computer-science', 'student1', null, 'Best way to learn PHP?', 'I am new to programming. Any tips for the labs?'],
            ['intro-to-computer-science', 'alice', null, 'Office hours next week', 'I will move Wednesday office hours to Thursday.'],
            ['intro-to-computer-science', 'student2', 1, 'Re: Best way to learn PHP?', 'Start with the lecture slides and do the exercises in order.'],
            ['calculus-i', 'student2', null, 'Question about the chain rule', 'Can someone explain when to apply the chain rule vs the product rule?'],
            ['calculus-i', 'bob', 4, 'Re: Question about the chain rule', 'The chain rule applies to composed functions f(g(x)). See the cheat sheet.'],
        ];
        foreach ($threads as [$course, $author, $parent, $subject, $body]) {
            $this->db->insert('discussions', [
                'course_id' => $this->ids['course_' . $course],
                'author_id' => $this->ids['user_' . $author],
                'parent_id' => $parent,
                'subject' => $subject,
                'body' => $body,
            ]);
        }
    }

    private function seedAssignments(): void
    {
        $assignments = [
            ['intro-to-computer-science', 'alice', 'Assignment 1: Variables & Loops', 'Write a program that prints numbers 1..100 with FizzBuzz logic.', '2026-10-15 23:59:59', 100],
            ['intro-to-computer-science', 'alice', 'Assignment 2: Functions', 'Implement a small library of string utilities.', '2026-11-15 23:59:59', 100],
            ['calculus-i', 'bob', 'Problem Set 1', 'Complete problems 1-10 in the textbook.', '2026-10-01 23:59:59', 50],
        ];
        foreach ($assignments as [$course, $instructor, $title, $desc, $due, $max]) {
            $id = $this->db->insert('assignments', [
                'course_id' => $this->ids['course_' . $course],
                'instructor_id' => $this->ids['user_' . $instructor],
                'title' => $title,
                'description' => $desc,
                'due_at' => $due,
                'max_points' => $max,
            ]);
            $this->ids['assignment_' . $this->slug($title)] = (int) $id;
        }
        // Seed submissions
        $subs = [
            ['assignment-1-variables-loops', 'student1', 'fizzbuzz-student1.txt', "<?php\nfor (\$i = 1; \$i <= 100; \$i++) {\n    echo \$i % 15 === 0 ? 'FizzBuzz' : (\$i % 3 === 0 ? 'Fizz' : (\$i % 5 === 0 ? 'Buzz' : \$i)) . \"\\n\";\n}\n"],
            ['assignment-1-variables-loops', 'student2', 'fizzbuzz-student2.txt', "for i in range(1, 101):\n    ...\n"],
            ['problem-set-1', 'student2', 'ps1-student2.txt', "Problem set 1 solutions (scanned).\n"],
        ];
        $dir = $this->config->uploadDir();
        foreach ($subs as [$assignment, $student, $filename, $content]) {
            $stored = 'seed-submission-' . $assignment . '-' . $student . '.txt';
            file_put_contents($dir . DIRECTORY_SEPARATOR . $stored, $content);
            $this->db->insert('submissions', [
                'assignment_id' => $this->ids['assignment_' . $assignment],
                'student_id' => $this->ids['user_' . $student],
                'filename' => $filename,
                'stored_path' => $stored,
                'mime_type' => 'text/plain',
                'size_bytes' => strlen($content),
                'status' => 'submitted',
            ]);
        }
    }

    private function seedQuizzes(): void
    {
        $quizSeeds = [
            [
                'intro-to-computer-science', 'alice', 'Quiz 1: PHP Basics', 'Covers variables, arrays, and control flow.', 10,
                [
                    ['Which keyword declares a constant variable in PHP?', 'multiple_choice', ['define', 'constant', 'var', 'let'], 'define', 1],
                    ['What does the PHP function array_map do?', 'multiple_choice', ['Applies a callback to each element', 'Sorts an array', 'Splices an array', 'Merges arrays'], 'Applies a callback to each element', 1],
                    ['What is the output of echo 2 + 3 * 4; ?', 'multiple_choice', ['20', '14', '24', '9'], '14', 1],
                ],
            ],
            [
                'calculus-i', 'bob', 'Quiz 1: Limits', 'Basic limits and continuity.', 15,
                [
                    ['What is lim(x->0) of sin(x)/x?', 'multiple_choice', ['0', '1', 'Infinity', 'Undefined'], '1', 1],
                    ['A function f is continuous at a if...', 'multiple_choice', ['f(a) exists and the limit equals f(a)', 'The limit does not exist', 'f(a) is undefined', 'The derivative is zero'], 'f(a) exists and the limit equals f(a)', 1],
                ],
            ],
        ];
        foreach ($quizSeeds as [$course, $instructor, $title, $desc, $timeLimit, $questions]) {
            $quizId = $this->db->insert('quizzes', [
                'course_id' => $this->ids['course_' . $course],
                'instructor_id' => $this->ids['user_' . $instructor],
                'title' => $title,
                'description' => $desc,
                'time_limit_minutes' => $timeLimit,
                'published' => 1,
            ]);
            foreach ($questions as [$prompt, $type, $options, $correct, $points]) {
                $this->db->insert('quiz_questions', [
                    'quiz_id' => $quizId,
                    'prompt' => $prompt,
                    'question_type' => $type,
                    'options_json' => (string) json_encode($options),
                    'correct_answer' => $correct,
                    'points' => $points,
                ]);
            }
            $this->ids['quiz_' . $this->slug($title)] = (int) $quizId;
        }

        // Completed attempt for student1 on "Quiz 1: PHP Basics"
        $quizId = $this->ids['quiz_quiz-1-php-basics'];
        $attemptId = $this->db->insert('quiz_attempts', [
            'quiz_id' => $quizId,
            'student_id' => $this->ids['user_student1'],
            'status' => 'completed',
            'score' => 3.0,
            'started_at' => '2026-09-02 10:00:00',
            'submitted_at' => '2026-09-02 10:08:00',
        ]);
        $questions = $this->db->select('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id', [$quizId]);
        foreach ($questions as $q) {
            $this->db->insert('quiz_answers', [
                'attempt_id' => $attemptId,
                'question_id' => (int) $q['id'],
                'answer_text' => (string) $q['correct_answer'],
                'is_correct' => 1,
                'points_earned' => (float) $q['points'],
            ]);
        }
    }

    private function seedGrades(): void
    {
        $items = [
            ['intro-to-computer-science', 'alice', 'Assignment 1', 100, 0.4],
            ['intro-to-computer-science', 'alice', 'Quiz 1', 10, 0.2],
            ['intro-to-computer-science', 'alice', 'Final Exam', 100, 0.4],
            ['calculus-i', 'bob', 'Problem Set 1', 50, 0.3],
            ['business-finance', 'alice', 'Case Study', 100, 0.5],
        ];
        $gradeSeeds = [
            ['Assignment 1', 'student1', 85, 'Good work on control flow.'],
            ['Assignment 1', 'student2', 92, 'Excellent.'],
            ['Quiz 1', 'student1', 10, 'Perfect score.'],
            ['Quiz 1', 'student2', 7, 'Review the array_map question.'],
            ['Problem Set 1', 'student2', 42, 'Solid effort.'],
            ['Case Study', 'student1', 88, 'Strong analysis.'],
        ];
        foreach ($items as [$course, $instructor, $name, $max, $weight]) {
            $itemId = $this->db->insert('grade_items', [
                'course_id' => $this->ids['course_' . $course],
                'instructor_id' => $this->ids['user_' . $instructor],
                'name' => $name,
                'max_points' => $max,
                'weight' => $weight,
            ]);
            $this->ids['grade_item_' . $this->slug($course) . '_' . $this->slug($name)] = (int) $itemId;
        }
        foreach ($gradeSeeds as [$item, $student, $score, $feedback]) {
            $course = match ($item) {
                'Assignment 1' => 'intro-to-computer-science',
                'Quiz 1' => 'intro-to-computer-science',
                'Problem Set 1' => 'calculus-i',
                'Case Study' => 'business-finance',
                default => 'intro-to-computer-science',
            };
            $itemId = $this->ids['grade_item_' . $this->slug($course) . '_' . $this->slug($item)];
            $this->db->insert('grades', [
                'grade_item_id' => $itemId,
                'student_id' => $this->ids['user_' . $student],
                'score' => $score,
                'feedback' => $feedback,
                'graded_by' => $this->ids['user_alice'],
            ]);
        }
    }

    private function seedSettings(): void
    {
        $settings = [
            'site_name' => 'P01 Learning Management System',
            'allow_registration' => 'true',
            'default_role' => 'student',
            'session_lifetime_minutes' => (string) $this->config->sessionLifetimeMinutes(),
            'announcements_enabled' => 'true',
            'grade_export_format' => 'csv',
        ];
        foreach ($settings as $key => $value) {
            $this->db->insert('settings', ['key' => $key, 'value' => $value]);
        }
    }

    private function seedExports(): void
    {
        $csv = "username,display_name,item,score,max_points\nstudent1,Anna Student,Assignment 1,85,100\nstudent2,Ben Student,Assignment 1,92,100\n";
        $file = 'seed-grade-export-' . date('Ymd') . '.csv';
        file_put_contents($this->config->exportDir() . DIRECTORY_SEPARATOR . $file, $csv);
        $this->db->insert('exports', [
            'course_id' => $this->ids['course_intro-to-computer-science'],
            'requested_by' => $this->ids['user_alice'],
            'format' => 'csv',
            'kind' => 'grades',
            'file_path' => $file,
            'status' => 'ready',
        ]);
    }

    private function seedReports(): void
    {
        $summary = [
            'total_courses' => 6,
            'total_students' => 3,
            'total_enrollments' => 11,
            'total_materials' => 4,
            'total_assignments' => 3,
            'total_quizzes' => 2,
        ];
        $this->db->insert('reports', [
            'requested_by' => $this->ids['user_admin'],
            'report_type' => 'courses',
            'filters_json' => (string) json_encode(['semester' => 'Fall 2026']),
            'summary_json' => (string) json_encode($summary),
            'status' => 'ready',
        ]);
    }

    private function seedAudit(): void
    {
        $events = [
            ['admin', 'system.seeded', 'system', 0, ['fixtures' => 'v1']],
            ['alice', 'course.create', 'courses', $this->ids['course_intro-to-computer-science'], ['title' => 'Intro to Computer Science']],
            ['admin', 'user.create', 'users', $this->ids['user_student3'], ['username' => 'student3']],
        ];
        foreach ($events as [$user, $action, $entity, $entityId, $detail]) {
            $this->db->insert('audit_events', [
                'user_id' => $this->ids['user_' . $user],
                'action' => $action,
                'entity_type' => $entity,
                'entity_id' => $entityId,
                'detail_json' => (string) json_encode($detail),
            ]);
        }
        $this->db->insert('account_access_log', [
            'user_id' => $this->ids['user_admin'],
            'action' => 'system.seeded',
            'detail_json' => (string) json_encode(['seed' => 'v1']),
        ]);
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
