# EduCollab Requirement 03

Minimal MVC Node.js application for:

- Bootstrapping
- Auth / User v0
- Course v0
- Membership v0
- Post
- Comment
- Search

## Stack

- Node.js
- Express
- better-sqlite3
- express-session
- EJS

## Install

```bash
npm install
```

## Run

```bash
npm start
```

App URL:

```bash
http://localhost:3000
```

## Database init

The database init and seed logic runs automatically on server start.

Manual init is also available:

```bash
npm run db:init
```

## Default admin credentials

- username: `admin`
- password: `adminpass`

## Requirement 03 notes

- posts, comments, and search are course-scoped
- a logged-in user must be a member of the course to create, read, update, delete, and search posts/comments in that course
- search uses simple SQLite `LIKE` matching
- this requirement keeps the earlier session, membership, and course-role logic unchanged

## Example curl commands

Register:

```bash
curl -i -c cookie.txt -X POST http://localhost:3000/api/auth/register \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"alice\",\"password\":\"pass123\",\"display_name\":\"Alice\"}"
```

Login:

```bash
curl -i -c cookie.txt -X POST http://localhost:3000/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"alice\",\"password\":\"pass123\"}"
```

Create course:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"CS101\",\"description\":\"Intro course\"}"
```

Create post:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses/1/posts \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"Welcome\",\"body\":\"First post body\"}"
```

List posts:

```bash
curl -i -b cookie.txt http://localhost:3000/api/courses/1/posts
```

Get one post:

```bash
curl -i -b cookie.txt http://localhost:3000/api/courses/1/posts/1
```

Update post:

```bash
curl -i -b cookie.txt -X PUT http://localhost:3000/api/courses/1/posts/1 \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"Welcome Updated\",\"body\":\"Updated body\"}"
```

Delete post:

```bash
curl -i -b cookie.txt -X DELETE http://localhost:3000/api/courses/1/posts/1
```

Create comment:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses/1/posts/1/comments \
  -H "Content-Type: application/json" \
  -d "{\"body\":\"Nice post\"}"
```

List comments:

```bash
curl -i -b cookie.txt http://localhost:3000/api/courses/1/posts/1/comments
```

Update comment:

```bash
curl -i -b cookie.txt -X PUT http://localhost:3000/api/courses/1/posts/1/comments/1 \
  -H "Content-Type: application/json" \
  -d "{\"body\":\"Updated comment\"}"
```

Delete comment:

```bash
curl -i -b cookie.txt -X DELETE http://localhost:3000/api/courses/1/posts/1/comments/1
```

Search posts:

```bash
curl -i -b cookie.txt "http://localhost:3000/api/courses/1/search/posts?keyword=Welcome"
```

Search comments:

```bash
curl -i -b cookie.txt "http://localhost:3000/api/courses/1/search/comments?keyword=Nice"
```
