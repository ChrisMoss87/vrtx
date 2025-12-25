<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Domain\CMS\Entities\CmsTemplate;
use App\Domain\CMS\Repositories\CmsTemplateRepositoryInterface;
use App\Domain\CMS\ValueObjects\TemplateType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsTemplateController extends Controller
{
    private const TABLE_TEMPLATES = 'cms_templates';
    private const TABLE_USERS = 'users';
    private const TABLE_PAGES = 'cms_pages';

    public function __construct(
        private readonly CmsTemplateRepositoryInterface $templateRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string|in:page,email,form,landing,blog,partial',
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = [];
        if (isset($validated['type'])) {
            $filters['type'] = $validated['type'];
        }
        if (isset($validated['is_active'])) {
            $filters['is_active'] = $validated['is_active'];
        }
        if (isset($validated['search'])) {
            $filters['search'] = $validated['search'];
        }
        $filters['sort_by'] = 'name';
        $filters['sort_dir'] = 'asc';

        $perPage = $validated['per_page'] ?? 25;
        $page = $request->integer('page', 1);

        $result = $this->templateRepository->paginate($filters, $perPage, $page);

        // Enrich with relations
        $items = [];
        foreach ($result->items as $item) {
            // Load creator
            if ($item['created_by']) {
                $creator = DB::table(self::TABLE_USERS)
                    ->where('id', $item['created_by'])
                    ->select(['id', 'name'])
                    ->first();
                $item['creator'] = $creator ? (array) $creator : null;
            }

            // Count pages
            $pagesCount = DB::table(self::TABLE_PAGES)
                ->where('template_id', $item['id'])
                ->count();
            $item['pages_count'] = $pagesCount;

            $items[] = $item;
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $result->currentPage,
                'last_page' => $result->lastPage,
                'per_page' => $result->perPage,
                'total' => $result->total,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_templates,slug',
            'description' => 'nullable|string',
            'type' => 'required|string|in:page,email,form,landing,blog,partial',
            'content' => 'nullable|array',
            'settings' => 'nullable|array',
            'thumbnail' => 'nullable|string|max:255',
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        // Ensure unique slug
        $originalSlug = $slug;
        $counter = 1;
        while ($this->templateRepository->findBySlug($slug) !== null) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $template = CmsTemplate::create(
            name: $validated['name'],
            slug: $slug,
            type: TemplateType::from($validated['type']),
            createdBy: Auth::id(),
        );

        // Save with additional fields using DB for flexibility
        $templateId = DB::table(self::TABLE_TEMPLATES)->insertGetId([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'content' => isset($validated['content']) ? json_encode($validated['content']) : null,
            'settings' => isset($validated['settings']) ? json_encode($validated['settings']) : null,
            'thumbnail' => $validated['thumbnail'] ?? null,
            'is_active' => true,
            'is_system' => false,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateArray = $this->templateRepository->findByIdAsArray($templateId);

        return response()->json([
            'data' => $templateArray,
            'message' => 'Template created successfully',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $templateArray = $this->templateRepository->findByIdAsArray($id);

        if (!$templateArray) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Load creator
        if ($templateArray['created_by']) {
            $creator = DB::table(self::TABLE_USERS)
                ->where('id', $templateArray['created_by'])
                ->select(['id', 'name'])
                ->first();
            $templateArray['creator'] = $creator ? (array) $creator : null;
        }

        // Count pages
        $pagesCount = DB::table(self::TABLE_PAGES)
            ->where('template_id', $id)
            ->count();
        $templateArray['pages_count'] = $pagesCount;

        return response()->json([
            'data' => $templateArray,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = $this->templateRepository->findById($id);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_templates,slug,' . $id,
            'description' => 'nullable|string',
            'type' => 'sometimes|string|in:page,email,form,landing,blog,partial',
            'content' => 'nullable|array',
            'settings' => 'nullable|array',
            'thumbnail' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = [];
        foreach ($validated as $key => $value) {
            if ($key === 'content' || $key === 'settings') {
                $updateData[$key] = is_array($value) ? json_encode($value) : $value;
            } else {
                $updateData[$key] = $value;
            }
        }
        $updateData['updated_at'] = now();

        DB::table(self::TABLE_TEMPLATES)->where('id', $id)->update($updateData);

        $templateArray = $this->templateRepository->findByIdAsArray($id);

        return response()->json([
            'data' => $templateArray,
            'message' => 'Template updated successfully',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $template = $this->templateRepository->findById($id);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Check if it's a system template
        if ($template->isSystem()) {
            return response()->json([
                'message' => 'System templates cannot be deleted',
            ], 422);
        }

        $this->templateRepository->delete($id);

        return response()->json([
            'message' => 'Template deleted successfully',
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $template = $this->templateRepository->findById($id);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $slug = $template->getSlug() . '-copy';
        $originalSlug = $slug;
        $counter = 1;
        while ($this->templateRepository->findBySlug($slug) !== null) {
            $slug = $originalSlug . '-' . $counter++;
        }

        // Use DB to duplicate with all fields
        $templateRecord = DB::table(self::TABLE_TEMPLATES)->where('id', $id)->first();
        $templateData = (array) $templateRecord;
        unset($templateData['id'], $templateData['created_at'], $templateData['updated_at'], $templateData['deleted_at']);
        $templateData['name'] = $template->getName() . ' (Copy)';
        $templateData['slug'] = $slug;
        $templateData['is_system'] = false;
        $templateData['created_by'] = Auth::id();
        $templateData['created_at'] = now();
        $templateData['updated_at'] = now();

        $newTemplateId = DB::table(self::TABLE_TEMPLATES)->insertGetId($templateData);
        $templateArray = $this->templateRepository->findByIdAsArray($newTemplateId);

        return response()->json([
            'data' => $templateArray,
            'message' => 'Template duplicated successfully',
        ], 201);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $template = $this->templateRepository->findById($id);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $validated = $request->validate([
            'data' => 'nullable|array',
        ]);

        return response()->json([
            'data' => [
                'content' => $template->getContent(),
                'settings' => $template->getSettings(),
                'preview_data' => $validated['data'] ?? [],
            ],
        ]);
    }
}
