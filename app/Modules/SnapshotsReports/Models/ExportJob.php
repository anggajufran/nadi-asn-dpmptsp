<?php
namespace App\Modules\SnapshotsReports\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class ExportJob extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['parameters' => 'array', 'expires_at' => 'datetime'];
    }
}
