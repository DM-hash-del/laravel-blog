<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Models\Tag;
use App\Services\AiAssist\FieldStates;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class AiAssistRequest extends FormRequest
{
    /**
     * The least content a field needs before the AI has enough to work from.
     */
    private const int MinimumTitleLength = 10;

    private const int MinimumExcerptLength = 40;

    private const int MinimumBodyLength = 200;

    private const int MinimumTagCount = 3;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('access-admin');
    }

    /**
     * Get the validation rules for the editor fields sent with Generate / enhance.
     *
     * Every field is optional here: the save rules are separate and never apply.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:65535'],
            'body' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'new_category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
            'new_tags' => ['nullable', 'string', 'max:400'],
            'enhance' => ['nullable', 'array'],
        ];

        foreach (array_diff(FieldStates::Fields, FieldStates::NotEnhanceable) as $field) {
            $rules["enhance.{$field}"] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * Block the request, before anything is sent, unless the AI has something to work from and something to do.
     *
     * The message appears only when Generate / enhance is pressed, never when saving.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->hasEnoughToWorkFrom()) {
                    $validator->errors()->add('ai', 'Add a bit more to work from first: a title of at least '.self::MinimumTitleLength.' characters, an excerpt of '.self::MinimumExcerptLength.', a body of '.self::MinimumBodyLength.', a category, or '.self::MinimumTagCount.' tags.');

                    return;
                }

                if ($this->fieldStates()->requested() === []) {
                    $validator->errors()->add('ai', 'Nothing to generate or enhance: tick Enhance with AI on a field.');
                }
            },
        ];
    }

    /**
     * Get each field's current value in the form the AI payload uses.
     *
     * @return array{title: string, excerpt: string, body: string, category: string, tags: list<string>}
     */
    public function fieldValues(): array
    {
        $categoryName = $this->filled('category_id')
            ? (string) Category::whereKey($this->integer('category_id'))->value('name')
            : trim((string) $this->input('new_category'));

        $tagNames = collect(Tag::whereKey((array) $this->input('tags', []))->orderBy('name')->pluck('name'))
            ->merge(Tag::namesFromList($this->input('new_tags')))
            ->values()
            ->all();

        return [
            'title' => trim((string) $this->input('title')),
            'excerpt' => trim((string) $this->input('excerpt')),
            'body' => trim((string) $this->input('body')),
            'category' => $categoryName,
            'tags' => $tagNames,
        ];
    }

    /**
     * Get each field's state from its value and its Enhance with AI box.
     */
    public function fieldStates(): FieldStates
    {
        $enhance = [];

        foreach (array_diff(FieldStates::Fields, FieldStates::NotEnhanceable) as $field) {
            $enhance[$field] = $this->boolean("enhance.{$field}");
        }

        return FieldStates::from($this->fieldValues(), $enhance);
    }

    /**
     * Get the form input with the suggestions written in, ready to flash back to the editor.
     *
     * Category names become an id, and tag names become existing tag ids or New tags text.
     * The Enhance boxes are left out so they return to their defaults.
     *
     * @param  array<string, mixed>  $suggestions
     * @return array<string, mixed>
     */
    public function inputWith(array $suggestions): array
    {
        $input = $this->except(['_token', '_method', 'enhance']);

        foreach (['title', 'excerpt', 'body'] as $field) {
            if (array_key_exists($field, $suggestions)) {
                $input[$field] = $suggestions[$field];
            }
        }

        if (array_key_exists('category', $suggestions)) {
            $input['category_id'] = $suggestions['category'] === null
                ? null
                : Category::where('name', $suggestions['category'])->value('id');
            $input['new_category'] = null;
        }

        if (array_key_exists('tags', $suggestions)) {
            $matchedIds = [];
            $newNames = [];

            foreach (Tag::namesFromList(implode(',', $suggestions['tags'])) as $name) {
                $tag = Tag::findByName($name);

                if ($tag !== null) {
                    $matchedIds[] = $tag->id;
                } else {
                    $newNames[] = $name;
                }
            }

            $input['tags'] = array_values(array_unique($matchedIds));
            $input['new_tags'] = implode(', ', $newNames);
        }

        return $input;
    }

    /**
     * Get the message shown with the suggestions, noting when no existing category fit.
     *
     * @param  array<string, mixed>  $suggestions
     */
    public function statusMessage(array $suggestions): string
    {
        $message = 'Suggestions added. Review them, then save.';

        if (array_key_exists('category', $suggestions) && $suggestions['category'] === null) {
            $message .= ' No existing category fits this post, so choose one or type a new one.';
        }

        return $message;
    }

    /**
     * Determine whether at least one field has enough content for the AI to work from.
     */
    private function hasEnoughToWorkFrom(): bool
    {
        $values = $this->fieldValues();

        return mb_strlen($values['title']) >= self::MinimumTitleLength
            || mb_strlen($values['excerpt']) >= self::MinimumExcerptLength
            || mb_strlen($values['body']) >= self::MinimumBodyLength
            || $values['category'] !== ''
            || count($values['tags']) >= self::MinimumTagCount;
    }
}
