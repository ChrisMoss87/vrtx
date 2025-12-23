<?php

declare(strict_types=1);

namespace App\Domain\Communication\Contracts;

use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\MessageDirection;
use App\Domain\Communication\ValueObjects\MessageParticipant;
use App\Domain\Communication\ValueObjects\RecordContext;

interface UnifiedMessageInterface
{
    public function getId(): ?int;

    public function getConversationId(): int;

    public function getChannelType(): ChannelType;

    public function getDirection(): MessageDirection;

    public function getContent(): ?string;

    public function getHtmlContent(): ?string;

    public function getSender(): MessageParticipant;

    public function getRecipients(): array;

    public function getAttachments(): array;

    public function getSourceMessageId(): ?string;

    public function getExternalMessageId(): ?string;

    public function getStatus(): string;

    public function getMetadata(): array;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getSentAt(): ?\DateTimeImmutable;

    public function getDeliveredAt(): ?\DateTimeImmutable;

    public function getReadAt(): ?\DateTimeImmutable;
}
