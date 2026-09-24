<?php
namespace App\Modules\Imports\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class ImportBatch extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['mapping' => 'array', 'expires_at' => 'datetime', 'headers' => 'array'];
    }
    public function rows()
    {
        return $this->hasMany(ImportRow::class, 'batch_id');
    }
}
