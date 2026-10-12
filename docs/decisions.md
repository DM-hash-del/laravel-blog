# Laravel AI Blog: Decision Log

Last exported: 2026-10-07

## Project summary

A public blog owned by one person, where an admin-only tool turns a topic or keywords into an AI-written draft post. This log records every decision agreed so far, step by step.

- **Stack:** PHP 8.4, Laravel, Blade, Tailwind v4, MariaDB, served locally with Herd.
- **AI in the app:** OpenAI API called from the Laravel backend.
- **Quality and tooling:** Pest for tests, Laravel Boost for AI-assisted development, Claude Code for the build.
- **Fixed constraints:** no public sign-up; the finished site is live and viewable by anyone, hosted on cPanel with cron available.

## Step 1: Scope (agreed)

| Question | Decision |
| --- | --- |
| AI provider | OpenAI API; an account already exists |
| Admin access | Real login; only the admin can use the generation tool; no public registration |
| After generation | Admin sees an editable draft, can save it, and can schedule publishing for later |
| Post management | Admin can edit and delete posts |
| Taxonomy | One category per post plus many tags; AI suggests them, admin can edit |
| Prompt record | Keep everything sent to the API for each generation |
| Purpose | Portfolio piece, live and public |

## Step 2: Database schema (agreed)

Five tables plus the pivot, created in this order because foreign keys need their parent tables to exist first.

| Table | Key columns | Rules |
| --- | --- | --- |
| users | Laravel default; optional is_admin flag | Registration disabled; admin-only actions protected by a Gate |
| categories | name, slug | Slug unique |
| tags | name, slug | Slug unique |
| posts | user_id, category_id (required), title, slug, excerpt (nullable), body (long text, Markdown, nullable until published), status, published_at (nullable), first_published_at (nullable, added in Stage 4), deleted_at | Soft deletes; status is draft, scheduled or published; slug unique. first_published_at records the first publish so the slug stays frozen after an unpublish |
| post_tag | post_id, tag_id | Pivot for the many-to-many link between posts and tags |
| ai_generations | post_id (nullable), user_id, topic_input, field_states (JSON), request_payload (JSON), response_payload (JSON), model, status, error_message, token counts, duration_ms | Logs every request, including failed ones; never stores the API key |

**Decisions made**

- Real scheduler: a scheduled command publishes posts whose published_at has passed, so status has three values (draft, scheduled, published).
- Generations live in their own table, so a failed generation is logged even if no post exists, and one post can have several generations.
- One category per post, many tags.
- Soft deletes on posts from the start.
- ai_generations.status is a backed enum (pending, completed, failed), alongside the post status enum (draft, scheduled, published).
- Only the agreed tables are created. Laravel's scaffolded password_reset_tokens, sessions, cache, jobs, job_batches and failed_jobs tables are removed: sessions and cache use files and the queue is sync (Step 4), and there is no password reset (Step 3).
- Body is Markdown, rendered to HTML by Laravel's built-in Markdown converter (no custom parser needed), with safe settings confirmed in the Blade step. The AI returns structured JSON (title, excerpt, body, category, tags) that maps onto these columns.

**Points to double check**

- **Slug and soft deletes:** a trashed post still holds its unique slug, so a new post cannot reuse it. Decided: accepted, so the slug stays reserved. (A slug is the readable part of a post's URL, such as /posts/my-first-post.)
- **AI-suggested categories and tags:** when the AI proposes a name, does the app match it to an existing record or create a new one? Deferred to the AI integration step; leaning towards specific tags that stay distinct from the category.
- **Scheduler needs a host that can run cron:** locally it runs through the scheduler worker command; cron is confirmed available on the cPanel host.
- **Log after permanent delete:** the nullable post_id is only cleared if a post is permanently deleted, which is the intended behaviour.

## Step 3: Scaffolding and admin auth (agreed)

| Question | Decision |
| --- | --- |
| Starter kit | None. The Livewire kit conflicts with the brief, and Breeze is not worth the risk of an unmaintained dependency |
| Authentication | Hand-built login and logout using Laravel's built-in auth; no registration, no password reset |
| Admin account | Created by a seeder that reads the email and password (and an optional name, defaulting to Admin) from .env through config/auth.php |
| Admin flag and Gate | Keep is_admin on users and a Gate that checks it, protecting every admin route |

**Authentication vs authorization:** the auth middleware answers "is someone logged in?". The Gate answers "is this logged-in user allowed to do this?". Admin routes use both.

**Should be there**

- [x] Login and logout routes only, with validation on the login form.
- [x] Login rate limiting (throttling) and session regeneration on login.
- [x] Admin routes behind both the auth middleware and the admin Gate.
- [x] Admin credentials read from .env by the seeder, and .env listed in .gitignore.

**Should not be there**

- [ ] Any registration route, view or controller.
- [ ] Admin email or password written directly in code or committed to git.
- [ ] An admin route reachable without the Gate check.

## Step 4: Project setup (agreed)

| Setup item | Decision |
| --- | --- |
| Names | App name "Laravel AI Blog"; project folder laravel-blog; Herd serves it locally at laravel-blog.test |
| Installer options | No starter kit; Pest as the test framework |
| Tooling | Node and PHP versions managed by Herd. Sessions and cache use files and the queue runs synchronously (SESSION_DRIVER=file, CACHE_STORE=file, QUEUE_CONNECTION=sync), so none of them needs a database table. Passwords containing special characters such as # are wrapped in double quotes in .env |
| Local database | MariaDB at 127.0.0.1:3306, dedicated database laravel_blog with its own user blog_user (access to that database only), credentials saved for .env, managed through TablePlus |
| Tailwind | v4 via Vite; confirm it is preconfigured in package.json after install |
| Git | Commit straight after scaffolding, then create the GitHub repo |
| Live hosting layout | A cPanel subdomain with its document root pointed at Laravel's public folder |

**Resolved:** a dedicated database and user were created instead of using root, which also rehearses the cPanel MySQL Databases workflow.

**Should be there**

- [ ] DB connection type set to mariadb in .env, with credentials only in .env.
- [ ] A clean first commit of the untouched scaffold before any changes.
- [ ] .env and vendor ignored by git; .env.example committed with empty secrets.

**Should not be there**

- [ ] Database root credentials in any committed file.
- [ ] A tailwind.config.js created by habit; v4 is configured in CSS.

## Step 5: Laravel Boost (agreed)

| Question | Decision |
| --- | --- |
| When | Installed straight after the clean scaffold commit, before any building |
| Agents | Claude Code only; Cursor is used purely as a text editor |
| Generated files | CLAUDE.md and the other generated files are committed to git |
| Scope | Dev dependency only; excluded from the production install on cPanel |

**Secrets:** Claude Code can read project files, including .env, unless a permission rule denies it. A deny rule for .env is applied in Claude Code's project settings (set up in the Claude Code step). The OpenAI key has a spending cap and auto-deposit is off. How Claude Code Cloud works without the API key and database credentials is covered in the cloud workflow step.

**Should be there**

- [ ] A git diff reviewed after boost:install, showing only the expected new files.
- [ ] Boost under require-dev in composer.json, not require.

**Should not be there**

- [ ] Guideline files for AI tools you do not use.
- [ ] Any secret copied into CLAUDE.md or another guideline file.

## Step 6: Routing, controllers and validation (agreed)

| Decision | Detail |
| --- | --- |
| Publish workflow | Post status is set by buttons on the editor: save draft, publish, schedule (date and time), delete. Autosave waits until the app is functionally working |
| Category rule | Every post must have a category. A category in use cannot be deleted: reassign its posts or create a new category first. Trashed posts still block deleting their category, which is accepted |
| Categories and tags admin | Dedicated panel with list, create, edit and delete, showing how many posts use each. Tags can be added and deleted freely, and unused tags can be removed from the panel. Deleting a post never deletes its category or tags |
| Category and tag slugs | Generated from the name at creation, then frozen. Renaming changes only the name, so archive URLs stay stable. Names are unique, ignoring case. A new name whose slug is still held by another row (for example one renamed away from that name) is rejected with a message on the name field |
| Admin panels | The category and tag lists show 25 per page. The admin layout has a nav bar (Categories, Tags) and shows a success (status) or error message after each action |
| Delete confirmation | Built in 8A: a shared dialog asks before deleting a category or tag and before Move to trash on a post, with the message and button label taken from the form's data-confirm and data-confirm-label attributes. Focus starts on Cancel, and Escape or a backdrop click backs out. Without JavaScript the form submits on the first click, as before |
| Post editor buttons | A draft or new post offers Save draft, Publish and Schedule. A scheduled post offers Update schedule, Publish now and Unschedule. A published post offers Update and Unpublish. Unpublishing returns a post to draft and takes it off the site. Move to trash sits below the form. Updating a published post keeps its original publish date. With JavaScript (8C), the schedule date stays hidden until Schedule or Update schedule is pressed, then shows with Confirm and Cancel, so nothing is scheduled without Confirm; it opens straight away after a failed schedule attempt so the error is visible, and is hidden on published posts. Without JavaScript the date field shows and Schedule submits as before |
| Body preview | Below the body field the editor shows a read-only preview of the body rendered through the shared Markdown component, so it matches the public post page. It shows the body as last saved (or as typed after a failed save). An unnamed Show preview checkbox, ticked by default, hides and shows it with CSS alone |
| Admin landing page | There is no dashboard. Logging in, or opening /admin, takes the admin to the post list |
| Post slugs | A new or draft post's slug is generated from the title. A title whose slug is held by another post, trashed posts included, is rejected with a message on the title. Once a post has been published, the editor shows an editable slug field instead, validated as a unique, lowercase, hyphenated slug |
| Post editor category | The editor offers a list of existing categories and a field to create a new one by name, but not both at once. A new category follows the same name rules as the panel and is created only when the post saves |
| After saving | Creating or updating a post from the editor returns the admin to the post list. Restoring a post opens the editor. A failed save stays on the editor with the input kept |
| Post trash | Deleting a post moves it to a Trash page, which lists trashed posts with a Restore button. A trashed post cannot be edited until restored. There is no permanent delete |
| Post editor | One form for writing and AI help, with no separate generation page and no separate brief input. Fields: title, excerpt, body (Markdown), category, tags, each with an enhance toggle |
| Field states | Empty means generate. Toggled means enhance. Toggled and empty means generate. Non-empty and not toggled means untouched, sent to the AI as read-only context. Toggles default on for empty fields and off for filled ones, and the admin can flip either. Every field is always sent in one standard payload with its state |
| AI action validation | Generate or enhance is blocked, and no API request is sent, unless at least one field has enough content to work from (examples: a title of N characters, 3 or more tags, a category, or a body of 200 characters after trimming). Exact minimums are set at build time |
| Save validation | Separate from the AI check, so writing a post by hand never triggers it. Publish and schedule require title, category, body and excerpt. A draft save requires only title and category, so body and excerpt are nullable in the database. The validation message about nothing to generate or enhance appears only when the admin presses Generate or Enhance, never when saving or publishing |
| AI result | Shown inline in the form for review; nothing is saved until the admin saves |
| Structure | Thin controllers, one Form Request class per form, admin routes grouped behind the auth middleware and the admin Gate |

**Rules to build in**

- The AI minimum is met when any one field has enough content to work from.
- The server discards any AI output for an untouched field, never relying on the AI to leave it alone.

**Should be there**

- [ ] Form Request classes for login, post save and category/tag forms.
- [ ] The restore route explicitly including trashed posts.
- [ ] Public queries returning only published posts.

**Should not be there**

- [ ] Validation written inline in controllers.
- [ ] API calls or business logic inside controllers.
- [ ] Any public route that can reveal a draft, scheduled or trashed post.

## Step 7: AI API integration (agreed)

| Decision | Detail |
| --- | --- |
| Calling OpenAI | Laravel's HTTP client directly, with no AI SDK |
| Where the code lives | One service class called by the controller. API key, model and timeout live in config/services.php, fed from .env |
| Output format | OpenAI strict JSON schema, built dynamically so it only asks for fields in the generate or enhance state. The server also discards output for untouched fields |
| Request mode | Synchronous with a firm timeout. A queue is revisited only if cPanel time limits cause problems |
| Logging | An ai_generations row is created before the call as pending, then updated with the response, token counts, duration or error. Failures are logged too |
| Errors | Timeouts, rate limits and malformed replies show a friendly message and keep everything the admin typed |
| Cost control | Throttle on the endpoint, a token cap per request and a cheaper model in development, all set in config |
| Category suggestions | The AI chooses only from existing categories (the list is sent with the request), and only when no category is chosen. It may answer that none fits, which leaves the category empty with a note asking the admin to choose or create one; it is never forced to pick. It never creates or changes a category, so the category has no Enhance box. New categories are added by the admin, in the panel or the editor |
| Tag suggestions | The AI may propose new tags. They are matched case-insensitively to existing tags and created only when there is no match |
| Endpoint and settings | OpenAI Chat Completions with a strict json_schema response format. .env holds OPENAI_API_KEY, OPENAI_MODEL (a model that supports Structured Outputs), OPENAI_TIMEOUT (default 30), OPENAI_MAX_COMPLETION_TOKENS (default 2000) and OPENAI_REQUESTS_PER_MINUTE (default 5), read only in config/services.php. Tests use dummy values from phpunit.xml and block stray HTTP requests |
| Code layout | App\Services\PostAssistant is the one class that calls OpenAI. App\Services\AiAssist holds FieldStates (works out each field's state), RequestBuilder (builds the payload and schema, unit-tested) and AiAssistFailed (the friendly error) |
| Minimum content | Generate / enhance needs at least one of: a title of 10+ characters, an excerpt of 40+, a body of 200+ after trimming, a category, or 3+ tags. If every field is filled and none is ticked, there is nothing to do and nothing is sent |
| Error records | A failed generation stores only a short reason code (timeout, http_429, http_401, refusal, length, malformed), never OpenAI's error text, which can echo part of the key |
| Repeated requests | Only one AI request per admin runs at a time (a cache lock). A repeat of the same request, such as a double-click, waits for the running one and reuses its outcome for 15 seconds instead of calling OpenAI again; different input waits its turn. A request that waits too long is turned away with a friendly message. Hitting the rate limit returns to the editor with the input kept and a "try again in N seconds" message, never a bare 429 page. The JavaScript pass (8F) shows the wait as a live countdown and keeps Generate disabled until it ends |
| Editor flow | Title, excerpt, body and tags each have an Enhance with AI checkbox, ticked by default when empty; category has none. Generate / enhance with AI sits below the save buttons so Enter still saves. Suggestions return as form input; a suggested category becomes the selected category, matching tags are ticked and unmatched tag names go into a New tags field. New tags (also usable by hand, comma separated, up to 6) are created only when the post is saved, reusing an existing tag whose name matches ignoring case |

**Should be there**

- [ ] The key read through config, with env() used only inside config files.
- [ ] An explicit HTTP timeout on every OpenAI request.
- [ ] The reply validated before any field is used.
- [ ] A throttle on the generate and enhance route.
- [ ] Tests that fake the HTTP call and never reach OpenAI.

**Should not be there**

- [ ] The API key in logs, the stored payload, the database, Blade views or JavaScript.
- [ ] OpenAI calls inside a controller or a view.
- [ ] AI output rendered as raw HTML.
- [ ] Model names hard-coded in several places.

## Step 8: Scheduled publishing (agreed)

| Decision | Detail |
| --- | --- |
| Mechanism | The Laravel scheduler runs a task every minute. Posts with status scheduled whose published_at has passed are set to published. Overlapping runs are prevented. The task is the posts:publish-scheduled command (registered in routes/console.php), which publishes each due post through Post::publish(). Its overlap lock expires after 5 minutes (not Laravel's 24-hour default), so a run killed partway delays publishing by minutes, not a day |
| Where it runs | Locally through the scheduler worker command. On cPanel through one cron entry every minute, using the correct PHP command-line path (checked in the hosting step) |
| Time zones | Stored in UTC, entered and shown in Europe/London |
| Changing your mind | An admin can unschedule a post (back to draft), unpublish a published post (back to draft) and edit a scheduled post |
| Editing rule | Every post can be edited and updated whatever its status; no state locks a post |
| Overdue | The admin list marks scheduled posts that are past their time as overdue, which shows if cron has stopped. It shows a red Overdue badge in the list and on the editor, where a note asks the admin to check the scheduler |
| Publish date | Publishing keeps a post's publish date when that date has already arrived: a published post keeps its original date, and a due or overdue scheduled post keeps its scheduled time, even if cron ran late. A draft, or a scheduled post published early, takes the current time |
| Running locally | composer run dev now runs php artisan schedule:work alongside the server; on its own, run php artisan schedule:work. The cPanel cron entry belongs to the deploy stage |
| Slug | A post keeps its slug once published unless the admin changes it deliberately, so editing a title never breaks a link. The freeze is recorded by first_published_at, so it survives an unpublish. The scheduler must publish through the same Post::publish() method as the editor so first_published_at is set |
| Display timezone | Europe/London is set once in config/app.php as display_timezone and read from there by the form and views |

**Should be there**

- [ ] Only the scheduled task changes a post from scheduled to published automatically.
- [ ] Dates stored in UTC and converted at the form boundary.
- [ ] Tests that move the clock to prove a post publishes at the right time.

**Should not be there**

- [ ] Local-time values stored in the database.
- [ ] Any status that stops a post being edited.
- [ ] A cron entry that runs a different PHP version from the app.

## Step 9: Blade templating and front-end (agreed)

| Decision | Detail |
| --- | --- |
| Layouts | Blade components with slots: a public layout, an admin layout and small reusable components such as post card, form field and status badge |
| Pages | Public: home list with pagination, single post, category and tag archives. Admin: login, post list, editor, category and tag panels |
| Inline AI result | Reload-based first. The form posts every field and the page returns with the fields filled in for review. Toggles are plain checkboxes, ticked by default when the field is empty. A JavaScript version comes later, alongside autosave |
| Markdown | Laravel's built-in converter with raw HTML stripped and unsafe links blocked, wrapped in one component. Tailwind's typography plugin styles the output |
| Search engines | The excerpt is reused as the page's meta description |
| Search, filter, sort | Done in the database query, never in the browser. Controlled by URL query parameters so results are bookmarkable and pagination keeps them. Search matches post titles and category names with simple text matching. The only other control is sort direction, except on the admin list, which also filters by post status (draft, scheduled or published). Defaults: no search, no filter, newest first. The same filter code serves the public home, the archives and the admin list |
| Slug | Generated from the title. It follows the title while a post has never been published and is frozen at first publish (confirmed). Links are built from route names, so they update automatically wherever the slug is used |
| Public URLs | Home at /, posts at /posts/{slug}, archives at /categories/{slug} and /tags/{slug}. Public pages show 10 posts per page. Only posts with status published are public; drafts, scheduled and trashed posts return 404 |
| List controls | The search and sort query parameters are search (up to 100 characters) and sort (newest or oldest). The admin list also takes status (draft, scheduled or published; empty means all), validated by Admin\PostListRequest; public pages ignore it. Invalid parameters redirect to the same list without its query string, with an error. The shared query logic lives in Post's published and listed scopes. Public lists sort by publish date; the admin list sorts by created date because drafts have none. LIKE wildcards in a search are matched literally |
| Markdown component | x-markdown takes the body as a prop (a slot would HTML-escape it first) and is styled by @tailwindcss/typography with prose-invert for dark mode |

**Notes**

- Dark mode is in the first build as part of the front-end design spec, which has its own step. Resolved: it follows the system setting by default, with a System / Light / Dark toggle added in 8B.
- RSS feed is a later extra.
- Tailwind's typography styling needs its dark variant so Markdown text stays readable in dark mode.

**Should be there**

- [ ] Search, filter and sort handled by reusable query logic in the database layer.
- [ ] Query parameters validated, with pagination keeping them.
- [ ] Markdown rendered in exactly one component.

**Should not be there**

- [ ] Filtering or sorting done by looping over results in Blade or JavaScript.
- [ ] Unescaped Blade output anywhere except the Markdown component.
- [ ] Draft, scheduled or trashed posts reachable from public pages.

## Step 10: Front-end design spec (agreed)

| Decision | Detail |
| --- | --- |
| Approach | Mobile-first for both the public site and the admin. Base styles target phones, and larger screens are layered on with Tailwind's breakpoints |
| Home page | A simple list of cards, each showing date published, title, category and excerpt |
| Style | Clean and text-focused, with a narrow reading column for posts |
| Accent colour | #4a95ba |
| Fonts | System fonts, with no external font requests |
| Dark mode | Follows the system setting by default. Built in 8B: a System / Light / Dark toggle in the public and admin headers, System by default, stores the choice in localStorage and sets data-theme on <html>. A custom dark variant in app.css applies data-theme and otherwise falls back to the system setting, and a small head script applies the stored choice before first paint so there is no flash. Without JavaScript the toggle is hidden and the site follows the system |
| Accessibility | The accent has roughly 3.3 to 1 contrast against white, below the 4.5 to 1 needed for body-size text. In light mode use the accent for backgrounds, borders and large elements, and a darker shade of it for link text. In dark mode the accent works as text. The accent may be nudged slightly to a close value that passes contrast checks, and the final shades are verified with a contrast checker |

**Should be there**

- [ ] Unprefixed Tailwind classes written for phones first, with larger layouts added through breakpoint prefixes.
- [ ] The accent colour and its darker shade defined once as theme tokens, then reused by name.
- [ ] Touch-friendly tap targets and no horizontal scrolling at phone width.
- [ ] A dark variant for every colour used, including the Markdown typography styling.

**Should not be there**

- [ ] The hex value repeated through templates instead of a theme token.
- [ ] Desktop-first styles patched afterwards for phones.
- [ ] Link text in the light accent on a white background.
- [ ] External font or style requests.

## Step 11: Testing with Pest (agreed)

| Decision | Detail |
| --- | --- |
| Framework | Pest, chosen at install |
| Test database | A separate MariaDB database, laravel_blog_testing, with access granted to blog_user. Tests never touch laravel_blog |
| Test types | Mostly feature tests that act like a visitor; unit tests for isolated classes such as the AI payload builder |
| Areas covered | Access control; save rules for drafts and publishing; the AI service with the HTTP call faked; the scheduler publishing at the right moment; public visibility of drafts, scheduled and trashed posts; search, filter and sort; slug freezing; blocked category deletion |
| AI calls | Always faked. Tests never reach OpenAI |
| Claude Code Cloud | Has no access to the local database, so cloud sessions run the tests against in-memory SQLite (see the cloud workflow step) |

**Should be there**

- [ ] Test configuration pointing at laravel_blog_testing.
- [ ] Feature tests resetting the database between tests.
- [ ] The HTTP client faked, with stray real requests blocked.
- [ ] Scheduler tests that move the clock.
- [ ] Assertions on database contents, not only status codes.

**Should not be there**

- [ ] Any test connecting to laravel_blog.
- [ ] Any test that calls the real OpenAI API.
- [ ] Tests that depend on running in a particular order.

**One-off check:** after the first test run, confirm the laravel_blog tables and rows are untouched.

## Step 12: Claude Code setup (agreed)

| Decision | Detail |
| --- | --- |
| CLAUDE.md | Generated by Boost, then extended with a distilled version of this Decision Log. Kept under about 200 lines |
| Project settings | The .claude folder in the project root. .claude/settings.json is shared and committed and holds deny rules for .env and .env.* with .env.example carved out, plus ask rules for commands that can print configuration. The deny rules cover Claude's file tools and recognised file commands such as cat, but not every subprocess, so the dedicated database user and the spending-capped OpenAI key remain the backstops. Personal overrides go in .claude/settings.local.json, which is not committed |
| Plan mode | Used for each build step: Claude explores and proposes a plan, the admin approves it, then code is written |
| Review skill | A user-invoked skill that checks the current git diff against the should and should-not lists in this log |
| Decisions skill | A reference skill that loads the full decision document on demand, for when a design question or confusion arises |
| Formatting hook | Laravel's formatter (Pint) runs automatically after edits, so it is guaranteed rather than requested |
| Tests | Claude runs the relevant tests as instructed in CLAUDE.md, rather than through a hook, because the full suite after every edit is slow and costly |
| Decision document in the repo | The full log is exported to Markdown as docs/decisions.md. It is the single source of truth read by both skills, and CLAUDE.md carries only the distilled version |
| Subagents | Not in the first build. Reconsidered if a need appears |

**Should be there**

- [ ] The .env deny rule tested by asking Claude Code to read .env and confirming it is refused.
- [ ] Skill descriptions that clearly say when each skill applies.
- [ ] A formatting hook command that runs in Git Bash on Windows.
- [ ] The repo copy of the decision document updated whenever a decision changes.

**Should not be there**

- [ ] Secrets in CLAUDE.md, skills or committed settings.
- [ ] .claude/settings.local.json committed to git.
- [ ] The whole decision document pasted into CLAUDE.md, which would load it into every session.

## Step 13: Claude Code Cloud workflow (agreed)

| Decision | Detail |
| --- | --- |
| Session type | Only plain cloud sessions are used for cloud work, never Projects or Routines |
| Spend check | Check the usage and extra-usage settings on the Claude account to confirm no auto top-up is enabled |
| GitHub connection | The repository is private for now and will be made public when it is ready for a senior developer to review. The Claude GitHub App is installed on this one repository only (proposed), rather than a terminal token that can reach every repository. A cloud session that is refused a permission is accepted as part of the learning |
| Cloud database | No MariaDB in the cloud. Cloud sessions run Pest against in-memory SQLite, set through an environment variable on the cloud environment. Full test runs are repeated locally on MariaDB before merging |
| OpenAI and .env | Not needed in the cloud. .env is git-ignored and the tests fake the OpenAI call |
| Working pattern | Plan locally in plan mode from the decision document, save the plan in the repo, commit and push a branch, then start a cloud session on that branch. Work comes back as a branch or pull request for review the next morning |
| First cloud tasks | In order: writing tests, search/filter/sort, the JavaScript pass, then design. Anything visual or interactive needs a manual check in the browser |
| Guardrails | Cloud work happens on a branch, never straight on main. Each plan file states what to build, what done means (tests pass), which decisions apply, and when to stop and note a question |
| Permissions | Cloud sessions have a permission mode chosen at the start. An unattended session that needs an answer waits until it is answered or the environment expires |

**Should be there**

- [ ] A plan file in the repo for every cloud task, referring to the decision document.
- [ ] A pull request reviewed with the review skill, plus a local test run on MariaDB, before merging.
- [ ] The Claude GitHub App limited to this repository.

**Should not be there**

- [ ] Projects or Routines used for cloud work.
- [ ] Secrets in environment variables on the cloud environment that tests do not need.
- [ ] Cloud work merged without a local check against MariaDB.

## Step 14: Security and secrets checklist (agreed)

This pulls together the security rules already decided in earlier steps, plus the standard Laravel protections.

| Area | Rule |
| --- | --- |
| Secrets | .env is never committed. The OpenAI key has a spending cap. The live key exists only on the cPanel host. Claude Code cannot read .env. No secrets appear in logs or in the stored AI request payload. Because the repository will be made public and git history is permanent, nothing sensitive is ever committed, and the exported decision document is checked for personal or infrastructure details before it is committed |
| Authentication | Login is throttled and the session is regenerated on login. There is no registration. Passwords are hashed by Laravel's default hashing. The admin account is seeded from .env values |
| Authorization | Every admin route sits behind the auth middleware and the admin Gate, and tests prove a guest and a non-admin are refused |
| Forms | Every form carries a CSRF token. Form Requests validate all input. Each model lists its fillable fields, which prevents mass assignment |
| Output | Blade escapes output by default. Markdown is rendered with raw HTML stripped and unsafe links blocked, in one component only |
| Database | Queries use Eloquent or bound parameters, never SQL built from strings. The application user has access to one database only. Tests use a separate database |
| AI endpoint | Throttled, with a token cap per request, a minimum-content check before any call, and a spending cap on the account |
| Production | Debug mode off, environment set to production, no error details shown to visitors, HTTPS on, development packages such as Boost excluded, and the project root with .env not publicly served |
| Dependencies | Run composer audit and npm audit now and then, and keep Laravel and packages updated |

**Should be there**

- [ ] A security pass over the finished app against this table before going live.
- [ ] Tests for the refusal cases: guests, non-admins, throttled logins.

**Should not be there**

- [ ] Debug mode on in production.
- [ ] Any route, form or output that skips the protections above.

## Step 15: Hosting and deployment on cPanel (agreed)

| Decision | Detail |
| --- | --- |
| Access | Shared cPanel hosting with no terminal, no SSH and no Git tool, and the PHP version is not visible in the interface. This is treated as fixed |
| PHP version | Confirmed: the command-line PHP is 8.4.26, identical to the local version, and web requests use PHP 8.4 through the cPanel handler. No temporary version page is needed. The new subdomain must also be set to PHP 8.4 |
| Deployment | Build locally, then upload. Install packages without development ones, run the front-end build, zip the project and extract it with File Manager (or FTP if the account has it). GitHub Actions could build the release zip later |
| Layout | The subdomain's document root can only point inside public_html, so the project folder sits at public_html/project-folder and the document root points at its public folder. The project root is then inside the main domain's web root, so a protective .htaccess is added at the project root and verified with a harmless test file requested through the main domain before any real secrets are uploaded. Dotfiles such as .env appear to be blocked server-wide, but other files in a project root, such as composer.json, can be readable through the main domain, so a project root is not protected by default and a protective rule is required. Planned rule: refuse any request whose hostname is not the subdomain, so everything under the project folder is blocked when reached through the main domain. It is tested after deployment with a harmless file. If that verification fails, the fallback is a split layout: only the public folder's contents in public_html and the rest of the app outside it |
| Artisan commands | With no terminal, one-off commands (migrations, caching) run through a temporary cron job that is removed afterwards. A test job has already run successfully with the full PHP path, so artisan commands can run the same way |
| Database | Created in cPanel's MySQL Databases tool. The schema is loaded by running migrations through a one-off cron job, or by importing exported SQL in phpMyAdmin, which is available |
| Admin account | The seeder also runs through a one-off cron job, reading credentials from the live .env, and those variables are removed from .env afterwards. The seeder runs before configuration is cached; once the admin variables are removed, config:cache (or config:clear) is run again so the cached configuration holds no admin password |
| Scheduler | One cron entry every minute running Laravel's scheduler with the correct PHP path |
| Live .env | Created with File Manager on the server, with debug off, a separate production app key generated locally and the production database and OpenAI values |
| Writable folders | The storage and bootstrap cache folders must be writable |

**Should be there**

- [ ] A release zip that excludes .env, tests, node_modules, .git and the docs folder.
- [ ] Packages installed without development ones, so Boost is absent in production.
- [ ] The built front-end assets included in the upload.
- [ ] Every temporary cron job and test page removed after use.
- [ ] A project-root .htaccess that blocks requests arriving through the main domain, tested by requesting a harmless file in the project root through the main domain's URL and getting a refusal.

**Should not be there**

- [ ] A version-check or phpinfo page left on the server.
- [ ] Real secrets uploaded before the protection test passes.
- [ ] Admin credentials left in the live .env after seeding.
- [ ] Admin credentials left in the cached configuration (bootstrap/cache/config.php) after seeding.
- [ ] A deny-everything rule at the project root, which would also block the subdomain's own pages.

## Step 16: Build order (agreed)

Each stage follows the same loop: plan mode, approve the plan, build, run the tests, run the review skill, commit.

| Stage | What gets built | Notes |
| --- | --- | --- |
| 0. Foundations | Create the project (no starter kit, Pest), connect MariaDB and the test database, first clean commit, GitHub repo (private until review), Boost, CLAUDE.md, .claude settings with the .env deny rules, the two skills, the formatting hook, the decision document in the repo | Nothing else starts until this is in place |
| 1. Data layer | Migrations, models, enums, factories, the admin seeder | Tests for relationships and rules |
| 2. Login and admin shell | Login and logout, throttling, the admin Gate, the admin layout | Access tests |
| 3. Categories and tags admin | List, create, edit and delete with the usage rules | Good cloud task |
| 4. Posts without AI | The editor, save rules, soft deletes and restore, slug rules | Hand-written posts work end to end |
| 5. Public site | Layouts, home list, post page with the Markdown component, archives, search, filter and sort, dark mode | Mobile-first. Search, filter and sort is a good cloud task |
| 6. AI integration | The service class, logging, the enhance toggles in the editor, the throttle | Tests with the HTTP call faked |
| 7. Scheduler | The scheduled publishing task and the overdue marker | Tests that move the clock |
| 8. JavaScript pass | Run as sub-stages 8A to 8G (see Stage 8 sub-stages below): delete confirmations, dark mode toggle, schedule reveal with Confirm, live Markdown preview, enhance without reload with a page lock and Cancel, a live rate-limit countdown, and autosave | Good cloud task, checked manually in the browser |
| 9. Deploy | Build, upload, the protection test, cron jobs, migrate, seed, security pass | Follows the hosting step |

### Stage 8 sub-stages

Stage 8 is split so features that share the editor flow are built once, in dependency order. Each sub-stage runs the full loop (plan mode, approval, build, tests, review skill, commit). Done means the server tests pass and the admin has checked the behaviour in the browser at phone width, light and dark.

Ground rules for every sub-stage:

- Plain JavaScript ES modules bundled by Vite in resources/js. No new dependencies (no Alpine, no framework).
- Progressive enhancement: every page keeps working with JavaScript off, exactly as before. Modules attach only to elements with data- hooks.
- Server rules stay on the server: validation, the minimum-content check, the throttle, the single-request lock and visibility never move into JavaScript.
- Pest covers every new or changed endpoint, including JSON responses. JavaScript behaviour is checked manually in the browser.

| Sub-stage | What gets built | Depends on | Why in this order |
| --- | --- | --- | --- |
| 8A. Foundations and delete confirmations | The app.js entry that loads modules by data- hook; a shared dialog-based confirm; confirmation before deleting a category or tag and before Move to trash on a post | Nothing | Small and self-contained; sets the module, data-hook and dialog patterns every later sub-stage reuses. Pattern: resources/js/app.js maps a data- hook selector to a dynamic import of resources/js/modules/<name>.js, which exports init(); a module loads only on pages with its hook, as its own chunk |
| 8B. Dark mode toggle | Tailwind v4 class-based dark variant with the system setting as the default; a no-flash script in the head; a toggle in both layouts, remembered in localStorage | 8A | Changes how every dark: class applies, so later sub-stages are checked in both modes through the toggle. Decided: three options (System, Light, Dark), System by default |
| 8C. Schedule reveal and Confirm | The schedule date stays hidden until Schedule is pressed, then a Confirm and Cancel pair submits or backs out | 8A | Editor-only with no server change; settles the button area before the AI controls change. Built: the date panel opens automatically after a failed schedule attempt, Cancel restores the previous date, and published posts never show it |
| 8D. Live Markdown preview | A throttled admin-only preview endpoint that renders through the shared Markdown component; the editor preview updates as the admin types (debounced); show and hide stays CSS-only | 8A | First fetch() sub-stage: builds the shared fetch helper (CSRF header, JSON errors, aborting stale requests) reused by 8E to 8G |
| 8E. Enhance without reload and page lock | The assist endpoint also answers JSON (the redirect stays for no-JS); suggestions fill the fields, category and tags in place and refresh the preview; the form goes inert behind a Generating… overlay with a Cancel button that aborts the request | 8D | Needs the fetch helper and the live preview; the server-side single-request lock stays as the backstop |
| 8F. Rate-limit countdown | The limiter answers JSON 429 with the seconds to wait for fetch requests, and the redirect message carries the seconds as a data attribute; a live countdown replaces the fixed number and Generate stays disabled until it ends | 8E | Reads the responses 8E handles |
| 8G. Autosave | Saving the editor periodically and on change | 8D, 8E, 8C | Overlaps with every editor feature (pauses during the AI page lock, refreshes the preview, never publishes or schedules), so it goes last |

Questions to settle when each sub-stage is planned:

- 8B (settled): three options, System, Light and Dark, with System as the default.
- 8G: whether autosave covers only drafts and new posts (saving a published post while typing would change the live page), whether the first autosave of a new post creates the draft and moves to its edit page, and what happens while the title is too short to save.

**Should be there**

- [ ] Every stage ending with passing tests and a committed, reviewed diff.
- [ ] The decision document updated whenever a stage changes a decision.

**Should not be there**

- [ ] A later stage started while an earlier stage has failing tests.
- [ ] AI integration work before hand-written posts and the public site work.

## Review checklist: schema, migrations and models

Use this when checking what Claude Code generates for the database layer.

**Should be there**

- [ ] One migration per table, in dependency order (users, categories, tags, posts, post_tag, ai_generations).
- [ ] Foreign keys with constraints, and unique indexes on every slug.
- [ ] Soft deletes on posts only, with the matching trait on the Post model.
- [ ] Casts: status as an enum, published_at as a date-time, both JSON columns as arrays.
- [ ] Relationships defined on both sides (post to user, category, tags and generations).
- [ ] An explicit list of fillable fields on each model (Laravel 13's #[Fillable] attribute, as on the scaffolded User model).
- [ ] Factories and a seeder so the dev site has sample data.

**Should not be there**

- [ ] Any API key stored in the database, in the logged payload, or in code.
- [ ] A registration route, controller or view left over from a starter kit.
- [ ] Tables or columns we did not agree (comments, roles, likes and so on).
- [ ] Edits to a migration that has already run; changes go in a new migration.
- [ ] A blanket guarded-nothing setting on a model, which allows mass assignment of every field.

## Steps checklist

- [x] Confirm scope and clarify open questions
- [x] Database schema
- [x] Scaffolding choice: starter kit and admin auth
- [x] Project setup: Herd, MariaDB connection, .env, Tailwind v4
- [x] Laravel Boost: install and how to review its output
- [x] Routing, controllers, form requests and validation
- [x] AI API integration design
- [x] Scheduled publishing and the Laravel scheduler
- [x] Blade templating and front-end display (includes Markdown rendering)
- [x] Front-end design spec: style, colours, typography, dark mode
- [x] Testing with Pest
- [x] Claude Code fundamentals: CLAUDE.md, skills, slash commands, subagents (includes the .env deny rules)
- [x] Claude Code Cloud and working workflow (includes running without the API key and DB credentials)
- [x] Security and secrets review
- [x] Hosting and deployment of the live site (cPanel, cron available)
- [x] Build order and milestones
- [x] Final export of this log to Markdown for the repository
