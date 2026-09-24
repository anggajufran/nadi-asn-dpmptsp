<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_assignments', function (Blueprint $t) {
            $t->string('duk_position_level', 60)->nullable();
            $t->string('training_level', 40)->nullable();
            $t->date('training_date')->nullable();
        });
        Schema::create('duk_candidates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('maker_id')->constrained('users');
            $t->foreignUuid('ruleset_id')->constrained('rulesets');
            $t->date('as_of');
            $t->jsonb('payload');
            $t->string('content_hash', 64);
            $t->timestampTz('created_at');
        });
        DB::statement(
            'CREATE TRIGGER duk_candidates_immutable BEFORE UPDATE OR DELETE ON duk_candidates FOR EACH ROW EXECUTE FUNCTION nadi_prevent_mutation()',
        );
        Schema::table('published_snapshots', function (Blueprint $t) {
            $t->foreignUuid('duk_candidate_id')
                ->nullable()
                ->unique()
                ->constrained('duk_candidates');
        });
    }
    public function down(): void
    {
        Schema::table(
            'published_snapshots',
            fn(Blueprint $t) => $t->dropConstrainedForeignId('duk_candidate_id'),
        );
        Schema::dropIfExists('duk_candidates');
        Schema::table(
            'employee_assignments',
            fn(Blueprint $t) => $t->dropColumn([
                'duk_position_level',
                'training_level',
                'training_date',
            ]),
        );
    }
};
