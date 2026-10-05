<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('visitor_daily_totals', function (Blueprint $t) {
            $t->date('visit_date')->primary();
            $t->bigInteger('visitors')->default(0);
        });
        Schema::create('visitor_presences', function (Blueprint $t) {
            $t->date('visit_date');
            $t->char('daily_hash', 64);
            $t->char('online_hash', 64);
            $t->timestampTz('last_seen')->index();
            $t->primary(['visit_date', 'daily_hash']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('visitor_presences');
        Schema::dropIfExists('visitor_daily_totals');
    }
};
