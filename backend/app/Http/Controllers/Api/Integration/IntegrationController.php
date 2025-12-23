<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Integration;

use App\Application\Services\Integration\IntegrationApplicationService;
use App\Domain\Integration\ValueObjects\IntegrationCategory;
use App\Domain\Integration\ValueObjects\SyncDirection;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly IntegrationApplicationService $service,
    ) {}

    /**
     * List all available integrations grouped by category
     */
    public function index(): JsonResponse
    {
        $integrations = $this->service->listAvailableIntegrations();

        return response()->json([
            'data' => $integrations,
        ]);
    }

    /**
     * Get a specific integration connection
     */
    public function show(string $slug): JsonResponse
    {
        $connection = $this->service->getConnection($slug);

        if (!$connection) {
            return response()->json([
                'error' => 'Integration not found',
            ], 404);
        }

        return response()->json([
            'data' => $connection,
        ]);
    }

    /**
     * Get integrations by category
     */
    public function byCategory(string $category): JsonResponse
    {
        $cat = IntegrationCategory::tryFrom($category);
        if (!$cat) {
            return response()->json([
                'error' => 'Invalid category',
            ], 400);
        }

        $connections = $this->service->getConnectionsByCategory($cat);

        return response()->json([
            'data' => $connections,
        ]);
    }

    /**
     * Initiate OAuth flow for an integration
     */
    public function getAuthorizationUrl(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'redirect_to' => 'nullable|string|url',
        ]);

        try {
            $authUrl = $this->service->initiateOAuthFlow(
                userId: $request->user()->id,
                slug: $slug,
                redirectTo: $request->input('redirect_to'),
            );

            return response()->json([
                'authorization_url' => $authUrl,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle OAuth callback
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        try {
            $result = $this->service->handleOAuthCallback(
                code: $request->input('code'),
                state: $request->input('state'),
                userId: $request->user()->id,
            );

            return response()->json([
                'data' => $result['connection'],
                'redirect_to' => $result['redirect_to'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'OAuth authentication failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Connect with API key
     */
    public function connectApiKey(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'api_key' => 'required_without:credentials|string',
            'api_secret' => 'nullable|string',
            'credentials' => 'required_without:api_key|array',
        ]);

        try {
            $credentials = $request->input('credentials', [
                'api_key' => $request->input('api_key'),
                'api_secret' => $request->input('api_secret'),
            ]);

            $connection = $this->service->connectWithApiKey(
                userId: $request->user()->id,
                slug: $slug,
                credentials: $credentials,
            );

            return response()->json([
                'data' => $connection,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Disconnect an integration
     */
    public function disconnect(string $slug): JsonResponse
    {
        $success = $this->service->disconnect($slug);

        if (!$success) {
            return response()->json([
                'error' => 'Integration not found or already disconnected',
            ], 404);
        }

        return response()->json([
            'message' => 'Integration disconnected successfully',
        ]);
    }

    /**
     * Refresh OAuth token
     */
    public function refreshToken(string $slug): JsonResponse
    {
        $success = $this->service->refreshToken($slug);

        if (!$success) {
            return response()->json([
                'error' => 'Failed to refresh token',
            ], 500);
        }

        return response()->json([
            'message' => 'Token refreshed successfully',
        ]);
    }

    /**
     * Update integration settings
     */
    public function updateSettings(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        $success = $this->service->updateSettings($slug, $request->input('settings'));

        if (!$success) {
            return response()->json([
                'error' => 'Integration not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Settings updated successfully',
        ]);
    }

    /**
     * Get sync logs for an integration
     */
    public function syncLogs(Request $request, string $slug): JsonResponse
    {
        $limit = $request->input('limit', 50);
        $logs = $this->service->getSyncLogs($slug, $limit);

        return response()->json([
            'data' => $logs,
        ]);
    }

    /**
     * Get field mappings for an integration
     */
    public function fieldMappings(Request $request, string $slug): JsonResponse
    {
        $entity = $request->input('entity');
        $mappings = $this->service->getFieldMappings($slug, $entity);

        return response()->json([
            'data' => $mappings,
        ]);
    }

    /**
     * Save field mappings for an integration
     */
    public function saveFieldMappings(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'entity' => 'required|string',
            'mappings' => 'required|array',
            'mappings.*.crm_field' => 'required|string',
            'mappings.*.external_field' => 'required|string',
            'mappings.*.direction' => 'nullable|string|in:push,pull,both',
            'mappings.*.transform' => 'nullable|string',
        ]);

        $success = $this->service->saveFieldMappings(
            slug: $slug,
            crmEntity: $request->input('entity'),
            mappings: $request->input('mappings'),
        );

        if (!$success) {
            return response()->json([
                'error' => 'Integration not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Field mappings saved successfully',
        ]);
    }

    /**
     * Trigger a sync for an integration
     */
    public function triggerSync(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string',
            'direction' => 'nullable|string|in:push,pull,both',
        ]);

        try {
            $direction = SyncDirection::tryFrom($request->input('direction', 'both')) ?? SyncDirection::BOTH;

            $syncLog = $this->service->startSync(
                slug: $slug,
                entityType: $request->input('entity_type'),
                direction: $direction,
            );

            if (!$syncLog) {
                return response()->json([
                    'error' => 'Integration not connected or not found',
                ], 400);
            }

            return response()->json([
                'data' => $syncLog,
                'message' => 'Sync started',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get connection statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->service->getConnectionStats();

        return response()->json([
            'data' => $stats,
        ]);
    }
}
