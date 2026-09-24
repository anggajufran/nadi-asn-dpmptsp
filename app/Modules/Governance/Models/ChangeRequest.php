<?php
namespace App\Modules\Governance\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class ChangeRequest extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'before' => 'array',
            'applied_at' => 'datetime',
            'base_revision' => 'integer',
        ];
    }
}
