<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectEmail extends Model
{
    protected $fillable = [
        'project_id',
        'email_name',
        'purchase_date',
        'expiry_date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
