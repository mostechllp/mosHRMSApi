<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warning extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'title',
        'subject',
        'description',
        'status',
        'issued_date',
        'created_by',
        'email_sent_at',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'email_sent_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
