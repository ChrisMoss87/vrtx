<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Proposal;

use App\Application\Services\Proposal\ProposalApplicationService;
use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\ProposalComment;
use App\Models\ProposalContentBlock;
use App\Models\ProposalPricingItem;
use App\Models\ProposalSection;
use App\Models\ProposalTemplate;
use App\Services\Proposal\ProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function __construct(
        protected ProposalApplicationService $proposalApplicationService,
        protected ProposalService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Proposal::with(['template', 'createdBy', 'assignedTo']);

        if ($request->has('status')) {
            $query->status($request->status);
        }

        if ($request->has('deal_id')) {
            $query->forDeal($request->integer('deal_id'));
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ilike', '%' . $request->search . '%')
                  ->orWhere('proposal_number', 'ilike', '%' . $request->search . '%');
            });
        }

        $proposals = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($proposals);
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

        $proposal = $this->service->create($validated);

        return response()->json($proposal, 201);
    }

    public function show(Proposal $proposal): JsonResponse
    {
        $proposal->load([
            'template',
            'createdBy',
            'assignedTo',
            'sections',
            'pricingItems',
            'comments.resolvedBy',
        ]);

        return response()->json($proposal);
    }

    public function update(Request $request, Proposal $proposal): JsonResponse
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

        $proposal = $this->service->update($proposal, $validated);

        return response()->json($proposal);
    }

    public function destroy(Proposal $proposal): JsonResponse
    {
        $this->service->delete($proposal);

        return response()->json(['message' => 'Proposal deleted']);
    }

    public function duplicate(Proposal $proposal): JsonResponse
    {
        $copy = $this->service->duplicate($proposal);

        return response()->json($copy, 201);
    }

    public function send(Request $request, Proposal $proposal): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'message' => 'nullable|string',
        ]);

        $this->service->send($proposal, $validated['email'], $validated['message'] ?? null);

        return response()->json(['message' => 'Proposal sent', 'public_url' => $proposal->getPublicUrl()]);
    }

    public function analytics(Proposal $proposal): JsonResponse
    {
        $analytics = $this->service->getAnalytics($proposal);

        return response()->json($analytics);
    }

    // Sections
    public function addSection(Request $request, Proposal $proposal): JsonResponse
    {
        $validated = $request->validate([
            'section_type' => 'required|string|in:' . implode(',', ProposalSection::TYPES),
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'settings' => 'nullable|array',
            'display_order' => 'nullable|integer',
        ]);

        $section = $this->service->addSection($proposal, $validated);

        return response()->json($section, 201);
    }

    public function updateSection(Request $request, ProposalSection $proposalSection): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'settings' => 'nullable|array',
            'is_visible' => 'nullable|boolean',
            'is_locked' => 'nullable|boolean',
        ]);

        $section = $this->service->updateSection($proposalSection, $validated);

        return response()->json($section);
    }

    public function deleteSection(ProposalSection $proposalSection): JsonResponse
    {
        $this->service->deleteSection($proposalSection);

        return response()->json(['message' => 'Section deleted']);
    }

    public function reorderSections(Request $request, Proposal $proposal): JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:proposal_sections,id',
        ]);

        $this->service->reorderSections($proposal, $validated['order']);

        return response()->json(['message' => 'Sections reordered']);
    }

    // Pricing Items
    public function addPricingItem(Request $request, Proposal $proposal): JsonResponse
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
            'pricing_type' => 'nullable|string|in:' . implode(',', ProposalPricingItem::PRICING_TYPES),
            'billing_frequency' => 'nullable|string',
            'product_id' => 'nullable|integer',
        ]);

        $item = $this->service->addPricingItem($proposal, $validated);

        return response()->json($item, 201);
    }

    public function updatePricingItem(Request $request, ProposalPricingItem $proposalPricingItem): JsonResponse
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

        $item = $this->service->updatePricingItem($proposalPricingItem, $validated);

        return response()->json($item);
    }

    public function deletePricingItem(ProposalPricingItem $proposalPricingItem): JsonResponse
    {
        $this->service->deletePricingItem($proposalPricingItem);

        return response()->json(['message' => 'Pricing item deleted']);
    }

    // Comments
    public function comments(Proposal $proposal): JsonResponse
    {
        $comments = $proposal->comments()
            ->with(['section', 'replyTo', 'replies', 'resolvedBy'])
            ->topLevel()
            ->get();

        return response()->json($comments);
    }

    public function addComment(Request $request, Proposal $proposal): JsonResponse
    {
        $validated = $request->validate([
            'section_id' => 'nullable|exists:proposal_sections,id',
            'comment' => 'required|string',
            'author_email' => 'required|email',
            'author_name' => 'nullable|string',
            'author_type' => 'nullable|string|in:client,internal',
            'reply_to_id' => 'nullable|exists:proposal_comments,id',
        ]);

        $comment = $this->service->addComment($proposal, $validated);

        return response()->json($comment, 201);
    }

    public function resolveComment(ProposalComment $proposalComment): JsonResponse
    {
        $this->service->resolveComment($proposalComment, auth()->id());

        return response()->json(['message' => 'Comment resolved']);
    }

    // Templates
    public function templates(Request $request): JsonResponse
    {
        $query = ProposalTemplate::active()->with('createdBy');

        if ($request->has('category')) {
            $query->category($request->category);
        }

        $templates = $query->orderBy('name')->get();

        return response()->json($templates);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|in:' . implode(',', ProposalTemplate::CATEGORIES),
            'default_sections' => 'nullable|array',
            'styling' => 'nullable|array',
            'cover_image_url' => 'nullable|url',
        ]);

        $validated['created_by'] = auth()->id();

        $template = ProposalTemplate::create($validated);

        return response()->json($template, 201);
    }

    public function showTemplate(ProposalTemplate $proposalTemplate): JsonResponse
    {
        return response()->json($proposalTemplate);
    }

    public function updateTemplate(Request $request, ProposalTemplate $proposalTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|in:' . implode(',', ProposalTemplate::CATEGORIES),
            'default_sections' => 'nullable|array',
            'styling' => 'nullable|array',
            'cover_image_url' => 'nullable|url',
            'is_active' => 'nullable|boolean',
        ]);

        $proposalTemplate->update($validated);

        return response()->json($proposalTemplate);
    }

    public function destroyTemplate(ProposalTemplate $proposalTemplate): JsonResponse
    {
        $proposalTemplate->delete();

        return response()->json(['message' => 'Template deleted']);
    }

    // Content Blocks
    public function contentBlocks(Request $request): JsonResponse
    {
        $blocks = $this->service->getContentBlocks($request->category);

        return response()->json($blocks);
    }

    public function storeContentBlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|in:' . implode(',', ProposalContentBlock::CATEGORIES),
            'block_type' => 'required|string|in:' . implode(',', ProposalContentBlock::TYPES),
            'content' => 'required|string',
            'settings' => 'nullable|array',
            'thumbnail_url' => 'nullable|url',
        ]);

        $validated['created_by'] = auth()->id();

        $block = ProposalContentBlock::create($validated);

        return response()->json($block, 201);
    }

    public function updateContentBlock(Request $request, ProposalContentBlock $proposalContentBlock): JsonResponse
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

        $proposalContentBlock->update($validated);

        return response()->json($proposalContentBlock);
    }

    public function destroyContentBlock(ProposalContentBlock $proposalContentBlock): JsonResponse
    {
        $proposalContentBlock->delete();

        return response()->json(['message' => 'Content block deleted']);
    }
}
