# EduCollab Requirement 04

Minimal MVC Node.js application covering:

- Bootstrapping
- Auth / User
- Course
- Membership
- Post
- Comment
- Search
- Assignment
- Submission
- Upload

## Install

```bash
npm install
```

## Run

```bash
npm start
```

Default URL:

```text
http://localhost:3000
```

## Database init

Database init and admin seed run automatically on server start.

Manual init:

```bash
npm run db:init
```

## Default admin credentials

- username: `admin`
- password: `adminpass`

## Membership and course roles

Supported course roles:

- `admin`
- `teacher`
- `student`
- `assistant`
- `senior-assistant`

Course creator is automatically added to the new course as `teacher`.

Role behavior in this version:

- `teacher` / `admin` can manage memberships
- `teacher` / `admin` / `assistant` / `senior-assistant` can manage assignments
- any logged-in course member can access posts, comments, search, assignment list/detail, upload list, and upload download
- staff can upload and delete files
- users can create and update their own submissions
- course staff can view all submissions for an assignment

## Uploads

Uploads are stored on local disk under:

```text
storage/uploads/
```

Metadata is stored in SQLite table `uploads`.

## Example curl commands

Register:

```bash
curl -i -c cookie.txt -X POST http://localhost:3000/api/auth/register   -H "Content-Type: application/json"   -d "{"username":"alice","password":"pass123","display_name":"Alice"}"
```

Login:

```bash
curl -i -c cookie.txt -X POST http://localhost:3000/api/auth/login   -H "Content-Type: application/json"   -d "{"username":"admin","password":"adminpass"}"
```

Create course:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses   -H "Content-Type: application/json"   -d "{"title":"CS101","description":"Intro course"}"
```

Add member:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses/1/members   -H "Content-Type: application/json"   -d "{"user_id":2,"role_in_course":"student"}"
```

Create post:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses/1/posts   -H "Content-Type: application/json"   -d "{"title":"Welcome","body":"First post body"}"
```

Create assignment:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses/1/assignments   -H "Content-Type: application/json"   -d "{"title":"HW1","description":"Solve problems","due_at":"2026-03-10 23:59:00"}"
```

Create submission:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses/1/assignments/1/submissions   -H "Content-Type: application/json"   -d "{"content_text":"My answer","attachment_upload_id":null}"
```

List my submissions:

```bash
curl -i -b cookie.txt http://localhost:3000/api/my/submissions
```

Upload file:

```bash
curl -i -b cookie.txt -X POST http://localhost:3000/api/courses/1/uploads   -F "file=@./example.txt"
```

List uploads:

```bash
curl -i -b cookie.txt http://localhost:3000/api/courses/1/uploads
```

Download upload:

```bash
curl -i -b cookie.txt http://localhost:3000/api/courses/1/uploads/1/download -o downloaded.bin
```
