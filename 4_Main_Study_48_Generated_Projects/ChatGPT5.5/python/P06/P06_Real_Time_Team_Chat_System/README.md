# P06 Real-time Team Chat System

可离线运行的 Python 3.12 团队聊天系统，使用 Flask 3、SQLAlchemy 2、SQLite 和 Flask-Sock。它实现频道与私聊消息、WebSocket 事件恢复、成员和频道管理、附件、确定性链接预览、presence、送达/已读状态、举报与审计。

> 这是确定性的教学实现，不连接外部消息、文件或网页服务。链接预览只接受两个保留测试域名，并从本地适配器生成元数据。

## 本地运行

前置条件：Python 3.12。

```bash
python -m venv .venv
# Windows: .venv\Scripts\activate
# macOS/Linux: source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
python bin/reset_database.py
python run.py
```

打开 <http://127.0.0.1:8080>。Flask-Sock 在同一服务暴露 `ws://127.0.0.1:8080/ws/workspaces/{workspaceId}`。

需要完全复现实测依赖版本时，使用 `pip install -r requirements-lock.txt`；该锁文件同时包含测试和 WebSocket 冒烟客户端依赖。

### 固定账号

| 身份 | 邮箱 | 密码 |
| --- | --- | --- |
| Member | `alice@example.test` | `Password123!` |
| Channel admin | `bob@example.test` | `Password123!` |
| Workspace admin | `admin@example.test` | `Password123!` |
| Non-member | `outsider@example.test` | `Password123!` |
| Disabled（负向夹具） | `disabled@example.test` | `Password123!` |

## 功能测试和覆盖率

每个 use case 有一个独立测试文件。测试通过 Flask test client 调用完整 HTTP 栈，并直接验证 WebSocket gateway 的连接、恢复、非法 JSON 和非法命令分支。

```bash
pip install -r requirements-dev.txt
python bin/run_functional_tests.py
coverage erase
coverage run -m unittest discover -s tests/functional -t .
coverage json -o var/coverage.json
python bin/check_coverage.py var/coverage.json
coverage report -m
```

`check_coverage.py` 使用纯分支公式 `covered_branches / num_branches`，低于 85% 会返回失败状态。当前实测：行覆盖率 98.16%，分支覆盖率 91.27%。

## Docker

```bash
docker compose up --build
```

SQLite 数据保存在 `chat-data` volume 中。

## Use case 追踪

| ID | 功能 | 路由/事件 | 测试文件 |
| --- | --- | --- | --- |
| CHAT-01 | Accounts | `/api/auth/*`, `/api/profile` | `test_chat01_accounts.py` |
| CHAT-02 | Workspaces and channels | `/api/workspaces*`, `/api/channels/{id}` | `test_chat02_workspaces_channels.py` |
| CHAT-03 | Membership lifecycle | invitations 和 workspace members 路由 | `test_chat03_membership.py` |
| CHAT-04 | Real-time messaging | channel messages；`WS /ws/workspaces/{id}` | `test_chat04_realtime_messages.py` |
| CHAT-05 | History and search | messages 分页；`/api/search/messages` | `test_chat05_history_search.py` |
| CHAT-06 | Direct messages | `/api/direct-threads*` | `test_chat06_direct_messages.py` |
| CHAT-07 | Attachments | channel attachments；attachment content | `test_chat07_attachments.py` |
| CHAT-08 | Link previews | `/api/link-previews*` | `test_chat08_link_previews.py` |
| CHAT-09 | Channel administration | archive；channel members | `test_chat09_channel_admin.py` |
| CHAT-10 | Connection and delivery | realtime resume；delivered receipt | `test_chat10_delivery.py` |
| CHAT-11 | Presence and read state | presence；read cursor | `test_chat11_presence_read.py` |
| CHAT-12 | Moderation and audit | reports；audit events | `test_chat12_moderation.py` |

规格要求的 39 条 HTTP API 路由和 1 条 WebSocket 路由全部注册。持久化结构位于 `database/schema.sql`，确定性夹具位于 `app/seed.py`，访问边界集中在 `app/repository.py`，路由实现位于 `app/routes.py`。

## 关键边界

- 会话 token 只通过 HTTP-only Cookie 传递，SQLite 仅保存 SHA-256 摘要。
- private channel、direct thread、附件、预览、搜索和实时事件均重新检查当前成员关系。
- 消息、私聊和其他可能产生重复副作用的创建操作使用唯一幂等键或自然唯一键。
- 附件只接受 `text/plain`、`image/png`、`application/pdf`，并限制大小；文件名会去除路径部分。
- 链接预览不执行外部 HTTP 请求，只接受 `https://docs.example.test` 和 `https://chat.example.test`。
