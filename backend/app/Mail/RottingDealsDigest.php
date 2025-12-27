<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\User\Entities\User;
use App\Services\Rotting\DealRottingService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RottingDealsDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public Collection $rottingDeals
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $count = $this->rottingDeals->count();
        $rottingCount = $this->rottingDeals->where('rot_status.status', DealRottingService::STATUS_ROTTING)->count();

        $subject = $rottingCount > 0
            ? "Action Required: {$rottingCount} deals need attention"
            : "Deal Health Update: {$count} deals to review";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rotting-deals-digest',
            with: [
                'user' => $this->user,
                'deals' => $this->rottingDeals,
                'summary' => $this->getSummary(),
            ],
        );
    }

    /**
     * Get summary statistics.
     */
    protected function getSummary(): array
    {
        return [
            'total' => $this->rottingDeals->count(),
            'rotting' => $this->rottingDeals->where('rot_status.status', DealRottingService::STATUS_ROTTING)->count(),
            'stale' => $this->rottingDeals->where('rot_status.status', DealRottingService::STATUS_STALE)->count(),
            'warming' => $this->rottingDeals->where('rot_status.status', DealRottingService::STATUS_WARMING)->count(),
        ];
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
