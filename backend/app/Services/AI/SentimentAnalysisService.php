<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SentimentAnalysisService
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Analyze sentiment of text content
     */
    public function analyze(
        string $text,
        string $entityType,
        int $entityId,
        ?string $recordModule = null,
        ?int $recordId = null
    ): ?SentimentScore {
        if (!$this->aiService->canUse()) {
            return null;
        }

        if (strlen(trim($text)) < 10) {
            return null;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->getSentimentPrompt(),
            ],
            [
                'role' => 'user',
                'content' => $text,
            ],
        ];

        try {
            $response = $this->aiService->complete(
                $messages,
                'sentiment_analysis',
                300,
                0.2,
                null,
                $entityType,
                $entityId
            );

            $result = json_decode($response['content'], true);

            if (!$result || !isset($result['score'])) {
                throw new \Exception('Invalid sentiment response format');
            }

            $score = $this->createScore($result, $entityType, $entityId, $recordModule, $recordId, $response['model']);

            // Check for alerts
            $this->checkAndCreateAlerts($score);

            // Update aggregate
            if ($recordModule && $recordId) {
                $this->updateAggregate($recordModule, $recordId);
            }

            return $score;
        } catch (\Exception $e) {
            Log::error("Sentiment analysis failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Analyze email sentiment
     */
    public function analyzeEmail(EmailMessage $email): ?SentimentScore
    {
        $text = $email->body_text ?: strip_tags($email->body_html ?? '');

        return $this->analyze(
            $text,
            'email',
            $email->id,
            $email->entity_type,
            $email->entity_id
        );
    }

    /**
     * Analyze note sentiment
     */
    public function analyzeNote(Note $note): ?SentimentScore
    {
        return $this->analyze(
            $note->content,
            'note',
            $note->id,
            $note->entity_type,
            $note->entity_id
        );
    }

    /**
     * Batch analyze emails for a record
     */
    public function analyzeRecordEmails(string $module, int $recordId): int
    {
        $emails = DB::table('email_messages')->where('entity_type', $module)
            ->where('entity_id', $recordId)
            ->whereDoesntHave('sentimentScore')
            ->get();

        $analyzed = 0;

        foreach ($emails as $email) {
            try {
                $this->analyzeEmail($email);
                $analyzed++;
            } catch (\Exception $e) {
                Log::error("Failed to analyze email {$email->id}: " . $e->getMessage());
            }
        }

        return $analyzed;
    }

    /**
     * Get sentiment summary for a record
     */
    public function getRecordSummary(string $module, int $recordId): array
    {
        $aggregate = DB::table('sentiment_aggregates')->where('record_module', $module)
            ->where('record_id', $recordId)
            ->first();

        if (!$aggregate) {
            return [
                'has_data' => false,
                'average_score' => null,
                'overall_sentiment' => null,
                'trend' => null,
                'breakdown' => [
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                ],
            ];
        }

        return [
            'has_data' => true,
            'average_score' => (float) $aggregate->average_score,
            'overall_sentiment' => $aggregate->overall_sentiment,
            'trend' => (float) $aggregate->trend,
            'is_improving' => $aggregate->isImproving(),
            'is_declining' => $aggregate->isDeclining(),
            'breakdown' => [
                'positive' => $aggregate->positive_count,
                'neutral' => $aggregate->neutral_count,
                'negative' => $aggregate->negative_count,
            ],
            'last_analyzed_at' => $aggregate->last_analyzed_at?->toIso8601String(),
        ];
    }

    /**
     * Get sentiment timeline for a record
     */
    public function getTimeline(string $module, int $recordId, int $limit = 20): Collection
    {
        return DB::table('sentiment_scores')->where('record_module', $module)
            ->where('record_id', $recordId)
            ->orderBy('analyzed_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($score) => [
                'id' => $score->id,
                'entity_type' => $score->entity_type,
                'entity_id' => $score->entity_id,
                'score' => (float) $score->score,
                'category' => $score->category,
                'emotion' => $score->emotion,
                'confidence' => (float) $score->confidence,
                'color' => $score->color,
                'icon' => $score->icon,
                'analyzed_at' => $score->analyzed_at?->toIso8601String(),
            ]);
    }

    /**
     * Get unread alerts for a user
     */
    public function getUnreadAlerts(?int $userId = null): Collection
    {
        return SentimentAlert::unread()
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Get records with declining sentiment
     */
    public function getDecliningRecords(string $module): Collection
    {
        return DB::table('sentiment_aggregates')->where('record_module', $module)
            ->where('trend', '<', -0.1)
            ->orderBy('trend', 'asc')
            ->limit(20)
            ->get();
    }

    /**
     * Get records with negative sentiment
     */
    public function getNegativeRecords(string $module): Collection
    {
        return DB::table('sentiment_aggregates')->where('record_module', $module)
            ->where('overall_sentiment', SentimentScore::CATEGORY_NEGATIVE)
            ->orderBy('average_score', 'asc')
            ->limit(20)
            ->get();
    }

    /**
     * Get sentiment distribution for module
     */
    public function getDistribution(string $module): array
    {
        $counts = DB::table('sentiment_aggregates')->where('record_module', $module)
            ->select('overall_sentiment', DB::raw('count(*) as count'))
            ->groupBy('overall_sentiment')
            ->pluck('count', 'overall_sentiment')
            ->toArray();

        return [
            'positive' => $counts[SentimentScore::CATEGORY_POSITIVE] ?? 0,
            'neutral' => $counts[SentimentScore::CATEGORY_NEUTRAL] ?? 0,
            'negative' => $counts[SentimentScore::CATEGORY_NEGATIVE] ?? 0,
        ];
    }

    /**
     * Create sentiment score record
     */
    protected function createScore(
        array $result,
        string $entityType,
        int $entityId,
        ?string $recordModule,
        ?int $recordId,
        string $model
    ): SentimentScore {
        return DB::table('sentiment_scores')->insertGetId([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'record_module' => $recordModule,
            'record_id' => $recordId,
            'score' => $result['score'],
            'category' => $result['category'] ?? SentimentScore::scoreToCategory($result['score']),
            'emotion' => $result['emotion'] ?? SentimentScore::EMOTION_NEUTRAL,
            'confidence' => $result['confidence'] ?? 0.8,
            'details' => $result['details'] ?? null,
            'model_used' => $model,
            'analyzed_at' => now(),
        ]);
    }

    /**
     * Check for alert conditions and create alerts
     */
    protected function checkAndCreateAlerts(SentimentScore $score): void
    {
        if (!$score->isConcerning()) {
            return;
        }

        $alertType = match (true) {
            $score->emotion === SentimentScore::EMOTION_URGENT => SentimentAlert::TYPE_URGENT_DETECTED,
            $score->category === SentimentScore::CATEGORY_NEGATIVE => SentimentAlert::TYPE_NEGATIVE_DETECTED,
            default => null,
        };

        if (!$alertType) {
            return;
        }

        $severity = match ($score->emotion) {
            SentimentScore::EMOTION_ANGRY, SentimentScore::EMOTION_URGENT => SentimentAlert::SEVERITY_HIGH,
            SentimentScore::EMOTION_FRUSTRATED => SentimentAlert::SEVERITY_MEDIUM,
            default => SentimentAlert::SEVERITY_LOW,
        };

        $message = match ($alertType) {
            SentimentAlert::TYPE_URGENT_DETECTED => 'Urgent sentiment detected in communication',
            SentimentAlert::TYPE_NEGATIVE_DETECTED => 'Negative sentiment detected - customer may need attention',
            default => 'Sentiment alert',
        };

        DB::table('sentiment_alerts')->insertGetId([
            'record_module' => $score->record_module,
            'record_id' => $score->record_id,
            'sentiment_id' => $score->id,
            'alert_type' => $alertType,
            'message' => $message,
            'severity' => $severity,
        ]);
    }

    /**
     * Update aggregate scores for a record
     */
    protected function updateAggregate(string $module, int $recordId): void
    {
        $aggregate = SentimentAggregate::getOrCreateFor($module, $recordId);
        $aggregate->recalculate();
    }

    /**
     * Get sentiment analysis prompt
     */
    protected function getSentimentPrompt(): string
    {
        return <<<'PROMPT'
Analyze the sentiment of the following text from a business communication context.

Return a JSON object with:
- score: number from -1 (very negative) to 1 (very positive), 0 is neutral
- category: one of "positive", "neutral", "negative"
- emotion: one of "happy", "satisfied", "neutral", "confused", "frustrated", "angry", "urgent"
- confidence: number 0-1 indicating how confident you are
- details: object with additional insights (optional)

Consider:
1. Overall tone and language
2. Specific words indicating satisfaction or frustration
3. Urgency signals
4. Customer service context
5. Business relationship implications

Be accurate and consistent in your analysis.
PROMPT;
    }
}
