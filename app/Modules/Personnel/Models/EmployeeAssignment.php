<?php
namespace App\Modules\Personnel\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class EmployeeAssignment extends Model
{
    use HasUuids;
    protected $guarded = ['id'];
    public function unitReference()
    {
        return $this->belongsTo(\App\Modules\References\Unit::class, 'unit_id');
    }
    protected function casts(): array
    {
        return [
            'effective_date' => 'date:Y-m-d',
            'training_date' => 'date:Y-m-d',
            'rank_tmt' => 'date:Y-m-d',
            'position_tmt' => 'date:Y-m-d',
            'service_years' => 'integer',
            'service_months' => 'integer',
        ];
    }
}
