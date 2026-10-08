# P12 功能测试与分支覆盖率报告

## 验证结论

环境：Python 3.12.13、coverage.py 7.10.4、SQLAlchemy 2.0.43、SQLite。覆盖范围是完整 `app/` 业务包，没有排除低覆盖业务文件。

| 指标 | 已覆盖 | 总计 | 覆盖率 | 要求 |
| --- | ---: | ---: | ---: | ---: |
| Statements/lines | 386 | 386 | **100.00%** | — |
| Branches | 144 | 154 | **93.51%** | ≥85% |

分支覆盖率超过门槛 8.51 个百分点。这里报告的是纯分支比例，而不是 coverage.py 将 statements 和 branches 合并后的 98% 总分。

## 每个 use case 的功能测试

| Use case | 测试文件 | 结果 |
| --- | --- | --- |
| DATA-01 Account access | `test_data01_account_access.py` | PASS |
| DATA-02 Dataset upload | `test_data02_dataset_upload.py` | PASS |
| DATA-03 Dataset catalog | `test_data03_dataset_catalog.py` | PASS |
| DATA-04 Data preview | `test_data04_preview.py` | PASS |
| DATA-05 Filter builder | `test_data05_filters.py` | PASS |
| DATA-06 Chart builder | `test_data06_charts.py` | PASS |
| DATA-07 Calculated columns | `test_data07_calculated_columns.py` | PASS |
| DATA-08 Dashboard sharing | `test_data08_dashboard_sharing.py` | PASS |
| DATA-09 Export | `test_data09_export.py` | PASS |
| DATA-10 Data source connections | `test_data10_sources.py` | PASS |
| DATA-11 Audit and lineage | `test_data11_audit_lineage.py` | PASS |
| DATA-12 Admin operations | `test_data12_admin.py` | PASS |

总计 12 个独立测试、0 失败。测试覆盖 `401/403/404/409/422`、CSV 编码/类型/大小/header/quota、分页边界、dataset 状态、filter grammar、数值与字符串比较、chart aggregation、restricted expression、share permission、导出格式、credential masking、连接状态、lineage 范围和 admin role/version validation。

## 复现命令

```bash
pip install -r requirements-dev.txt
python bin/run_functional_tests.py
coverage erase
coverage run -m unittest discover -s tests/functional -t .
coverage json -o var/coverage.json
python bin/check_coverage.py var/coverage.json
coverage report -m
```

机器可读结果保存在 `var/coverage.json` 和 `var/coverage-summary.json`。`check_coverage.py` 会在纯 branch coverage 低于 85% 时返回非零状态。
