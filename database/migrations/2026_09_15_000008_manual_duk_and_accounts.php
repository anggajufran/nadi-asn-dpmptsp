<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->unsignedInteger('duk_order')->nullable()->index();
        });
        DB::statement(
            'WITH ordered AS (SELECT id, row_number() OVER (ORDER BY source_order ASC NULLS LAST, created_at, id) AS n FROM employees) UPDATE employees e SET duk_order = ordered.n FROM ordered WHERE e.id = ordered.id',
        );
        Schema::table('users', function (Blueprint $t) {
            $t->foreignUuid('employee_id')
                ->nullable()
                ->unique()
                ->constrained('employees')
                ->restrictOnDelete();
        });
        DB::statement(
            'UPDATE users u SET employee_id = e.id FROM employees e WHERE u.login_name = e.nip',
        );
        DB::statement('ALTER TABLE change_requests DROP CONSTRAINT maker_checker_different');
        DB::unprepared(
            "CREATE FUNCTION nadi_check_self_approval() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.checker_id = NEW.maker_id AND NOT EXISTS (SELECT 1 FROM users WHERE id = NEW.checker_id AND role = 'SUPER_ADMIN') THEN RAISE EXCEPTION 'Only Super Admin may approve own changes'; END IF; RETURN NEW; END; $$; CREATE TRIGGER check_self_approval BEFORE INSERT OR UPDATE ON change_requests FOR EACH ROW EXECUTE FUNCTION nadi_check_self_approval();",
        );
    }
    public function down(): void
    {
        throw new RuntimeException(
            'Migrasi ini menyimpan urutan manual dan kebijakan persetujuan; gunakan migrasi lanjutan.',
        );
    }
};
