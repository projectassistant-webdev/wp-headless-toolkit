# Changelog

All notable changes to the Prism Framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

**This file is auto-generated from git commits using conventional commits.**

To update: `./scripts/docs/generate-changelog.sh`

---

## [2.68.0] - 2026-02-11

### Features

-  Re-enable pytest-xdist parallel execution (-n auto)
-  Add blog-image-gen skill for AI blog featured images
-  Expand /test-optimize skill from 6 to 10 dimensions
- **REQ-PRISM-075**:  Phase 3 - Rollout integration with schema migrations
- **REQ-PRISM-075**:  Phase 2 - Schema versioning infrastructure
- **REQ-PRISM-075**:  Phase 1 - Expert visibility logging fix
-  Infrastructure cleanup bundle (REQ-PRISM-073)
-  Create pipeline-ssh-setup skill for Bitbucket-Cloudways SSH automation
-  Rewrite deploy-cloudways skill to use cloudways CLI
-  Enhance refactoring agent and skill with Codex integration
-  Add test-optimize skill definition and update SKILL_INDEX
-  Implement test_optimization analysis engine
-  Implement context7_docs module with TDD tests
-  Add context7_library_map with TDD tests

### Bug Fixes

-  Add web/.htaccess to Bedrock Capistrano linked_files documentation
-  Un-skip TestSchemaMigration tests and fix stale version assertion
-  Un-skip CLI tests and fix test fixture for pipeline scripts
-  Replace os.chdir() with monkeypatch.chdir() across 10 test files
-  Add defensive key reconstruction in read_pipeline_state() to prevent data loss
-  Make blog-image-gen fully project-agnostic
-  Remove project-specific examples from blog-image-gen skill
-  repo name regex fails on names with dots (e.g. andersonswim.com)
-  Replace deprecated find_module with sys.modules sentinel in import test
-  Isolate TestRequireVisual from filesystem state and selector inventory
-  Cloudways skill per-environment SSH user + gitignore tools/
-  Cloudways skill Docker exception + fix test_file_utils permission assertion
-  Handle rsync exit 23 gracefully, exclude .prism-manifest.json
-  Overhaul create-project.sh exclusions and post-copy processing
-  Prevent 600 permissions on atomic writes (ACL inheritance)
- **lint**:  Add missing os import in background_task_manager.py
- **codex**:  Remove kill 0 from EXIT trap causing exit code 144
-  Eliminate false positive database migration detection in PR descriptions
-  Address REQ-PRISM-075 audit feedback (H1-H3, M1-M8)
-  Remove -n auto from pytest.ini (pytest-xdist not installed)
-  Use has_failures boolean instead of tests_failed int/string in test-pass marker check
-  Reword AC-16 to allow corrective flag replacements per Section 2.1
-  Address audit feedback for REQ-PRISM-074
-  Update tests after removing Nexus env var propagation
-  Address audit findings in REQ-PRISM-073 requirements and runbook
-  Correct Step 5 pipe syntax and error handling in pipeline-ssh-setup skill
-  Address audit findings in REQ-PRISM-072 requirements and runbook
-  Update refactoring agent version tests to match v4.0.0
-  Address audit findings in deploy-cloudways skill
-  Replace git checkout rollback with safe git revert pattern in runbook
-  Resolve merge conflict in CHANGELOG.md
-  Address Phase 2 audit issues (shlex.split, docker_compose_path, parse output logging)
-  Use app-scoped paths in Capistrano templates
-  Address Phase 1 audit issues (get_library_ids exception handling, atomic write cleanup)
-  Auto-stage selector inventories during PR creation

### Documentation

-  Add Capistrano linked files and .htaccess 404 fix to Bedrock skill
-  Consolidate roadmap with phased Opus 4.6, codebase health, and learning loop plan
- **REQ-PRISM-076**:  Address all audit findings for requirements and runbook
-  Add Opus 4.6 enhancement strategy, learning loop architecture, and audit reports
- **REQ-PRISM-076**:  Add requirements and runbook for codebase health Phase 1
-  Update README agent/skill counts (35 agents, 61 skills)
-  Update changelog and skills system doc for Cloudways skill changes
-  Update codex integration doc date for exit trap fix
-  Update documentation for test-optimize 10 dimensions and PR fix
-  Update README.md version to 2.64.3
-  Update feature docs for REQ-PRISM-075
- **REQ-PRISM-075**:  Add strategy document for expert visibility and rollout schema versioning
-  Update CHANGELOG.md for REQ-PRISM-075
- **REQ-PRISM-075**:  Add requirements and runbook for expert visibility and rollout schema versioning
-  Update README.md version to 2.64.2
-  Add tech debt cleanup bundle strategy document
-  Clean up TECH-DEBT.md and ROADMAP.md - close resolved items, move completed work
- **REQ-PRISM-074**:  Improve sync and cloudways skill documentation
- **REQ-PRISM-074**:  Add requirements and runbook for sync and cloudways skill improvements
-  Add sync and cloudways skill improvement reports
-  Update changelog, README counts, features doc, and strategy for REQ-PRISM-073
-  cloudways skill vs cloudways-api integration gap analysis
- **REQ-PRISM-073**:  Add requirements and runbook for infrastructure cleanup bundle
- **REQ-PRISM-072**:  Add requirements and runbook for pipeline-ssh-setup skill
-  Update agent count to 35 in README
-  Update changelog and feature docs for REQ-PRISM-071
- **REQ-PRISM-071**:  Add requirements and runbook for deploy-cloudways skill improvement
-  Add webroot and db-sync to deploy-cloudways skill scope
-  Update changelog, README counts, and feature docs for refactoring skill enhancement
-  Update feature docs and README for REQ-PRISM-069
-  add deploy-cloudways skill improvement strategy and update ROADMAP
-  Update changelog for REQ-PRISM-069
-  Add pre-push hook Docker path mapping bug report
- **REQ-PRISM-069**:  Address audit findings in requirements and runbook
- **REQ-PRISM-069**:  Add requirements and runbook for Context7 expert agent docs + test optimization skill
-  Add strategy for Context7 expert agent docs + test optimization skill

### Refactoring

-  Move blog image design files to templates/

### Tests

-  Add tests for test_optimization analysis engine

### Maintenance

-  Add __init__.py to tests/unit/ and tests/docs/
-  Rename kebab-case Python files to snake_case
-  Remove pytest.ini, consolidate config in pyproject.toml
-  Delete duplicate can-parallelize.py and update references
-  Remove unused ROADMAP.md.template
-  Bump version (patch)
-  Bump version (patch)
-  Bump version (minor)
-  Bump version (minor)
-  Remove unused tempfile import in test_config_migrations.py
-  Bump version (patch)
-  Bump version (patch)
-  Bump version (patch)
-  Bump version (minor)
-  Stop propagating Nexus env vars to child projects
-  Stop propagating Nexus env vars to child projects
-  Bump version (minor)
-  Bump version (minor)
-  Bump version (minor)
-  Bump version (minor)
-  Add new test files to taxonomy.yml

### Code Style

-  Rename blog image templates to match conventions

---

## [v2.60.1] - 2026-02-07

-  Add X-Sync-API-Key auth header to Nexus notification calls
-  Bump version (patch)
-  Skip readonly dir test when running as root (CI compatibility)
-  Bump version (minor)
-  Update version references to 2.59.0
-  Bump version (minor)
-  Update changelog, roadmap, and feature docs for REQ-PRISM-068
-  Add pipeline SSH setup strategy and Capistrano templates
-  Close path validation gaps in remediation state functions (M-1, M-2)
-  Add Nexus-specific strategy for Prism remote integration

---

## [v2.33.1] - 2026-01-31

-  Add X-Sync-API-Key auth header to Nexus notification calls
-  Bump version (patch)
-  Skip readonly dir test when running as root (CI compatibility)
-  Bump version (minor)
-  Update version references to 2.59.0
-  Bump version (minor)
-  Update changelog, roadmap, and feature docs for REQ-PRISM-068
-  Add pipeline SSH setup strategy and Capistrano templates
-  Close path validation gaps in remediation state functions (M-1, M-2)
-  Add Nexus-specific strategy for Prism remote integration
-  Update documentation for remediation wiring (Phase 4)
-  Create idle-remediation.md Orchestrator rules (Phase 4)
-  Add create_fix_branch() to pipeline_manager.py (Phase 4)
-  Address 4 audit findings for REQ-PRISM-068 Phase 3
-  Add CI monitor metadata write and register remediation tests
-  Add remediation state functions to pipeline_manager.py (TDD GREEN)
-  Add pipeline manager remediation tests (TDD RED)
-  Register test_pre_branch_guard_ci_health.py in taxonomy.yml
-  Add exit code 4 for post-merge CI failure in auto-progress.sh (TDD GREEN)
-  Add exit code 4 tests for post-merge CI failure (TDD RED)

---

## [v2.33.0] - 2026-01-30

-  Bump version (patch)
-  Strip PR_META comment from PR description before API call
-  Auto-populate PR summary and add staleness validation
-  Bump version to 2.34.0 for FIX-052 Codex enforcement
-  Add Codex enforcement documentation
-  Implement Phase 3 - Integration tests and documentation
-  Implement Phase 2 - enforce-codex-audit.sh and orchestrator rules
-  Implement Phase 1 - Codex enforcement in preflight gate
-  Correct bash heredoc syntax and add infrastructure script policy
-  Address audit findings for codex audit enforcement requirements
-  Add requirements and runbook for Codex audit enforcement
-  Update strategy with existing infrastructure findings
-  Add strategy for Codex audit enforcement
-  Exclude backup files from being staged
-  Add multi-service Docker routing strategy
-  Add PR template validation consolidation strategy

---

## [v2.29.1] - 2026-01-29

-  Add subproject support items to roadmap
-  Add ad-hoc mode learning gap report and roadmap item
-  Update changelog and README for rollout config safety
-  Bump version (minor)
-  Add automatic project-config backup and verification during rollout
-  Bump version (minor)
-  Update changelog and feature docs for Nexus direct sync
-  Enable direct Nexus sync from child projects
-  Apply consistent line formatting across prism scripts
-  Bump version (minor)
-  Update feature docs with config validation changes
-  Update CHANGELOG.md for FIX-046
-  Add strategy document and fix test lint issues
-  Add pre-commit hook for project config validation (Phase 4)
-  add --refresh-config flag to prism-rollout.sh
-  Implement Phase 2 - Config Validation & Migration
-  Add backward compatibility for get_docker_path() legacy keys
-  Address audit findings for requirements and runbook
-  Add requirements and runbook for project config validation
-  Add roadmap items for Docker Model Runner, GLM-4.7, and strict audit scoring

---

## [v2.26.2] - 2026-01-29

-  Bump version (patch)
-  Increase rollout integration test timeouts to 120s
-  Bump version (minor)
-  Bump version (patch)
-  Mock detect_tech_stack in test_frontend_check_conditional
-  Bump version (minor)
-  Sync README.md version and counts
-  Add LLM Review Configuration documentation (REQ-PRISM-026)
-  Update CHANGELOG for FIX-040 LLM Review Config
-  Add LLM Review Config support to Phase 5 scripts
-  Implement Phase 4 - Update security & performance scripts with LLM review config
-  Add Phases 4-5 for remaining audit scripts
-  Update codex-consistency-check.sh with config-driven exclusions (Phase 3)
-  Search up to 3 levels deep for docker-compose.yml
-  Update quality gate runner to use LLM review config (Phase 2)
-  Add LLM review configuration module (Phase 1)
-  Add Strategy→Codex file targeting improvement item
-  Address audit findings for LLM review config requirements
-  Add requirements and runbook for LLM review configuration
-  Bump version (minor)

---

## [v2.16.1] - 2026-01-12

-  Bump version (patch)
-  Auto-generate PR description template when missing
-  Bump version (patch)
-  Update documentation and README counts
-  Add missing mocks for create_pipeline() dependencies in atomic_branch_creation tests
-  Use code hash for test-pass marker validation
-  Bump version (minor)
-  Sync README counts for v2.24.0
-  Touch feature docs for REQ-PRISM-023
-  Update feature documentation and changelog for REQ-PRISM-023
-  Wire project_root through detect_mode helpers
-  Implement Phase 6 - SCOPED_PROJECT Integration (REQ-PRISM-023)
-  Trust test-pass marker instead of re-running full suite
-  Implement Phase 5 - Proactive Mode Detection (REQ-PRISM-023)
-  Enforce docker-first policy for test execution
-  Add Python 3.9 compatibility with __future__ annotations
-  Implement Phase 4 - Orchestrator Enforcement (REQ-PRISM-023)
-  Add requirements context loading for Testing Agent (Phase 3)
-  Implement Phase 2 - Automated Remediation Trigger (REQ-PRISM-023)
-  Add requirements_docs tracking to pipeline state (Phase 1)

---

## [v2.10.0] - 2026-01-11

-  Update prism-pipeline-automation.md with frontend recon enforcement
-  Add frontend recon enforcement to PR quality gates
-  Add missing test failure parameters to capture_issue()
-  Sync README version to 2.16.1
-  Remove unused imports and fix TYPE_CHECKING block
-  Remove auto-regenerate from changelog pre-commit hook
-  Add test failure discovery integration to feature docs
-  Update CHANGELOG and README for test failure discovery integration
-  Add Phase 3 - Testing Agent Update & CI/CD Integration (REQ-TFD-005, REQ-TFD-006)
-  Add test failure discovery integration (Phase 2)
-  Add failure classifier for test failure discovery (REQ-TFD-001)
-  Add requirements and runbook for test failure discovery integration
-  Add strategy document for test failure discovery integration
-  Add changelog entry for rollout test fix
-  Correct test extraction logic for cleanup function
-  Add .prism/learnings/ to gitignore and untrack files
-  Add Python 3.9 compatibility for UTC import (cherry-pick)
-  Reset manifest to remove test artifact entries
-  Add lint fix to feature changelog
-  Update changelog and feature docs for lint fix PR

---

## [v2.5.2] - 2026-01-25

*Tag release*

---

## [v2.4.0] - 2026-01-15

-  Add shellcheck to CI pipeline apt-get install
-  Skip shellcheck test if shellcheck not installed
-  Update project registry to JSON and update script
-  Add JSON project registry for Prism rollouts
-  Add noqa: E402 for rollout compatibility
-  Use target project's scripts for generators
-  Capture ALL test failures to block PRs
-  Update feature doc dates for pipeline quality enhancements
-  Update CHANGELOG and README skill count
-  Add shellcheck to Docker image for test validation
-  Add unified strategy document for pipeline quality enhancements
-  Add discovery phase integration to pipeline
-  Add lint check before agent commits
-  Add consolidated RUNBOOK for pipeline quality enhancements
-  Clean up ROADMAP and close completed TECH-DEBT items
-  Add /pytest-optimize and /rollout skills, update ROADMAP with completed items
-  Add async agent monitoring protocol and command failure tracking
-  Add PR hard gate enforcement
-  Pipeline resilience and auto-recovery
-  Add orchestrator behavioral standards enforcement

---

## [v2.3.1] - 2026-01-07

-  Bump version to 2.3.0
-  Complete PIPELINE_STAGES.md with missing sections
-  Resolve shellcheck validation syntax error in prism-rollout.sh
-  Add shellcheck SC2034 directives to remaining rollout scripts
-  Merge staging and improve rollout script
-  Update CHANGELOG.md for v2.17.0
-  Add verify_pipeline_checkpoints.py to feature docs and changelog
-  Integrate hard enforcement into PR workflow
-  Add hard enforcement gates and optimize CLAUDE.md
-  Remove CLAUDE.project.md from .gitignore
-  Bump version to 2.16.2
-  Note skill update in prism-skills-system.md
-  Regenerate changelog
-  Replace PLANNING.md references with ROADMAP.md
-  Update README with WordPress agent and skill counts
-  Update changelog and skills documentation for WordPress agent
-  Add WordPress agent with theme and plugin skills
-  Add .prism/discovery/ to gitignore
-  Add REQ maturity system for pre-existing requirements
-  Add comprehensive pipeline preparation guide

---

## Additional Documentation

- **Upgrade Guides**: See `docs/UPGRADE-GUIDE.md`
- **Version Comparison**: See `docs/VERSION-COMPARISON.md`
- **Support**: https://bitbucket.org/projectassistant/prism/issues

---

**Current Version**: v2.68.0
**Repository**: https://bitbucket.org/projectassistant/prism
**Generated**: 2026-02-11
