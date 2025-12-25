<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadScoringService
{
    public function __construct(
        protected ?AiService $aiService = null
    ) {}

    /**
     * Score a single record
     */
    public function scoreRecord(ModuleRecord $record): ?LeadScore
    {
        $model = ScoringModel::forModule($record->module_api_name)
            ->active()
            ->first();

        if (!$model) {
            return null;
        }

        $recordData = $this->prepareRecordData($record);
        $result = $model->calculateScore($recordData);

        return DB::transaction(function () use ($record, $model, $result) {
            $leadScore = LeadScore::updateOrCreate(
                [
                    'record_module' => $record->module_api_name,
                    'record_id' => $record->id,
                ],
                [
                    'scoring_model_id' => $model->id,
                    'score' => $result['score'],
                    'grade' => $result['grade'],
                    'breakdown' => $result['breakdown'],
                    'explanations' => $result['explanations'],
                    'last_calculated_at' => now(),
                ]
            );

            // Record history
            DB::table('lead_score_histories')->insertGetId([
                'lead_score_id' => $leadScore->id,
                'score' => $result['score'],
                'grade' => $result['grade'],
                'change_reason' => 'recalculated',
            ]);

            return $leadScore;
        });
    }

    /**
     * Batch score multiple records
     */
    public function batchScore(string $moduleApiName, ?array $recordIds = null): int
    {
        $model = ScoringModel::forModule($moduleApiName)
            ->active()
            ->first();

        if (!$model) {
            return 0;
        }

        $query = DB::table('module_records')->where('module_api_name', $moduleApiName);

        if ($recordIds) {
            $query->whereIn('id', $recordIds);
        }

        $scored = 0;

        $query->chunk(100, function ($records) use ($model, &$scored) {
            foreach ($records as $record) {
                try {
                    $this->scoreRecord($record);
                    $scored++;
                } catch (\Exception $e) {
                    Log::error("Failed to score record {$record->id}: " . $e->getMessage());
                }
            }
        });

        return $scored;
    }

    /**
     * Score using AI (for complex/contextual scoring)
     */
    public function scoreWithAi(ModuleRecord $record): ?LeadScore
    {
        if (!$this->aiService || !$this->aiService->canUse()) {
            return null;
        }

        $recordData = $this->prepareRecordData($record);

        $messages = [
            [
                'role' => 'system',
                'content' => $this->getAiScoringPrompt(),
            ],
            [
                'role' => 'user',
                'content' => json_encode($recordData, JSON_PRETTY_PRINT),
            ],
        ];

        try {
            $response = $this->aiService->complete(
                $messages,
                'ai_lead_scoring',
                500,
                0.3,
                null,
                $record->module_api_name,
                $record->id
            );

            $result = json_decode($response['content'], true);

            if (!$result || !isset($result['score'])) {
                throw new \Exception('Invalid AI response format');
            }

            return DB::transaction(function () use ($record, $result, $response) {
                $leadScore = LeadScore::updateOrCreate(
                    [
                        'record_module' => $record->module_api_name,
                        'record_id' => $record->id,
                    ],
                    [
                        'score' => min(100, max(0, $result['score'])),
                        'grade' => $result['grade'] ?? $this->scoreToGrade($result['score']),
                        'breakdown' => $result['breakdown'] ?? [],
                        'explanations' => $result['explanations'] ?? [],
                        'ai_insights' => $result['insights'] ?? null,
                        'model_used' => $response['model'],
                        'last_calculated_at' => now(),
                    ]
                );

                DB::table('lead_score_histories')->insertGetId([
                    'lead_score_id' => $leadScore->id,
                    'score' => $leadScore->score,
                    'grade' => $leadScore->grade,
                    'change_reason' => 'ai_scored',
                ]);

                return $leadScore;
            });
        } catch (\Exception $e) {
            Log::error("AI scoring failed for record {$record->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get score history for a record
     */
    public function getScoreHistory(string $module, int $recordId, int $days = 30): Collection
    {
        $leadScore = DB::table('lead_scores')->where('record_module', $module)
            ->where('record_id', $recordId)
            ->first();

        if (!$leadScore) {
            return collect();
        }

        return DB::table('lead_score_histories')->where('lead_score_id', $leadScore->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get top scored records
     */
    public function getTopScored(string $moduleApiName, int $limit = 10): Collection
    {
        return DB::table('lead_scores')->where('record_module', $moduleApiName)
            ->where('grade', 'A')
            ->orderBy('score', 'desc')
            ->limit($limit)
            ->with('record')
            ->get();
    }

    /**
     * Get records by grade
     */
    public function getByGrade(string $moduleApiName, string $grade): Collection
    {
        return DB::table('lead_scores')->where('record_module', $moduleApiName)
            ->where('grade', $grade)
            ->orderBy('score', 'desc')
            ->with('record')
            ->get();
    }

    /**
     * Get scoring distribution
     */
    public function getDistribution(string $moduleApiName): array
    {
        $grades = DB::table('lead_scores')->where('record_module', $moduleApiName)
            ->select('grade', DB::raw('count(*) as count'))
            ->groupBy('grade')
            ->pluck('count', 'grade')
            ->toArray();

        return [
            'A' => $grades['A'] ?? 0,
            'B' => $grades['B'] ?? 0,
            'C' => $grades['C'] ?? 0,
            'D' => $grades['D'] ?? 0,
            'F' => $grades['F'] ?? 0,
        ];
    }

    /**
     * Get average score
     */
    public function getAverageScore(string $moduleApiName): float
    {
        return DB::table('lead_scores')->where('record_module', $moduleApiName)
            ->avg('score') ?? 0;
    }

    /**
     * Prepare record data for scoring
     */
    protected function prepareRecordData(ModuleRecord $record): array
    {
        $data = $record->data ?? [];

        // Add metadata
        $data['_id'] = $record->id;
        $data['_module'] = $record->module_api_name;
        $data['_created_at'] = $record->created_at?->toDateTimeString();
        $data['_updated_at'] = $record->updated_at?->toDateTimeString();

        // Add activity counts if available
        $data['_activity_count'] = $record->activities()->count();
        $data['_email_count'] = $record->emails()->count();
        $data['_note_count'] = $record->notes()->count();

        // Add days since creation
        $data['_days_since_created'] = $record->created_at
            ? now()->diffInDays($record->created_at)
            : null;

        return $data;
    }

    /**
     * Convert numeric score to grade
     */
    protected function scoreToGrade(int $score): string
    {
        return match (true) {
            $score >= 80 => 'A',
            $score >= 60 => 'B',
            $score >= 40 => 'C',
            $score >= 20 => 'D',
            default => 'F',
        };
    }

    /**
     * Get AI scoring prompt
     */
    protected function getAiScoringPrompt(): string
    {
        return <<<'PROMPT'
You are an expert lead scoring analyst. Analyze the given record data and provide a lead score.

Return a JSON object with:
- score: number 0-100 (0 = coldest, 100 = hottest)
- grade: letter A-F based on score
- breakdown: object with category scores (e.g., {"engagement": 80, "fit": 70, "timing": 60})
- explanations: array of strings explaining key scoring factors
- insights: string with actionable recommendations for sales

Consider these factors:
1. Data completeness (more complete = better)
2. Activity engagement (emails, notes, meetings)
3. Company fit (industry, size if available)
4. Timing signals (recency of interactions)
5. Lead source quality
6. Job title/seniority if available

Be consistent and objective in scoring.
PROMPT;
    }
}
