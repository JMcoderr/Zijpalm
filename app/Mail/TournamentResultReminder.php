<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TournamentResultReminder extends Mailable
{
    use SerializesModels;

    public function __construct(public Collection $matches) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ontbrekende uitslagen van de voetbalpoule',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.tournament-result-reminder',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
