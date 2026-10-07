# EduCollab API tests (new APIs after r1 + r2)

This pack targets the APIs that were added after **r1 + r2**, specifically:

- Posts
  - `POST /api/courses/:course_id/posts`
  - `GET /api/courses/:course_id/posts`
  - `GET /api/courses/:course_id/posts/:post_id`
  - `PUT /api/courses/:course_id/posts/:post_id`
  - `DELETE /api/courses/:course_id/posts/:post_id`
  - `GET /api/courses/:course_id/search/posts?keyword=...`

- Comments
  - `POST /api/courses/:course_id/posts/:post_id/comments`
  - `GET /api/courses/:course_id/posts/:post_id/comments`
  - `PUT /api/courses/:course_id/posts/:post_id/comments/:comment_id`
  - `DELETE /api/courses/:course_id/posts/:post_id/comments/:comment_id`
  - `GET /api/courses/:course_id/search/comments?keyword=...`

## How to run

1) Copy the `tests/` folder into the project root (same folder where `app.js` exists).

2) Run with Node's built-in test runner (Node 18+):

```bash
node --test tests/api.posts_comments.functional.test.js
node --test tests/api.posts_comments.exploit.test.js
```

Or run all tests in this pack:

```bash
node --test tests/api.posts_comments.*.test.js
```

## Notes

- `functional` tests verify normal API behavior.
- `exploit` tests verify that specific attacks succeed **on vulnerable code** (IDOR/ownership-check bugs).
  If these endpoints are fixed later, the exploit tests should start failing, and can be converted into regression tests.
