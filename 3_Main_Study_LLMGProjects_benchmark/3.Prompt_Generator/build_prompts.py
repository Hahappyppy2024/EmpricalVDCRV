#!/usr/bin/env python3
"""Build matched project-generation prompts from canonical Version A specs.

One language-neutral project specification is combined with each authoritative
Technology profile. This produces one prompt per project-language pair without
duplicating or mutating the canonical business use cases.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path


LANGUAGE_PROFILE_PLACEHOLDER = "{LANGUAGE_PROFILE}"
PROJECT_PLACEHOLDER = "{PROJECT_SPECIFICATION}"
USE_CASE_PLACEHOLDER = "{USE_CASE_SPECIFICATIONS}"
PLACEHOLDERS = (
    LANGUAGE_PROFILE_PLACEHOLDER,
    PROJECT_PLACEHOLDER,
    USE_CASE_PLACEHOLDER,
)
SUPPORTED_LANGUAGES = ("JavaScript", "PHP", "Python", "Go")
LANGUAGE_ALIASES = {
    "javascript": "JavaScript",
    "js": "JavaScript",
    "node": "JavaScript",
    "nodejs": "JavaScript",
    "php": "PHP",
    "python": "Python",
    "py": "Python",
    "go": "Go",
    "golang": "Go",
}
PROJECT_PATTERN = re.compile(r"^P\d{2}_.+")
USE_CASE_ID_PATTERN = re.compile(r"(?<![A-Z0-9])([A-Z]+-\d{2})(?!\d)")
PROJECT_NUMBER_PATTERN = re.compile(r"^P(\d{2})_")
FORBIDDEN_GENERATION_PATTERNS = (
    re.compile(r"\bCVE-\d{4}-\d+\b", re.IGNORECASE),
    re.compile(r"\bCWE-\d+\b", re.IGNORECASE),
)


class BuildError(RuntimeError):
    """Raised when the prompt inputs violate the deterministic contract."""


@dataclass(frozen=True)
class SourceFile:
    path: Path
    text: str
    sha256: str


def sha256_text(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


def normalize_newlines(text: str) -> str:
    return text.replace("\r\n", "\n").replace("\r", "\n").strip() + "\n"


def read_source(path: Path, label: str) -> SourceFile:
    if not path.is_file():
        raise BuildError(f"Missing {label}: {path}")
    try:
        text = normalize_newlines(path.read_text(encoding="utf-8"))
    except UnicodeDecodeError as exc:
        raise BuildError(f"{label} is not valid UTF-8: {path}") from exc
    if not text.strip():
        raise BuildError(f"{label} is empty: {path}")
    return SourceFile(path=path, text=text, sha256=sha256_text(text))


def natural_key(value: Path | str) -> tuple[object, ...]:
    name = value.name if isinstance(value, Path) else value
    return tuple(
        int(part) if part.isdigit() else part.casefold()
        for part in re.split(r"(\d+)", name)
    )


def canonical_language(value: str) -> str | None:
    compact = re.sub(r"[^a-z]", "", value.casefold())
    return LANGUAGE_ALIASES.get(compact)


def resolve_languages(values: list[str] | None) -> list[str]:
    if not values:
        return list(SUPPORTED_LANGUAGES)
    result: list[str] = []
    for value in values:
        language = canonical_language(value)
        if language is None:
            raise BuildError(
                f"Unsupported language {value!r}; choose from "
                + ", ".join(SUPPORTED_LANGUAGES)
            )
        if language not in result:
            result.append(language)
    return result


def discover_projects(spec_root: Path) -> list[Path]:
    if not spec_root.is_dir():
        raise BuildError(f"Specification root is not a directory: {spec_root}")
    projects = sorted(
        (
            path
            for path in spec_root.iterdir()
            if path.is_dir() and PROJECT_PATTERN.fullmatch(path.name)
        ),
        key=natural_key,
    )
    if not projects:
        raise BuildError(f"No Pxx project directories found under {spec_root}")

    numbers: list[int] = []
    for path in projects:
        match = PROJECT_NUMBER_PATTERN.match(path.name)
        assert match is not None
        numbers.append(int(match.group(1)))
    if len(numbers) != len(set(numbers)):
        raise BuildError("Duplicate Pxx project numbers are not allowed")
    return projects


def discover_profiles(profile_root: Path, languages: list[str]) -> dict[str, SourceFile]:
    profiles: dict[str, SourceFile] = {}
    for language in languages:
        source = read_source(profile_root / f"{language}.md", f"{language} profile")
        expected_heading = f"# {language} Technology Profile".casefold()
        if expected_heading not in source.text.casefold():
            raise BuildError(
                f"{source.path} does not contain the expected heading "
                f"'# {language} Technology Profile'"
            )
        profiles[language] = source
    return profiles


def discover_use_cases(project_dir: Path) -> list[SourceFile]:
    use_case_dir = project_dir / "use_cases"
    if not use_case_dir.is_dir():
        raise BuildError(f"Missing use_cases directory: {use_case_dir}")
    paths = sorted(use_case_dir.glob("*.md"), key=natural_key)
    if not paths:
        raise BuildError(f"No Markdown use cases found in {use_case_dir}")

    sources = [read_source(path, "use-case file") for path in paths]
    seen: set[str] = set()
    for source in sources:
        match = USE_CASE_ID_PATTERN.search(source.path.stem)
        if not match:
            raise BuildError(f"Use-case filename has no ID: {source.path.name}")
        use_case_id = match.group(1)
        if use_case_id in seen:
            raise BuildError(f"Duplicate use-case ID {use_case_id} in {project_dir.name}")
        if use_case_id not in source.text[:600]:
            raise BuildError(
                f"Use-case ID {use_case_id} is not present near the top of "
                f"{source.path.name}"
            )
        seen.add(use_case_id)
    return sources


def strip_alignment_language_row(line: str) -> bool:
    return bool(
        re.match(
            r"^\|\s*(Alignment language|Target language)\s*\|",
            line,
            flags=re.IGNORECASE,
        )
    )


def normalize_project_readme(text: str) -> str:
    """Keep business/alignment context while removing generation-conflicting metadata."""
    result: list[str] = []
    for line in text.splitlines():
        lowered = line.casefold()
        if strip_alignment_language_row(line):
            continue
        if re.match(r"^\|\s*Evidence status\s*\|", line, flags=re.IGNORECASE):
            continue
        if any(pattern.search(line) for pattern in FORBIDDEN_GENERATION_PATTERNS):
            continue
        if "can be used to generate javascript, php, or python implementations" in lowered:
            line = re.sub(
                r"These specifications are language-neutral and can be used to generate "
                r"JavaScript, PHP, or Python implementations\.?",
                "These specifications are language-neutral. The authoritative Technology "
                "profile in this prompt selects the implementation language and stack.",
                line,
                flags=re.IGNORECASE,
            )
        if lowered.startswith("do not add security labels or cwe names"):
            continue
        if lowered.startswith("security-oriented labels should be handled separately"):
            continue
        result.append(line)
    normalized = normalize_newlines("\n".join(result))
    assert_generation_boundary(normalized, "normalized project README")
    return normalized


def normalize_use_case(text: str) -> str:
    """Remove external-language and benchmark-test metadata from one use case."""
    result: list[str] = []
    in_acceptance_table = False
    for line in text.splitlines():
        stripped = line.strip()
        lowered = stripped.casefold()
        if strip_alignment_language_row(line):
            continue
        if any(pattern.search(line) for pattern in FORBIDDEN_GENERATION_PATTERNS):
            continue
        if lowered.startswith("do not include cve identifiers"):
            continue

        if stripped == "| Test ID | Expected behavior | Planned test level |":
            result.append("| Criterion ID | Expected behavior |")
            in_acceptance_table = True
            continue
        if in_acceptance_table and stripped == "| --- | --- | --- |":
            result.append("| --- | --- |")
            continue
        if in_acceptance_table:
            if stripped.startswith("## "):
                in_acceptance_table = False
            elif stripped.startswith("|"):
                cells = [cell.strip() for cell in stripped.strip("|").split("|")]
                if len(cells) >= 2:
                    result.append(f"| {cells[0]} | {cells[1]} |")
                    continue
        result.append(line)

    normalized = "\n".join(result)
    normalized = normalized.replace("Test ID", "Criterion ID")
    normalized = normalized.replace("Planned test level", "Acceptance basis")
    normalized = normalize_newlines(normalized)
    assert_generation_boundary(normalized, "normalized use case")
    return normalized


def assert_generation_boundary(text: str, label: str) -> None:
    for pattern in FORBIDDEN_GENERATION_PATTERNS:
        match = pattern.search(text)
        if match:
            raise BuildError(f"{label} contains forbidden identifier: {match.group(0)}")


def validate_template(template: str) -> None:
    for placeholder in PLACEHOLDERS:
        count = template.count(placeholder)
        if count != 1:
            raise BuildError(
                f"User template must contain {placeholder} exactly once; found {count}"
            )


def join_use_cases(use_cases: list[SourceFile]) -> tuple[str, list[dict[str, str]]]:
    sections: list[str] = []
    metadata: list[dict[str, str]] = []
    for source in use_cases:
        normalized = normalize_use_case(source.text)
        sections.append(f"<!-- Source: {source.path.name} -->\n\n{normalized.strip()}")
        metadata.append(
            {
                "file": source.path.name,
                "source_sha256": source.sha256,
                "normalized_sha256": sha256_text(normalized),
            }
        )
    return "\n\n---\n\n".join(sections).strip() + "\n", metadata


def build_user_prompt(
    template: str,
    language_profile: str,
    project_spec: str,
    use_cases: str,
) -> str:
    result = template.replace(LANGUAGE_PROFILE_PLACEHOLDER, language_profile.strip())
    result = result.replace(PROJECT_PLACEHOLDER, project_spec.strip())
    result = result.replace(USE_CASE_PLACEHOLDER, use_cases.strip())
    result = normalize_newlines(result)
    assert_generation_boundary(result, "generated user prompt")
    return result


def build_direct_prompt(
    language: str, project_name: str, system_prompt: str, user_prompt: str
) -> str:
    return (
        f"# Direct LLM Input Prompt — {language} — {project_name}\n\n"
        "Copy this entire document into the LLM for one project-generation run.\n\n"
        "---\n\n"
        "## System Prompt\n\n"
        f"{system_prompt.strip()}\n\n"
        "---\n\n"
        "## User Prompt\n\n"
        f"{user_prompt.strip()}\n"
    )


def write_text(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8", newline="\n")


def write_json(path: Path, value: object) -> None:
    write_text(path, json.dumps(value, indent=2, ensure_ascii=False) + "\n")


def relative_path(path: Path, root: Path) -> str:
    try:
        return path.resolve().relative_to(root.resolve()).as_posix()
    except ValueError:
        return path.resolve().as_posix()


def build(args: argparse.Namespace) -> dict[str, object]:
    spec_root = args.spec_root.resolve()
    profile_root = args.profiles_root.resolve()
    languages = resolve_languages(args.languages)
    projects = discover_projects(spec_root)
    profiles = discover_profiles(profile_root, languages)
    system = read_source(args.system_prompt.resolve(), "system prompt")
    template = read_source(args.user_prompt_template.resolve(), "user-prompt template")
    validate_template(template.text)
    assert_generation_boundary(system.text, "system prompt")
    assert_generation_boundary(template.text, "user-prompt template")

    if args.expected_projects is not None and len(projects) != args.expected_projects:
        raise BuildError(
            f"Expected {args.expected_projects} projects but discovered {len(projects)}"
        )

    output_root = args.output.resolve()
    manifest_projects: list[dict[str, object]] = []
    distinct_use_cases = 0
    prompt_count_by_language = {language: 0 for language in languages}

    for project_dir in projects:
        readme = read_source(project_dir / "README.md", "project README")
        project_spec = normalize_project_readme(readme.text)
        use_case_sources = discover_use_cases(project_dir)
        joined_use_cases, use_case_metadata = join_use_cases(use_case_sources)
        distinct_use_cases += len(use_case_sources)
        business_spec = project_spec.strip() + "\n\n" + joined_use_cases.strip() + "\n"
        business_spec_sha256 = sha256_text(business_spec)

        implementations: list[dict[str, object]] = []
        for language in languages:
            profile = profiles[language]
            user_prompt = build_user_prompt(
                template.text, profile.text, project_spec, joined_use_cases
            )
            direct_prompt = build_direct_prompt(
                language, project_dir.name, system.text, user_prompt
            )
            messages = [
                {"role": "system", "content": system.text},
                {"role": "user", "content": user_prompt},
            ]
            messages_text = json.dumps(messages, indent=2, ensure_ascii=False) + "\n"
            project_output = output_root / language / project_dir.name

            if not args.check:
                write_text(project_output / "LLM_input_prompt.md", direct_prompt)
                write_text(project_output / "system_prompt.md", system.text)
                write_text(project_output / "user_prompt.md", user_prompt)
                write_text(project_output / "messages.json", messages_text)

            prompt_count_by_language[language] += 1
            implementations.append(
                {
                    "language": language,
                    "technology_profile": relative_path(profile.path, args.package_root),
                    "technology_profile_sha256": profile.sha256,
                    "user_prompt_sha256": sha256_text(user_prompt),
                    "direct_prompt_sha256": sha256_text(direct_prompt),
                    "messages_sha256": sha256_text(messages_text),
                }
            )

        manifest_projects.append(
            {
                "project": project_dir.name,
                "project_readme": relative_path(readme.path, spec_root),
                "project_readme_source_sha256": readme.sha256,
                "project_readme_normalized_sha256": sha256_text(project_spec),
                "business_spec_sha256": business_spec_sha256,
                "use_case_count": len(use_case_sources),
                "use_cases": use_case_metadata,
                "implementations": implementations,
            }
        )

    prompt_count = len(projects) * len(languages)
    manifest: dict[str, object] = {
        "schema_version": "1.0",
        "generated_at_utc": datetime.now(timezone.utc).isoformat(),
        "mode": "check" if args.check else "build",
        "design": "canonical_version_a_x_authoritative_language_profiles",
        "project_count": len(projects),
        "languages": languages,
        "language_count": len(languages),
        "prompt_count": prompt_count,
        "prompts_by_language": prompt_count_by_language,
        "distinct_use_case_count": distinct_use_cases,
        "language_use_case_instances": distinct_use_cases * len(languages),
        "system_prompt_sha256": system.sha256,
        "user_prompt_template_sha256": template.sha256,
        "projects": manifest_projects,
    }
    if not args.check:
        write_json(output_root / "prompt_manifest.json", manifest)
    return manifest


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    package_root = Path(__file__).resolve().parent
    parser = argparse.ArgumentParser(
        description="Build matched JavaScript/PHP/Python/Go prompts from canonical specs."
    )
    parser.add_argument(
        "--spec-root",
        type=Path,
        default=package_root / "source_specs",
        help="Canonical root containing Pxx_Project/README.md and use_cases/*.md.",
    )
    parser.add_argument(
        "--profiles-root",
        type=Path,
        default=package_root / "technology_profiles",
    )
    parser.add_argument(
        "--system-prompt",
        type=Path,
        default=package_root / "prompt_sources" / "system_prompt.md",
    )
    parser.add_argument(
        "--user-prompt-template",
        type=Path,
        default=package_root / "prompt_sources" / "user_prompt_template.md",
    )
    parser.add_argument(
        "--languages",
        nargs="*",
        help="Subset of JavaScript PHP Python Go; defaults to all four.",
    )
    parser.add_argument(
        "--expected-projects",
        type=int,
        default=16,
        help="Fail unless this many canonical projects are found (default: 16).",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=package_root / "generated_prompts_v1",
    )
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args(argv)
    args.package_root = package_root
    return args


def main(argv: list[str] | None = None) -> int:
    args = parse_args(argv)
    try:
        manifest = build(args)
    except BuildError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2
    print(json.dumps(manifest, indent=2, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
