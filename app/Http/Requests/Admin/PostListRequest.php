<?php

namespace App\Http\Requests\Admin;

use App\Enums\PostStatus;
use App\Http\Requests\PostListRequest as PublicPostListRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PostListRequest extends PublicPostListRequest
{
    /**
     * Get the validation rules for the admin list's URL query parameters.
     *
     * Adds a status filter, which only the admin list offers.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['nullable', Rule::enum(PostStatus::class)],
        ];
    }

    /**
     * Get the validated search, sort and status, where a null status means every status.
     *
     * @return array{search: ?string, sort: string, status: ?PostStatus}
     */
    public function filters(): array
    {
        return [
            ...parent::filters(),
            'status' => PostStatus::tryFrom((string) $this->validated('status')),
        ];
    }
}
