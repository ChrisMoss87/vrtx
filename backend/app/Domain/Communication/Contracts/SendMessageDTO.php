<?php

declare(strict_types=1);

namespace App\Domain\Communication\Contracts;

use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\MessageParticipant;
use App\Domain\Communication\ValueObjects\RecordContext;

final readonly class SendMessageDTO
{
    public function __construct(
        public ChannelType $channel,
        public string $conversationId,
        public string $content,
        public ?string $htmlContent,
        public MessageParticipant $sender,
        public array $recipients,
        public array $attachments = [],
        public ?RecordContext $recordContext = null,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            channel: ChannelType::from($data['channel']),
            conversationId: (string) $data['conversation_id'],
            content: $data['content'],
            htmlContent: $data['html_content'] ?? null,
            sender: MessageParticipant::fromArray($data['sender']),
            recipients: array_map(
                fn(array $r) => MessageParticipant::fromArray($r),
                $data['recipients'] ?? []
            ),
            attachments: $data['attachments'] ?? [],
            recordContext: isset($data['record_context'])
                ? RecordContext::fromArray($data['record_context'])
                : null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
