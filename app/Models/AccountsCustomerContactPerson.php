<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountsCustomerContactPerson extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accounts_customer_contact_persons';

    protected $fillable = [
        'customer_id',
        'salutation',
        'first_name',
        'last_name',
        'email',
        'work_phone',
        'mobile',
    ];

    protected $appends = [
        'firstName',
        'lastName',
        'workPhone',
        'fullName',
    ];

    public function customer()
    {
        return $this->belongsTo(AccountsCustomer::class, 'customer_id');
    }

    public function getFirstNameAttribute(): ?string
    {
        return $this->attributes['first_name'] ?? null;
    }

    public function getLastNameAttribute(): ?string
    {
        return $this->attributes['last_name'] ?? null;
    }

    public function getWorkPhoneAttribute(): ?string
    {
        return $this->attributes['work_phone'] ?? null;
    }

    public function getFullNameAttribute(): string
    {
        $salutation = $this->salutation ? $this->salutation . ' ' : '';
        return trim($salutation . ($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}
