<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\ValueObjects;

/**
 * Value object representing a user's cursor position in a document.
 */
final class CursorPosition
{
    private function __construct(
        private int $line,
        private int $column,
        private ?int $selectionStartLine,
        private ?int $selectionStartColumn,
        private ?int $selectionEndLine,
        private ?int $selectionEndColumn,
    ) {}

    /**
     * Create a cursor position without selection.
     */
    public static function at(int $line, int $column): self
    {
        return new self(
            line: $line,
            column: $column,
            selectionStartLine: null,
            selectionStartColumn: null,
            selectionEndLine: null,
            selectionEndColumn: null,
        );
    }

    /**
     * Create a cursor position with selection.
     */
    public static function withSelection(
        int $line,
        int $column,
        int $selectionStartLine,
        int $selectionStartColumn,
        int $selectionEndLine,
        int $selectionEndColumn,
    ): self {
        return new self(
            line: $line,
            column: $column,
            selectionStartLine: $selectionStartLine,
            selectionStartColumn: $selectionStartColumn,
            selectionEndLine: $selectionEndLine,
            selectionEndColumn: $selectionEndColumn,
        );
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function hasSelection(): bool
    {
        return $this->selectionStartLine !== null;
    }

    public function getSelectionStart(): ?array
    {
        if (!$this->hasSelection()) {
            return null;
        }

        return [
            'line' => $this->selectionStartLine,
            'column' => $this->selectionStartColumn,
        ];
    }

    public function getSelectionEnd(): ?array
    {
        if (!$this->hasSelection()) {
            return null;
        }

        return [
            'line' => $this->selectionEndLine,
            'column' => $this->selectionEndColumn,
        ];
    }

    /**
     * Convert to array for persistence.
     */
    public function toArray(): array
    {
        $data = [
            'line' => $this->line,
            'column' => $this->column,
        ];

        if ($this->hasSelection()) {
            $data['selection'] = [
                'start' => [
                    'line' => $this->selectionStartLine,
                    'column' => $this->selectionStartColumn,
                ],
                'end' => [
                    'line' => $this->selectionEndLine,
                    'column' => $this->selectionEndColumn,
                ],
            ];
        }

        return $data;
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        if (isset($data['selection'])) {
            return self::withSelection(
                line: $data['line'],
                column: $data['column'],
                selectionStartLine: $data['selection']['start']['line'],
                selectionStartColumn: $data['selection']['start']['column'],
                selectionEndLine: $data['selection']['end']['line'],
                selectionEndColumn: $data['selection']['end']['column'],
            );
        }

        return self::at($data['line'], $data['column']);
    }

    public function equals(self $other): bool
    {
        return $this->line === $other->line
            && $this->column === $other->column
            && $this->selectionStartLine === $other->selectionStartLine
            && $this->selectionStartColumn === $other->selectionStartColumn
            && $this->selectionEndLine === $other->selectionEndLine
            && $this->selectionEndColumn === $other->selectionEndColumn;
    }
}
