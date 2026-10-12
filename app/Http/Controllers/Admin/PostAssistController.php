<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiAssistRequest;
use App\Models\Post;
use App\Services\AiAssist\AiAssistFailed;
use App\Services\PostAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class PostAssistController extends Controller
{
    /**
     * The editor fields a JSON reply fills in place.
     *
     * @var list<string>
     */
    private const array EditorFields = ['title', 'excerpt', 'body', 'category_id', 'new_category', 'tags', 'new_tags'];

    /**
     * Ask the AI for suggestions and return them to the editor for review.
     *
     * The editor's in-place mode asks for JSON and gets the fields back with nothing
     * flashed to the session; otherwise the editor reloads with them filled in.
     * Nothing is saved, and on failure everything the admin typed is kept.
     */
    public function __invoke(AiAssistRequest $request, PostAssistant $assistant, ?Post $post = null): RedirectResponse|JsonResponse
    {
        try {
            $suggestions = $assistant->suggest($request->user(), $post, $request->fieldValues(), $request->fieldStates());
        } catch (AiAssistFailed $failure) {
            return $request->expectsJson()
                ? response()->json(['message' => $failure->getMessage(), 'errors' => ['ai' => [$failure->getMessage()]]], 422)
                : back()->withInput()->withErrors(['ai' => $failure->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $request->statusMessage($suggestions),
                'input' => Arr::only($request->inputWith($suggestions), self::EditorFields),
            ]);
        }

        return back()
            ->withInput($request->inputWith($suggestions))
            ->with('status', $request->statusMessage($suggestions));
    }
}
