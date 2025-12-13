<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Evaluates workflow conditions against context data.
 */
class ConditionEvaluator
{
    /**
     * Operator categories for UI organization.
     */
    public const CATEGORY_COMPARISON = 'comparison';
    public const CATEGORY_STRING = 'string';
    public const CATEGORY_NULL = 'null_check';
    public const CATEGORY_LIST = 'list';
    public const CATEGORY_BOOLEAN = 'boolean';
    public const CATEGORY_DATE = 'date';
    public const CATEGORY_USER = 'user';
    public const CATEGORY_RELATED = 'related';
    public const CATEGORY_CHANGE = 'change';
    public const CATEGORY_FORMULA = 'formula';

    /**
     * Evaluate a set of conditions against context data.
     *
     * Conditions format:
     * [
     *     'logic' => 'and' | 'or',
     *     'groups' => [
     *         [
     *             'logic' => 'and' | 'or',
     *             'conditions' => [
     *                 ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
     *                 ['field' => 'amount', 'operator' => 'greater_than', 'value' => 1000],
     *             ]
     *         ]
     *     ]
     * ]
     */
    public function evaluate(array $conditions, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // Simple array of conditions (no groups)
        if (isset($conditions[0]) && isset($conditions[0]['field'])) {
            return $this->evaluateConditionGroup($conditions, 'and', $context);
        }

        // Grouped conditions
        $groupLogic = $conditions['logic'] ?? 'and';
        $groups = $conditions['groups'] ?? [];

        if (empty($groups)) {
            return true;
        }

        $results = [];
        foreach ($groups as $group) {
            $groupConditions = $group['conditions'] ?? [];
            $innerLogic = $group['logic'] ?? 'and';
            $results[] = $this->evaluateConditionGroup($groupConditions, $innerLogic, $context);
        }

        return $this->combineResults($results, $groupLogic);
    }

    /**
     * Evaluate a group of conditions.
     */
    protected function evaluateConditionGroup(array $conditions, string $logic, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $results = [];
        foreach ($conditions as $condition) {
            $results[] = $this->evaluateCondition($condition, $context);
        }

        return $this->combineResults($results, $logic);
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;
        $valueType = $condition['value_type'] ?? 'static';

        // Get the actual value from context using dot notation
        $actualValue = $this->getValueFromContext($field, $context);

        // Resolve the expected value based on value type
        $expectedValue = $this->resolveValue($value, $valueType, $context);

        return $this->compare($actualValue, $operator, $expectedValue, $context);
    }

    /**
     * Resolve a value based on its type.
     */
    protected function resolveValue(mixed $value, string $valueType, array $context): mixed
    {
        return match ($valueType) {
            'field' => $this->getValueFromContext((string) $value, $context),
            'current_user' => \Illuminate\Support\Facades\Auth::id(),
            'current_date' => now()->toDateString(),
            'current_datetime' => now()->toDateTimeString(),
            'now' => now(),
            default => $value, // 'static' or any other type
        };
    }

    /**
     * Get a value from context using dot notation.
     */
    protected function getValueFromContext(string $field, array $context): mixed
    {
        $keys = explode('.', $field);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Compare values using the specified operator.
     */
    protected function compare(mixed $actual, string $operator, mixed $expected, array $context = []): bool
    {
        return match ($operator) {
            // Basic comparison operators
            'equals', 'eq', '==' => $actual == $expected,
            'not_equals', 'neq', '!=' => $actual != $expected,
            'greater_than', 'gt', '>' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'greater_than_or_equals', 'gte', '>=' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'less_than', 'lt', '<' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'less_than_or_equals', 'lte', '<=' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,

            // String operators
            'contains' => is_string($actual) && is_string($expected) && str_contains(strtolower($actual), strtolower($expected)),
            'not_contains' => is_string($actual) && is_string($expected) && !str_contains(strtolower($actual), strtolower($expected)),
            'starts_with' => is_string($actual) && is_string($expected) && str_starts_with(strtolower($actual), strtolower($expected)),
            'ends_with' => is_string($actual) && is_string($expected) && str_ends_with(strtolower($actual), strtolower($expected)),
            'matches_pattern' => is_string($actual) && is_string($expected) && preg_match($expected, $actual) === 1,
            'regex' => is_string($actual) && is_string($expected) && preg_match($expected, $actual) === 1,

            // Null/Empty operators
            'is_empty' => empty($actual),
            'is_not_empty' => !empty($actual),
            'is_null' => $actual === null,
            'is_not_null' => $actual !== null,

            // List operators
            'in' => is_array($expected) && in_array($actual, $expected),
            'not_in' => is_array($expected) && !in_array($actual, $expected),
            'array_contains' => is_array($actual) && in_array($expected, $actual),
            'array_not_contains' => is_array($actual) && !in_array($expected, $actual),
            'array_length_equals' => is_array($actual) && count($actual) == $expected,
            'array_length_gt' => is_array($actual) && count($actual) > $expected,
            'array_length_lt' => is_array($actual) && count($actual) < $expected,

            // Boolean operators
            'is_true' => $actual === true || $actual === 1 || $actual === '1' || $actual === 'true',
            'is_false' => $actual === false || $actual === 0 || $actual === '0' || $actual === 'false',

            // Date operators
            'date_equals' => $this->compareDates($actual, $expected, 'equals'),
            'date_before' => $this->compareDates($actual, $expected, 'before'),
            'date_after' => $this->compareDates($actual, $expected, 'after'),
            'date_on_or_before' => $this->compareDates($actual, $expected, 'on_or_before'),
            'date_on_or_after' => $this->compareDates($actual, $expected, 'on_or_after'),
            'date_between' => $this->compareDateBetween($actual, $expected),
            'date_in_next' => $this->compareDateInNext($actual, $expected),
            'date_in_past' => $this->compareDateInPast($actual, $expected),
            'is_today' => $this->isToday($actual),
            'is_yesterday' => $this->isYesterday($actual),
            'is_tomorrow' => $this->isTomorrow($actual),
            'is_this_week' => $this->isThisWeek($actual),
            'is_last_week' => $this->isLastWeek($actual),
            'is_next_week' => $this->isNextWeek($actual),
            'is_this_month' => $this->isThisMonth($actual),
            'is_last_month' => $this->isLastMonth($actual),
            'is_next_month' => $this->isNextMonth($actual),
            'is_this_year' => $this->isThisYear($actual),
            'is_overdue' => $this->isOverdue($actual),

            // User operators
            'is_current_user' => $this->isCurrentUser($actual),
            'is_not_current_user' => !$this->isCurrentUser($actual),
            'is_record_owner' => $this->isRecordOwner($actual, $context),
            'is_in_role' => $this->isInRole($actual, $expected),
            'is_in_team' => $this->isInTeam($actual, $expected),

            // Related record operators
            'has_related' => $this->hasRelatedRecords($actual, $expected, $context),
            'has_no_related' => !$this->hasRelatedRecords($actual, $expected, $context),
            'related_count_equals' => $this->compareRelatedCount($actual, $expected, 'equals', $context),
            'related_count_gt' => $this->compareRelatedCount($actual, $expected, 'gt', $context),
            'related_count_lt' => $this->compareRelatedCount($actual, $expected, 'lt', $context),
            'related_count_gte' => $this->compareRelatedCount($actual, $expected, 'gte', $context),
            'related_count_lte' => $this->compareRelatedCount($actual, $expected, 'lte', $context),

            // Change detection operators
            'changed' => isset($expected['old']) && isset($expected['new']) && $expected['old'] !== $expected['new'],
            'changed_to' => isset($expected['new']) && $actual === $expected['new'],
            'changed_from' => isset($expected['old']) && $expected['old'] !== $actual,
            'changed_from_to' => $this->hasChangedFromTo($actual, $expected, $context),
            'has_changed' => $this->fieldHasChanged($actual, $context),
            'has_not_changed' => !$this->fieldHasChanged($actual, $context),
            'was_empty_now_filled' => $this->wasEmptyNowFilled($actual, $context),
            'was_filled_now_empty' => $this->wasFilledNowEmpty($actual, $context),

            // Formula operator
            'formula' => $this->evaluateFormula($expected, $context),

            default => false,
        };
    }

    /**
     * Compare two dates.
     */
    protected function compareDates(mixed $actual, mixed $expected, string $comparison): bool
    {
        try {
            $actualDate = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            $expectedDate = $expected instanceof Carbon ? $expected : Carbon::parse($expected);

            return match ($comparison) {
                'equals' => $actualDate->isSameDay($expectedDate),
                'before' => $actualDate->isBefore($expectedDate),
                'after' => $actualDate->isAfter($expectedDate),
                'on_or_before' => $actualDate->isSameDay($expectedDate) || $actualDate->isBefore($expectedDate),
                'on_or_after' => $actualDate->isSameDay($expectedDate) || $actualDate->isAfter($expectedDate),
                default => false,
            };
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is between two dates.
     */
    protected function compareDateBetween(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected) || !isset($expected['start']) || !isset($expected['end'])) {
            return false;
        }

        try {
            $actualDate = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            $startDate = Carbon::parse($expected['start'])->startOfDay();
            $endDate = Carbon::parse($expected['end'])->endOfDay();

            return $actualDate->between($startDate, $endDate);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is within the next X days/weeks/months.
     */
    protected function compareDateInNext(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected) || !isset($expected['amount']) || !isset($expected['unit'])) {
            return false;
        }

        try {
            $actualDate = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            $now = Carbon::now();
            $futureDate = match ($expected['unit']) {
                'hours' => $now->copy()->addHours($expected['amount']),
                'days' => $now->copy()->addDays($expected['amount']),
                'weeks' => $now->copy()->addWeeks($expected['amount']),
                'months' => $now->copy()->addMonths($expected['amount']),
                'years' => $now->copy()->addYears($expected['amount']),
                default => $now->copy()->addDays($expected['amount']),
            };

            return $actualDate->between($now, $futureDate);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is within the past X days/weeks/months.
     */
    protected function compareDateInPast(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected) || !isset($expected['amount']) || !isset($expected['unit'])) {
            return false;
        }

        try {
            $actualDate = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            $now = Carbon::now();
            $pastDate = match ($expected['unit']) {
                'hours' => $now->copy()->subHours($expected['amount']),
                'days' => $now->copy()->subDays($expected['amount']),
                'weeks' => $now->copy()->subWeeks($expected['amount']),
                'months' => $now->copy()->subMonths($expected['amount']),
                'years' => $now->copy()->subYears($expected['amount']),
                default => $now->copy()->subDays($expected['amount']),
            };

            return $actualDate->between($pastDate, $now);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is today.
     */
    protected function isToday(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isToday();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is yesterday.
     */
    protected function isYesterday(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isYesterday();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is tomorrow.
     */
    protected function isTomorrow(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isTomorrow();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is in this week.
     */
    protected function isThisWeek(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isCurrentWeek();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is in last week.
     */
    protected function isLastWeek(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isLastWeek();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is in next week.
     */
    protected function isNextWeek(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isNextWeek();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is in this month.
     */
    protected function isThisMonth(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isCurrentMonth();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is in last month.
     */
    protected function isLastMonth(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isLastMonth();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is in next month.
     */
    protected function isNextMonth(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isNextMonth();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is in this year.
     */
    protected function isThisYear(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isCurrentYear();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a date is overdue (in the past).
     */
    protected function isOverdue(mixed $actual): bool
    {
        try {
            $date = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            return $date->isPast() && !$date->isToday();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if the value is the current user's ID.
     */
    protected function isCurrentUser(mixed $actual): bool
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return false;
        }

        return $actual == $currentUser->id;
    }

    /**
     * Check if the value matches the record owner.
     */
    protected function isRecordOwner(mixed $actual, array $context): bool
    {
        $ownerId = $context['record']['owner_id'] ?? $context['record']['assigned_to'] ?? $context['record']['user_id'] ?? null;
        return $actual == $ownerId;
    }

    /**
     * Check if a user is in a specific role.
     */
    protected function isInRole(mixed $userId, mixed $expected): bool
    {
        if (!$userId || !$expected) {
            return false;
        }

        // Get user and check role
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return false;
        }

        $roles = is_array($expected) ? $expected : [$expected];
        return $user->hasAnyRole($roles);
    }

    /**
     * Check if a user is in a specific team.
     */
    protected function isInTeam(mixed $userId, mixed $expected): bool
    {
        if (!$userId || !$expected) {
            return false;
        }

        // Get user and check team membership
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return false;
        }

        // Check if user has a teams relationship
        if (!method_exists($user, 'teams')) {
            return false;
        }

        $teamIds = is_array($expected) ? $expected : [$expected];
        return $user->teams()->whereIn('teams.id', $teamIds)->exists();
    }

    /**
     * Check if the record has related records.
     */
    protected function hasRelatedRecords(mixed $actual, mixed $expected, array $context): bool
    {
        $relatedCount = $this->getRelatedCount($actual, $expected, $context);
        return $relatedCount > 0;
    }

    /**
     * Compare related record count.
     */
    protected function compareRelatedCount(mixed $actual, mixed $expected, string $comparison, array $context): bool
    {
        if (!is_array($expected) || !isset($expected['relation']) || !isset($expected['count'])) {
            return false;
        }

        $relatedCount = $this->getRelatedCount($actual, $expected['relation'], $context);
        $expectedCount = (int) $expected['count'];

        return match ($comparison) {
            'equals' => $relatedCount === $expectedCount,
            'gt' => $relatedCount > $expectedCount,
            'lt' => $relatedCount < $expectedCount,
            'gte' => $relatedCount >= $expectedCount,
            'lte' => $relatedCount <= $expectedCount,
            default => false,
        };
    }

    /**
     * Get the count of related records.
     */
    protected function getRelatedCount(mixed $actual, mixed $relation, array $context): int
    {
        // Check if count is already provided in context
        $countKey = "related_counts.{$relation}";
        $count = $this->getValueFromContext($countKey, $context);
        if ($count !== null) {
            return (int) $count;
        }

        // Check for nested relation data in context
        $relationData = $this->getValueFromContext("record.{$relation}", $context);
        if (is_array($relationData)) {
            return count($relationData);
        }

        return 0;
    }

    /**
     * Check if a field has changed from a specific value to another.
     */
    protected function hasChangedFromTo(mixed $actual, mixed $expected, array $context): bool
    {
        if (!is_array($expected) || !isset($expected['from']) || !isset($expected['to'])) {
            return false;
        }

        $oldValue = $context['old_data'][$expected['field'] ?? ''] ?? null;
        $newValue = $actual;

        return $oldValue == $expected['from'] && $newValue == $expected['to'];
    }

    /**
     * Check if a field has changed.
     */
    protected function fieldHasChanged(mixed $field, array $context): bool
    {
        if (!isset($context['old_data']) || !isset($context['record'])) {
            return false;
        }

        $fieldName = is_string($field) ? $field : '';
        $oldValue = $context['old_data'][$fieldName] ?? null;
        $newValue = $context['record'][$fieldName] ?? null;

        return $oldValue !== $newValue;
    }

    /**
     * Check if a field was empty and is now filled.
     */
    protected function wasEmptyNowFilled(mixed $field, array $context): bool
    {
        if (!isset($context['old_data']) || !isset($context['record'])) {
            return false;
        }

        $fieldName = is_string($field) ? $field : '';
        $oldValue = $context['old_data'][$fieldName] ?? null;
        $newValue = $context['record'][$fieldName] ?? null;

        return empty($oldValue) && !empty($newValue);
    }

    /**
     * Check if a field was filled and is now empty.
     */
    protected function wasFilledNowEmpty(mixed $field, array $context): bool
    {
        if (!isset($context['old_data']) || !isset($context['record'])) {
            return false;
        }

        $fieldName = is_string($field) ? $field : '';
        $oldValue = $context['old_data'][$fieldName] ?? null;
        $newValue = $context['record'][$fieldName] ?? null;

        return !empty($oldValue) && empty($newValue);
    }

    /**
     * Evaluate a formula expression safely.
     * Supports basic arithmetic and field references without using eval().
     */
    protected function evaluateFormula(mixed $formula, array $context): bool
    {
        if (!is_string($formula)) {
            return false;
        }

        try {
            // Use safe expression evaluator instead of eval()
            $result = SafeExpressionEvaluator::evaluate($formula, $context);

            return (bool) $result;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Combine results using AND/OR logic.
     */
    protected function combineResults(array $results, string $logic): bool
    {
        if (empty($results)) {
            return true;
        }

        return match (strtolower($logic)) {
            'and' => !in_array(false, $results, true),
            'or' => in_array(true, $results, true),
            default => !in_array(false, $results, true),
        };
    }

    /**
     * Get all available operators.
     */
    public static function getOperators(): array
    {
        return [
            // Basic comparison
            'equals' => 'Equals',
            'not_equals' => 'Does not equal',
            'greater_than' => 'Greater than',
            'greater_than_or_equals' => 'Greater than or equals',
            'less_than' => 'Less than',
            'less_than_or_equals' => 'Less than or equals',

            // String
            'contains' => 'Contains',
            'not_contains' => 'Does not contain',
            'starts_with' => 'Starts with',
            'ends_with' => 'Ends with',
            'matches_pattern' => 'Matches pattern (regex)',

            // Null/Empty
            'is_empty' => 'Is empty',
            'is_not_empty' => 'Is not empty',
            'is_null' => 'Is null',
            'is_not_null' => 'Is not null',

            // List
            'in' => 'Is in list',
            'not_in' => 'Is not in list',
            'array_contains' => 'Array contains',
            'array_not_contains' => 'Array does not contain',
            'array_length_equals' => 'Array length equals',
            'array_length_gt' => 'Array length greater than',
            'array_length_lt' => 'Array length less than',

            // Boolean
            'is_true' => 'Is true',
            'is_false' => 'Is false',

            // Date
            'date_equals' => 'Date equals',
            'date_before' => 'Date is before',
            'date_after' => 'Date is after',
            'date_on_or_before' => 'Date is on or before',
            'date_on_or_after' => 'Date is on or after',
            'date_between' => 'Date is between',
            'date_in_next' => 'Date is in the next',
            'date_in_past' => 'Date was in the past',
            'is_today' => 'Is today',
            'is_yesterday' => 'Is yesterday',
            'is_tomorrow' => 'Is tomorrow',
            'is_this_week' => 'Is this week',
            'is_last_week' => 'Is last week',
            'is_next_week' => 'Is next week',
            'is_this_month' => 'Is this month',
            'is_last_month' => 'Is last month',
            'is_next_month' => 'Is next month',
            'is_this_year' => 'Is this year',
            'is_overdue' => 'Is overdue',

            // User
            'is_current_user' => 'Is current user',
            'is_not_current_user' => 'Is not current user',
            'is_record_owner' => 'Is record owner',
            'is_in_role' => 'Is in role',
            'is_in_team' => 'Is in team',

            // Related records
            'has_related' => 'Has related records',
            'has_no_related' => 'Has no related records',
            'related_count_equals' => 'Related count equals',
            'related_count_gt' => 'Related count greater than',
            'related_count_lt' => 'Related count less than',
            'related_count_gte' => 'Related count at least',
            'related_count_lte' => 'Related count at most',

            // Change detection
            'changed' => 'Has changed',
            'changed_to' => 'Changed to',
            'changed_from' => 'Changed from',
            'changed_from_to' => 'Changed from X to Y',
            'has_changed' => 'Field has changed',
            'has_not_changed' => 'Field has not changed',
            'was_empty_now_filled' => 'Was empty, now filled',
            'was_filled_now_empty' => 'Was filled, now empty',

            // Formula
            'formula' => 'Formula (advanced)',
        ];
    }

    /**
     * Get operators organized by category with metadata.
     */
    public static function getOperatorsWithMetadata(): array
    {
        return [
            self::CATEGORY_COMPARISON => [
                'label' => 'Comparison',
                'operators' => [
                    'equals' => ['label' => 'Equals', 'requires_value' => true, 'field_types' => ['*']],
                    'not_equals' => ['label' => 'Does not equal', 'requires_value' => true, 'field_types' => ['*']],
                    'greater_than' => ['label' => 'Greater than', 'requires_value' => true, 'field_types' => ['number', 'integer', 'decimal', 'currency', 'percent']],
                    'greater_than_or_equals' => ['label' => 'Greater than or equals', 'requires_value' => true, 'field_types' => ['number', 'integer', 'decimal', 'currency', 'percent']],
                    'less_than' => ['label' => 'Less than', 'requires_value' => true, 'field_types' => ['number', 'integer', 'decimal', 'currency', 'percent']],
                    'less_than_or_equals' => ['label' => 'Less than or equals', 'requires_value' => true, 'field_types' => ['number', 'integer', 'decimal', 'currency', 'percent']],
                ],
            ],
            self::CATEGORY_STRING => [
                'label' => 'Text',
                'operators' => [
                    'contains' => ['label' => 'Contains', 'requires_value' => true, 'field_types' => ['text', 'string', 'textarea', 'email', 'url', 'phone']],
                    'not_contains' => ['label' => 'Does not contain', 'requires_value' => true, 'field_types' => ['text', 'string', 'textarea', 'email', 'url', 'phone']],
                    'starts_with' => ['label' => 'Starts with', 'requires_value' => true, 'field_types' => ['text', 'string', 'textarea', 'email', 'url', 'phone']],
                    'ends_with' => ['label' => 'Ends with', 'requires_value' => true, 'field_types' => ['text', 'string', 'textarea', 'email', 'url', 'phone']],
                    'matches_pattern' => ['label' => 'Matches pattern (regex)', 'requires_value' => true, 'field_types' => ['text', 'string', 'textarea']],
                ],
            ],
            self::CATEGORY_NULL => [
                'label' => 'Empty/Null',
                'operators' => [
                    'is_empty' => ['label' => 'Is empty', 'requires_value' => false, 'field_types' => ['*']],
                    'is_not_empty' => ['label' => 'Is not empty', 'requires_value' => false, 'field_types' => ['*']],
                    'is_null' => ['label' => 'Is null', 'requires_value' => false, 'field_types' => ['*']],
                    'is_not_null' => ['label' => 'Is not null', 'requires_value' => false, 'field_types' => ['*']],
                ],
            ],
            self::CATEGORY_LIST => [
                'label' => 'List/Array',
                'operators' => [
                    'in' => ['label' => 'Is in list', 'requires_value' => true, 'value_type' => 'array', 'field_types' => ['*']],
                    'not_in' => ['label' => 'Is not in list', 'requires_value' => true, 'value_type' => 'array', 'field_types' => ['*']],
                    'array_contains' => ['label' => 'Array contains', 'requires_value' => true, 'field_types' => ['multiselect', 'tags', 'array']],
                    'array_not_contains' => ['label' => 'Array does not contain', 'requires_value' => true, 'field_types' => ['multiselect', 'tags', 'array']],
                    'array_length_equals' => ['label' => 'Array length equals', 'requires_value' => true, 'value_type' => 'number', 'field_types' => ['multiselect', 'tags', 'array']],
                    'array_length_gt' => ['label' => 'Array length greater than', 'requires_value' => true, 'value_type' => 'number', 'field_types' => ['multiselect', 'tags', 'array']],
                    'array_length_lt' => ['label' => 'Array length less than', 'requires_value' => true, 'value_type' => 'number', 'field_types' => ['multiselect', 'tags', 'array']],
                ],
            ],
            self::CATEGORY_BOOLEAN => [
                'label' => 'Boolean',
                'operators' => [
                    'is_true' => ['label' => 'Is true', 'requires_value' => false, 'field_types' => ['boolean', 'switch', 'checkbox']],
                    'is_false' => ['label' => 'Is false', 'requires_value' => false, 'field_types' => ['boolean', 'switch', 'checkbox']],
                ],
            ],
            self::CATEGORY_DATE => [
                'label' => 'Date/Time',
                'operators' => [
                    'date_equals' => ['label' => 'Date equals', 'requires_value' => true, 'value_type' => 'date', 'field_types' => ['date', 'datetime']],
                    'date_before' => ['label' => 'Date is before', 'requires_value' => true, 'value_type' => 'date', 'field_types' => ['date', 'datetime']],
                    'date_after' => ['label' => 'Date is after', 'requires_value' => true, 'value_type' => 'date', 'field_types' => ['date', 'datetime']],
                    'date_on_or_before' => ['label' => 'Date is on or before', 'requires_value' => true, 'value_type' => 'date', 'field_types' => ['date', 'datetime']],
                    'date_on_or_after' => ['label' => 'Date is on or after', 'requires_value' => true, 'value_type' => 'date', 'field_types' => ['date', 'datetime']],
                    'date_between' => ['label' => 'Date is between', 'requires_value' => true, 'value_type' => 'date_range', 'field_types' => ['date', 'datetime']],
                    'date_in_next' => ['label' => 'Date is in the next', 'requires_value' => true, 'value_type' => 'duration', 'field_types' => ['date', 'datetime']],
                    'date_in_past' => ['label' => 'Date was in the past', 'requires_value' => true, 'value_type' => 'duration', 'field_types' => ['date', 'datetime']],
                    'is_today' => ['label' => 'Is today', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                    'is_yesterday' => ['label' => 'Is yesterday', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                    'is_tomorrow' => ['label' => 'Is tomorrow', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                    'is_this_week' => ['label' => 'Is this week', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                    'is_last_week' => ['label' => 'Is last week', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                    'is_next_week' => ['label' => 'Is next week', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                    'is_this_month' => ['label' => 'Is this month', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                    'is_last_month' => ['label' => 'Is last month', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                    'is_next_month' => ['label' => 'Is next month', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                    'is_this_year' => ['label' => 'Is this year', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                    'is_overdue' => ['label' => 'Is overdue', 'requires_value' => false, 'field_types' => ['date', 'datetime']],
                ],
            ],
            self::CATEGORY_USER => [
                'label' => 'User',
                'operators' => [
                    'is_current_user' => ['label' => 'Is current user', 'requires_value' => false, 'field_types' => ['lookup', 'user']],
                    'is_not_current_user' => ['label' => 'Is not current user', 'requires_value' => false, 'field_types' => ['lookup', 'user']],
                    'is_record_owner' => ['label' => 'Is record owner', 'requires_value' => false, 'field_types' => ['lookup', 'user']],
                    'is_in_role' => ['label' => 'Is in role', 'requires_value' => true, 'value_type' => 'role', 'field_types' => ['lookup', 'user']],
                    'is_in_team' => ['label' => 'Is in team', 'requires_value' => true, 'value_type' => 'team', 'field_types' => ['lookup', 'user']],
                ],
            ],
            self::CATEGORY_RELATED => [
                'label' => 'Related Records',
                'operators' => [
                    'has_related' => ['label' => 'Has related records', 'requires_value' => true, 'value_type' => 'relation', 'field_types' => ['relation']],
                    'has_no_related' => ['label' => 'Has no related records', 'requires_value' => true, 'value_type' => 'relation', 'field_types' => ['relation']],
                    'related_count_equals' => ['label' => 'Related count equals', 'requires_value' => true, 'value_type' => 'relation_count', 'field_types' => ['relation']],
                    'related_count_gt' => ['label' => 'Related count greater than', 'requires_value' => true, 'value_type' => 'relation_count', 'field_types' => ['relation']],
                    'related_count_lt' => ['label' => 'Related count less than', 'requires_value' => true, 'value_type' => 'relation_count', 'field_types' => ['relation']],
                    'related_count_gte' => ['label' => 'Related count at least', 'requires_value' => true, 'value_type' => 'relation_count', 'field_types' => ['relation']],
                    'related_count_lte' => ['label' => 'Related count at most', 'requires_value' => true, 'value_type' => 'relation_count', 'field_types' => ['relation']],
                ],
            ],
            self::CATEGORY_CHANGE => [
                'label' => 'Change Detection',
                'operators' => [
                    'has_changed' => ['label' => 'Field has changed', 'requires_value' => false, 'field_types' => ['*'], 'context' => 'update'],
                    'has_not_changed' => ['label' => 'Field has not changed', 'requires_value' => false, 'field_types' => ['*'], 'context' => 'update'],
                    'changed_to' => ['label' => 'Changed to', 'requires_value' => true, 'field_types' => ['*'], 'context' => 'update'],
                    'changed_from' => ['label' => 'Changed from', 'requires_value' => true, 'field_types' => ['*'], 'context' => 'update'],
                    'changed_from_to' => ['label' => 'Changed from X to Y', 'requires_value' => true, 'value_type' => 'from_to', 'field_types' => ['*'], 'context' => 'update'],
                    'was_empty_now_filled' => ['label' => 'Was empty, now filled', 'requires_value' => false, 'field_types' => ['*'], 'context' => 'update'],
                    'was_filled_now_empty' => ['label' => 'Was filled, now empty', 'requires_value' => false, 'field_types' => ['*'], 'context' => 'update'],
                ],
            ],
            self::CATEGORY_FORMULA => [
                'label' => 'Advanced',
                'operators' => [
                    'formula' => ['label' => 'Formula (advanced)', 'requires_value' => true, 'value_type' => 'formula', 'field_types' => ['*']],
                ],
            ],
        ];
    }

    /**
     * Get operators available for a specific field type.
     */
    public static function getOperatorsForFieldType(string $fieldType): array
    {
        $allOperators = self::getOperatorsWithMetadata();
        $result = [];

        foreach ($allOperators as $category => $data) {
            $operators = [];
            foreach ($data['operators'] as $key => $meta) {
                $fieldTypes = $meta['field_types'] ?? ['*'];
                if (in_array('*', $fieldTypes) || in_array($fieldType, $fieldTypes)) {
                    $operators[$key] = $meta['label'];
                }
            }
            if (!empty($operators)) {
                $result[$category] = [
                    'label' => $data['label'],
                    'operators' => $operators,
                ];
            }
        }

        return $result;
    }

    /**
     * Get value types supported.
     */
    public static function getValueTypes(): array
    {
        return [
            'static' => 'Static value',
            'field' => 'Another field value',
            'current_user' => 'Current user',
            'current_date' => 'Current date',
            'current_datetime' => 'Current date and time',
            'now' => 'Now (date/time)',
        ];
    }
}
