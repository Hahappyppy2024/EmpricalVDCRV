<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'EduCollab', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        nav a { margin-right: 1rem; }
        form { margin: 1rem 0; }
        input, textarea, select { display: block; width: 100%; max-width: 640px; margin: 0.5rem 0 1rem; padding: 0.5rem; }
        button { padding: 0.5rem 1rem; }
        .error { color: #b00020; }
        .card { border: 1px solid #ddd; padding: 1rem; margin: 1rem 0; }
    </style>
</head>
<body>
<nav>
    <a href="/">Home</a>
    <a href="/courses">Courses</a>
    <a href="/courses/new">New Course</a>
    <a href="/memberships">My Memberships</a>
    <a href="/my/submissions">My Submissions</a>
    <a href="/my/quizzes">My Quiz Attempts</a>
    <a href="/register">Register</a>
    <a href="/login">Login</a>
    <a href="/me">Me</a>
    <a href="/admin/users">Users</a>
    <form action="/logout" method="post" style="display:inline;">
        <button type="submit">Logout</button>
    </form>
</nav>
<hr>
