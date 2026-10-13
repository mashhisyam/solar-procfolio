<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyDigest extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(public array $rows) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Procfolio: '.count($this->rows).' pengadaan perlu ditindaklanjuti');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.daily-digest', with: ['rows' => $this->rows, 'url' => rtrim(config('app.url'), '/')]);
    }
}
