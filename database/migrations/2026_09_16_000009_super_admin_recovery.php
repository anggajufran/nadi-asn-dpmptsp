<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn(Blueprint $t) => $t->string('recovery_email', 200)->nullable());
        // Existing installations retain their stored recovery address. Provision new ones privately.
        $login = env('NADI_INITIAL_RECOVERY_NIP');
        $email = env('NADI_INITIAL_RECOVERY_EMAIL');
        if ($login && $email) {
            DB::table('users')
                ->where('login_name', $login)
                ->where('role', 'SUPER_ADMIN')
                ->update(['recovery_email' => $email]);
        }
        Schema::create('account_recoveries', function (Blueprint $t) {
            $t->foreignUuid('user_id')->primary()->constrained('users');
            $t->string('token_hash', 64)->unique();
            $t->text('totp_secret');
            $t->bigInteger('security_version');
            $t->timestampTz('expires_at');
            $t->timestampTz('created_at');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('account_recoveries');
        Schema::table('users', fn(Blueprint $t) => $t->dropColumn('recovery_email'));
    }
};
