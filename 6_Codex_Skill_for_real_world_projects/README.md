# Reusable Skills for Project-Level Vulnerability Management

This repository provides three reusable LLM skills for evaluating project-level vulnerability detection, executable confirmation, repair, and verification under different levels of vulnerability guidance. The skills are project-independent: they do **not** embed PrestaShop-specific CVEs, known vulnerable locations, or patches. PrestaShop 8.1.0 is one example application, not a requirement.

## 1\. Experimental conditions

|Condition|Skill directory|Input guidance|Fixed knowledge|
|-|-|-|-|
|**U — Unguided**|`unguided-vulnerability-management`|Current project only|None|
|**C — CWE-ID Guided**|`cwe-vulnerability-management`|Current project + explicitly supplied CWE identifiers|CWE IDs only|
|**CK — CWE-Knowledge Guided**|`cwe-knowledge-guided-vulnerability-management`|Current project + embedded 2025 CWE Top 25 knowledge|`references/CWE\\\_Top25\\\_Knowledge.txt`|

For the PrestaShop case study, **C** uses the complete **MITRE 2025 CWE Top 25** as its predefined screening set. **CK** uses the same 25 CWE identifiers with fixed definitions, manifestations, specificity notes, and inspection guidance. Do not substitute a project-specific CWE shortlist. The **C** skill expects the CWE identifiers to be explicitly supplied in the run prompt; the **CK** skill reads its embedded knowledge file.

The skills implement the same high-level workflow:

```text
Clean vulnerable project
  -> Candidate vulnerability detection
  -> VCT construction (exploit test + benign control)
  -> Baseline confirmation
  -> Repair CONFIRMED vulnerability instances only
  -> Functional tests + post-repair VCT verification
  -> Save results for independent evaluation
```

A CWE is a weakness *category*, not a concrete vulnerability. Multiple independent vulnerabilities can have the same CWE.

## 2\. Installation

Unzip each skill and place the **skill folder** under the current project's `.codex/skills/` directory:

```text
PROJECT\\\_ROOT/
├── .codex/
│   └── skills/
│       ├── unguided-vulnerability-management/
│       │   ├── SKILL.md
│       │   └── references/
│       ├── cwe-vulnerability-management/
│       │   ├── SKILL.md
│       │   └── references/
│       └── cwe-knowledge-guided-vulnerability-management/
│           ├── SKILL.md
│           └── references/
│               └── CWE\\\_Top25\\\_Knowledge.txt
└── \\\[project files]
```

Use the **v2** archive of the CWE-Knowledge Guided skill, which contains the fixed Top-25 knowledge file. Open the project root in the Codex/LLM coding environment, and confirm that the skill names are recognized. Keep the complete `references/` folders: `SKILL.md` depends on them.

## 3\. Prepare independent experimental runs

1. Select and preserve an **unmodified vulnerable baseline** and record its source version or commit.
2. Create a separate clean copy or equivalent reproducible reset for **each condition**. Do not run C on code already changed by U, or CK on code already changed by C.
3. Install the skill files in each copy without modifying the evaluated application's source code.
4. Keep known CVEs, security advisories, patched releases, fixing commits, public exploit solutions, and ground-truth answer files **outside** the model-accessible project. Do not expose them in prompts or tool results.
5. Use the same model, environment, functional-test protocol, resource limits, and repair budget across conditions when comparing guidance levels. Record any deviations.
6. Archive reports, VCTs, functional-test logs, and repaired code **before** resetting or starting another run.

Example layout:

```text
experiment/
├── PrestaShop-8.1.0\\\_U/
├── PrestaShop-8.1.0\\\_C/
├── PrestaShop-8.1.0\\\_CK/
└── evaluation-ground-truth/   # separate; never model-accessible
```

## 4\. Run U — Unguided

Open the clean **U** project in the coding environment and send:

```text
Use $unguided-vulnerability-management on the current project.

Treat the current project as the baseline.
Perform the complete workflow defined by the skill.
Begin now.
```

Do **not** supply a CWE list, CVE IDs, known vulnerable components, or vulnerability descriptions. The model discovers candidate instances and assigns CWE classifications as outputs.

## 5\. Run C — CWE-ID Guided

Open the clean **C** project. For the PrestaShop case study, use the full **2025 CWE Top 25** (not a shortlist inferred from its known CVEs):

```text
Use $cwe-vulnerability-management on the current project.

Treat the current project as the baseline.
Target CWE types (MITRE 2025 Top 25):
CWE-79, CWE-89, CWE-352, CWE-862, CWE-787,
CWE-22, CWE-416, CWE-125, CWE-78, CWE-94,
CWE-120, CWE-434, CWE-476, CWE-121, CWE-502,
CWE-122, CWE-863, CWE-20, CWE-284, CWE-200,
CWE-306, CWE-918, CWE-77, CWE-639, CWE-770.

Treat these identifiers as screening guidance, not confirmed findings.
Perform the complete workflow defined by the skill.
Begin now.
```

This condition provides **CWE IDs only**, not extended CWE descriptions, known vulnerable files, CVE details, or patches.

## 6\. Run CK — CWE-Knowledge Guided

Open the clean **CK** project and send:

```text
Use $cwe-knowledge-guided-vulnerability-management on the current project.

Treat the current project as the baseline.
Use only the fixed CWE knowledge embedded in the skill.
Perform the complete workflow defined by the skill.
Begin now.
```

The skill reads `references/CWE\\\_Top25\\\_Knowledge.txt`. Do not paste additional vulnerability knowledge, replace the file between runs, or retrieve external sources to enrich it. Preserve the exact knowledge file in the replication package.

## 7\. Confirmation and repair rules

* Every concrete candidate must have a unique instance ID; CWE alone is not a unique ID.
* A **Vulnerability Confirmation Test (VCT)** consists of an **exploit test** and a **benign control**.
* Run confirmation against the **unmodified baseline**, before any production-code repair.
* **CONFIRMED:** the benign control works and the exploit reproducibly demonstrates the suspected weakness.
* **REJECTED:** executable evidence meaningfully contradicts the candidate hypothesis while legitimate behavior is established.
* **INCONCLUSIVE:** the evidence is insufficient (e.g., failed setup, unreachable path, broken benign control). Do not silently classify it as rejected.
* Repair **only confirmed** instances. Do not delete, weaken, or disable tests to manufacture a passing result.
* After repair, the benign control must still work, and the exploit must be **blocked for the intended security reason**. Crashes, unrelated errors, unavailable services, or failed test setup are not security passes.
* Run the required project functional tests as well as the frozen confirmed VCTs. Record failures and regressions, including those already present in the baseline.
* Claim **secure-and-correct** only if **all required functional tests pass and all confirmed VCTs pass**. VCT success alone is insufficient.

Follow the detailed attempt limits and file conventions in each installed `SKILL.md` and its `references/` files. If an experimental budget is imposed, keep it consistent and report it.

## 8\. What to preserve from each run

Archive at least:

|Artifact|Minimum content|
|-|-|
|Run metadata|Condition, model/version, project commit, prompts, skill version/hash, test environment|
|Detection report|Candidate instance IDs, inferred CWE, location, root cause, evidence|
|Baseline VCT log|Exploit and benign outcomes; confirmed/rejected/inconclusive status|
|Repair record|Modified files, attempts, candidate-to-fix mapping|
|Post-repair VCT log|Benign preservation and exploit blocking per confirmed instance|
|Functional-test log|Passed, failed, errors, skipped; baseline and post-repair comparison|
|Final summary|Candidates, confirmed, repaired, VCT passes, remaining failures|

The exact generated output paths depend on the installed skill definitions and project test framework. Preserve the files actually produced; do not assume all projects use the same test directory layout.

## 9\. Ground-truth evaluation (performed after runs)

For a historical-vulnerability case study such as PrestaShop 8.1.0, **ground truth must remain external until the run is finished**. Match confirmed model findings to documented CVEs at the **vulnerability-instance** level, using affected behavior, code path, and root cause. **Matching CWE IDs alone is insufficient.**

Record separately:

* **Recovered:** a confirmed finding matches a documented vulnerability.
* **Inconclusive:** a plausible corresponding candidate exists but executable confirmation is insufficient.
* **Not recovered:** no confirmed corresponding instance is established; distinguish absence of a candidate from inconclusive confirmation in detailed records.
* **Repaired:** a recovered instance passes post-repair VCT verification.
* **Additional finding:** a confirmed finding that does not match the documented CVE list; do **not** automatically label it a false positive without independent validation.

For PrestaShop, the external reference consists of five documented CVEs: `CVE-2023-39524`, `CVE-2023-39525`, `CVE-2023-39526`, `CVE-2023-39527`, and `CVE-2023-39529`. **These identifiers belong in the evaluator's records only, never in the model's run prompt.**

## 10\. Reproducibility checklist

* \[ ] All three conditions start from the same clean application baseline.
* \[ ] U receives no predefined CWE guidance.
* \[ ] C receives exactly the complete 2025 CWE Top 25 IDs.
* \[ ] CK uses the same 25 IDs plus the unchanged embedded knowledge file.
* \[ ] No condition receives project-specific CVE/patch/location ground truth.
* \[ ] All candidate instances have executable confirmation records.
* \[ ] Only confirmed instances are repaired.
* \[ ] Functional and security test outcomes are both retained.
* \[ ] Post-hoc CVE matching is independent of model execution.
* \[ ] All skills, prompts, test logs, and run metadata are archived.

## References

* MITRE, **2025 CWE Top 25 Most Dangerous Software Weaknesses**: https://cwe.mitre.org/top25/archive/2025/2025\_cwe\_top25.html
* PrestaShop project: https://www.prestashop-project.org/
* PrestaShop 8.1.1 security/maintenance release: https://build.prestashop-project.org/news/2023/prestashop-8-1-1-maintenance-release/

The references support study documentation and **post-hoc evaluation**. They are **not** instructions to browse project-specific advisories or patches during model execution.



