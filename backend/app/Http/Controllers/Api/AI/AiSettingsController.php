<?php

namespace App\Http\Controllers\Api\AI;

use App\Application\Services\AI\AIApplicationService;
use App\Http\Controllers\Controller;
use App\Services\AI\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AiSettingsController extends Controller
{
    public function __construct(
        protected AIApplicationService $aiApplicationService,
        protected AiService $aiService
    ) {}

    /**
     * Get AI settings
     */
    public function index(): JsonResponse
    {
        $settings = $this->aiService->getSettings();

        if (!$settings) {
            return response()->json([
                'settings' => null,
                'is_configured' => false,
            ]);
        }

        return response()->json([
            'settings' => [
                'id' => $settings->id,
                'is_enabled' => $settings->is_enabled,
                'provider' => $settings->provider,
                'model' => $settings->model,
                'has_api_key' => (bool) $settings->encrypted_api_key,
                'max_tokens' => $settings->max_tokens,
                'temperature' => (float) $settings->temperature,
                'monthly_budget_cents' => $settings->monthly_budget_cents,
                'monthly_usage_cents' => $settings->monthly_usage_cents,
                'budget_reset_day' => $settings->budget_reset_day,
                'features' => $settings->features,
            ],
            'is_configured' => $settings->is_enabled && $settings->getDecryptedApiKey(),
            'available_providers' => AiSetting::PROVIDERS,
            'available_models' => AiSetting::MODELS,
        ]);
    }

    /**
     * Update AI settings
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_enabled' => 'sometimes|boolean',
            'provider' => 'sometimes|in:' . implode(',', array_keys(AiSetting::PROVIDERS)),
            'model' => 'sometimes|string',
            'api_key' => 'sometimes|nullable|string',
            'max_tokens' => 'sometimes|integer|min:100|max:8000',
            'temperature' => 'sometimes|numeric|min:0|max:2',
            'monthly_budget_cents' => 'sometimes|nullable|integer|min:0',
            'budget_reset_day' => 'sometimes|integer|min:1|max:28',
            'features' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $settings = DB::table('ai_settings')->first() ?? new AiSetting();

        $data = $request->only([
            'is_enabled',
            'provider',
            'model',
            'max_tokens',
            'temperature',
            'monthly_budget_cents',
            'budget_reset_day',
            'features',
        ]);

        // Handle API key separately (encrypted)
        if ($request->has('api_key') && $request->api_key) {
            $settings->setApiKey($request->api_key);
        }

        $settings->fill($data);
        $settings->save();

        return response()->json([
            'message' => 'AI settings updated successfully',
            'settings' => [
                'id' => $settings->id,
                'is_enabled' => $settings->is_enabled,
                'provider' => $settings->provider,
                'model' => $settings->model,
                'has_api_key' => (bool) $settings->encrypted_api_key,
                'max_tokens' => $settings->max_tokens,
                'temperature' => (float) $settings->temperature,
                'monthly_budget_cents' => $settings->monthly_budget_cents,
                'monthly_usage_cents' => $settings->monthly_usage_cents,
            ],
        ]);
    }

    /**
     * Get usage statistics
     */
    public function usage(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        return response()->json([
            'current_month' => $this->aiService->getCurrentMonthUsage(),
            'statistics' => $this->aiService->getUsageStats($startDate, $endDate),
        ]);
    }

    /**
     * Test AI connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            if (!$this->aiService->isEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI is not enabled or API key is missing',
                ], 400);
            }

            $response = $this->aiService->complete(
                [
                    ['role' => 'user', 'content' => 'Say "Connection successful" and nothing else.'],
                ],
                'connection_test',
                50,
                0
            );

            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'model' => $response['model'],
                'response' => $response['content'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get prompts
     */
    public function prompts(): JsonResponse
    {
        $prompts = AiPrompt::orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json([
            'prompts' => $prompts,
        ]);
    }

    /**
     * Create/Update prompt
     */
    public function savePrompt(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'sometimes|exists:ai_prompts,id',
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'system_prompt' => 'required|string',
            'user_prompt_template' => 'required|string',
            'variables' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $prompt = $request->id
            ? AiPrompt::findOrFail($request->id)
            : new AiPrompt();

        $prompt->fill($request->only([
            'name',
            'slug',
            'category',
            'system_prompt',
            'user_prompt_template',
            'variables',
            'is_active',
        ]));
        $prompt->save();

        return response()->json([
            'message' => 'Prompt saved successfully',
            'prompt' => $prompt,
        ]);
    }

    /**
     * Delete prompt
     */
    public function deletePrompt(int $id): JsonResponse
    {
        $prompt = DB::table('ai_prompts')->where('id', $id)->first();
        $prompt->delete();

        return response()->json([
            'message' => 'Prompt deleted successfully',
        ]);
    }
}
