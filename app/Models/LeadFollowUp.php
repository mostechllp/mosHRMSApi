<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadFollowUp extends Model
{
    use HasFactory;

    protected $table = 'lead_follow_ups';

    protected $fillable = [
        'lead_id',
        'follow_up_type',
        'follow_up_date',
        'status',
        'notes',
        'outcome',
        'performed_by',
        'created_by',
    ];

    protected $casts = [
        'follow_up_date' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function performer()
    {
        return $this->belongsTo(Employee::class, 'performed_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
