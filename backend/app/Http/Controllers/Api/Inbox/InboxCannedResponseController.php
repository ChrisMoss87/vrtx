<?php

namespace App\Http\Controllers\Api\Inbox;

use App\Http\Controllers\Controller;
use App\Models\InboxCannedResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InboxCannedResponseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InboxCannedResponse::with('creator:id,name')
            ->active();

        if ($request->filled('inbox_id')) {
            $query->forInbox($request->inbox_id);
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('shortcut', 'ilike', "%{$search}%")
                  ->orWhere('body', 'ilike', "%{$search}%");
            });
        }

        $responses = $query->orderBy('name')->get();

        return response()->json(['data' => $responses]);
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

        $validated['created_by'] = auth()->id();

        $response = InboxCannedResponse::create($validated);

        return response()->json(['data' => $response], 201);
    }

    public function show(InboxCannedResponse $inboxCannedResponse): JsonResponse
    {
        $inboxCannedResponse->load(['inbox:id,name', 'creator:id,name']);
        return response()->json(['data' => $inboxCannedResponse]);
    }

    public function update(Request $request, InboxCannedResponse $inboxCannedResponse): JsonResponse
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

        $inboxCannedResponse->update($validated);

        return response()->json(['data' => $inboxCannedResponse]);
    }

    public function destroy(InboxCannedResponse $inboxCannedResponse): JsonResponse
    {
        $inboxCannedResponse->delete();
        return response()->json(null, 204);
    }

    public function render(Request $request, InboxCannedResponse $inboxCannedResponse): JsonResponse
    {
        $validated = $request->validate([
            'variables' => 'nullable|array',
        ]);

        $rendered = $inboxCannedResponse->render($validated['variables'] ?? []);

        // Increment use count
        $inboxCannedResponse->incrementUseCount();

        return response()->json([
            'data' => [
                'subject' => $inboxCannedResponse->subject,
                'body' => $rendered,
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $query = InboxCannedResponse::active();

        if ($request->filled('inbox_id')) {
            $query->forInbox($request->inbox_id);
        }

        $categories = $query->whereNotNull('category')
            ->distinct()
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

        $query = InboxCannedResponse::active()
            ->byShortcut($validated['shortcut']);

        if ($validated['inbox_id'] ?? null) {
            $query->forInbox($validated['inbox_id']);
        }

        $response = $query->first();

        if (!$response) {
            return response()->json(['message' => 'Canned response not found'], 404);
        }

        return response()->json(['data' => $response]);
    }
}
