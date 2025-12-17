<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use App\Models\CmsForm;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsPublicController extends Controller
{
    /**
     * Get a published page by slug.
     */
    public function page(string $slug, string $type = 'page'): JsonResponse
    {
        $page = CmsPage::where('slug', $slug)
            ->where('type', $type)
            ->published()
            ->with(['author:id,name', 'featuredImage', 'categories:id,name,slug', 'tags:id,name,slug'])
            ->first();

        if (!$page) {
            return response()->json([
                'message' => 'Page not found',
            ], 404);
        }

        // Increment view count
        $page->incrementViewCount();

        return response()->json([
            'data' => $page,
        ]);
    }

    /**
     * Get a blog post by slug.
     */
    public function blogPost(string $slug): JsonResponse
    {
        return $this->page($slug, 'blog');
    }

    /**
     * List published blog posts.
     */
    public function blogPosts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:cms_categories,id',
            'tag_id' => 'nullable|integer|exists:cms_tags,id',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = CmsPage::published()
            ->blogPosts()
            ->with(['author:id,name', 'featuredImage', 'categories:id,name,slug'])
            ->orderByDesc('published_at');

        if (isset($validated['category_id'])) {
            $query->whereHas('categories', fn($q) => $q->where('cms_categories.id', $validated['category_id']));
        }

        if (isset($validated['tag_id'])) {
            $query->whereHas('tags', fn($q) => $q->where('cms_tags.id', $validated['tag_id']));
        }

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        $posts = $query->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'data' => $posts->items(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Get form for embedding.
     */
    public function formEmbed(string $slug): JsonResponse
    {
        $form = CmsForm::where('slug', $slug)
            ->active()
            ->first();

        if (!$form) {
            return response()->json([
                'message' => 'Form not found',
            ], 404);
        }

        // Increment view count
        $form->incrementViewCount();

        return response()->json([
            'data' => [
                'id' => $form->id,
                'name' => $form->name,
                'slug' => $form->slug,
                'description' => $form->description,
                'fields' => $form->fields,
                'submit_button_text' => $form->submit_button_text,
                'settings' => $form->settings,
            ],
        ]);
    }

    /**
     * Submit a form.
     */
    public function formSubmit(Request $request, string $slug): JsonResponse
    {
        $form = CmsForm::where('slug', $slug)
            ->active()
            ->first();

        if (!$form) {
            return response()->json([
                'message' => 'Form not found',
            ], 404);
        }

        // Build validation rules from form fields
        $rules = [];
        foreach ($form->fields as $field) {
            $fieldRules = [];

            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field['type']) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'select':
                case 'radio':
                    if (!empty($field['options'])) {
                        $options = collect($field['options'])->pluck('value')->toArray();
                        $fieldRules[] = 'in:' . implode(',', $options);
                    }
                    break;
                case 'checkbox':
                    $fieldRules[] = 'boolean';
                    break;
            }

            if (!empty($field['validation'])) {
                $fieldRules = array_merge($fieldRules, $field['validation']);
            }

            $rules[$field['name']] = $fieldRules;
        }

        $validated = $request->validate($rules);

        // Create submission
        $submission = $form->submissions()->create([
            'data' => $validated,
            'metadata' => [
                'submitted_at' => now()->toIso8601String(),
                'referrer' => $request->header('Referer'),
            ],
            'source_url' => $request->header('Referer'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Increment submission count
        $form->incrementSubmissionCount();

        // TODO: Process submit action (create lead, send webhook, etc.)
        // This would be handled by a service class

        $response = [
            'message' => $form->success_message ?? 'Thank you for your submission!',
            'submission_id' => $submission->id,
        ];

        if ($form->redirect_url) {
            $response['redirect_url'] = $form->redirect_url;
        }

        return response()->json($response);
    }
}
