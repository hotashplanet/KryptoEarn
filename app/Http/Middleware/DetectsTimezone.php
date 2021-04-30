<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DetectsTimezone
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        config(['app.timezone' => $request->user()->timezone]);
        date_default_timezone_set(config('app.timezone'));

        return $next($request);
    }
}
