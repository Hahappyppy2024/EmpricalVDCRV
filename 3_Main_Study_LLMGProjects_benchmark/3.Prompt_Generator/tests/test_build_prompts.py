from __future__ import annotations

import argparse
import json
import sys
import tempfile
import unittest
from pathlib import Path


PACKAGE_ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PACKAGE_ROOT))

import build_prompts  # noqa: E402


SYSTEM = "Build one application. Do not generate tests.\n"
TEMPLATE = """Profile
{LANGUAGE_PROFILE}
Project
{PROJECT_SPECIFICATION}
Cases
{USE_CASE_SPECIFICATIONS}
"""
README = """# P01 — Test Project

## Real-World Alignment

| Field | Value |
| --- | --- |
| Alignment target | Example App |
| Target language | PHP |
| Evidence status | NVD example such as CVE-2024-1234. |

These specifications are language-neutral and can be used to generate JavaScript, PHP, or Python implementations.
"""
USE_CASE = """# TEST-01 — First

| Field | Defined value |
| --- | --- |
| Use case | TEST-01 |
| Alignment language | PHP |

Do not include CVE identifiers, patch details, or vulnerability-specific instructions in the project-generation prompt.

## Functional acceptance criteria

| Test ID | Expected behavior | Planned test level |
| --- | --- | --- |
| TEST-01-FA-01 | workflow completes | Automated API/integration test |
"""


class PromptBuilderTests(unittest.TestCase):
    def make_tree(self, root: Path) -> argparse.Namespace:
        specs = root / "source_specs"
        project = specs / "P01_Test_Project"
        cases = project / "use_cases"
        cases.mkdir(parents=True)
        (project / "README.md").write_text(README, encoding="utf-8")
        (cases / "TEST-01_First.md").write_text(USE_CASE, encoding="utf-8")

        profiles = root / "technology_profiles"
        profiles.mkdir()
        for language in build_prompts.SUPPORTED_LANGUAGES:
            (profiles / f"{language}.md").write_text(
                f"# {language} Technology Profile\n\nUse {language}.\n",
                encoding="utf-8",
            )

        prompt_sources = root / "prompt_sources"
        prompt_sources.mkdir()
        system = prompt_sources / "system.md"
        template = prompt_sources / "template.md"
        system.write_text(SYSTEM, encoding="utf-8")
        template.write_text(TEMPLATE, encoding="utf-8")
        return argparse.Namespace(
            spec_root=specs,
            profiles_root=profiles,
            system_prompt=system,
            user_prompt_template=template,
            languages=None,
            expected_projects=1,
            output=root / "generated",
            check=False,
            package_root=root,
        )

    def test_build_writes_four_matched_prompts(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            args = self.make_tree(Path(directory))
            manifest = build_prompts.build(args)

            self.assertEqual(manifest["project_count"], 1)
            self.assertEqual(manifest["prompt_count"], 4)
            self.assertEqual(manifest["distinct_use_case_count"], 1)
            for language in build_prompts.SUPPORTED_LANGUAGES:
                direct = (
                    args.output
                    / language
                    / "P01_Test_Project"
                    / "LLM_input_prompt.md"
                )
                self.assertTrue(direct.is_file())
                text = direct.read_text(encoding="utf-8")
                self.assertIn(f"# {language} Technology Profile", text)
                self.assertIn("TEST-01", text)
                self.assertNotIn("CVE-2024-1234", text)
                self.assertNotIn("| Alignment language |", text)
                self.assertNotIn("Planned test level", text)

    def test_messages_are_role_separated(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            args = self.make_tree(Path(directory))
            build_prompts.build(args)
            messages_path = (
                args.output / "Go" / "P01_Test_Project" / "messages.json"
            )
            messages = json.loads(messages_path.read_text(encoding="utf-8"))
            self.assertEqual([item["role"] for item in messages], ["system", "user"])

    def test_check_mode_does_not_write_output(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            args = self.make_tree(Path(directory))
            args.check = True
            manifest = build_prompts.build(args)
            self.assertEqual(manifest["mode"], "check")
            self.assertFalse(args.output.exists())

    def test_language_aliases_and_subset(self) -> None:
        self.assertEqual(
            build_prompts.resolve_languages(["js", "golang", "py", "php"]),
            ["JavaScript", "Go", "Python", "PHP"],
        )
        with self.assertRaises(build_prompts.BuildError):
            build_prompts.resolve_languages(["ruby"])

    def test_template_requires_all_placeholders_once(self) -> None:
        with self.assertRaises(build_prompts.BuildError):
            build_prompts.validate_template(
                "{LANGUAGE_PROFILE}\n{PROJECT_SPECIFICATION}\n"
            )

    def test_duplicate_use_case_id_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            args = self.make_tree(Path(directory))
            cases = args.spec_root / "P01_Test_Project" / "use_cases"
            (cases / "TEST-01_Duplicate.md").write_text(USE_CASE, encoding="utf-8")
            with self.assertRaises(build_prompts.BuildError):
                build_prompts.build(args)

    def test_expected_project_count_is_enforced(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            args = self.make_tree(Path(directory))
            args.expected_projects = 16
            with self.assertRaises(build_prompts.BuildError):
                build_prompts.build(args)


if __name__ == "__main__":
    unittest.main()
