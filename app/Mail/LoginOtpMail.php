<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class LoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $userName;
    public int $expiresInMinutes;

    public function __construct(string $otp, string $userName = 'User', int $expiresInMinutes = 10)
    {
        $this->otp = $otp;
        $this->userName = $userName;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function envelope(): Envelope
    {
        $appName = config('app.name', 'Mostech HRMS');
        $fromAddress = config('mail.from.address', 'mostechtesting@gmail.com');
        $fromName = config('mail.from.name', $appName);

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: [new Address($fromAddress, $fromName)],
            subject: "Your {$appName} Login Verification Code",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-otp',
            text: 'emails.login-otp-text',
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Priority' => '1 (Highest)',
                'X-MSMail-Priority' => 'High',
                'Importance' => 'High',
                'X-Auto-Response-Suppress' => 'All',
                'Auto-Submitted' => 'auto-generated',
                'X-Entity-Ref-Type' => 'transactional',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
