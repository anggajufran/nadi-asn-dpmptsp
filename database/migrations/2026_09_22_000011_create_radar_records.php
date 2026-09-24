<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('radar_records', function (Blueprint $t) {
            $t->uuid('employee_id');
            $t->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $t->string('kind', 3);
            $t->jsonb('data');
            $t->unsignedInteger('revision')->default(1);
            $t->unsignedInteger('employee_revision');
            $t->uuid('updated_by');
            $t->foreign('updated_by')->references('id')->on('users');
            $t->timestamps();
            $t->primary(['employee_id', 'kind']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('radar_records');
    }
};
