<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PostRequest extends FormRequest
{
    /**
     * The format sent by the datetime-local input.
     */
    private const string DATE_TIME_FORMAT = 'Y-m-d\TH:i';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('access-admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A draft needs only a title and category; publishing and scheduling also
     * need the excerpt and body. The slug is editable only once frozen.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'action' => ['required', Rule::in(['draft', 'publish', 'schedule'])],
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'required_without:new_category', 'integer', 'exists:categories,id'],
            'new_category' => ['nullable', 'required_without:category_id', 'prohibits:category_id', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'excerpt' => ['nullable', 'string', 'max:65535', 'required_if:action,publish,schedule'],
            'body' => ['nullable', 'string', 'required_if:action,publish,schedule'],
            'published_at' => ['nullable', 'required_if:action,schedule', 'date_format:'.self::DATE_TIME_FORMAT],
        ];

        if ($this->editedPost()?->isSlugFrozen()) {
            $rules['slug'] = [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('posts', 'slug')->ignore($this->editedPost()),
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required_without' => 'Choose a category or type a new one.',
            'new_category.required_without' => 'Choose a category or type a new one.',
            'new_category.prohibits' => 'Choose an existing category or type a new one, not both.',
            'excerpt.required_if' => 'An excerpt is needed to publish or schedule.',
            'body.required_if' => 'A body is needed to publish or schedule.',
            'published_at.required_if' => 'Choose a date and time to schedule.',
            'slug.regex' => 'The slug may contain only lowercase letters, numbers and single hyphens.',
            'slug.unique' => 'Another post, possibly in the trash, already uses this slug.',
        ];
    }

    /**
     * Check the slug the title would produce and that a schedule is in the future.
     *
     * Each check is skipped when its field already failed its rules.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('title') || $this->editedPost()?->isSlugFrozen()) {
                    return;
                }

                $slug = Str::slug((string) $this->input('title'));

                if ($slug === '') {
                    $validator->errors()->add('title', 'The title must contain letters or numbers.');

                    return;
                }

                $slugIsTaken = Post::withTrashed()
                    ->where('slug', $slug)
                    ->when($this->editedPost(), fn ($query, Post $post) => $query->whereKeyNot($post->getKey()))
                    ->exists();

                if ($slugIsTaken) {
                    $validator->errors()->add('title', "Another post, possibly in the trash, already uses this title's URL.");
                }
            },
            function (Validator $validator): void {
                if ($this->newCategoryName() === null || $validator->errors()->has('new_category')) {
                    return;
                }

                $error = Category::nameError($this->newCategoryName());

                if ($error !== null) {
                    $validator->errors()->add('new_category', $error);
                }
            },
            function (Validator $validator): void {
                if ($this->action() !== 'schedule' || $validator->errors()->has('published_at')) {
                    return;
                }

                if (! $this->scheduledFor()->isFuture()) {
                    $validator->errors()->add('published_at', 'The scheduled time must be in the future.');
                }
            },
        ];
    }

    /**
     * Get the validated post fields to fill, including the slug only when it is editable.
     *
     * The category id is absent when a new category is being created instead.
     *
     * @return array{title: string, category_id?: int, excerpt: ?string, body: ?string, slug?: string}
     */
    public function postAttributes(): array
    {
        $fields = ['title', 'excerpt', 'body', 'slug'];

        if ($this->newCategoryName() === null) {
            $fields[] = 'category_id';
        }

        return $this->safe()->only($fields);
    }

    /**
     * Get the name of the category to create for the post, or null when an existing one was chosen.
     */
    public function newCategoryName(): ?string
    {
        return $this->filled('new_category') ? (string) $this->input('new_category') : null;
    }

    /**
     * Get the validated tag ids.
     *
     * @return array<int, int>
     */
    public function tagIds(): array
    {
        return array_map('intval', $this->validated('tags') ?? []);
    }

    /**
     * Get the button the admin pressed: draft, publish or schedule.
     */
    public function action(): string
    {
        return (string) $this->input('action');
    }

    /**
     * Get the scheduled time, entered in the display timezone, converted to UTC.
     */
    public function scheduledFor(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            '!'.self::DATE_TIME_FORMAT,
            (string) $this->input('published_at'),
            config('app.display_timezone'),
        )->utc();
    }

    /**
     * Get the post being updated, or null when creating one.
     */
    private function editedPost(): ?Post
    {
        return $this->route('post');
    }
}
