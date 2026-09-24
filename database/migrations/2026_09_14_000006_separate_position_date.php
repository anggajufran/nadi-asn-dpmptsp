<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    public function up(): void
    {
        Schema::table(
            'employee_assignments',
            fn(Blueprint $t) => $t->date('position_tmt')->nullable(),
        );
        DB::table('employee_assignments')->update(['position_tmt' => DB::raw('effective_date')]);
    }
    public function down(): void
    {
        Schema::table('employee_assignments', fn(Blueprint $t) => $t->dropColumn('position_tmt'));
    }
};
