<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name', 200);
            $t->string('email', 200)->unique();
            $t->string('password');
            $t->string('role', 30);
            $t->boolean('is_active')->default(true);
            $t->bigInteger('security_version')->default(1);
            $t->text('totp_secret')->nullable();
            $t->bigInteger('last_totp_step')->nullable();
            $t->rememberToken();
            $t->timestampsTz();
        });
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignUuid('user_id')->nullable()->index()->constrained();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->integer('expiration')->index();
        });
        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->integer('expiration');
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestampTz('failed_at')->useCurrent();
        });
        Schema::create('outbox_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kind');
            $t->uuid('aggregate_id');
            $t->jsonb('payload');
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('processed_at')->nullable()->index();
        });
    }
    public function down(): void
    {
        foreach (
            ['outbox_events', 'failed_jobs', 'jobs', 'cache_locks', 'cache', 'sessions', 'users']
            as $table
        ) {
            Schema::dropIfExists($table);
        }
    }
};
