<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'created_by',
        'deleted_by',
        'parent_id'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * "Parent/Child/Grandchild" — a top-level folder just returns its own name.
     */
    public function getFullPathAttribute(): string
    {
        $segments = [$this->name];
        $node = $this;

        while ($node->parent_id) {
            $node = $node->parent ?? Folder::withTrashed()->find($node->parent_id);
            if (!$node) {
                break;
            }
            array_unshift($segments, $node->name);
        }

        return implode('/', $segments);
    }

    /**
     * All descendant folder ids (children, grandchildren, ...), this folder excluded.
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->children()->get() as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }
}
