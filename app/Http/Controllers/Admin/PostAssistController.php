<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiAssistRequest;
use App\Models\Post;
use App\Services\AiAssist\AiAssistFailed;
use App\Services\PostAssistant;
use Illuminate\Http\RedirectResponse;

class PostAssistController extends Controller
{
    /**
     * Ask the AI for suggestions and return to the editor with them filled in for review.
     *
     * Nothing is saved: the admin reviews the suggestions and saves as usual.
     * On failure, everything the admin typed is kept.
     */
    public function __invoke(AiAssistRequest $request, PostAssistant $assistant, ?Post $post = null): RedirectResponse
    {
        try {
            $suggestions = $assistant->suggest($request->user(), $post, $request->fieldValues(), $request->fieldStates());
        } catch (AiAssistFailed $failure) {
            return back()->withInput()->withErrors(['ai' => $failure->getMessage()]);
        }

        return back()
            ->withInput($request->inputWith($suggestions))
            ->with('status', $request->statusMessage($suggestions));
    }
}
