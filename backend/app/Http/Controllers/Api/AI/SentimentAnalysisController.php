<?php

namespace App\Http\Controllers\Api\AI;

use App\Application\Services\AI\AIApplicationService;
use App\Http\Controllers\Controller;
use App\Services\AI\SentimentAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SentimentAnalysisController extends Controller
{
    public function __construct(
        protected AIApplicationService $aiApplicationService,
        protected SentimentAnalysisService $sentimentService
    ) {}

    /**
     * Analyze text sentiment
     */
    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:10000',
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
            'record_module' => 'sometimes|nullable|string',
            'record_id' => 'sometimes|nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $score = $this->sentimentService->analyze(
                $request->text,
                $request->entity_type,
                $request->entity_id,
                $request->record_module,
                $request->record_id
            );

            if (!$score) {
                return response()->json([
                    'error' => 'Sentiment analysis is not available',
                ], 400);
            }

            return response()->json([
                'sentiment' => $this->formatScore($score),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to analyze sentiment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze email sentiment
     */
    public function analyzeEmail(int $emailId): JsonResponse
    {
        try {
            $email = DB::table('email_messages')->where('id', $emailId)->first();
            $score = $this->sentimentService->analyzeEmail($email);

            if (!$score) {
                return response()->json([
                    'error' => 'Sentiment analysis is not available',
                ], 400);
            }

            return response()->json([
                'sentiment' => $this->formatScore($score),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to analyze email sentiment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sentiment summary for a record
     */
    public function getRecordSummary(string $module, int $recordId): JsonResponse
    {
        return response()->json([
            'summary' => $this->sentimentService->getRecordSummary($module, $recordId),
        ]);
    }

    /**
     * Get sentiment timeline for a record
     */
    public function getTimeline(string $module, int $recordId, Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);

        return response()->json([
            'timeline' => $this->sentimentService->getTimeline($module, $recordId, $limit),
        ]);
    }

    /**
     * Get unread alerts
     */
    public function alerts(): JsonResponse
    {
        $alerts = $this->sentimentService->getUnreadAlerts(Auth::id());

        return response()->json([
            'alerts' => $alerts->map(fn ($alert) => $this->formatAlert($alert)),
        ]);
    }

    /**
     * Mark alert as read
     */
    public function markAlertRead(int $id): JsonResponse
    {
        $alert = DB::table('sentiment_alerts')->where('id', $id)->first();
        $alert->markAsRead();

        return response()->json([
            'message' => 'Alert marked as read',
        ]);
    }

    /**
     * Dismiss alert
     */
    public function dismissAlert(int $id): JsonResponse
    {
        $alert = DB::table('sentiment_alerts')->where('id', $id)->first();
        $alert->dismiss(Auth::id());

        return response()->json([
            'message' => 'Alert dismissed',
        ]);
    }

    /**
     * Get records with declining sentiment
     */
    public function declining(string $module): JsonResponse
    {
        $records = $this->sentimentService->getDecliningRecords($module);

        return response()->json([
            'records' => $records->map(fn ($r) => [
                'record_module' => $r->record_module,
                'record_id' => $r->record_id,
                'average_score' => (float) $r->average_score,
                'overall_sentiment' => $r->overall_sentiment,
                'trend' => (float) $r->trend,
            ]),
        ]);
    }

    /**
     * Get records with negative sentiment
     */
    public function negative(string $module): JsonResponse
    {
        $records = $this->sentimentService->getNegativeRecords($module);

        return response()->json([
            'records' => $records->map(fn ($r) => [
                'record_module' => $r->record_module,
                'record_id' => $r->record_id,
                'average_score' => (float) $r->average_score,
                'overall_sentiment' => $r->overall_sentiment,
                'trend' => (float) $r->trend,
            ]),
        ]);
    }

    /**
     * Get sentiment distribution
     */
    public function distribution(string $module): JsonResponse
    {
        return response()->json([
            'distribution' => $this->sentimentService->getDistribution($module),
        ]);
    }

    /**
     * Batch analyze record emails
     */
    public function batchAnalyzeEmails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module' => 'required|string',
            'record_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $analyzed = $this->sentimentService->analyzeRecordEmails(
                $request->module,
                $request->record_id
            );

            return response()->json([
                'message' => "Analyzed {$analyzed} emails",
                'analyzed_count' => $analyzed,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Batch analysis failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format score for response
     */
    protected function formatScore($score): array
    {
        return [
            'id' => $score->id,
            'entity_type' => $score->entity_type,
            'entity_id' => $score->entity_id,
            'record_module' => $score->record_module,
            'record_id' => $score->record_id,
            'score' => (float) $score->score,
            'category' => $score->category,
            'emotion' => $score->emotion,
            'confidence' => (float) $score->confidence,
            'details' => $score->details,
            'color' => $score->color,
            'icon' => $score->icon,
            'model_used' => $score->model_used,
            'analyzed_at' => $score->analyzed_at?->toIso8601String(),
        ];
    }

    /**
     * Format alert for response
     */
    protected function formatAlert(SentimentAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'record_module' => $alert->record_module,
            'record_id' => $alert->record_id,
            'alert_type' => $alert->alert_type,
            'message' => $alert->message,
            'severity' => $alert->severity,
            'is_read' => $alert->is_read,
            'created_at' => $alert->created_at->toIso8601String(),
        ];
    }
}
