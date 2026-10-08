<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Seeder
{
    public static function reset(string $root, string $path): PDO
    {
        /*
         * Support both:
         * - Linux absolute path:
         *      /tmp/test.sqlite
         *
         * - Windows absolute path:
         *      C:\Users\name\test.sqlite
         *
         * - Relative path:
         *      var/lms.sqlite
         */

        $isAbsolute =
            str_starts_with($path, '/') ||
            preg_match('/^[A-Za-z]:[\\\\\/]/', $path);

        $absolute = $isAbsolute
            ? $path
            : $root . DIRECTORY_SEPARATOR . $path;


        if (is_file($absolute)) {
            unlink($absolute);
        }


        $pdo = Database::connect($absolute);


        $schema = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';

        $pdo->exec(
            (string) file_get_contents($schema)
        );


        $password = password_hash(
            'Password123!',
            PASSWORD_DEFAULT
        );


        $users = [
            [
                'student@example.test',
                $password,
                'Sam Student',
                'student',
                'Learning web engineering.',
                'America/Toronto'
            ],

            [
                'student2@example.test',
                $password,
                'Taylor Student',
                'student',
                '',
                'UTC'
            ],

            [
                'instructor@example.test',
                $password,
                'Iris Instructor',
                'instructor',
                'Course instructor.',
                'America/Toronto'
            ],

            [
                'instructor2@example.test',
                $password,
                'Morgan Instructor',
                'instructor',
                '',
                'UTC'
            ],

            [
                'admin@example.test',
                $password,
                'Alex Admin',
                'admin',
                'Platform administrator.',
                'UTC'
            ],
        ];


        $stmt = $pdo->prepare(
            'INSERT INTO users(
                email,
                password_hash,
                display_name,
                role,
                bio,
                timezone
            )
            VALUES(?,?,?,?,?,?)'
        );


        foreach ($users as $user) {

            $stmt->execute($user);

            $pdo
                ->prepare(
                    'INSERT INTO user_preferences(user_id)
                     VALUES(?)'
                )
                ->execute([
                    (int)$pdo->lastInsertId()
                ]);
        }


        $pdo->exec(
            "INSERT INTO categories(name,slug)
             VALUES
             ('Computer Science','computer-science'),
             ('Design','design'),
             ('Business','business')"
        );


        $pdo->exec(
            "INSERT INTO courses(
                title,
                description,
                category_id,
                status,
                capacity,
                enrollment_opens_at,
                enrollment_closes_at,
                version
            )
            VALUES
            (
                'Web Application Engineering',
                'Build maintainable server-side web applications.',
                1,
                'published',
                30,
                '2020-01-01T00:00:00Z',
                '2035-12-31T23:59:59Z',
                1
            ),
            (
                'Interface Design Studio',
                'Practice accessible interface design.',
                2,
                'published',
                20,
                '2020-01-01T00:00:00Z',
                '2035-12-31T23:59:59Z',
                1
            ),
            (
                'Advanced Platform Topics',
                'An instructor-only draft course.',
                1,
                'draft',
                15,
                '2020-01-01T00:00:00Z',
                '2035-12-31T23:59:59Z',
                1
            )"
        );


        $pdo->exec(
            "INSERT INTO course_instructors(course_id,user_id)
             VALUES
             (1,3),
             (2,4),
             (3,3)"
        );


        $pdo->exec(
            "INSERT INTO enrollments(
                course_id,
                student_id,
                status
             )
             VALUES
             (1,1,'active'),
             (1,2,'active'),
             (2,1,'completed')"
        );


        $pdo->exec(
            "INSERT INTO materials(
                course_id,
                title,
                description,
                visibility,
                external_url
             )
             VALUES
             (
                1,
                'Course handbook',
                'Syllabus and policies',
                'visible',
                'https://example.invalid/local-handbook'
             ),
             (
                1,
                'Instructor notes',
                'Planning notes',
                'hidden',
                NULL
             )"
        );


        $pdo->exec(
            "INSERT INTO announcements(
                course_id,
                title,
                body,
                publish_at,
                author_id
             )
             VALUES
             (
                1,
                'Welcome',
                'Welcome to Web Application Engineering.',
                '2025-01-01T09:00:00Z',
                3
             ),
             (
                1,
                'Future module',
                'This announcement is scheduled.',
                '2035-01-01T09:00:00Z',
                3
             )"
        );


        $pdo->exec(
            "INSERT INTO discussion_topics(course_id,title)
             VALUES
             (1,'Introductions')"
        );


        $pdo->exec(
            "INSERT INTO discussion_posts(
                topic_id,
                author_id,
                body,
                is_topic_post
             )
             VALUES
             (
                1,
                3,
                'Introduce yourself and share one learning goal.',
                1
             ),
             (
                1,
                1,
                'I want to learn reliable API design.',
                0
             )"
        );


        $pdo->exec(
            "INSERT INTO assignments(
                course_id,
                title,
                instructions,
                due_at,
                max_score
             )
             VALUES
             (
                1,
                'Architecture brief',
                'Describe an MVC architecture.',
                '2035-05-01T23:59:59Z',
                100
             )"
        );


        $pdo->exec(
            "INSERT INTO submissions(
                assignment_id,
                student_id,
                version,
                text_content,
                is_late,
                is_current,
                score,
                feedback,
                released
             )
             VALUES
             (
                1,
                1,
                1,
                'My architecture separates transport, domain logic, and persistence.',
                0,
                1,
                88,
                'Clear separation of concerns.',
                1
             )"
        );


        $pdo->exec(
            "INSERT INTO quizzes(
                course_id,
                title,
                time_limit_minutes,
                attempt_limit,
                max_score,
                status
             )
             VALUES
             (
                1,
                'HTTP Fundamentals',
                20,
                2,
                10,
                'published'
             )"
        );


        $pdo->exec(
            "INSERT INTO questions(
                quiz_id,
                prompt,
                choices_json,
                correct_answer,
                points,
                position
             )
             VALUES
             (
                1,
                'Which method is normally safe and idempotent?',
                '[\"GET\",\"POST\",\"PATCH\"]',
                'GET',
                5,
                1
             ),
             (
                1,
                'Which status means not authenticated?',
                '[\"401\",\"403\",\"404\"]',
                '401',
                5,
                2
             )"
        );


        $pdo->exec(
            "INSERT INTO notifications(
                user_id,
                type,
                message,
                link
             )
             VALUES
             (
                1,
                'announcement',
                'A welcome announcement was posted.',
                '/courses/1'
             ),
             (
                3,
                'submission',
                'A new assignment submission is available.',
                '/courses/1'
             )"
        );


        $pdo->exec(
            "INSERT INTO audit_events(
                actor_id,
                action,
                entity_type,
                entity_id
             )
             VALUES
             (
                5,
                'seed',
                'database',
                1
             )"
        );


        return $pdo;
    }
}