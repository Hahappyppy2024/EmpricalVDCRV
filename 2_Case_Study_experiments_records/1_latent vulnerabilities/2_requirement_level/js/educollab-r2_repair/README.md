# EduCollab Requirement 02

Minimal MVC Node.js application for:

- Bootstrapping
- Auth / User v0
- Course v0
- Membership v0
- Course-level role enforcement helpers

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

## Membership and course roles

Membership records are stored in the `memberships` table.

Allowed course roles:

- `admin`
- `teacher`
- `student`
- `assistant`
- `senior-assistant`

Course role rules in this requirement:

- course `teacher` and course `admin` can add members
- course `teacher` and course `admin` can update member roles
- course `teacher` and course `admin` can remove members
- any logged-in user can view course members
- any logged-in user can view their own memberships
- when a user creates a course, that user is automatically added as a `teacher` in that course

Global admin behavior from Requirement 01 is preserved only for `/admin/users`.
Course role checks are handled with reusable middleware for later requirements.

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

List course members:

```bash
curl -i -b cookie.txt http://localhost:3000/api/courses/1/members
```

Add member to course:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses/1/members \
  -H "Content-Type: application/json" \
  -d "{\"user_id\":2,\"role_in_course\":\"student\"}"
```

Update member role:

```bash
curl -i -b cookie.txt -X PUT http://localhost:3000/api/courses/1/members/2 \
  -H "Content-Type: application/json" \
  -d "{\"role_in_course\":\"assistant\"}"
```

Remove member:

```bash
curl -i -b cookie.txt -X DELETE http://localhost:3000/api/courses/1/members/2
```

My memberships:

```bash
curl -i -b cookie.txt http://localhost:3000/api/memberships
```
