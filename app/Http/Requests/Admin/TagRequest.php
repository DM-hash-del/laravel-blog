<?php

namespace App\Http\Requests\Admin;

use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class TagRequest extends FormRequest
{
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Check the name and the slug it would produce, reporting any problem on the name field.
     *
     * Skipped when the name already failed its rules, so the admin sees one message.
     * Slugs are set at creation and never change, so the slug clash check runs on create only.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('name')) {
                    return;
                }

                if ($this->slug() === '') {
                    $validator->errors()->add('name', 'The name must contain letters or numbers.');

                    return;
                }

                if ($this->nameIsTaken()) {
                    $validator->errors()->add('name', 'A tag with this name already exists.');

                    return;
                }

                if ($this->isCreating() && Tag::where('slug', $this->slug())->exists()) {
                    $validator->errors()->add('name', "This name's URL is already used by another tag. Choose a different name.");
                }
            },
        ];
    }

    /**
     * Get the validated name with its generated slug, ready to create a tag.
     *
     * Updates save the validated name only, so the slug never changes after creation.
     *
     * @return array{name: string, slug: string}
     */
    public function attributesForCreate(): array
    {
        return [
            'name' => $this->validated('name'),
            'slug' => $this->slug(),
        ];
    }

    /**
     * Determine whether another tag already has this name, ignoring case.
     */
    private function nameIsTaken(): bool
    {
        return Tag::whereRaw('lower(name) = ?', [Str::lower((string) $this->input('name'))])
            ->when($this->route('tag'), fn ($query, Tag $tag) => $query->whereKeyNot($tag->getKey()))
            ->exists();
    }

    /**
     * Determine whether the request creates a new tag rather than updating one.
     */
    private function isCreating(): bool
    {
        return $this->route('tag') === null;
    }

    /**
     * Generate the slug from the submitted name.
     */
    private function slug(): string
    {
        return Str::slug((string) $this->input('name'));
    }
}
