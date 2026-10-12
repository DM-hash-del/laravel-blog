<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Post lists are public; the admin list is still guarded by its route group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for the list's URL query parameters.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
        ];
    }

    /**
     * Get the validated search and sort, defaulting to no search and newest first.
     *
     * @return array{search: ?string, sort: string}
     */
    public function filters(): array
    {
        return [
            'search' => $this->validated('search'),
            'sort' => $this->validated('sort') ?? 'newest',
        ];
    }

    /**
     * Send invalid parameters back to the same list without its query string.
     */
    protected function getRedirectUrl(): string
    {
        return $this->url();
    }
}
