<?php

namespace App\Mail;

use App\Models\Warning;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WarningNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Warning $warning;

    /**
     * Create a new message instance.
     */
    public function __construct(Warning $warning)
    {
        $this->warning = $warning;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->warning->subject ?? 'Official Warning Notice - ' . $this->warning->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.warning_notice',
            with: [
                'warning' => $this->warning,
                'employee' => $this->warning->employee,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (empty($this->warning->attachment_path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('public', $this->warning->attachment_path)
                ->as($this->warning->attachment_name ?? basename($this->warning->attachment_path)),
        ];
    }
}