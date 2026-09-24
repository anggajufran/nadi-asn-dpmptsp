<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reference_values', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kind', 30);
            $t->string('code', 50);
            $t->string('label', 200);
            $t->boolean('verified')->default(false);
            $t->boolean('active')->default(true);
            $t->jsonb('metadata')->nullable();
            $t->unique(['kind', 'code']);
            $t->timestampsTz();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('nip', 18)->unique();
            $t->string('name', 200);
            $t->string('gender', 20);
            $t->string('birth_place', 100)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('education', 30);
            $t->text('drive_url')->nullable();
            $t->bigInteger('revision')->default(1);
            $t->timestampsTz();
            $t->index('name');
        });
        DB::statement(
            "ALTER TABLE employees ADD CONSTRAINT nip_18_digits CHECK (nip ~ '^[0-9]{18}$')",
        );
        DB::statement(
            "ALTER TABLE employees ADD CONSTRAINT employee_gender CHECK (gender IN ('Perempuan','Laki-laki'))",
        );
        Schema::create('employee_assignments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->constrained()->restrictOnDelete();
            $t->string('type', 30);
            $t->string('unit', 150);
            $t->string('position', 200);
            $t->string('position_type', 50);
            $t->string('grade', 20);
            $t->string('status', 30);
            $t->date('effective_date');
            $t->string('record_status', 20)->default('OFFICIAL');
            $t->foreignUuid('approved_by')->constrained('users')->restrictOnDelete();
            $t->date('contract_start')->nullable();
            $t->date('contract_end')->nullable();
            $t->timestampsTz();
            $t->index(['employee_id', 'record_status', 'effective_date']);
        });
        DB::statement(
            'ALTER TABLE employee_assignments ADD CONSTRAINT contract_date_order CHECK (contract_end IS NULL OR contract_start IS NOT NULL AND contract_end >= contract_start)',
        );
        DB::statement(
            "CREATE UNIQUE INDEX unique_official_assignment_date ON employee_assignments(employee_id, effective_date) WHERE record_status = 'OFFICIAL'",
        );
        Schema::create('change_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('employee_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('maker_id')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('checker_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('status', 30)->default('PENDING')->index();
            $t->jsonb('before');
            $t->jsonb('changes');
            $t->bigInteger('base_revision');
            $t->string('content_hash', 64);
            $t->text('reason');
            $t->text('decision_reason')->nullable();
            $t->timestampTz('applied_at')->nullable();
            $t->timestampsTz();
        });
        DB::statement(
            'ALTER TABLE change_requests ADD CONSTRAINT maker_checker_different CHECK (checker_id IS NULL OR checker_id <> maker_id)',
        );
        Schema::create('audit_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_name', 200);
            $t->string('action', 200);
            $t->string('subject', 200);
            $t->jsonb('metadata');
            $t->timestampTz('created_at')->index();
            $t->index(['actor_id', 'created_at']);
        });
        DB::unprepared(
            "CREATE FUNCTION nadi_prevent_mutation() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'Append-only record'; END; \$\$; CREATE TRIGGER audit_immutable BEFORE UPDATE OR DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION nadi_prevent_mutation();",
        );
    }
    public function down(): void
    {
        foreach (
            [
                'audit_events',
                'change_requests',
                'employee_assignments',
                'employees',
                'reference_values',
            ]
            as $table
        ) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP FUNCTION IF EXISTS nadi_prevent_mutation()');
    }
};
