<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Quotation $quotation;
    public string $emailSubject;
    public string $messageBody;
    public ?string $pdfContent;
    public ?string $pdfFilename;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Quotation $quotation,
        string $emailSubject,
        string $messageBody,
        ?string $pdfContent = null,
        ?string $pdfFilename = null
    ) {
        $this->quotation    = $quotation;
        $this->emailSubject = $emailSubject;
        $this->messageBody  = $messageBody;
        $this->pdfContent   = $pdfContent;
        $this->pdfFilename  = $pdfFilename ?? ('Quotation_' . ($quotation->quotation_number ?? $quotation->id) . '.pdf');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.quotation',
            with: [
                'quotation'    => $this->quotation,
                'messageBody'  => $this->messageBody,
                'emailSubject' => $this->emailSubject,
                'hasPdf'       => !empty($this->pdfContent),
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
        if (empty($this->pdfContent)) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
