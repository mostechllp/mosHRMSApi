<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lead_id',
        'lead_type',
        'lead_name',
        'company_name',
        'designation',
        'industry',
        'website',
        'lead_source',
        'lead_status',
        'priority',
        'email',
        'primary_phone',
        'alternate_phone',
        'postal_code',
        'address',
        'city',
        'state',
        'country',
        'assigned_salesperson_id',
        'sales_team',
        'expected_value',
        'expected_closing_date',
        'interested_products',
        'notes',
        'next_follow_up_at',
        'follow_up_type',
        'follow_up_notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'interested_products'   => 'array',
        'expected_value'        => 'decimal:2',
        'expected_closing_date' => 'date',
        'next_follow_up_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($lead) {
            if (empty($lead->lead_id)) {
                $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
                $lead->lead_id = 'LED-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function assignedSalesperson()
    {
        return $this->belongsTo(Employee::class, 'assigned_salesperson_id');
    }

    public function followUps()
    {
        return $this->hasMany(LeadFollowUp::class, 'lead_id')->orderBy('follow_up_date', 'desc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
