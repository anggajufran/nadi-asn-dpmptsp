<?php
namespace App\Modules\Imports\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class ImportRow extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['source' => 'array', 'diff' => 'array', 'errors' => 'array'];
    }
}
