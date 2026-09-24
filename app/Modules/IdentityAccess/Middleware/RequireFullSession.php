<?php
namespace App\Modules\IdentityAccess\Middleware;
use Closure;
use Illuminate\Support\Facades\Auth;
class RequireFullSession
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        if (
            !$user ||
            !$user->is_active ||
            !in_array($user->role, ['ADMIN', 'SUPER_ADMIN'], true) ||
            !$request->session()->get('totp_completed') ||
            $request->session()->get('security_version') !== $user->security_version ||
            now()->timestamp > (int) $request->session()->get('absolute_expires_at', 0)
        ) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return response()->json(['message' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
        }
        return $next($request);
    }
}
