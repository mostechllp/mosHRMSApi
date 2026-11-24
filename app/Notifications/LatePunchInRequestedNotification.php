<?php

namespace App\Notifications;

use App\Models\AttendanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LatePunchInRequestedNotification extends Notification
{
    use Queueable;

    public AttendanceRequest $attendanceRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(AttendanceRequest $attendanceRequest)
    {
        $this->attendanceRequest = $attendanceRequest;
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
        $employee = $this->attendanceRequest->employee;
        $employeeName = $employee ? trim("{$employee->first_name} {$employee->last_name}") : 'An employee';
        $timeStr = $this->attendanceRequest->request_time ?? 'N/A';
        $dateStr = $this->attendanceRequest->request_date ?? 'today';
        $reason = $this->attendanceRequest->reason ?: 'No reason provided';

        return (new MailMessage)
            ->subject("Late Punch-in Approval Request: {$employeeName}")
            ->greeting("Hello {$notifiable->username},")
            ->line("{$employeeName} attempted to punch in after 10:00 AM on {$dateStr} at {$timeStr}.")
            ->line("**Reason for Late Arrival:** {$reason}")
            ->line("The employee is currently blocked from punching in until HR approves this request.")
            ->action('Review Attendance Requests', url('/attendance/requests'))
            ->line('Please review and approve or reject this request.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $employee = $this->attendanceRequest->employee;
        $employeeName = $employee ? trim("{$employee->first_name} {$employee->last_name}") : 'An employee';

        return [
            'type'            => 'late_punch_in_request',
            'request_id'      => $this->attendanceRequest->id,
            'employee_id'     => $this->attendanceRequest->employee_id,
            'employee_name'   => $employeeName,
            'request_date'    => $this->attendanceRequest->request_date,
            'request_time'    => $this->attendanceRequest->request_time,
            'reason'          => $this->attendanceRequest->reason,
            'status'          => $this->attendanceRequest->status,
            'title'           => 'Late Punch-in Approval Required',
            'message'         => "{$employeeName} requested late punch-in approval ({$this->attendanceRequest->request_time}). Reason: {$this->attendanceRequest->reason}",
        ];
    }
}
