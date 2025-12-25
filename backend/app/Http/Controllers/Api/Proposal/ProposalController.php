<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Proposal;

use App\Domain\Proposal\Repositories\ProposalRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    // Section types
    private const SECTION_TYPES = ['cover', 'text', 'pricing', 'terms', 'signature', 'custom', 'image', 'video', 'table'];

    // Pricing types
    private const PRICING_TYPES = ['fixed', 'hourly', 'recurring', 'usage_based'];

    // Template categories
    private const TEMPLATE_CATEGORIES = ['sales', 'services', 'consulting', 'software', 'other'];

    // Content block categories
    private const CONTENT_BLOCK_CATEGORIES = ['intro', 'about', 'services', 'testimonials', 'team', 'pricing', 'terms', 'other'];

    // Content block types
    private const CONTENT_BLOCK_TYPES = ['text', 'image', 'video', 'table', 'quote', 'list', 'code'];

    public function __construct(
        protected ProposalRepositoryInterface $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->has('status')) {
            $filters['status'] = $request->status;
        }

        if ($request->has('deal_id')) {
            $filters['deal_id'] = $request->integer('deal_id');
        }

        if ($request->has('search')) {
            $filters['search'] = $request->search;
        }

        $result = $this->repository->listProposals(
            filters: $filters,
            perPage: $request->integer('per_page', 25),
            page: $request->integer('page', 1)
        );

        return response()->json($result->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'template_id' => 'nullable|exists:proposal_templates,id',
            'deal_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'cover_page' => 'nullable|array',
            'styling' => 'nullable|array',
            'currency' => 'nullable|string|size:3',
            'valid_until' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'sections' => 'nullable|array',
            'pricing_items' => 'nullable|array',
        ]);

        $validated['created_by'] = auth()->id();
        $proposal = $this->repository->create($validated);

        return response()->json($proposal, 201);
    }

    public function show(int $id): JsonResponse
    {
        $proposal = $this->repository->findByIdAsArray($id);

        if (!$proposal) {
            return response()->json(['message' => 'Proposal not found'], 404);
        }

        return response()->json($proposal);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'cover_page' => 'nullable|array',
            'styling' => 'nullable|array',
            'currency' => 'nullable|string|size:3',
            'valid_until' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'sections' => 'nullable|array',
            'pricing_items' => 'nullable|array',
        ]);

        $proposal = $this->repository->update($id, $validated);

        return response()->json($proposal);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->repository->delete($id);

        return response()->json(['message' => 'Proposal deleted']);
    }

    public function duplicate(int $id): JsonResponse
    {
        $copy = $this->repository->duplicate($id, auth()->id());

        return response()->json($copy, 201);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'message' => 'nullable|string',
        ]);

        $proposal = $this->repository->sendProposal($id, $validated['email']);

        return response()->json([
            'message' => 'Proposal sent',
            'public_url' => url("/proposals/{$proposal['uuid']}")
        ]);
    }

    public function analytics(int $id): JsonResponse
    {
        $analytics = $this->repository->getProposalEngagement($id);

        return response()->json($analytics);
    }

    // Sections
    public function addSection(Request $request, int $proposalId): JsonResponse
    {
        $validated = $request->validate([
            'section_type' => 'required|string|in:' . implode(',', self::SECTION_TYPES),
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'settings' => 'nullable|array',
            'display_order' => 'nullable|integer',
        ]);

        $section = $this->repository->addSection($proposalId, $validated);

        return response()->json($section, 201);
    }

    public function updateSection(Request $request, int $sectionId): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'settings' => 'nullable|array',
            'is_visible' => 'nullable|boolean',
            'is_locked' => 'nullable|boolean',
        ]);

        $section = $this->repository->updateSection($sectionId, $validated);

        return response()->json($section);
    }

    public function deleteSection(int $sectionId): JsonResponse
    {
        $this->repository->deleteSection($sectionId);

        return response()->json(['message' => 'Section deleted']);
    }

    public function reorderSections(Request $request, int $proposalId): JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:proposal_sections,id',
        ]);

        $this->repository->reorderSections($proposalId, $validated['order']);

        return response()->json(['message' => 'Sections reordered']);
    }

    // Pricing Items
    public function addPricingItem(Request $request, int $proposalId): JsonResponse
    {
        $validated = $request->validate([
            'section_id' => 'nullable|exists:proposal_sections,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'nullable|string',
            'unit_price' => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'is_optional' => 'nullable|boolean',
            'pricing_type' => 'nullable|string|in:' . implode(',', self::PRICING_TYPES),
            'billing_frequency' => 'nullable|string',
            'product_id' => 'nullable|integer',
        ]);

        $item = $this->repository->addPricingItem($proposalId, $validated);

        return response()->json($item, 201);
    }

    public function updatePricingItem(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'sometimes|numeric|min:0',
            'unit' => 'nullable|string',
            'unit_price' => 'sometimes|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'is_optional' => 'nullable|boolean',
            'is_selected' => 'nullable|boolean',
        ]);

        $item = $this->repository->updatePricingItem($itemId, $validated);

        return response()->json($item);
    }

    public function deletePricingItem(int $itemId): JsonResponse
    {
        $this->repository->deletePricingItem($itemId);

        return response()->json(['message' => 'Pricing item deleted']);
    }

    // Comments
    public function comments(int $proposalId): JsonResponse
    {
        $comments = $this->repository->getComments($proposalId);

        return response()->json($comments);
    }

    public function addComment(Request $request, int $proposalId): JsonResponse
    {
        $validated = $request->validate([
            'section_id' => 'nullable|exists:proposal_sections,id',
            'comment' => 'required|string',
            'author_email' => 'required|email',
            'author_name' => 'nullable|string',
            'author_type' => 'nullable|string|in:client,internal',
            'reply_to_id' => 'nullable|exists:proposal_comments,id',
        ]);

        $comment = $this->repository->addComment($proposalId, $validated);

        return response()->json($comment, 201);
    }

    public function resolveComment(int $commentId): JsonResponse
    {
        $this->repository->resolveComment($commentId, auth()->id());

        return response()->json(['message' => 'Comment resolved']);
    }

    // Templates
    public function templates(Request $request): JsonResponse
    {
        $filters = ['active_only' => true];

        if ($request->has('category')) {
            $filters['category'] = $request->category;
        }

        $templates = $this->repository->listTemplates($filters);

        return response()->json($templates);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|in:' . implode(',', self::TEMPLATE_CATEGORIES),
            'default_sections' => 'nullable|array',
            'styling' => 'nullable|array',
            'cover_image_url' => 'nullable|url',
        ]);

        $validated['created_by'] = auth()->id();
        $template = $this->repository->createTemplate($validated);

        return response()->json($template, 201);
    }

    public function showTemplate(int $templateId): JsonResponse
    {
        $template = $this->repository->getTemplate($templateId);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return response()->json($template);
    }

    public function updateTemplate(Request $request, int $templateId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|in:' . implode(',', self::TEMPLATE_CATEGORIES),
            'default_sections' => 'nullable|array',
            'styling' => 'nullable|array',
            'cover_image_url' => 'nullable|url',
            'is_active' => 'nullable|boolean',
        ]);

        $template = $this->repository->updateTemplate($templateId, $validated);

        return response()->json($template);
    }

    public function destroyTemplate(int $templateId): JsonResponse
    {
        $this->repository->deleteTemplate($templateId);

        return response()->json(['message' => 'Template deleted']);
    }

    // Content Blocks - These need to be added to repository interface
    // For now, using direct Query Builder until ContentBlock repository is added
    public function contentBlocks(Request $request): JsonResponse
    {
        $query = \Illuminate\Support\Facades\DB::table('proposal_content_blocks')
            ->where('is_active', true);

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $blocks = $query->get()->map(function ($block) {
            $arr = (array) $block;
            if (isset($arr['settings']) && is_string($arr['settings'])) {
                $arr['settings'] = json_decode($arr['settings'], true);
            }
            return $arr;
        })->toArray();

        return response()->json($blocks);
    }

    public function storeContentBlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|in:' . implode(',', self::CONTENT_BLOCK_CATEGORIES),
            'block_type' => 'required|string|in:' . implode(',', self::CONTENT_BLOCK_TYPES),
            'content' => 'required|string',
            'settings' => 'nullable|array',
            'thumbnail_url' => 'nullable|url',
        ]);

        $blockId = \Illuminate\Support\Facades\DB::table('proposal_content_blocks')->insertGetId([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? 'other',
            'block_type' => $validated['block_type'],
            'content' => $validated['content'],
            'settings' => json_encode($validated['settings'] ?? []),
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'is_active' => true,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $block = \Illuminate\Support\Facades\DB::table('proposal_content_blocks')->where('id', $blockId)->first();
        $arr = (array) $block;
        if (isset($arr['settings']) && is_string($arr['settings'])) {
            $arr['settings'] = json_decode($arr['settings'], true);
        }

        return response()->json($arr, 201);
    }

    public function updateContentBlock(Request $request, int $blockId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'nullable|string',
            'block_type' => 'sometimes|string',
            'content' => 'sometimes|string',
            'settings' => 'nullable|array',
            'thumbnail_url' => 'nullable|url',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = ['updated_at' => now()];
        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (isset($validated['category'])) $updateData['category'] = $validated['category'];
        if (isset($validated['block_type'])) $updateData['block_type'] = $validated['block_type'];
        if (isset($validated['content'])) $updateData['content'] = $validated['content'];
        if (isset($validated['settings'])) $updateData['settings'] = json_encode($validated['settings']);
        if (isset($validated['thumbnail_url'])) $updateData['thumbnail_url'] = $validated['thumbnail_url'];
        if (isset($validated['is_active'])) $updateData['is_active'] = $validated['is_active'];

        \Illuminate\Support\Facades\DB::table('proposal_content_blocks')->where('id', $blockId)->update($updateData);

        $block = \Illuminate\Support\Facades\DB::table('proposal_content_blocks')->where('id', $blockId)->first();
        $arr = (array) $block;
        if (isset($arr['settings']) && is_string($arr['settings'])) {
            $arr['settings'] = json_decode($arr['settings'], true);
        }

        return response()->json($arr);
    }

    public function destroyContentBlock(int $blockId): JsonResponse
    {
        \Illuminate\Support\Facades\DB::table('proposal_content_blocks')->where('id', $blockId)->delete();

        return response()->json(['message' => 'Content block deleted']);
    }
}
