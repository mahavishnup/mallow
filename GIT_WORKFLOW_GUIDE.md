# Git Workflow

## Branch strategy

Use a simple trunk-based workflow with short-lived feature branches.

- `main` — production-ready branch
- `feature/<topic>` — feature work
- `fix/<issue>` — defect fixes
- `chore/<task>` — tooling, docs, repo maintenance

## Recommended flow

```bash
git checkout main
git pull --ff-only
git checkout -b feature/my-change
git add .
git commit -m "feat: add usage dashboard metrics"
git push -u origin feature/my-change
```

## Commit conventions

Follow a concise conventional commit style:

- `feat:` for new capabilities
- `fix:` for bug fixes
- `refactor:` for structural improvements
- `docs:` for project documentation
- `test:` for test additions or updates
- `chore:` for build, config, or maintenance work

Examples:

```bash
git commit -m "feat: add idempotent usage ingestion"
git commit -m "fix: correct mid-cycle proration handling"
git commit -m "docs: add quick start and architecture notes"
```

## Pull requests

Before opening a PR:

1. run the relevant test set
2. ensure the frontend builds
3. ensure static analysis is clean
4. confirm migration and seed flow works from a clean database
5. update docs if behavior changes

PR checklist:

- brief summary of the change
- affected files or modules
- verification commands executed
- screenshots or demo notes if UX changed
- risk or rollback notes if relevant

## Merge policy

- prefer squash merge for small feature work
- keep PRs focused on one problem or capability
- avoid mixing refactors with feature work unless required
- require green CI before merge

## Release notes

For higher-risk changes, add a short release note with:

- the business outcome
- technical impact
- migration or environment changes
- rollback guidance

## Local hygiene

```bash
git status
git fetch --all
git rebase origin/main
```

If work is paused, keep branches small and rebased often so merges stay easy to review.
