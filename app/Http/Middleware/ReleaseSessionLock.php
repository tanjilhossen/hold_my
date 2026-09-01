<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ReleaseSessionLock
{
    /**
     * Handle an incoming request.
     *
     * Releases PHP file session locks immediately after reading session data into memory.
     * This prevents long-running API calls, background Puppeteer bots, and live slot checks
     * from blocking navigation or loading in other tabs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Don't close session lock on login/logout submit routes so user credentials can be written
        if ($request->is('login') || $request->is('logout') || $request->routeIs('login.submit')) {
            return $next($request);
        }

        // Save session state to disk and close the file lock on sess_<id>
        if (function_exists('session') && session()->isStarted()) {
            session()->save();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return $next($request);
    }
}
