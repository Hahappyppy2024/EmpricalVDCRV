# P06 功能测试与分支覆盖率报告

## 结论

环境：Python 3.12.13、coverage.py 7.10.4、SQLite/SQLAlchemy 2。采集范围为完整 `app/` 业务包，未排除低覆盖业务文件。

| 指标 | 已覆盖 | 总计 | 覆盖率 | 要求 |
| --- | ---: | ---: | ---: | ---: |
| Statements/lines | 480 | 489 | 98.16% | — |
| Branches | 115 | 126 | **91.27%** | ≥85% |

分支覆盖率超过要求 6.27 个百分点。这里使用纯分支计算，不使用 coverage.py 将 statements 和 branches 合并后的 96.75% 总分冒充分支覆盖率。

## Use case 测试结果

| Use case | 独立测试文件 | 结果 |
| --- | --- | --- |
| CHAT-01 Accounts | `test_chat01_accounts.py` | PASS |
| CHAT-02 Workspaces and channels | `test_chat02_workspaces_channels.py` | PASS |
| CHAT-03 Membership lifecycle | `test_chat03_membership.py` | PASS |
| CHAT-04 Real-time messaging | `test_chat04_realtime_messages.py` | PASS |
| CHAT-05 Message history and search | `test_chat05_history_search.py` | PASS |
| CHAT-06 Direct messages | `test_chat06_direct_messages.py` | PASS |
| CHAT-07 Attachments | `test_chat07_attachments.py` | PASS |
| CHAT-08 Link previews | `test_chat08_link_previews.py` | PASS |
| CHAT-09 Channel administration | `test_chat09_channel_admin.py` | PASS |
| CHAT-10 Connection and delivery | `test_chat10_delivery.py` | PASS |
| CHAT-11 Presence and read state | `test_chat11_presence_read.py` | PASS |
| CHAT-12 Moderation and audit | `test_chat12_moderation.py` | PASS |

总计 12 个测试、0 失败。测试覆盖成功路径以及 `401/403/404/409/422`、成员与私有频道边界、消息所有权、状态转换、乐观版本、幂等冲突、分页范围、附件限制、预览域名 allow-list、送达幂等、恢复顺序与管理员审计。

## 复现

```bash
pip install -r requirements-dev.txt
python bin/run_functional_tests.py
coverage erase
coverage run -m unittest discover -s tests/functional -t .
coverage json -o var/coverage.json
python bin/check_coverage.py var/coverage.json
coverage report -m
```

机器可读结果保存在 `var/coverage.json` 与 `var/coverage-summary.json`。覆盖率门槛脚本会在纯 branch coverage 低于 85% 时返回非零状态。
