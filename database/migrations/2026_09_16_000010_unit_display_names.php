<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void{
  foreach(['KEPALA DINAS'=>'Kepala Dinas','SEKRETARIS DINAS'=>'Sekretaris'] as $old=>$new){
   $unit=DB::table('reference_values')->where('kind','unit')->where('label',$old)->first();
   if(!$unit)continue;
   DB::table('employee_assignments')->where('unit',$old)->whereNull('unit_id')->update(['unit_id'=>$unit->id]);
   DB::table('reference_values')->where('id',$unit->id)->update(['label'=>$new,'updated_at'=>now()]);
  }
 }
 public function down():void{}
};
