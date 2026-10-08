# P16 功能测试与覆盖率报告

## 验证结果

测试环境：PHP 8.3.6、Xdebug 3.2.0、PDO SQLite。覆盖率采集范围严格限定为 `src/*.php` 业务源码，不计入测试、依赖和启动脚本。

| 指标 | 已覆盖 | 总计 | 覆盖率 | 门槛 |
| --- | ---: | ---: | ---: | ---: |
| 行 | 62 | 62 | 100.00% | — |
| 分支 | 231 | 242 | **95.45%** | 85% |
| 路径 | 112 | 380 | 29.47% | 未设门槛 |

结论：分支覆盖率超过要求 10.45 个百分点。路径覆盖率不是本次验收指标；它要求覆盖一个函数内所有分支组合，组合数会快速增长，因此不能与分支覆盖率直接比较。

## 每个 use case 的功能测试

| Use case | 测试文件 | 断言数 | 结果 |
| --- | --- | ---: | --- |
| SYS-01 Account access | `SYS01AccountAccessTest.php` | 12 | PASS |
| SYS-02 Server dashboard | `SYS02ServerDashboardTest.php` | 13 | PASS |
| SYS-03 Log viewer | `SYS03LogViewerTest.php` | 13 | PASS |
| SYS-04 Service control | `SYS04ServiceControlTest.php` | 12 | PASS |
| SYS-05 Job scheduler | `SYS05JobSchedulerTest.php` | 16 | PASS |
| SYS-06 Job execution history | `SYS06JobExecutionHistoryTest.php` | 13 | PASS |
| SYS-07 Backup manager | `SYS07BackupManagerTest.php` | 10 | PASS |
| SYS-08 Configuration editor | `SYS08ConfigurationEditorTest.php` | 13 | PASS |
| SYS-09 Alert center | `SYS09AlertCenterTest.php` | 15 | PASS |
| SYS-10 Health-check targets | `SYS10HealthCheckTargetsTest.php` | 16 | PASS |
| SYS-11 API token manager | `SYS11ApiTokenManagerTest.php` | 15 | PASS |
| SYS-12 Audit logs and administration | `SYS12AuditAdministrationTest.php` | 18 | PASS |
| **合计** | **12 个文件** | **166** | **0 失败** |

测试不仅验证成功响应，也覆盖 `401/403/404/409/422`、角色边界、状态转换、过期或重复操作、幂等键、乐观版本冲突、分页和筛选、目标地址 allow-list、敏感输出脱敏及 token 只显示一次等业务分支。

## 按源码文件统计

| 文件 | 行覆盖 | 分支覆盖 | 路径覆盖 |
| --- | ---: | ---: | ---: |
| `src/ApiException.php` | 2/2 | 2/2 | 2/2 |
| `src/AppKernel.php` | 2/2 | 5/6 | 3/4 |
| `src/Auth.php` | 2/2 | 20/21 | 10/16 |
| `src/Database.php` | 2/2 | 3/4 | 2/3 |
| `src/Http.php` | 2/2 | 20/22 | 7/27 |
| `src/Routes.php` | 48/48 | 166/171 | 78/315 |
| `src/Seeder.php` | 2/2 | 7/8 | 3/6 |
| `src/SystemRepository.php` | 2/2 | 8/8 | 7/7 |

## 复现命令

```bash
php bin/run-functional-tests.php
php -d xdebug.mode=coverage bin/collect-coverage.php var/coverage.json
php bin/summarize-coverage.php var/coverage.json
```

原始结果保存在 `var/coverage.json`，机器可读汇总保存在 `var/coverage-summary.json`，测试运行日志保存在 `var/coverage-test.log`。`summarize-coverage.php` 会在分支覆盖率低于 85% 时以状态码 1 失败。
