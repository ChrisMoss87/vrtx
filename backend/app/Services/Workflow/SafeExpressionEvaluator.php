<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use Illuminate\Support\Facades\Log;

/**
 * Safe expression evaluator that replaces dangerous eval() usage.
 * Supports basic arithmetic operations and comparisons without code injection risks.
 */
class SafeExpressionEvaluator
{
    /**
     * Allowed operators for arithmetic expressions.
     */
    private const ARITHMETIC_OPERATORS = ['+', '-', '*', '/', '%'];

    /**
     * Allowed comparison operators.
     */
    private const COMPARISON_OPERATORS = ['==', '!=', '<', '>', '<=', '>=', '===', '!=='];

    /**
     * Allowed logical operators.
     */
    private const LOGICAL_OPERATORS = ['&&', '||', 'and', 'or'];

    /**
     * Evaluate a mathematical expression safely.
     * Only supports: numbers, +, -, *, /, %, parentheses
     *
     * @param string $expression The expression to evaluate (e.g., "10 + 5 * 2")
     * @return float|int|null The result or null if invalid
     */
    public static function evaluateMath(string $expression): float|int|null
    {
        // Remove whitespace
        $expression = preg_replace('/\s+/', '', $expression);

        // Validate that expression only contains safe characters
        if (!preg_match('/^[\d\+\-\*\/\%\(\)\.]+$/', $expression)) {
            Log::warning('SafeExpressionEvaluator: Invalid math expression', [
                'expression' => $expression,
            ]);
            return null;
        }

        // Check for empty or invalid patterns
        if (empty($expression) || preg_match('/[\+\-\*\/\%]{2,}/', $expression)) {
            return null;
        }

        // Check balanced parentheses
        if (substr_count($expression, '(') !== substr_count($expression, ')')) {
            return null;
        }

        try {
            return self::parseExpression($expression);
        } catch (\Throwable $e) {
            Log::warning('SafeExpressionEvaluator: Math evaluation failed', [
                'expression' => $expression,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Evaluate a boolean/comparison expression safely.
     *
     * @param string $expression The expression (e.g., "10 > 5")
     * @param array $context Optional context for variable substitution
     * @return bool The result
     */
    public static function evaluateBoolean(string $expression, array $context = []): bool
    {
        // Replace field references with values first
        $expression = self::substituteVariables($expression, $context);

        // Try to evaluate as a comparison
        foreach (self::COMPARISON_OPERATORS as $operator) {
            if (str_contains($expression, $operator)) {
                $parts = explode($operator, $expression, 2);
                if (count($parts) === 2) {
                    $left = self::evaluateValue(trim($parts[0]));
                    $right = self::evaluateValue(trim($parts[1]));

                    return match ($operator) {
                        '==' => $left == $right,
                        '!=' => $left != $right,
                        '===' => $left === $right,
                        '!==' => $left !== $right,
                        '<' => is_numeric($left) && is_numeric($right) && $left < $right,
                        '>' => is_numeric($left) && is_numeric($right) && $left > $right,
                        '<=' => is_numeric($left) && is_numeric($right) && $left <= $right,
                        '>=' => is_numeric($left) && is_numeric($right) && $left >= $right,
                        default => false,
                    };
                }
            }
        }

        // Try to evaluate as a simple boolean
        $value = self::evaluateValue(trim($expression));
        return (bool) $value;
    }

    /**
     * Substitute variables in an expression from context.
     * Supports {field_name} and {{field_name}} syntax.
     */
    public static function substituteVariables(string $expression, array $context): string
    {
        // Replace {{field}} syntax
        $expression = preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            function ($matches) use ($context) {
                return self::getContextValue($matches[1], $context);
            },
            $expression
        );

        // Replace {field} syntax
        $expression = preg_replace_callback(
            '/\{([^}]+)\}/',
            function ($matches) use ($context) {
                return self::getContextValue($matches[1], $context);
            },
            $expression
        );

        return $expression;
    }

    /**
     * Get a value from context using dot notation.
     */
    private static function getContextValue(string $path, array $context): string
    {
        $path = trim($path);
        $keys = explode('.', $path);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return '0';
            }
        }

        if ($value === null) {
            return '0';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            // Escape for safe comparison
            return "'" . str_replace("'", "\\'", $value) . "'";
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return '0';
        }

        return (string) $value;
    }

    /**
     * Evaluate a single value (number, string, or boolean).
     */
    private static function evaluateValue(string $value): mixed
    {
        $value = trim($value);

        // Boolean literals
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        // Null
        if ($value === 'null') {
            return null;
        }

        // String literal (quoted)
        if (preg_match('/^[\'"](.*)[\'\"]$/', $value, $matches)) {
            return $matches[1];
        }

        // Number
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // Try to evaluate as math expression
        $mathResult = self::evaluateMath($value);
        if ($mathResult !== null) {
            return $mathResult;
        }

        return $value;
    }

    /**
     * Parse and evaluate a mathematical expression using recursive descent parser.
     * This is a safe alternative to eval().
     */
    private static function parseExpression(string $expression): float|int
    {
        $pos = 0;
        $result = self::parseAddSub($expression, $pos);

        if ($pos !== strlen($expression)) {
            throw new \InvalidArgumentException('Unexpected character at position ' . $pos);
        }

        return $result;
    }

    /**
     * Parse addition and subtraction (lowest precedence).
     */
    private static function parseAddSub(string $expr, int &$pos): float|int
    {
        $result = self::parseMulDiv($expr, $pos);

        while ($pos < strlen($expr)) {
            $char = $expr[$pos];

            if ($char === '+') {
                $pos++;
                $result += self::parseMulDiv($expr, $pos);
            } elseif ($char === '-') {
                $pos++;
                $result -= self::parseMulDiv($expr, $pos);
            } else {
                break;
            }
        }

        return $result;
    }

    /**
     * Parse multiplication, division, and modulo (higher precedence).
     */
    private static function parseMulDiv(string $expr, int &$pos): float|int
    {
        $result = self::parseUnary($expr, $pos);

        while ($pos < strlen($expr)) {
            $char = $expr[$pos];

            if ($char === '*') {
                $pos++;
                $result *= self::parseUnary($expr, $pos);
            } elseif ($char === '/') {
                $pos++;
                $divisor = self::parseUnary($expr, $pos);
                if ($divisor == 0) {
                    throw new \DivisionByZeroError('Division by zero');
                }
                $result /= $divisor;
            } elseif ($char === '%') {
                $pos++;
                $divisor = self::parseUnary($expr, $pos);
                if ($divisor == 0) {
                    throw new \DivisionByZeroError('Modulo by zero');
                }
                $result = fmod((float) $result, (float) $divisor);
            } else {
                break;
            }
        }

        return $result;
    }

    /**
     * Parse unary operators (negative numbers).
     */
    private static function parseUnary(string $expr, int &$pos): float|int
    {
        if ($pos < strlen($expr) && $expr[$pos] === '-') {
            $pos++;
            return -self::parsePrimary($expr, $pos);
        }

        if ($pos < strlen($expr) && $expr[$pos] === '+') {
            $pos++;
        }

        return self::parsePrimary($expr, $pos);
    }

    /**
     * Parse primary values: numbers and parenthesized expressions.
     */
    private static function parsePrimary(string $expr, int &$pos): float|int
    {
        // Handle parentheses
        if ($pos < strlen($expr) && $expr[$pos] === '(') {
            $pos++; // Skip '('
            $result = self::parseAddSub($expr, $pos);

            if ($pos >= strlen($expr) || $expr[$pos] !== ')') {
                throw new \InvalidArgumentException('Missing closing parenthesis');
            }
            $pos++; // Skip ')'

            return $result;
        }

        // Parse number
        $start = $pos;
        while ($pos < strlen($expr) && (ctype_digit($expr[$pos]) || $expr[$pos] === '.')) {
            $pos++;
        }

        if ($start === $pos) {
            throw new \InvalidArgumentException('Expected number at position ' . $pos);
        }

        $number = substr($expr, $start, $pos - $start);

        return str_contains($number, '.') ? (float) $number : (int) $number;
    }

    /**
     * Evaluate a formula with field substitution and return the result.
     * This is the main entry point for workflow formula evaluation.
     *
     * @param string $formula The formula with optional {{field}} references
     * @param array $context The context containing field values
     * @return mixed The evaluated result
     */
    public static function evaluate(string $formula, array $context = []): mixed
    {
        // Substitute variables first
        $expression = self::substituteVariables($formula, $context);

        // Check if it's a comparison (boolean result)
        foreach (self::COMPARISON_OPERATORS as $op) {
            if (str_contains($expression, $op)) {
                return self::evaluateBoolean($expression, []);
            }
        }

        // Try as math expression
        // Clean the expression - remove any quotes that might have been added
        $cleanExpr = preg_replace("/^'(.+)'$/", '$1', trim($expression));

        if (is_numeric($cleanExpr)) {
            return str_contains($cleanExpr, '.') ? (float) $cleanExpr : (int) $cleanExpr;
        }

        $mathResult = self::evaluateMath($cleanExpr);
        if ($mathResult !== null) {
            return $mathResult;
        }

        // Return as-is if not evaluable
        return $expression;
    }
}
