<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $t) {
            $t->jsonb('headers')->nullable();
            $t->integer('row_count')->default(0);
            $t->string('preview_hash', 64)->nullable();
            $t->text('error_message')->nullable();
            $t->uuid('duplicate_of')->nullable();
            $t->timestampTz('confirmed_at')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table(
            'import_batches',
            fn(Blueprint $t) => $t->dropColumn([
                'headers',
                'row_count',
                'preview_hash',
                'error_message',
                'duplicate_of',
                'confirmed_at',
            ]),
        );
    }
};
