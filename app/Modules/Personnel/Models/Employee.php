<?php
namespace App\Modules\Personnel\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Employee extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'revision' => 'integer',
            'graduation_date' => 'date:Y-m-d',
            'source_issues' => 'array',
            'source_payload' => 'array',
            'source_order' => 'integer',
            'duk_order' => 'integer',
            'age_at_reference' => 'integer',
        ];
    }
    public function scopeInDukOrder($query)
    {
        return $query
            ->orderByRaw('duk_order ASC NULLS LAST')
            ->orderByRaw('source_order ASC NULLS LAST')
            ->orderBy('created_at')
            ->orderBy('id');
    }
    public function assignments()
    {
        return $this->hasMany(EmployeeAssignment::class);
    }
}
