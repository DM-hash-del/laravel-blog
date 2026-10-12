<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostPreviewRequest;
use Illuminate\Http\JsonResponse;

class PostPreviewController extends Controller
{
    /**
     * Render the editor's body preview through the same partial the editor page uses.
     */
    public function __invoke(PostPreviewRequest $request): JsonResponse
    {
        return response()->json([
            'html' => view('admin.posts._preview-content', ['body' => $request->validated('body')])->render(),
        ]);
    }
}
