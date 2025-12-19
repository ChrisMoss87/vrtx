<?php

declare(strict_types=1);

namespace App\Domain\Reporting\ValueObjects;

use JsonSerializable;

/**
 * Represents a calculated/formula field in a report.
 *
 * Supports:
 * - Basic arithmetic: +, -, *, /
 * - Functions: IF, CASE, COALESCE, ABS, ROUND, FLOOR, CEIL
 * - Date functions: DATEDIFF, DATE_PART
 * - Aggregations: SUM, AVG, COUNT, MIN, MAX
 */
final readonly class CalculatedField implements JsonSerializable
{
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_STRING = 'string';
    public const TYPE_BOOLEAN = 'boolean';

    /**
     * @param string $name Unique name/alias for this calculated field
     * @param string $formula The formula expression
     * @param string $label Display label
     * @param string $resultType Expected result type
     * @param array<string> $dependencies Field names this formula depends on
     * @param int|null $precision Decimal precision for number results
     */
    public function __construct(
        public string $name,
        public string $formula,
        public string $label,
        public string $resultType = self::TYPE_NUMBER,
        public array $dependencies = [],
        public ?int $precision = 2,
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        $formula = $data['formula'] ?? '';

        return new self(
            name: $data['name'] ?? $data['alias'] ?? 'calculated_' . time(),
            formula: $formula,
            label: $data['label'] ?? $data['name'] ?? 'Calculated Field',
            resultType: $data['result_type'] ?? self::TYPE_NUMBER,
            dependencies: $data['dependencies'] ?? self::extractDependencies($formula),
            precision: $data['precision'] ?? 2,
        );
    }

    /**
     * Extract field dependencies from a formula.
     *
     * @return array<string>
     */
    public static function extractDependencies(string $formula): array
    {
        // Match field references like {field_name} or {module.field_name}
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_\.]*)\}/', $formula, $matches);

        return array_unique($matches[1] ?? []);
    }

    /**
     * Convert formula to SQL expression.
     *
     * @param callable|null $fieldResolver Optional function to resolve field to SQL column
     */
    public function toSqlExpression(?callable $fieldResolver = null): string
    {
        $sql = $this->formula;

        // Default field resolver using JSONB
        $resolver = $fieldResolver ?? fn(string $field) => $this->defaultFieldToSql($field);

        // Replace field references with SQL columns
        $sql = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_\.]*)\}/',
            fn($matches) => $resolver($matches[1]),
            $sql
        );

        // Convert function names to PostgreSQL equivalents
        $sql = $this->convertFunctions($sql);

        return $sql;
    }

    /**
     * Default field to SQL converter.
     */
    private function defaultFieldToSql(string $field): string
    {
        // Handle system fields
        if (in_array($field, ['id', 'created_at', 'updated_at', 'module_id'])) {
            return $field;
        }

        // Handle module-prefixed fields (e.g., company.name)
        if (str_contains($field, '.')) {
            [$alias, $fieldName] = explode('.', $field, 2);
            if (in_array($fieldName, ['id', 'created_at', 'updated_at', 'module_id'])) {
                return "\"{$alias}\".{$fieldName}";
            }
            return "\"{$alias}\".data->>'{$fieldName}'";
        }

        // Regular JSON field
        return "data->>'{$field}'";
    }

    /**
     * Convert common function names to PostgreSQL.
     */
    private function convertFunctions(string $sql): string
    {
        // Date difference - DATEDIFF(end, start) => EXTRACT(EPOCH FROM (end - start)) / 86400
        $sql = preg_replace_callback(
            '/DATEDIFF\s*\(\s*([^,]+)\s*,\s*([^)]+)\s*\)/i',
            fn($m) => "EXTRACT(EPOCH FROM ({$m[1]}::timestamp - {$m[2]}::timestamp)) / 86400",
            $sql
        );

        // Date part - DATE_PART('day', field) stays the same for PostgreSQL
        // Nothing to convert

        // IF(condition, true_val, false_val) => CASE WHEN condition THEN true_val ELSE false_val END
        $sql = preg_replace_callback(
            '/IF\s*\(\s*([^,]+)\s*,\s*([^,]+)\s*,\s*([^)]+)\s*\)/i',
            fn($m) => "CASE WHEN {$m[1]} THEN {$m[2]} ELSE {$m[3]} END",
            $sql
        );

        // CONCAT stays the same for PostgreSQL
        // COALESCE stays the same
        // ABS, ROUND, FLOOR, CEIL stay the same

        return $sql;
    }

    /**
     * Validate the formula syntax.
     *
     * @return array<string> List of validation errors, empty if valid
     */
    public function validate(): array
    {
        $errors = [];

        // Check for empty formula
        if (empty(trim($this->formula))) {
            $errors[] = 'Formula cannot be empty';
            return $errors;
        }

        // Check for balanced parentheses
        $open = substr_count($this->formula, '(');
        $close = substr_count($this->formula, ')');
        if ($open !== $close) {
            $errors[] = 'Unbalanced parentheses in formula';
        }

        // Check for balanced braces
        $openBrace = substr_count($this->formula, '{');
        $closeBrace = substr_count($this->formula, '}');
        if ($openBrace !== $closeBrace) {
            $errors[] = 'Unbalanced braces in formula';
        }

        // Check for disallowed SQL keywords (security)
        $disallowed = ['DROP', 'DELETE', 'INSERT', 'UPDATE', 'TRUNCATE', 'ALTER', 'CREATE', 'EXEC', 'EXECUTE', ';', '--'];
        foreach ($disallowed as $keyword) {
            if (stripos($this->formula, $keyword) !== false) {
                $errors[] = "Formula contains disallowed keyword: {$keyword}";
            }
        }

        return $errors;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'formula' => $this->formula,
            'label' => $this->label,
            'result_type' => $this->resultType,
            'dependencies' => $this->dependencies,
            'precision' => $this->precision,
        ];
    }
}
