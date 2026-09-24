<?php
namespace App\Modules\Audit\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class AuditEvent extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }
    protected static function booted(): void
    {
        static::updating(fn() => throw new \LogicException('Audit events are append-only.'));
        static::deleting(fn() => throw new \LogicException('Audit events are append-only.'));
    }
}
