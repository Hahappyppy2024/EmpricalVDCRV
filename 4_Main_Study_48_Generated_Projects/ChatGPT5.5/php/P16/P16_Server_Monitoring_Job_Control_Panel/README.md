# P16 Server Monitoring & Job Control Panel

一个可离线运行的 PHP 8.3 + Slim 4 + PDO SQLite 教学项目，实现服务器监控、服务与任务控制、备份、告警、健康检查、令牌及管理功能。浏览器界面位于 `/`，API 使用存储在 SQLite 中的服务端会话，并通过 HTTP-only Cookie 识别用户。

> 本项目使用确定性的本地模拟数据与动作适配器，不会控制真实服务器，也不会访问外部监控地址。健康检查目标只允许预定义的本机地址。

## 快速运行

### 前置条件

- PHP 8.3，启用 `pdo_sqlite`
- Composer 2.x（若压缩包已包含 `vendor/`，可跳过安装依赖）

```bash
composer install --no-interaction
cp .env.example .env
php bin/reset-database.php
php -S 127.0.0.1:8080 -t public public/router.php
```

打开 <http://127.0.0.1:8080>。

### 确定性测试账号

| 角色 | 邮箱 | 密码 |
| --- | --- | --- |
| Operator | `operator@example.test` | `Password123!` |
| Admin | `admin@example.test` | `Password123!` |
| Disabled（负向测试） | `disabled@example.test` | `Password123!` |

重置数据库会删除本地 SQLite 数据并恢复上述固定夹具：

```bash
php bin/reset-database.php
```

## 功能测试

每个 use case 对应一个独立功能测试文件，测试通过 Slim 的 PSR-7 应用入口执行完整 HTTP 请求、认证、路由、业务规则与 SQLite 持久化流程。

```bash
php bin/run-functional-tests.php
```

当前基线：12 个测试文件、166 条断言、0 失败。

### 分支覆盖率

需要安装 Xdebug 3，并启用 coverage 模式：

```bash
php -d xdebug.mode=coverage bin/collect-coverage.php var/coverage.json
php bin/summarize-coverage.php var/coverage.json
```

汇总脚本只统计 `src/` 业务源码，并在分支覆盖率低于 85% 时返回非零退出码。已验证基线：行覆盖率 100%，分支覆盖率 95.45%。详见 `COVERAGE_REPORT.md`。

## Docker

```bash
docker compose up --build
```

访问 <http://127.0.0.1:8080>。SQLite 数据保存在 `system-data` volume 中。

## Use case 追踪

| ID | 功能 | API 路由 | 功能测试 |
| --- | --- | --- | --- |
| SYS-01 | 账户访问 | `/api/auth/*` | `tests/Functional/SYS01AccountAccessTest.php` |
| SYS-02 | 服务器仪表板 | `/api/servers`, `/api/servers/{id}`, `/metrics` | `tests/Functional/SYS02ServerDashboardTest.php` |
| SYS-03 | 日志查看 | `/api/servers/{id}/log-sources`, `/logs` | `tests/Functional/SYS03LogViewerTest.php` |
| SYS-04 | 服务控制 | `/api/servers/{id}/services`, `/api/services/*` | `tests/Functional/SYS04ServiceControlTest.php` |
| SYS-05 | 任务调度 | `/api/servers/{id}/jobs`, `/api/jobs/*` | `tests/Functional/SYS05JobSchedulerTest.php` |
| SYS-06 | 任务执行历史 | `/api/jobs/{id}/runs`, `/api/job-runs/{id}` | `tests/Functional/SYS06JobExecutionHistoryTest.php` |
| SYS-07 | 备份管理 | `/api/servers/{id}/backups`, `/api/backups/*` | `tests/Functional/SYS07BackupManagerTest.php` |
| SYS-08 | 配置编辑 | `/api/servers/{id}/configuration` | `tests/Functional/SYS08ConfigurationEditorTest.php` |
| SYS-09 | 告警中心 | `/api/alerts*` | `tests/Functional/SYS09AlertCenterTest.php` |
| SYS-10 | 健康检查目标 | `/api/health-check-targets*` | `tests/Functional/SYS10HealthCheckTargetsTest.php` |
| SYS-11 | API 令牌管理 | `/api/admin/api-tokens*` | `tests/Functional/SYS11ApiTokenManagerTest.php` |
| SYS-12 | 审计与管理 | `/api/admin/audit-events`, `/users`, `/servers` | `tests/Functional/SYS12AuditAdministrationTest.php` |

主要实现集中在 `src/Routes.php`、`src/Auth.php`、`src/SystemRepository.php`，数据库结构和固定夹具分别位于 `database/schema.sql` 与 `src/Seeder.php`。项目共注册 44 条规格要求的 HTTP 路由；本规格不要求 WebSocket，因此使用普通 HTTP。

## 确定性实现选择

- 服务启动、停止、重启和任务执行均为本地状态机模拟，不执行系统命令。
- 任务命令限定为 `cleanup`、`backup`、`health-check`；输出中的敏感信息固定脱敏为 `[REDACTED]`。
- 健康检查仅允许 HTTP `127.0.0.1:8080/8081` 的 `/health` 或 `/status`，以及 TCP `127.0.0.1:9000/9001`。
- API token 只在创建或轮换响应中显示一次，数据库仅保存 SHA-256 摘要与可识别前缀。
- 配置字段采用 allow-list 和类型校验，写入新版本而不是覆盖历史版本。

## Two-layer HTTP security validation

A PowerShell security suite is included in `tests/exploit/`. It contains a `Core` layer and a `P16_Specific` Server Monitoring & Job Control layer.

```powershell
composer db:reset
composer start
```

Then in a second terminal:

```powershell
cd tests\exploit
.\run_all_tests.ps1
```
