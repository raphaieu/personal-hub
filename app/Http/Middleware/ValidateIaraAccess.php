<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gateway /iara — chave interna fora de local/testing; allowlist opcional por IP.
 */
final class ValidateIaraAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $allowed */
        $allowed = config('services.iara.allowed_ips', []);

        if ($allowed !== [] && ! $this->ipAllowed($request->ip(), $allowed)) {
            abort(403, 'Forbidden');
        }

        if (! App::environment(['local', 'testing'])) {
            $expected = (string) config('services.iara.internal_key');

            if ($expected === '') {
                abort(503, 'Gateway Iara não configurado.');
            }

            $provided = (string) $request->header('X-Internal-Key', '');

            abort_unless(hash_equals($expected, $provided), 403, 'Forbidden');
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function ipAllowed(?string $ip, array $allowed): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        return in_array($ip, $allowed, true);
    }
}
