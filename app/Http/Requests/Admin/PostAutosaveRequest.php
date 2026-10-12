<?php

namespace App\Http\Requests\Admin;

use App\Enums\PostStatus;
use Illuminate\Validation\Validator;

/**
 * Autosave: the Save draft rules, applied automatically while the admin edits a draft or new post.
 *
 * It always saves as a draft, never with a date, and never creates a category or
 * tag from the New category or New tags fields; those wait for a manual save.
 */
class PostAutosaveRequest extends PostRequest
{
    /**
     * Force a plain draft save, whatever buttons or fields the form carried.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => 'draft',
            'new_category' => null,
            'new_tags' => null,
            'published_at' => null,
        ]);
    }

    /**
     * Add to the Save draft checks: an existing post must still be a draft.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $post = $this->editedPost();

                if ($post !== null && $post->status !== PostStatus::Draft) {
                    $validator->errors()->add('post', 'Only drafts are autosaved.');
                }
            },
        ];
    }
}
