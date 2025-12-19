<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use App\Domain\Workflow\Services\ConditionEvaluationService;
use Illuminate\Support\Facades\Log;

/**
 * Action that branches workflow execution based on conditions.
 * Evaluates multiple condition branches and returns which branch to follow.
 */
class ConditionBranchAction implements ActionInterface
{
    protected ConditionEvaluationService $conditionEvaluator;

    public function __construct(ConditionEvaluationService $conditionEvaluator)
    {
        $this->conditionEvaluator = $conditionEvaluator;
    }

    /**
     * Execute the condition branch action.
     *
     * Returns the branch_id of the first matching condition branch,
     * or the default_branch if no conditions match.
     */
    public function execute(array $config, array $context): array
    {
        $branches = $config['branches'] ?? [];
        $defaultBranch = $config['default_branch'] ?? null;
        $evaluationMode = $config['evaluation_mode'] ?? 'first_match';

        $matchingBranches = [];
        $evaluatedBranches = [];

        foreach ($branches as $index => $branch) {
            $branchId = $branch['branch_id'] ?? "branch_{$index}";
            $branchName = $branch['name'] ?? "Branch {$index}";
            $conditions = $branch['conditions'] ?? [];

            $isMatch = $this->conditionEvaluator->evaluate($conditions, $context);

            $evaluatedBranches[] = [
                'branch_id' => $branchId,
                'name' => $branchName,
                'matched' => $isMatch,
            ];

            if ($isMatch) {
                $matchingBranches[] = $branchId;

                // In first_match mode, return immediately
                if ($evaluationMode === 'first_match') {
                    Log::info('Workflow condition branch matched', [
                        'branch_id' => $branchId,
                        'branch_name' => $branchName,
                        'mode' => $evaluationMode,
                    ]);

                    return [
                        'selected_branch' => $branchId,
                        'matching_branches' => [$branchId],
                        'evaluated_branches' => $evaluatedBranches,
                        'used_default' => false,
                    ];
                }
            }
        }

        // If we're here in first_match mode, no branches matched
        if ($evaluationMode === 'first_match') {
            if ($defaultBranch) {
                Log::info('Workflow condition using default branch', [
                    'default_branch' => $defaultBranch,
                ]);

                return [
                    'selected_branch' => $defaultBranch,
                    'matching_branches' => [],
                    'evaluated_branches' => $evaluatedBranches,
                    'used_default' => true,
                ];
            }

            return [
                'selected_branch' => null,
                'matching_branches' => [],
                'evaluated_branches' => $evaluatedBranches,
                'used_default' => false,
                'no_match' => true,
            ];
        }

        // For 'all_matching' mode, return all matching branches
        Log::info('Workflow condition branches evaluated', [
            'matching_branches' => $matchingBranches,
            'mode' => $evaluationMode,
        ]);

        return [
            'selected_branch' => $matchingBranches[0] ?? $defaultBranch,
            'matching_branches' => $matchingBranches,
            'evaluated_branches' => $evaluatedBranches,
            'used_default' => empty($matchingBranches) && $defaultBranch !== null,
        ];
    }

    public static function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'evaluation_mode',
                    'label' => 'Evaluation Mode',
                    'type' => 'select',
                    'required' => false,
                    'default' => 'first_match',
                    'options' => [
                        ['value' => 'first_match', 'label' => 'First Matching Branch'],
                        ['value' => 'all_matching', 'label' => 'All Matching Branches (Parallel)'],
                    ],
                    'description' => 'How to select branches when multiple conditions match',
                ],
                [
                    'name' => 'branches',
                    'label' => 'Condition Branches',
                    'type' => 'branch_list',
                    'required' => true,
                    'description' => 'Define condition branches. First matching branch is executed.',
                    'item_schema' => [
                        'branch_id' => [
                            'type' => 'text',
                            'label' => 'Branch ID',
                            'required' => true,
                        ],
                        'name' => [
                            'type' => 'text',
                            'label' => 'Branch Name',
                            'required' => true,
                        ],
                        'conditions' => [
                            'type' => 'conditions',
                            'label' => 'Conditions',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'name' => 'default_branch',
                    'label' => 'Default Branch',
                    'type' => 'text',
                    'required' => false,
                    'description' => 'Branch ID to use when no conditions match (else branch)',
                ],
            ],
        ];
    }

    public function validate(array $config): array
    {
        $errors = [];

        $branches = $config['branches'] ?? [];

        if (empty($branches)) {
            $errors['branches'] = 'At least one branch is required';
            return $errors;
        }

        $branchIds = [];
        foreach ($branches as $index => $branch) {
            if (empty($branch['branch_id'])) {
                $errors["branches.{$index}.branch_id"] = "Branch ID is required";
            } elseif (in_array($branch['branch_id'], $branchIds)) {
                $errors["branches.{$index}.branch_id"] = "Duplicate branch ID";
            } else {
                $branchIds[] = $branch['branch_id'];
            }

            if (empty($branch['conditions'])) {
                $errors["branches.{$index}.conditions"] = "Conditions are required";
            }
        }

        return $errors;
    }
}
