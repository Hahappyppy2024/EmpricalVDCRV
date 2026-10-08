# P12 Data Analytics Dashboard

一个可离线运行的 Python 3.12 数据分析仪表板，使用 Flask 3、SQLAlchemy 2 和 SQLite。系统实现 CSV ingestion、dataset catalog/preview、受限 filter 和 calculated expression、chart aggregation、dashboard sharing、CSV/PDF 导出、脱敏数据源配置、lineage、audit 与 admin operations。

> 项目采用确定性本地适配器。它不会连接外部数据库；连接测试只识别文档化的本地主机别名。PDF 是确定性教学导出，不是排版引擎生成的复杂报告。

## 本地运行

前置条件：Python 3.12。

```bash
python -m venv .venv
# Windows: .venv\Scripts\activate
# macOS/Linux: source .venv/bin/activate
pip install -r requirements.txt
python bin/reset_database.py
python run.py
```

访问 <http://127.0.0.1:8080>。完全复现实测依赖可改用 `pip install -r requirements-lock.txt`。

### 固定账号

| 身份 | 邮箱 | 密码 |
| --- | --- | --- |
| Analyst | `analyst@example.test` | `Password123!` |
| Viewer | `viewer@example.test` | `Password123!` |
| Admin | `admin@example.test` | `Password123!` |
| Workspace outsider | `outsider@example.test` | `Password123!` |
| Disabled（负向夹具） | `disabled@example.test` | `Password123!` |

## 功能测试和覆盖率

每个 use case 对应一个独立功能测试文件：

```bash
pip install -r requirements-dev.txt
python bin/run_functional_tests.py

coverage erase
coverage run -m unittest discover -s tests/functional -t .
coverage json -o var/coverage.json
python bin/check_coverage.py var/coverage.json
coverage report -m
```

门槛脚本使用纯分支公式 `covered_branches / num_branches`，低于 85% 返回失败。当前实测：语句/行覆盖率 100%，分支覆盖率 93.51%。

## Docker

```bash
docker compose up --build
```

SQLite 数据保存在 `analytics-data` volume 中。

## Use case 追踪

| ID | 功能 | 主要路由 | 独立测试 |
| --- | --- | --- | --- |
| DATA-01 | Account access | `/api/auth/*` | `test_data01_account_access.py` |
| DATA-02 | Dataset upload | `POST /api/datasets`, ingestion | `test_data02_dataset_upload.py` |
| DATA-03 | Dataset catalog | `/api/datasets*`, archive | `test_data03_dataset_catalog.py` |
| DATA-04 | Data preview | dataset preview/schema | `test_data04_preview.py` |
| DATA-05 | Filter builder | query-preview；chart filters | `test_data05_filters.py` |
| DATA-06 | Chart builder | `/api/charts*` | `test_data06_charts.py` |
| DATA-07 | Calculated columns | calculated-column CRUD | `test_data07_calculated_columns.py` |
| DATA-08 | Dashboard sharing | dashboards 和 shares | `test_data08_dashboard_sharing.py` |
| DATA-09 | Export | chart CSV；dashboard PDF | `test_data09_export.py` |
| DATA-10 | Data sources | `/api/data-sources*` | `test_data10_sources.py` |
| DATA-11 | Audit and lineage | lineage；workspace audit | `test_data11_audit_lineage.py` |
| DATA-12 | Admin operations | admin workspaces/members/connectors | `test_data12_admin.py` |

规格中的 41 条 HTTP API 全部注册；没有 use case 要求实时事件，因此没有人为加入 WebSocket。数据库结构位于 `database/schema.sql`，固定夹具位于 `app/seed.py`，授权边界位于 `app/repository.py`，API 实现位于 `app/routes.py`。

## 重要实现约束

- CSV 文件限制大小、UTF-8、规范 header、1–1000 行和 workspace quota；字段类型通过固定规则推断。
- Filter 只允许 `eq/ne/gt/gte/lt/lte/contains`，不拼接 SQL。
- Calculated column 只允许 `column +|-|*|/ number` 语法，不执行任意代码。
- Dashboard 每次读取和修改都会重新解析 workspace membership、owner 和 share permission。
- 数据源只保存 secret 的 SHA-256 摘要和 `********` 掩码；API 从不返回摘要或原始 secret。
- 连接检查仅对 `warehouse.local` 和 `localhost` 返回本地 ready，对 `offline.local` 确定性返回 failed，不发起网络请求。
