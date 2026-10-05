<?php
namespace App\Modules\Analytics\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Modules\Analytics\Services\VisitorStatistics;
class VisitorController
{
    public function index(VisitorStatistics $statistics)
    {
        return response()->json($statistics->read())->header('Cache-Control', 'no-store');
    }
    public function heartbeat(Request $request, VisitorStatistics $statistics)
    {
        // Stateless anti-forgery: browsers cannot supply another site's Origin.
        // Authenticated mutations retain Laravel session/CSRF protection unchanged.
        abort_unless(
            $request->header('Origin') === rtrim((string) config('nadi.frontend_url'), '/'),
            403,
            'Asal permintaan statistik tidak diizinkan.',
        );
        abort_if(
            count($request->all()) > 0,
            422,
            'Permintaan statistik tidak menerima data pribadi.',
        );
        $visitor = $request->cookie('nadi_visitor');
        $new = !is_string($visitor) || !Str::isUuid($visitor);
        if ($new) {
            $visitor = (string) Str::uuid();
        }
        // Only active browser heartbeats count; ordinary crawlers/health probes never count.
        if (
            !preg_match(
                '/bot|crawler|spider|headless|curl|wget|monitor/i',
                $request->userAgent() ?? '',
            )
        ) {
            $statistics->record($visitor);
        }
        $response = response()->json($statistics->read())->header('Cache-Control', 'no-store');
        if ($new) {
            // Laravel encrypts this first-party cookie; no account/IP/User-Agent is stored in counters.
            $response->cookie(
                'nadi_visitor',
                $visitor,
                60 * 24 * 365,
                '/',
                null,
                (bool) config('session.secure'),
                true,
                false,
                'strict',
            );
        }
        return $response;
    }
}
