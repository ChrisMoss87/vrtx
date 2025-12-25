<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CmsPublicController extends Controller
{
    private const TABLE_PAGES = 'cms_pages';
    private const TABLE_FORMS = 'cms_forms';
    private const TABLE_SUBMISSIONS = 'cms_form_submissions';
    private const TABLE_USERS = 'users';
    private const TABLE_MEDIA = 'cms_media';
    private const TABLE_CATEGORIES = 'cms_categories';
    private const TABLE_TAGS = 'cms_tags';
    private const TABLE_PAGE_CATEGORY = 'cms_category_page';
    private const TABLE_PAGE_TAG = 'cms_page_tag';

    public function page(string $slug, string $type = 'page'): JsonResponse
    {
        $page = DB::table(self::TABLE_PAGES)
            ->where('slug', $slug)
            ->where('type', $type)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->first();

        if (!$page) {
            return response()->json([
                'message' => 'Page not found',
            ], 404);
        }

        $pageArray = (array) $page;

        // Load author
        if ($page->author_id) {
            $author = DB::table(self::TABLE_USERS)
                ->where('id', $page->author_id)
                ->select(['id', 'name'])
                ->first();
            $pageArray['author'] = $author ? (array) $author : null;
        }

        // Load featured image
        if ($page->featured_image_id) {
            $image = DB::table(self::TABLE_MEDIA)
                ->where('id', $page->featured_image_id)
                ->first();
            $pageArray['featured_image'] = $image ? (array) $image : null;
        }

        // Load categories
        $categories = DB::table(self::TABLE_CATEGORIES)
            ->join(self::TABLE_PAGE_CATEGORY, self::TABLE_CATEGORIES . '.id', '=', self::TABLE_PAGE_CATEGORY . '.cms_category_id')
            ->where(self::TABLE_PAGE_CATEGORY . '.cms_page_id', $page->id)
            ->select([self::TABLE_CATEGORIES . '.id', self::TABLE_CATEGORIES . '.name', self::TABLE_CATEGORIES . '.slug'])
            ->get();
        $pageArray['categories'] = array_map(fn($c) => (array) $c, $categories->all());

        // Load tags
        $tags = DB::table(self::TABLE_TAGS)
            ->join(self::TABLE_PAGE_TAG, self::TABLE_TAGS . '.id', '=', self::TABLE_PAGE_TAG . '.cms_tag_id')
            ->where(self::TABLE_PAGE_TAG . '.cms_page_id', $page->id)
            ->select([self::TABLE_TAGS . '.id', self::TABLE_TAGS . '.name', self::TABLE_TAGS . '.slug'])
            ->get();
        $pageArray['tags'] = array_map(fn($t) => (array) $t, $tags->all());

        // Increment view count
        DB::table(self::TABLE_PAGES)->where('id', $page->id)->increment('view_count');

        // Decode JSON fields
        if ($page->content) {
            $pageArray['content'] = json_decode($page->content, true);
        }
        if ($page->settings) {
            $pageArray['settings'] = json_decode($page->settings, true);
        }

        return response()->json([
            'data' => $pageArray,
        ]);
    }

    public function blogPost(string $slug): JsonResponse
    {
        return $this->page($slug, 'blog');
    }

    public function blogPosts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:cms_categories,id',
            'tag_id' => 'nullable|integer|exists:cms_tags,id',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = DB::table(self::TABLE_PAGES)
            ->where('status', 'published')
            ->where('type', 'blog')
            ->whereNotNull('published_at');

        if (isset($validated['category_id'])) {
            $query->whereExists(function ($q) use ($validated) {
                $q->select(DB::raw(1))
                    ->from(self::TABLE_PAGE_CATEGORY)
                    ->whereColumn(self::TABLE_PAGE_CATEGORY . '.cms_page_id', self::TABLE_PAGES . '.id')
                    ->where(self::TABLE_PAGE_CATEGORY . '.cms_category_id', $validated['category_id']);
            });
        }

        if (isset($validated['tag_id'])) {
            $query->whereExists(function ($q) use ($validated) {
                $q->select(DB::raw(1))
                    ->from(self::TABLE_PAGE_TAG)
                    ->whereColumn(self::TABLE_PAGE_TAG . '.cms_page_id', self::TABLE_PAGES . '.id')
                    ->where(self::TABLE_PAGE_TAG . '.cms_tag_id', $validated['tag_id']);
            });
        }

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('published_at');

        // Get total count
        $total = $query->count();

        // Get paginated items
        $perPage = $validated['per_page'] ?? 10;
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;
        $posts = $query->skip($offset)->take($perPage)->get();

        // Enrich with relations
        $items = [];
        foreach ($posts as $post) {
            $postArray = (array) $post;

            // Load author
            if ($post->author_id) {
                $author = DB::table(self::TABLE_USERS)
                    ->where('id', $post->author_id)
                    ->select(['id', 'name'])
                    ->first();
                $postArray['author'] = $author ? (array) $author : null;
            }

            // Load featured image
            if ($post->featured_image_id) {
                $image = DB::table(self::TABLE_MEDIA)
                    ->where('id', $post->featured_image_id)
                    ->first();
                $postArray['featured_image'] = $image ? (array) $image : null;
            }

            // Load categories
            $categories = DB::table(self::TABLE_CATEGORIES)
                ->join(self::TABLE_PAGE_CATEGORY, self::TABLE_CATEGORIES . '.id', '=', self::TABLE_PAGE_CATEGORY . '.cms_category_id')
                ->where(self::TABLE_PAGE_CATEGORY . '.cms_page_id', $post->id)
                ->select([self::TABLE_CATEGORIES . '.id', self::TABLE_CATEGORIES . '.name', self::TABLE_CATEGORIES . '.slug'])
                ->get();
            $postArray['categories'] = array_map(fn($c) => (array) $c, $categories->all());

            // Decode JSON content if needed
            if ($post->content) {
                $postArray['content'] = json_decode($post->content, true);
            }

            $items[] = $postArray;
        }

        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    public function formEmbed(string $slug): JsonResponse
    {
        $form = DB::table(self::TABLE_FORMS)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$form) {
            return response()->json([
                'message' => 'Form not found',
            ], 404);
        }

        // Increment view count
        DB::table(self::TABLE_FORMS)->where('id', $form->id)->increment('view_count');

        $formArray = [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'description' => $form->description,
            'fields' => $form->fields ? json_decode($form->fields, true) : [],
            'submit_button_text' => $form->submit_button_text,
            'settings' => $form->settings ? json_decode($form->settings, true) : null,
        ];

        return response()->json([
            'data' => $formArray,
        ]);
    }

    public function formSubmit(Request $request, string $slug): JsonResponse
    {
        $form = DB::table(self::TABLE_FORMS)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$form) {
            return response()->json([
                'message' => 'Form not found',
            ], 404);
        }

        $fields = json_decode($form->fields, true);

        // Build validation rules from form fields
        $rules = [];
        foreach ($fields as $field) {
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
        $submissionId = DB::table(self::TABLE_SUBMISSIONS)->insertGetId([
            'form_id' => $form->id,
            'data' => json_encode($validated),
            'metadata' => json_encode([
                'submitted_at' => now()->toIso8601String(),
                'referrer' => $request->header('Referer'),
            ]),
            'source_url' => $request->header('Referer'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        // Increment submission count
        DB::table(self::TABLE_FORMS)->where('id', $form->id)->increment('submission_count');

        $response = [
            'message' => $form->success_message ?? 'Thank you for your submission!',
            'submission_id' => $submissionId,
        ];

        if ($form->redirect_url) {
            $response['redirect_url'] = $form->redirect_url;
        }

        return response()->json($response);
    }
}
