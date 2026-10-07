
# PHP R4 tests (API only)

这套测试只覆盖 **R4 新增功能**，把 R1/R2/R3 仅作为前置环境使用：
- assignments
- submissions
- uploads

## 文件
- `tests/functional_tests/test_api_functional_r4.py`
- `tests/security_tests/exploit_tests/test_api_exploit_r4.py`
- `tests/conftest.py`
- `tests/helpers.py`

## 运行方式
把这些文件解压到 PHP 项目根目录后运行：

```bash
python -m venv .venv
.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install pytest
pip install requests
pytest -q tests/test_api_functional_r4.py
pytest -q tests/test_api_exploit_r4.py
```

## 说明
- functional test 关注 R4 的正常 API 流程与授权边界。
- exploit test 关注当前代码里 **与 R4 直接相关**、且能从代码中看出来的两类问题：
  1. 学生可把 staff 上传的文件直接绑定到自己的 submission 上（`attachment_upload_id` 只校验课程归属，不校验所有权/可用性边界）。
  2. 非 student 角色（如 assistant）也能创建 submission，因为提交接口只要求 course member，而不要求 student 角色。

## 注意
当前容器里无法实际启动这个 PHP 项目进行验证，因为缺少 `pdo_sqlite` 扩展；因此这里提供的是**按代码实现写出的测试**，建议在本地 PHP+SQLite 环境中执行。
