---
name: review-step
description: "Review the uncommitted changes against the \"Should be there\" and \"Should not be there\" lists in docs/decisions.md and report pass, fail or unsure for each item. Run only when the user invokes /review-step."
disable-model-invocation: true
allowed-tools: Bash(git status:*), Bash(git diff:*), Bash(git log:*), Bash(git ls-files:*), Read, Grep, Glob
---

# Review Step

Check what changed since the last commit against the agreed decisions in `docs/decisions.md`. This is a report, not a fix.

## Ground Rules

- Do NOT edit, create or delete any file. Do not run formatters, tests, migrations or commits. Report only.
- Every verdict needs evidence: the `file:line` behind it, or the reason none exists.
- Never guess a pass. If the diff alone can't prove an item, mark it unsure and say what would settle it.

## Process

### 1. Check for changes

Run `git status --porcelain`. If it prints nothing, reply "Nothing has changed since the last commit" and stop.

### 2. Collect the changes

- `git diff HEAD --stat` for an overview, then `git diff HEAD` for staged and unstaged changes together.
- Untracked files (`??` in the status) are not in the diff. Read them directly.
- Skip `vendor/`, `node_modules/`, `public/build/` and lock files unless they are the point of the change.

### 3. Read the decision log

Read `docs/decisions.md` in full. Each step is a `## Step N: ...` heading, and most have **Should be there** and **Should not be there** lists. Treat `## Review checklist: schema, migrations and models` as a step too.

### 4. Work out which steps apply

Map each changed file to the steps it relates to. Use this guide, but read the steps themselves, since one file can touch several:

| Changed files | Steps |
| --- | --- |
| Migrations, models, factories, seeders, enums | Step 2, Review checklist: schema |
| Login, logout, auth middleware, Gates | Step 3 |
| `.env.example`, config, Vite, Tailwind setup | Step 4 |
| Boost files, `.ai/`, `composer.json` dev tools | Step 5 |
| Routes, controllers, Form Requests | Step 6 |
| AI service, `config/services.php`, generation logging | Step 7 |
| Console commands, `routes/console.php`, schedule | Step 8 |
| Blade views, components, Markdown rendering | Step 9 |
| CSS, theme tokens, layout and design | Step 10 |
| `tests/`, `phpunit.xml` | Step 11 |
| `.claude/`, `CLAUDE.md`, skills, hooks | Step 12 |
| Cloud or branch workflow files | Step 13 |
| Deployment files | Step 15 |
| Build order (what stage this work belongs to) | Step 16 |

Step 14 (security and secrets) applies to every change.

### 5. Check each list

For each relevant step, go through every item on both lists:

- **Should be there**: pass if the change contains it, fail if it should be there by now and isn't, unsure if it may belong to a later stage or can't be seen in the diff.
- **Should not be there**: pass if it is absent, fail if the change introduces it.

### 6. Report

One table per step:

```
### Step 6: Routing, controllers and validation

| Item | Verdict | Evidence |
| --- | --- | --- |
| One Form Request per form | pass | app/Http/Requests/PostRequest.php:12 |
| Inline validation in controllers | fail | app/Http/Controllers/PostController.php:40 |
| Restore route includes trashed posts | unsure | No restore route yet; check once it is added |
```

Then add:

- **Not covered by any step**: anything built that no step agreed on, such as extra tables, columns, routes, features, packages or files, with `file:line`. Write "None" if there is nothing.
- **Summary**: counts of pass, fail and unsure, and the fails listed first.

Stop there. Do not offer fixes unless the user asks.
