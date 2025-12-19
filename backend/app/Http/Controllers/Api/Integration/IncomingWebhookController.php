<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\IncomingWebhook;
use App\Models\IncomingWebhookLog;
use App\Models\Module;
use App\Models\ModuleRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncomingWebhookController extends Controller
{
    /**
     * List all incoming webhooks for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = IncomingWebhook::where('user_id', Auth::id())
            ->with(['module:id,name,api_name'])
            ->orderBy('created_at', 'desc');

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $webhooks = $query->get()->map(fn ($webhook) => $this->formatWebhook($webhook));

        return response()->json([
            'data' => $webhooks,
            'available_actions' => [
                IncomingWebhook::ACTION_CREATE => 'Create new record',
                IncomingWebhook::ACTION_UPDATE => 'Update existing record',
                IncomingWebhook::ACTION_UPSERT => 'Create or update record',
            ],
        ]);
    }

    /**
     * Create a new incoming webhook.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'module_id' => ['required', 'exists:modules,id'],
            'field_mapping' => ['required', 'array'],
            'field_mapping.*' => ['nullable', 'string'],
            'action' => ['required', 'in:create,update,upsert'],
            'upsert_field' => ['required_if:action,update,upsert', 'nullable', 'string'],
        ]);

        $webhook = IncomingWebhook::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'token' => IncomingWebhook::generateToken(),
            'module_id' => $validated['module_id'],
            'field_mapping' => $validated['field_mapping'],
            'action' => $validated['action'],
            'upsert_field' => $validated['upsert_field'] ?? null,
            'is_active' => true,
            'received_count' => 0,
        ]);

        return response()->json([
            'message' => 'Incoming webhook created successfully',
            'webhook' => $this->formatWebhook($webhook),
            'url' => $webhook->getUrl(),
            'token' => $webhook->token, // Show once
            'warning' => 'Store this URL securely. The token will not be shown again.',
        ], 201);
    }

    /**
     * Get a specific incoming webhook.
     */
    public function show(int $id): JsonResponse
    {
        $webhook = IncomingWebhook::where('user_id', Auth::id())
            ->with(['module:id,name,api_name'])
            ->findOrFail($id);

        // Get recent logs
        $recentLogs = $webhook->logs()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($log) => $this->formatLog($log));

        // Get stats
        $stats = [
            'total' => $webhook->logs()->count(),
            'success' => $webhook->logs()->where('status', IncomingWebhookLog::STATUS_SUCCESS)->count(),
            'failed' => $webhook->logs()->where('status', IncomingWebhookLog::STATUS_FAILED)->count(),
            'invalid' => $webhook->logs()->where('status', IncomingWebhookLog::STATUS_INVALID)->count(),
        ];

        return response()->json([
            'webhook' => $this->formatWebhook($webhook, true),
            'recent_logs' => $recentLogs,
            'stats' => $stats,
        ]);
    }

    /**
     * Update an incoming webhook.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $webhook = IncomingWebhook::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'module_id' => ['sometimes', 'exists:modules,id'],
            'field_mapping' => ['sometimes', 'array'],
            'field_mapping.*' => ['nullable', 'string'],
            'action' => ['sometimes', 'in:create,update,upsert'],
            'upsert_field' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $webhook->update($validated);

        return response()->json([
            'message' => 'Incoming webhook updated successfully',
            'webhook' => $this->formatWebhook($webhook->fresh(['module:id,name,api_name'])),
        ]);
    }

    /**
     * Delete an incoming webhook.
     */
    public function destroy(int $id): JsonResponse
    {
        $webhook = IncomingWebhook::where('user_id', Auth::id())
            ->findOrFail($id);

        $webhook->delete();

        return response()->json([
            'message' => 'Incoming webhook deleted successfully',
        ]);
    }

    /**
     * Regenerate webhook token.
     */
    public function regenerateToken(int $id): JsonResponse
    {
        $webhook = IncomingWebhook::where('user_id', Auth::id())
            ->findOrFail($id);

        $newToken = IncomingWebhook::generateToken();
        $webhook->update(['token' => $newToken]);

        return response()->json([
            'message' => 'Webhook token regenerated successfully',
            'url' => $webhook->getUrl(),
            'token' => $newToken,
            'warning' => 'Update your integrations with the new URL.',
        ]);
    }

    /**
     * Get webhook logs.
     */
    public function logs(Request $request, int $id): JsonResponse
    {
        $webhook = IncomingWebhook::where('user_id', Auth::id())
            ->findOrFail($id);

        $query = $webhook->logs()
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->paginate($request->integer('per_page', 25));

        $logs->getCollection()->transform(fn ($log) => $this->formatLog($log));

        return response()->json($logs);
    }

    /**
     * Receive webhook payload (public endpoint).
     */
    public function receive(Request $request, string $token): JsonResponse
    {
        $ipAddress = $request->ip();

        $webhook = IncomingWebhook::findByToken($token);

        if (!$webhook) {
            Log::warning('Invalid incoming webhook token', [
                'token_prefix' => substr($token, 0, 10),
                'ip' => $ipAddress,
            ]);

            return response()->json([
                'error' => 'Invalid webhook token',
            ], 404);
        }

        $payload = $request->all();

        if (empty($payload)) {
            IncomingWebhookLog::logInvalid(
                $webhook->id,
                [],
                'Empty payload received',
                $ipAddress
            );

            return response()->json([
                'error' => 'Empty payload',
            ], 400);
        }

        try {
            $recordId = $this->processWebhook($webhook, $payload);

            $webhook->recordReceived();

            IncomingWebhookLog::logSuccess(
                $webhook->id,
                $payload,
                $recordId,
                $ipAddress
            );

            return response()->json([
                'success' => true,
                'record_id' => $recordId,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            IncomingWebhookLog::logInvalid(
                $webhook->id,
                $payload,
                'Validation failed: ' . json_encode($e->errors()),
                $ipAddress
            );

            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            IncomingWebhookLog::logFailed(
                $webhook->id,
                $payload,
                $e->getMessage(),
                $ipAddress
            );

            Log::error('Incoming webhook processing failed', [
                'webhook_id' => $webhook->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Processing failed',
            ], 500);
        }
    }

    /**
     * Process webhook and create/update record.
     */
    protected function processWebhook(IncomingWebhook $webhook, array $payload): ?int
    {
        $mappedData = $webhook->mapData($payload);

        if (empty($mappedData)) {
            throw new \Exception('No fields mapped from payload');
        }

        $module = $webhook->module;

        return DB::transaction(function () use ($webhook, $mappedData, $module) {
            switch ($webhook->action) {
                case IncomingWebhook::ACTION_CREATE:
                    $record = ModuleRecord::create([
                        'module_id' => $module->id,
                        'data' => $mappedData,
                        'created_by' => $webhook->user_id,
                        'updated_by' => $webhook->user_id,
                    ]);
                    return $record->id;

                case IncomingWebhook::ACTION_UPDATE:
                    $record = $this->findRecordForUpdate($webhook, $mappedData);
                    if (!$record) {
                        throw new \Exception("Record not found for update using field: {$webhook->upsert_field}");
                    }
                    $record->update([
                        'data' => array_merge($record->data, $mappedData),
                        'updated_by' => $webhook->user_id,
                    ]);
                    return $record->id;

                case IncomingWebhook::ACTION_UPSERT:
                    $record = $this->findRecordForUpdate($webhook, $mappedData);
                    if ($record) {
                        $record->update([
                            'data' => array_merge($record->data, $mappedData),
                            'updated_by' => $webhook->user_id,
                        ]);
                        return $record->id;
                    } else {
                        $record = ModuleRecord::create([
                            'module_id' => $module->id,
                            'data' => $mappedData,
                            'created_by' => $webhook->user_id,
                            'updated_by' => $webhook->user_id,
                        ]);
                        return $record->id;
                    }

                default:
                    throw new \Exception("Unknown action: {$webhook->action}");
            }
        });
    }

    /**
     * Find record for update based on upsert field.
     */
    protected function findRecordForUpdate(IncomingWebhook $webhook, array $mappedData): ?ModuleRecord
    {
        $upsertField = $webhook->upsert_field;

        if (!$upsertField || !isset($mappedData[$upsertField])) {
            return null;
        }

        return ModuleRecord::where('module_id', $webhook->module_id)
            ->whereJsonContains("data->{$upsertField}", $mappedData[$upsertField])
            ->first();
    }

    /**
     * Format webhook for response.
     */
    protected function formatWebhook(IncomingWebhook $webhook, bool $includeToken = false): array
    {
        $data = [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'description' => $webhook->description,
            'module' => $webhook->module ? [
                'id' => $webhook->module->id,
                'name' => $webhook->module->name,
                'api_name' => $webhook->module->api_name,
            ] : null,
            'field_mapping' => $webhook->field_mapping,
            'action' => $webhook->action,
            'upsert_field' => $webhook->upsert_field,
            'is_active' => $webhook->is_active,
            'url' => $webhook->getUrl(),
            'received_count' => $webhook->received_count,
            'last_received_at' => $webhook->last_received_at?->toIso8601String(),
            'created_at' => $webhook->created_at->toIso8601String(),
            'updated_at' => $webhook->updated_at->toIso8601String(),
        ];

        if ($includeToken) {
            $data['token_prefix'] = substr($webhook->token, 0, 8) . '...';
        }

        return $data;
    }

    /**
     * Format log for response.
     */
    protected function formatLog(IncomingWebhookLog $log): array
    {
        return [
            'id' => $log->id,
            'status' => $log->status,
            'record_id' => $log->record_id,
            'error_message' => $log->error_message,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
