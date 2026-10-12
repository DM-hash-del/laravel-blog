@php
    $status = $post->exists ? $post->status : \App\Enums\PostStatus::Draft;
    $displayTimezone = config('app.display_timezone');
    $selectedTagIds = array_map('intval', old('tags', $post->tags->pluck('id')->all()));
    $isEmpty = [
        'title' => blank(old('title', $post->title)),
        'excerpt' => blank(old('excerpt', $post->excerpt)),
        'body' => blank(old('body', $post->body)),
        'tags' => $selectedTagIds === [] && blank(old('new_tags')),
    ];
    $scheduledFor = $status === \App\Enums\PostStatus::Scheduled
        ? $post->published_at->timezone($displayTimezone)->format('Y-m-d\TH:i')
        : null;
    $primaryButton = 'min-h-11 rounded-md bg-accent-dark px-4 font-medium text-white hover:bg-accent-dark/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 dark:bg-accent dark:text-gray-950 dark:hover:bg-accent/90 dark:focus-visible:ring-offset-gray-950';
    $secondaryButton = 'min-h-11 rounded-md border border-gray-300 px-4 font-medium text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800';
@endphp

@php($autosaveUrl = ! $post->exists ? route('admin.posts.autosave') : ($status === \App\Enums\PostStatus::Draft ? route('admin.posts.autosave.update', $post) : null))
{{-- Autosave (resources/js/modules/autosave.js) covers new posts and drafts only. --}}
<form method="POST" action="{{ $action }}" class="flex flex-col gap-6" @if ($autosaveUrl) data-autosave data-autosave-url="{{ $autosaveUrl }}" @endif>
    @csrf
    @if ($post->exists)
        @method('PUT')
    @endif

    <div class="flex flex-col gap-1">
        <x-form-field name="title" label="Title" :value="$post->title" required maxlength="255" />
        <x-enhance-toggle field="title" :is-empty="$isEmpty['title']" />
    </div>

    @if ($post->isSlugFrozen())
        <x-form-field name="slug" label="Slug" :value="$post->slug" required maxlength="255" />
    @else
        <p class="-mt-3 text-sm break-all text-gray-600 dark:text-gray-400">
            URL slug: generated from the title until the post is first published{{ $post->exists ? ' (currently '.$post->slug.')' : '' }}.
        </p>
    @endif

    <div class="flex flex-col gap-1.5">
        <label for="category_id" class="text-sm font-medium">Category</label>
        <select id="category_id" name="category_id"
            @error('category_id') aria-invalid="true" aria-describedby="category_id-error" @enderror
            class="min-h-11 rounded-md border border-gray-300 bg-white px-3 text-base focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent dark:border-gray-700 dark:bg-gray-900">
            <option value="">Choose a category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('category_id', $post->category_id) === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id')
            <p id="category_id-error" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <x-form-field name="new_category" label="Or create a new category" maxlength="255"
        hint="Leave the list on ‘Choose a category’ and type a name to create one. Leave both empty and Generate / enhance picks the closest existing category, if one fits; it never creates or changes a category." />

    <fieldset class="flex flex-col gap-1.5">
        <legend class="text-sm font-medium">Tags</legend>
        @if ($tags->isEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-400">No tags yet. Add them in the Tags panel.</p>
        @else
            <div class="mt-1.5 flex flex-wrap gap-2">
                @foreach ($tags as $tag)
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-gray-300 px-3 text-sm has-checked:border-accent has-checked:bg-accent/10 dark:border-gray-700 dark:has-checked:bg-accent/15">
                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTagIds, true))
                            class="size-4 accent-accent-dark dark:accent-accent">
                        {{ $tag->name }}
                    </label>
                @endforeach
            </div>
        @endif
        @error('tags')
            <p class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror
        @error('tags.*')
            <p class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror
    </fieldset>

    <div class="flex flex-col gap-1">
        <x-form-field name="new_tags" label="New tags" maxlength="400"
            hint="Comma separated. Created when you save, unless a tag with the same name already exists." />
        <x-enhance-toggle field="tags" :is-empty="$isEmpty['tags']" />
    </div>

    <div class="flex flex-col gap-1">
        <x-form-textarea name="excerpt" label="Excerpt" :value="$post->excerpt" rows="3"
            hint="A short summary, also used as the page's search description. Needed to publish or schedule." />
        <x-enhance-toggle field="excerpt" :is-empty="$isEmpty['excerpt']" />
    </div>

    <div class="group flex flex-col gap-3">
        <x-form-textarea name="body" label="Body" :value="$post->body" rows="16" class="font-mono text-sm"
            hint="Written in Markdown. Needed to publish or schedule." />
        <x-enhance-toggle field="body" :is-empty="$isEmpty['body']" />

        {{-- Unnamed, so it is never submitted. Hides the preview with CSS alone; resources/js/modules/preview.js renders it live. --}}
        <label for="show-preview" class="flex min-h-11 cursor-pointer items-center gap-2 self-start text-sm font-medium">
            <input id="show-preview" type="checkbox" checked aria-controls="body-preview" class="size-4 accent-accent-dark dark:accent-accent">
            Show preview
        </label>

        <section id="body-preview" aria-labelledby="body-preview-heading" data-live-preview data-preview-url="{{ route('admin.posts.preview') }}"
            class="rounded-md border border-gray-200 bg-gray-50 p-4 break-words group-has-[#show-preview:not(:checked)]:hidden dark:border-gray-800 dark:bg-gray-900">
            <h2 id="body-preview-heading" class="text-sm font-medium">Preview</h2>
            <p data-preview-note class="text-sm text-gray-600 dark:text-gray-400">Shows the body as last saved. Save to update it.</p>

            <div data-preview-content>
                @include('admin.posts._preview-content', ['body' => old('body', $post->body)])
            </div>
        </section>
    </div>

    {{-- With JavaScript (resources/js/modules/schedule.js) this stays hidden until Schedule is pressed, then submits with Confirm. --}}
    <div data-schedule-panel @if ($errors->has('published_at') || old('action') === 'schedule') data-schedule-open @endif class="flex flex-col gap-1.5">
        <label for="published_at" class="text-sm font-medium">Schedule for</label>
        <p id="published_at-hint" class="text-sm text-gray-600 dark:text-gray-400">UK time ({{ $displayTimezone }}). Used only when scheduling.</p>
        <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', $scheduledFor) }}"
            @error('published_at') aria-invalid="true" aria-describedby="published_at-error" @enderror
            class="min-h-11 rounded-md border border-gray-300 bg-white px-3 text-base focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent sm:max-w-xs dark:border-gray-700 dark:bg-gray-900">
        @error('published_at')
            <p id="published_at-error" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror

        <div data-schedule-actions hidden class="mt-2 flex flex-col gap-2 sm:flex-row">
            <button type="submit" name="action" value="schedule" class="{{ $primaryButton }}">
                {{ $status === \App\Enums\PostStatus::Scheduled ? 'Update schedule' : 'Confirm schedule' }}
            </button>
            <button type="button" data-schedule-cancel class="{{ $secondaryButton }}">Cancel</button>
        </div>
    </div>

    <div class="flex flex-col gap-3 border-t border-gray-200 pt-6 sm:flex-row sm:flex-wrap dark:border-gray-800">
        @switch($status)
            @case(\App\Enums\PostStatus::Published)
                <button type="submit" name="action" value="publish" class="{{ $primaryButton }}">Update</button>
                <button type="submit" name="action" value="draft" class="{{ $secondaryButton }}">Unpublish</button>
                @break
            @case(\App\Enums\PostStatus::Scheduled)
                <button type="submit" name="action" value="schedule" data-schedule-trigger class="{{ $primaryButton }}">Update schedule</button>
                <button type="submit" name="action" value="publish" class="{{ $secondaryButton }}">Publish now</button>
                <button type="submit" name="action" value="draft" class="{{ $secondaryButton }}">Unschedule</button>
                @break
            @default
                <button type="submit" name="action" value="draft" class="{{ $secondaryButton }}">Save draft</button>
                <button type="submit" name="action" value="publish" class="{{ $primaryButton }}">Publish</button>
                <button type="submit" name="action" value="schedule" data-schedule-trigger class="{{ $secondaryButton }}">Schedule</button>
        @endswitch
    </div>
    @error('action')
        <p class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror

    {{-- After the save buttons, so pressing Enter in a field saves rather than calling the AI. --}}
    <div class="flex flex-col gap-2 rounded-md border border-accent/40 bg-accent/5 p-4 dark:bg-accent/10">
        <p class="text-sm text-gray-700 dark:text-gray-300">
            Empty fields are generated and ticked fields are enhanced; the rest are used as context. Suggestions are filled in for review and nothing is saved until you save.
        </p>
        @php($assistUrl = $post->exists ? route('admin.posts.assist', $post) : route('admin.posts.assist'))
        <button type="submit" formaction="{{ $assistUrl }}" formnovalidate data-ai-assist data-assist-url="{{ $assistUrl }}"
            @if ($aiRetryAfter > 0) disabled data-retry-after="{{ $aiRetryAfter }}" @endif
            class="{{ $secondaryButton }} self-start bg-white disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-900">
            Generate / enhance with AI
        </button>
        @if ($aiRetryAfter > 0)
            <p data-ai-status role="status" aria-live="polite" class="text-sm text-red-700 dark:text-red-400">Too many AI requests. Try again in {{ $aiRetryAfter }} seconds.</p>
        @else
            @error('ai')
                <p role="alert" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror
            <p data-ai-status role="status" aria-live="polite" hidden class="text-sm"></p>
        @endif
    </div>
</form>

<x-ai-overlay />

<x-toast />
