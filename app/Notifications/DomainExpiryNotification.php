<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DomainExpiryNotification extends Notification
{
    use Queueable;

    protected array $expiryData;

    /**
     * Create a new notification instance.
     *
     * @param array $expiryData
     */
    public function __construct(array $expiryData)
    {
        $this->expiryData = $expiryData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->expiryData['title'] ?? 'Domain / Email Expiry Alert';
        $message = $this->expiryData['message'] ?? 'A domain or project email is nearing expiry.';

        $recipientName = $notifiable->username ?: ($notifiable->email ?: 'User');

        return (new MailMessage)
            ->subject($title)
            ->greeting("Hello {$recipientName},")
            ->line($message)
            ->line('Expiry Date: ' . ($this->expiryData['expiry_date'] ?? 'N/A'))
            ->action('View Projects', url('/admin/projects'))
            ->line('Please take the necessary action.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->expiryData['type'] ?? 'domain_expiry',
            'title' => $this->expiryData['title'] ?? null,
            'name' => $this->expiryData['name'] ?? null,
            'project_name' => $this->expiryData['project_name'] ?? null,
            'expiry_date' => $this->expiryData['expiry_date'] ?? null,
            'days_remaining' => $this->expiryData['days_remaining'] ?? null,
            'status' => $this->expiryData['status'] ?? null,
            'message' => $this->expiryData['message'] ?? '',
        ];
    }
}
