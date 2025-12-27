<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\ValueObjects;

use InvalidArgumentException;

/**
 * Value object encapsulating the document content stored as Y.js state.
 */
final class DocumentContent
{
    private function __construct(
        private string $yjsState,
        private ?string $htmlSnapshot,
        private ?string $textContent,
        private int $characterCount,
        private int $wordCount,
    ) {}

    /**
     * Create content from a Y.js state vector (base64 encoded).
     */
    public static function fromYjsState(
        string $yjsState,
        ?string $htmlSnapshot = null,
        ?string $textContent = null,
    ): self {
        $charCount = $textContent !== null ? mb_strlen($textContent) : 0;
        $wordCount = $textContent !== null ? str_word_count($textContent) : 0;

        return new self(
            yjsState: $yjsState,
            htmlSnapshot: $htmlSnapshot,
            textContent: $textContent,
            characterCount: $charCount,
            wordCount: $wordCount,
        );
    }

    /**
     * Create empty content for a new document.
     */
    public static function empty(DocumentType $type): self
    {
        return new self(
            yjsState: '',
            htmlSnapshot: null,
            textContent: null,
            characterCount: 0,
            wordCount: 0,
        );
    }

    /**
     * Create content from raw binary Y.js state.
     */
    public static function fromBinary(
        string $binaryState,
        ?string $htmlSnapshot = null,
        ?string $textContent = null,
    ): self {
        return self::fromYjsState(
            base64_encode($binaryState),
            $htmlSnapshot,
            $textContent,
        );
    }

    /**
     * Get the Y.js state as base64 encoded string.
     */
    public function getYjsState(): string
    {
        return $this->yjsState;
    }

    /**
     * Get the Y.js state as raw binary.
     */
    public function getYjsStateBinary(): string
    {
        return base64_decode($this->yjsState) ?: '';
    }

    /**
     * Get the HTML snapshot for previewing.
     */
    public function getHtmlSnapshot(): ?string
    {
        return $this->htmlSnapshot;
    }

    /**
     * Get plain text content for search indexing.
     */
    public function getTextContent(): ?string
    {
        return $this->textContent;
    }

    /**
     * Get character count.
     */
    public function getCharacterCount(): int
    {
        return $this->characterCount;
    }

    /**
     * Get word count.
     */
    public function getWordCount(): int
    {
        return $this->wordCount;
    }

    /**
     * Check if content is empty.
     */
    public function isEmpty(): bool
    {
        return $this->yjsState === '' || $this->characterCount === 0;
    }

    /**
     * Create a new content with updated snapshots.
     */
    public function withSnapshots(?string $htmlSnapshot, ?string $textContent): self
    {
        return new self(
            yjsState: $this->yjsState,
            htmlSnapshot: $htmlSnapshot,
            textContent: $textContent,
            characterCount: $textContent !== null ? mb_strlen($textContent) : 0,
            wordCount: $textContent !== null ? str_word_count($textContent) : 0,
        );
    }

    /**
     * Create a new content with merged Y.js state.
     */
    public function withMergedState(string $newYjsState): self
    {
        return new self(
            yjsState: $newYjsState,
            htmlSnapshot: $this->htmlSnapshot,
            textContent: $this->textContent,
            characterCount: $this->characterCount,
            wordCount: $this->wordCount,
        );
    }

    public function equals(self $other): bool
    {
        return $this->yjsState === $other->yjsState;
    }
}
