<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectDomainExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;
    public array $expiringDomains;
    public array $expiringEmails;
    public int $daysThreshold;

    /**
     * Create a new message instance.
     */
    public function __construct(string $recipientName, array $expiringDomains, array $expiringEmails, int $daysThreshold = 30)
    {
        $this->recipientName = $recipientName;
        $this->expiringDomains = $expiringDomains;
        $this->expiringEmails = $expiringEmails;
        $this->daysThreshold = $daysThreshold;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $count = count($this->expiringDomains) + count($this->expiringEmails);
        return new Envelope(
            subject: "Domain & Project Email Expiry Alert ({$count} item(s) expiring/expired)",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.project_domain_expiry',
            with: [
                'recipientName'   => $this->recipientName,
                'expiringDomains' => $this->expiringDomains,
                'expiringEmails'  => $this->expiringEmails,
                'daysThreshold'   => $this->daysThreshold,
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
        return [];
    }
}
