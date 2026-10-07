# EduCollab Requirement 01

Minimal MVC Node.js application for:

- Bootstrapping
- Auth / User v0
- Course v0

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

## Notes

This requirement uses a minimal schema with no role column.
Admin-only behavior is determined by the seeded username `admin`.

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

Current user:

```bash
curl -i -b cookie.txt http://localhost:3000/api/auth/me
```

Create course:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"CS101\",\"description\":\"Intro course\"}"
```

List courses:

```bash
curl -i -b cookie.txt http://localhost:3000/api/courses
```

Get one course:

```bash
curl -i -b cookie.txt http://localhost:3000/api/courses/1
```

Update course:

```bash
curl -i -b cookie.txt -X PUT http://localhost:3000/api/courses/1 \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"CS101 Updated\",\"description\":\"Updated description\"}"
```

Delete course:

```bash
curl -i -b cookie.txt -X DELETE http://localhost:3000/api/courses/1
```

List users as admin:

```bash
curl -i -c admin_cookie.txt -X POST http://localhost:3000/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"admin\",\"password\":\"adminpass\"}"
```

```bash
curl -i -b admin_cookie.txt http://localhost:3000/api/users
```
