<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proposals';

    public const STATUS_PREPARING = 'Preparing';
    public const STATUS_PENDING   = 'Pending';
    public const STATUS_SENT      = 'Sent';
    public const STATUS_APPROVED  = 'Approved';
    public const STATUS_REJECT    = 'Reject';

    public const STATUSES = [
        self::STATUS_PREPARING,
        self::STATUS_PENDING,
        self::STATUS_SENT,
        self::STATUS_APPROVED,
        self::STATUS_REJECT,
    ];

    protected $fillable = [
        'serial_no',
        'date',
        'customer',
        'customer_id',
        'proposal_reference_no',
        'drive_link',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'serial_no'   => 'integer',
        'date'        => 'date:Y-m-d',
        'customer_id' => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    protected $appends = [
        's_no',
        'proposal_reference',
    ];

    /**
     * Alias for serial_no (S.No).
     */
    public function getSNoAttribute(): ?int
    {
        return $this->serial_no;
    }

    /**
     * Alias for proposal_reference_no.
     */
    public function getProposalReferenceAttribute(): ?string
    {
        return $this->proposal_reference_no;
    }

    /**
     * Customer relationship (optional if customer exists in Customer table).
     */
    public function customerRelation(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Creator relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Updater relationship.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
