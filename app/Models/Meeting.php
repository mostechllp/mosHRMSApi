<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'date',
        'start_time',
        'end_time',
        'host_user_id',
        'participant_ids',   // stored as JSON array of user IDs
        'client_name',
        'client_email',
        'related_to',        // plain string text field – NOT an enum/dropdown
        'description',
        'meeting_link',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'participant_ids' => 'array',
        'date' => 'date:Y-m-d',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    //  Relationships
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The host of the meeting (a User).
     */
    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /**
     * Participants resolved from the participant_ids JSON array.
     * Returns a Collection of User models.
     */
    public function getParticipantsAttribute()
    {
        if (empty($this->participant_ids)) {
            return collect();
        }

        return User::whereIn('id', $this->participant_ids)
            ->select('id', 'username', 'email')
            ->with('employee:id,user_id,avatar')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'avatar' => $user->employee->avatar ?? null,
                ];
            });
    }

    /**
     * Who created this meeting record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who last updated this meeting record.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Booted
    // ──────────────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Meeting $meeting) {
            if (auth()->check()) {
                $meeting->created_by = $meeting->created_by ?? auth()->id();
                // Default host to the authenticated user if not provided
                $meeting->host_user_id = $meeting->host_user_id ?? auth()->id();
            }
        });

        static::updating(function (Meeting $meeting) {
            if (auth()->check()) {
                $meeting->updated_by = auth()->id();
            }
        });
    }
}
