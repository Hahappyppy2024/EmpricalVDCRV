# EduCollab PHP Requirement 03

Minimal PHP 8+ MVC-style application for bootstrapping, auth/user, course management, membership, posts, comments, and search.

## Requirements

- PHP 8+
- PDO SQLite extension enabled

## Install

No external dependencies are required.

## Run

```bash
php scripts/init.php
php -S 127.0.0.1:8000 -t public
```

The application auto-runs DB initialization, admin seeding, and course creator membership backfill on server start.

## Default admin credentials

- username: `admin`
- password: `admin123`

## Role rules

- `teacher` and `admin` can add members, update roles, and remove members
- any logged-in course member can view the course member list
- any logged-in course member can create, view, update, and delete posts in that course
- any logged-in course member can create, view, update, and delete comments in that course
- any logged-in course member can search posts and comments in that course

## Search behavior

- Post search uses SQL `LIKE` over `title` and `body`
- Comment search uses SQL `LIKE` over `body`

## Example curl commands

Login:

```bash
curl -i -c cookie.txt -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

Create course:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses \
  -H "Content-Type: application/json" \
  -d '{"title":"Course A","description":"Demo"}'
```

Create post:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/posts \
  -H "Content-Type: application/json" \
  -d '{"title":"Welcome","body":"Hello course members"}'
```

List posts:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/courses/1/posts
```

Create comment:

```bash
curl -i -b cookie.txt -X POST http://127.0.0.1:8000/api/courses/1/posts/1/comments \
  -H "Content-Type: application/json" \
  -d '{"body":"Nice post"}'
```

List comments:

```bash
curl -i -b cookie.txt http://127.0.0.1:8000/api/courses/1/posts/1/comments
```

Search posts:

```bash
curl -i -b cookie.txt "http://127.0.0.1:8000/api/courses/1/search/posts?keyword=Welcome"
```

Search comments:

```bash
curl -i -b cookie.txt "http://127.0.0.1:8000/api/courses/1/search/comments?keyword=Nice"
```
