<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void
    {
        Schema::create('rulesets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kind', 10);
            $t->integer('version');
            $t->string('status', 30)->default('DRAFT');
            $t->jsonb('parameters');
            $t->text('regulatory_basis')->nullable();
            $t->string('content_hash', 64);
            $t->foreignUuid('maker_id')->constrained('users');
            $t->foreignUuid('checker_id')->nullable()->constrained('users');
            $t->date('effective_date')->nullable();
            $t->timestampsTz();
            $t->unique(['kind', 'version']);
        });
        Schema::create('import_batches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('maker_id')->constrained('users');
            $t->string('filename', 200);
            $t->string('file_path');
            $t->string('checksum', 64)->index();
            $t->string('status', 30)->default('UPLOADED');
            $t->jsonb('mapping')->nullable();
            $t->timestampTz('expires_at');
            $t->timestampsTz();
        });
        Schema::create('import_rows', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('batch_id')->constrained('import_batches')->restrictOnDelete();
            $t->integer('row_number');
            $t->string('status', 30);
            $t->jsonb('source');
            $t->jsonb('diff')->nullable();
            $t->jsonb('errors')->nullable();
            $t->foreignUuid('change_request_id')->nullable()->constrained('change_requests');
            $t->timestampsTz();
            $t->unique(['batch_id', 'row_number']);
        });
        Schema::create('published_snapshots', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kind', 30);
            $t->string('version_label', 50);
            $t->date('as_of');
            $t->foreignUuid('ruleset_id')->constrained('rulesets');
            $t->jsonb('payload');
            $t->string('content_hash', 64);
            $t->string('template_version', 50);
            $t->foreignUuid('maker_id')->constrained('users');
            $t->foreignUuid('publisher_id')->constrained('users');
            $t->timestampTz('published_at');
        });
        DB::statement(
            'ALTER TABLE published_snapshots ADD CONSTRAINT snapshot_checker CHECK (publisher_id <> maker_id)',
        );
        DB::statement(
            'CREATE TRIGGER snapshots_immutable BEFORE UPDATE OR DELETE ON published_snapshots FOR EACH ROW EXECUTE FUNCTION nadi_prevent_mutation()',
        );
        Schema::create('public_releases', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->date('as_of');
            $t->bigInteger('data_revision');
            $t->string('policy_version', 30);
            $t->jsonb('safe_payload');
            $t->string('content_hash', 64);
            $t->foreignUuid('maker_id')->constrained('users');
            $t->foreignUuid('checker_id')->constrained('users');
            $t->timestampTz('approved_at');
            $t->timestampTz('expires_at');
        });
        DB::statement(
            'ALTER TABLE public_releases ADD CONSTRAINT public_checker CHECK (checker_id <> maker_id)',
        );
        DB::statement(
            'CREATE TRIGGER public_releases_immutable BEFORE UPDATE OR DELETE ON public_releases FOR EACH ROW EXECUTE FUNCTION nadi_prevent_mutation()',
        );
        Schema::create('export_jobs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('owner_id')->constrained('users');
            $t->string('status', 30);
            $t->string('format', 10);
            $t->string('kind', 30);
            $t->string('file_path')->nullable();
            $t->jsonb('parameters');
            $t->timestampTz('expires_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained('users');
            $t->string('kind', 50);
            $t->string('title', 200);
            $t->jsonb('payload');
            $t->timestampTz('read_at')->nullable();
            $t->timestampsTz();
            $t->index(['user_id', 'read_at']);
        });
    }
    public function down(): void
    {
        foreach (
            [
                'notifications',
                'export_jobs',
                'public_releases',
                'published_snapshots',
                'import_rows',
                'import_batches',
                'rulesets',
            ]
            as $table
        ) {
            Schema::dropIfExists($table);
        }
    }
};
