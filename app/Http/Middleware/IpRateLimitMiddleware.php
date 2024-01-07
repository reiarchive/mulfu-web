<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;


class IpRateLimitMiddleware
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle($request, Closure $next)
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, 1)) {
            $retryAfter = $this->limiter->availableIn($key);
            Log::info($request->ip() ." Kena rate limit");
            return response()
                ->json(["error" => 3, "time" => $retryAfter * 1000, "message" => "Harap tunggu " . $retryAfter  . " detik untuk mengirim otp lain"])
                ->setStatusCode(429);
        }

        $this->limiter->hit($key, 30); // Allow 1 request every 20 seconds.

        $remainingAttempts = $this->limiter->retriesLeft($key, 30);

        Log::info($remainingAttempts);

        return $next($request);
    }

    protected function resolveRequestSignature($request)
    {
        return sha1(
            $request->method() . '|' .
                $request->server('SERVER_NAME') . '|' .
                $request->path() . '|' .
                $request->ip()
        );
    }
}
