<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->integer('source_order')->nullable();
            $t->text('institution')->nullable();
            $t->date('graduation_date')->nullable();
            $t->integer('age_at_reference')->nullable();
            $t->jsonb('source_payload')->nullable();
            $t->jsonb('source_issues')->nullable();
        });
        Schema::table('employee_assignments', function (Blueprint $t) {
            $t->foreignUuid('unit_id')->nullable()->constrained('reference_values');
            $t->string('rank_name', 200)->nullable();
            $t->date('rank_tmt')->nullable();
            $t->integer('service_years')->nullable();
            $t->integer('service_months')->nullable();
            $t->string('service_reference', 7)->default('2026-09');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('email', 200)->nullable()->change();
            $t->string('login_name', 200)->nullable()->unique();
        });
        Schema::create('account_activations', function (Blueprint $t) {
            $t->foreignUuid('user_id')->primary()->constrained('users');
            $t->string('token_hash', 64);
            $t->timestampTz('expires_at');
        });
        DB::table('users')
            ->whereNotIn('role', ['ADMIN', 'SUPER_ADMIN'])
            ->update(['is_active' => false, 'security_version' => DB::raw('security_version+1')]);
        DB::table('employee_assignments')
            ->where('type', 'PPPK Full Time')
            ->update(['type' => 'PPPK Penuh Waktu']);
    }
    public function down(): void
    {
        throw new \RuntimeException(
            'Migration contains personnel history; use a reviewed forward migration.',
        );
    }
};
