<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

# Project Decisions (distilled)

Distilled from `docs/decisions.md`, the single source of truth. When a design question is not answered here, read the relevant step there before guessing. If a change alters a decision, update `docs/decisions.md` in the same change.

## Product

- "Laravel AI Blog": a public blog with one owner. An admin-only editor uses the OpenAI API to generate or enhance draft posts.
- Stack: PHP 8.4, Laravel, Blade, Tailwind v4 (configured in CSS, no `tailwind.config.js`), MariaDB, Pest, Herd locally, cPanel in production.
- No public registration, no password reset, no starter kit (no Breeze, Livewire or Jetstream).
- Don't add tables, columns or features that were not agreed (comments, roles, likes and so on). Ask first.

## Workflow

- Every build stage runs the same loop: plan mode, approval, build, relevant tests, review against `docs/decisions.md`, commit.
- Build order: foundations, data layer, login and admin shell, categories and tags admin, posts without AI, public site, AI integration, scheduler, JavaScript pass, deploy.
- Don't start a later stage while an earlier one has failing tests. Don't start AI work before hand-written posts and the public site work.
- Cloud work happens on a branch, never on main.

## Data model

- Tables, in migration order: users, categories, tags, posts, post_tag, ai_generations. One migration per table.
- `users`: Laravel default plus an `is_admin` boolean.
- `categories` and `tags`: `name` and a unique `slug`.
- `posts`: `user_id`, `category_id` (required), `title`, unique `slug`, `excerpt` (nullable), `body` (long text, Markdown, nullable), `status`, `published_at` (nullable), `first_published_at` (nullable, records the first publish), soft deletes.
- `post_tag`: pivot. One category per post, many tags per post.
- `ai_generations`: `post_id` (nullable), `user_id`, `topic_input`, `field_states` (JSON), `request_payload` (JSON), `response_payload` (JSON), `model`, `status`, `error_message`, token counts, `duration_ms`. It logs every request, failures included.
- `status` is a backed enum with three cases: Draft, Scheduled, Published.
- `ai_generations.status` is a backed enum: Pending, Completed, Failed.
- Casts: status as the enum, `published_at` as a datetime, JSON columns as arrays.
- Soft deletes on posts only. A trashed post keeps its slug reserved.
- Foreign key constraints, relationships defined on both sides, and an explicit fillable list on every model (the `#[Fillable]` attribute). Never use `$guarded = []` or `#[Unguarded]`.
- Never edit a migration that has already run. Make a new migration instead.
- Factories for every model, plus a seeder that creates the admin from `.env` values.

## Auth and access

- Hand-built login and logout only, using Laravel's built-in auth. Login is throttled and the session is regenerated on login.
- Every admin route sits inside a group behind both the `auth` middleware and an admin Gate that checks `is_admin`.
- There is no dashboard: logging in, or opening `/admin`, lands on the post list.
- Admin credentials are never written in code. The seeder reads them from `.env`.

## Routing, controllers, validation

- Thin controllers. Validation lives in one Form Request per form (login, post save, category, tag). No inline validation.
- No business logic or API calls in controllers or views.
- Public queries return only published posts. No public route may expose a draft, scheduled or trashed post.
- The restore route explicitly includes trashed posts. Deleted posts go to a Trash page with Restore. There is no permanent delete.
- The post editor offers an existing category or a new one typed by name (not both), checked with the panel's name rules. Creating or updating a post returns to the post list; restoring opens the editor; a failed save stays on the editor with the input kept.
- Editor buttons by status: draft or new gets Save draft, Publish and Schedule; scheduled gets Update schedule, Publish now and Unschedule; published gets Update and Unpublish (back to draft).
- Save rules: a draft needs title and category. Publish and schedule need title, category, excerpt and body.
- The editor uses buttons to set status: save draft, publish, schedule (date and time), delete. Every post can be edited whatever its status.
- A category that any post uses, trashed posts included, cannot be deleted. Tags can be deleted freely. Deleting a post never deletes its category or tags.
- The categories and tags panel lists (25 per page), creates, edits and deletes them, and shows how many posts use each.

## AI integration

- One service class calls OpenAI through Laravel's HTTP client. No AI SDK.
- The API key, model, timeout and token cap live in `config/services.php`, fed from `.env`. Call `env()` only inside config files. Never hard-code a model name anywhere else.
- Each editor field (title, excerpt, body, category, tags) has a state, and every field except category has an enhance toggle:
  - empty: generate
  - toggled: enhance
  - toggled and empty: generate
  - filled and not toggled: untouched, sent to the AI as read-only context
- Toggles default to on for empty fields and off for filled ones. Every field is always sent, with its state, in one standard payload.
- The request uses OpenAI's strict JSON schema, built dynamically so it only asks for fields in the generate or enhance state. The server validates the reply and always discards output for untouched fields.
- Minimum-content check: Generate or Enhance is blocked, and no request is sent, unless at least one field has enough content to work from. This check is separate from save validation, and its message appears only when Generate or Enhance is pressed.
- Requests are synchronous with an explicit timeout on every request. The generate and enhance route is throttled.
- Create an `ai_generations` row as pending before the call, then update it with the response, tokens and duration, or with the error.
- On a timeout, rate limit or malformed reply, show a friendly message and keep everything the admin typed.
- Categories: the AI picks only from existing categories, whose list is sent with the request, and only when none is chosen. It may answer that none fits (nullable enum), leaving the category empty with a note. It never creates or changes a category.
- Tags: the AI may propose new ones. Match them case-insensitively to existing tags, and create a tag only when nothing matches.
- Code: `App\Services\PostAssistant` is the only class that calls OpenAI; helpers live in `App\Services\AiAssist`. `.env` settings: `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_TIMEOUT`, `OPENAI_MAX_COMPLETION_TOKENS`, `OPENAI_REQUESTS_PER_MINUTE`.
- Minimum content: a 10+ character title, 40+ excerpt, 200+ body, a category, or 3+ tags. Failed generations store only a reason code, never OpenAI's error text.
- AI-suggested tags that don't match an existing tag go into the editor's New tags field and are created only when the post is saved.
- One AI request per admin at a time (cache lock); an identical repeat reuses the running request's outcome. The rate limit returns to the editor with a friendly message, never a bare 429.
- AI results appear inline in the form for review. Nothing is saved until the admin saves.
- The API key must never appear in logs, stored payloads, the database, Blade or JavaScript.

## Scheduled publishing

- A scheduled task runs every minute, with overlap prevented. It publishes Scheduled posts whose `published_at` has passed. Nothing else changes a post from scheduled to published automatically.
- Dates are stored in UTC, then entered and displayed in Europe/London. Convert at the form boundary.
- An admin can unschedule a post (back to draft), unpublish a post (back to draft) or edit a scheduled post. The scheduler publishes through `Post::publish()` so `first_published_at` is set.
- The display timezone is `config('app.display_timezone')`. Never write the zone name anywhere else.
- The admin list marks scheduled posts past their time as overdue (a red badge, plus a note in the editor).
- The task is `posts:publish-scheduled`, scheduled in `routes/console.php` with `withoutOverlapping(5)` so a stuck lock clears in 5 minutes. A due or overdue post keeps its scheduled time as its publish date. Locally it runs via `composer run dev` or `php artisan schedule:work`.

## Slugs

- Generated from the title. The slug follows the title until the first publish (`first_published_at`), then freezes, even after an unpublish. After that, only a deliberate admin edit in the slug field changes it. A title whose slug another post holds, trashed included, is rejected on the title.
- Category and tag slugs are generated from the name at creation and never change. Renames change only the name. Names are unique ignoring case, and a new name whose slug is already taken is rejected on the name field.
- Always build links with `route()` and named routes.

## Blade and front end

- Blade components with slots: a public layout, an admin layout, and small components such as post card, form field and status badge.
- Public pages: home list with pagination (10 per page), single post, category and tag archives at `/`, `/posts/{slug}`, `/categories/{slug}` and `/tags/{slug}`. Admin pages: login, post list, editor, category and tag panels.
- Markdown is rendered in exactly one component, using Laravel's built-in converter with raw HTML stripped and unsafe links blocked. It is styled by the Tailwind typography plugin, including its dark variant.
- Unescaped Blade output (`{!! !!}`) is allowed only in that Markdown component.
- The excerpt doubles as the page's meta description.
- Search, filter and sort happen in the database query, never in Blade or JavaScript. They are driven by validated URL query parameters that pagination keeps. Search matches post titles and category names. Defaults: no search, no filter, newest first. One reusable piece of query logic serves the home page, the archives and the admin list: `Post::published()` and `Post::listed()`, with parameters `search` and `sort=newest|oldest` validated by `PostListRequest`. The admin list alone also filters by `status` (validated by `Admin\PostListRequest`); public pages ignore it.
- The first build uses reload-based AI results and plain checkbox toggles. Autosave, enhance without reload, a dark mode toggle, delete confirmations (including Move to trash) and a schedule date that appears only when Schedule is chosen, with a Confirm button, an AI request page lock (fetch, inert form, Generating… overlay, Cancel via AbortController) and a live Markdown preview (re-rendered as the admin types through a throttled admin-only endpoint that uses the same Markdown component) come in the later JavaScript pass. The editor's body preview already exists, showing the last saved body, with a CSS-only show/hide checkbox. A live countdown for the AI rate-limit wait is also part of the pass. Stage 8 runs as sub-stages 8A to 8G in the order set in `docs/decisions.md` (Step 16, Stage 8 sub-stages): plain JavaScript modules, no new dependencies, every page still working without JavaScript.
- Built so far in the JavaScript pass: delete confirmations (8A), the theme toggle (8B), and the schedule reveal with Confirm (8C, `data-schedule-panel`, `data-schedule-trigger`).
- JavaScript modules: `resources/js/app.js` maps a `data-` hook selector to a dynamic import of `resources/js/modules/<name>.js` exporting `init()`. Forms with `data-confirm` (and `data-confirm-label`) use the shared `<x-confirm-dialog>` in the admin layout.

## Design

- Mobile-first: unprefixed classes target phones and breakpoint prefixes add larger layouts. Never write desktop-first styles and patch them for phones.
- Clean, text-focused design with a narrow reading column. The home page is a list of cards showing date, title, category and excerpt.
- System fonts only. No external font or stylesheet requests.
- The accent is `#4a95ba`. Define it and a darker shade once as Tailwind theme tokens and use them by name, never as repeated hex values.
- In light mode, use the accent for backgrounds, borders and large elements, and the darker shade for link text. Never put light-accent link text on white. In dark mode, the accent works as text. Check final shades with a contrast checker.
- Dark mode follows the system setting by default; a System / Light / Dark toggle (8B) overrides it via `data-theme` on `<html>`, applied before first paint by `<x-theme-script>`. Every colour needs a dark variant.
- Tap targets must be touch-friendly, with no horizontal scroll at phone width.

## Testing

- Mostly feature tests. Unit tests for isolated classes such as the AI payload builder.
- Locally, tests use the separate `laravel_blog_testing` database and never `laravel_blog`. Cloud sessions use in-memory SQLite.
- Reset the database between feature tests. Tests must not depend on running order.
- Always fake the HTTP client, with stray requests prevented. No test may reach OpenAI.
- Scheduler tests move the clock.
- Assert on database contents, not just status codes.
- Must-cover areas: guest and non-admin refusals, throttled login, draft and publish save rules, the AI service, scheduled publishing, public visibility of draft, scheduled and trashed posts, search, filter and sort, slug freezing, and blocked category deletion.

## Security

- Never read, print or commit `.env` or any secret. Never put secrets in CLAUDE.md, skills, committed settings, logs or stored AI payloads.
- The repository is public and git history is permanent, so commit nothing with personal or infrastructure details.
- CSRF on every form, Form Requests for all input, an explicit `#[Fillable]` list on every model.
- Use Eloquent or bound parameters. Never build SQL from strings.
- Boost and other development tools stay in `require-dev`.
