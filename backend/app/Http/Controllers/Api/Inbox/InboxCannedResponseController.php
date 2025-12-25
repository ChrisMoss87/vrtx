<?php

namespace App\Http\Controllers\Api\Inbox;

use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InboxCannedResponseController extends Controller
{
    public function __construct(
        protected InboxConversationRepositoryInterface $conversationRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->filled('inbox_id')) {
            $filters['inbox_id'] = $request->inbox_id;
        }

        if ($request->filled('category')) {
            $filters['category'] = $request->category;
        }

        if ($request->filled('search')) {
            $filters['search'] = $request->search;
        }

        $filters['active_only'] = true;

        $perPage = $request->input('per_page', 100);
        $page = $request->input('page', 1);

        $result = $this->conversationRepository->listCannedResponses($filters, $perPage, $page);

        return response()->json([
            'data' => $result->items,
            'meta' => [
                'current_page' => $result->currentPage,
                'per_page' => $result->perPage,
                'total' => $result->total,
                'last_page' => $result->lastPage,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inbox_id' => 'nullable|exists:shared_inboxes,id',
            'name' => 'required|string|max:255',
            'shortcut' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'attachments' => 'nullable|array',
        ]);

        $response = $this->conversationRepository->createCannedResponse($validated, auth()->id());

        return response()->json(['data' => $response], 201);
    }

    public function show(int $id): JsonResponse
    {
        $response = DB::table('inbox_canned_responses')->where('id', $id)->first();
        if (!$response) {
            return response()->json(['message' => 'Canned response not found'], 404);
        }

        $responseArray = (array) $response;

        // Decode JSON field
        if (isset($responseArray['attachments']) && is_string($responseArray['attachments'])) {
            $responseArray['attachments'] = json_decode($responseArray['attachments'], true);
        }

        // Load inbox
        if (isset($responseArray['inbox_id']) && $responseArray['inbox_id']) {
            $inbox = DB::table('shared_inboxes')->where('id', $responseArray['inbox_id'])->first(['id', 'name']);
            $responseArray['inbox'] = $inbox ? (array) $inbox : null;
        } else {
            $responseArray['inbox'] = null;
        }

        // Load creator
        if (isset($responseArray['created_by'])) {
            $creator = DB::table('users')->where('id', $responseArray['created_by'])->first(['id', 'name']);
            $responseArray['creator'] = $creator ? (array) $creator : null;
        } else {
            $responseArray['creator'] = null;
        }

        return response()->json(['data' => $responseArray]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'inbox_id' => 'nullable|exists:shared_inboxes,id',
            'name' => 'sometimes|string|max:255',
            'shortcut' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:255',
            'body' => 'sometimes|string',
            'attachments' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $response = DB::table('inbox_canned_responses')->where('id', $id)->first();
        if (!$response) {
            return response()->json(['message' => 'Canned response not found'], 404);
        }

        $updated = $this->conversationRepository->updateCannedResponse($id, $validated);

        return response()->json(['data' => $updated]);
    }

    public function destroy(int $id): JsonResponse
    {
        $response = DB::table('inbox_canned_responses')->where('id', $id)->first();
        if (!$response) {
            return response()->json(['message' => 'Canned response not found'], 404);
        }

        $this->conversationRepository->deleteCannedResponse($id);
        return response()->json(null, 204);
    }

    public function render(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'variables' => 'nullable|array',
        ]);

        $response = DB::table('inbox_canned_responses')->where('id', $id)->first();
        if (!$response) {
            return response()->json(['message' => 'Canned response not found'], 404);
        }

        $rendered = $this->conversationRepository->useCannedResponse($id, $validated['variables'] ?? []);

        return response()->json([
            'data' => [
                'subject' => $response->subject,
                'body' => $rendered,
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $query = DB::table('inbox_canned_responses')
            ->where('is_active', true)
            ->whereNotNull('category');

        if ($request->filled('inbox_id')) {
            $query->where('inbox_id', $request->inbox_id);
        } else {
            $query->whereNull('inbox_id');
        }

        $categories = $query->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return response()->json(['data' => $categories]);
    }

    public function findByShortcut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shortcut' => 'required|string',
            'inbox_id' => 'nullable|integer',
        ]);

        $response = $this->conversationRepository->getCannedResponseByShortcut(
            $validated['shortcut'],
            $validated['inbox_id'] ?? null
        );

        if (!$response) {
            return response()->json(['message' => 'Canned response not found'], 404);
        }

        return response()->json(['data' => $response]);
    }
}
