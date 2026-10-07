# EduCollab r1 增量 API 测试（相对于 r1 新增的 membership API）

这套测试只覆盖 **相对于 r1 新增的 API**，也就是 membership 相关接口：

- `POST /api/courses/:course_id/members`
- `GET /api/courses/:course_id/members`
- `PUT /api/courses/:course_id/members/:membership_id`
- `DELETE /api/courses/:course_id/members/:membership_id`
- `GET /api/memberships`

## 文件说明

- `tests/_helper.js`：启动/关闭服务、维持 session cookie、读 SQLite
- `tests/api.memberships.functional.test.js`：正常功能流测试
- `tests/api.memberships.exploit.test.js`：漏洞利用测试

## 适用前提

把这些文件放到**已经合并了 membership 功能后的项目根目录**里，再运行。

项目至少要满足：

- `app.js` 可以用 `node app.js` 启动
- 依赖已经安装完成（如 `npm install`）
- 新增 membership API 路由已经接入
- 数据库初始化逻辑已经包含 `memberships` 表

## 运行方式

单独运行 functional test：

```bash
node --test tests/api.memberships.functional.test.js
```

单独运行 exploit test：

```bash
node --test tests/api.memberships.exploit.test.js
```

一起运行：

```bash
node --test tests/api.memberships.*.test.js
```

## 测试意图

### Functional test

验证新增 membership API 的正常流程：

- 教师创建课程
- 教师给学生加课
- 查询课程成员
- 更新成员角色
- 学生查询自己的 memberships
- 删除成员

### Exploit test

当前代码里最明显的新漏洞点是：

- `GET /api/courses/:course_id/members` 只要求“已登录”，没有要求“是该课程成员”

因此 exploit test 验证的是：

- 一个**完全不属于该课程**的已登录用户，仍然可以读取该课程的成员名单
- 并且可以看到用户名、角色等 roster 信息

这类 exploit test 在**漏洞仍然存在时应当通过**，因为它验证的是“攻击成功”。

## 说明

这套测试是针对“相对于 r1 新增的 membership API”写的，不重复覆盖你上一版已经有的 auth / users / courses 基础 API。
