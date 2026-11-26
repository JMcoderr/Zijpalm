<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CharityRequestReviewedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly bool $approved,
        public readonly ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->approved
                ? 'Je aanvraag voor Lief & Leed is goedgekeurd'
                : 'Je aanvraag voor Lief & Leed is afgewezen',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.charity-request-reviewed',
            with: [
                'approved' => $this->approved,
                'reason' => $this->approved ? null : $this->reason,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
