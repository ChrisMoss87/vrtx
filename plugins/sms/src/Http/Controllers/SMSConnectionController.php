<?php

declare(strict_types=1);

namespace Plugins\SMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\SMS\Application\Services\SMSApplicationService;

class SMSConnectionController extends Controller
{
    public function __construct(
        private readonly SMSApplicationService $smsService,
    ) {}

    /**
     * List all SMS connections.
     */
    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', false);
        $connections = $this->smsService->listConnections($activeOnly);

        return response()->json(['data' => $connections]);
    }

    /**
     * Create a new SMS connection.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|in:twilio,vonage,messagebird',
            'phone_number' => 'required|string|max:50',
            'account_sid' => 'required|string',
            'auth_token' => 'required|string',
            'messaging_service_sid' => 'nullable|string|max:100',
            'daily_limit' => 'nullable|integer|min:1',
            'monthly_limit' => 'nullable|integer|min:1',
        ]);

        $connection = $this->smsService->createConnection($validated);

        return response()->json(['data' => $connection], 201);
    }

    /**
     * Get a specific connection.
     */
    public function show(int $connection): JsonResponse
    {
        $data = $this->smsService->getConnection($connection);

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
            'auth_token' => 'sometimes|string',
            'status' => 'sometimes|in:active,inactive',
            'daily_limit' => 'sometimes|integer|min:1',
            'monthly_limit' => 'sometimes|integer|min:1',
        ]);

        $data = $this->smsService->updateConnection($connection, $validated);

        return response()->json(['data' => $data]);
    }

    /**
     * Delete a connection.
     */
    public function destroy(int $connection): JsonResponse
    {
        $this->smsService->deleteConnection($connection);

        return response()->json(null, 204);
    }

    /**
     * Test connection credentials.
     */
    public function test(int $connection): JsonResponse
    {
        $result = $this->smsService->testConnection($connection);

        return response()->json($result);
    }

    /**
     * Get connection usage statistics.
     */
    public function usage(int $connection): JsonResponse
    {
        $data = $this->smsService->getConnection($connection);

        if (!$data) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        // Get usage from repository
        $usage = app(\Plugins\SMS\Domain\Repositories\SMSRepositoryInterface::class)
            ->getConnectionUsage($connection);

        return response()->json(['data' => $usage]);
    }
}
