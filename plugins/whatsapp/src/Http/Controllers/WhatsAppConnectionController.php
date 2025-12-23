<?php

declare(strict_types=1);

namespace Plugins\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\WhatsApp\Application\Services\WhatsAppApplicationService;

class WhatsAppConnectionController extends Controller
{
    public function __construct(
        private readonly WhatsAppApplicationService $whatsAppService,
    ) {}

    /**
     * List all WhatsApp connections.
     */
    public function index(): JsonResponse
    {
        $connections = $this->whatsAppService->listConnections();

        return response()->json(['data' => $connections]);
    }

    /**
     * Create a new WhatsApp connection.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number_id' => 'required|string|max:100',
            'business_account_id' => 'required|string|max:100',
            'access_token' => 'required|string',
            'webhook_verify_token' => 'nullable|string|max:255',
        ]);

        $connection = $this->whatsAppService->createConnection($validated);

        return response()->json(['data' => $connection], 201);
    }

    /**
     * Get a specific connection.
     */
    public function show(int $connection): JsonResponse
    {
        $data = $this->whatsAppService->getConnection($connection);

        if (!$data) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Update a connection.
     */
    public function update(Request $request, int $connection): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'access_token' => 'sometimes|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $data = $this->whatsAppService->updateConnection($connection, $validated);

        return response()->json(['data' => $data]);
    }

    /**
     * Delete a connection.
     */
    public function destroy(int $connection): JsonResponse
    {
        $this->whatsAppService->deleteConnection($connection);

        return response()->json(null, 204);
    }

    /**
     * Test connection credentials.
     */
    public function test(int $connection): JsonResponse
    {
        $result = $this->whatsAppService->testConnection($connection);

        return response()->json($result);
    }
}
