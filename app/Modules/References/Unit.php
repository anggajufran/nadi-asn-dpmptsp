<?php
namespace App\Modules\References;
class Unit extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'reference_values';
    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;
}
