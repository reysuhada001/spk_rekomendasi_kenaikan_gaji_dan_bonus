<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Silakan login dulu.');
        }

        // Normalisasi: dukung "role:owner,hr,leader" atau "role:owner hr leader"
        $normalized = [];
        foreach ($roles as $r) {
            foreach (preg_split('/[,\s]+/', $r, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                $normalized[] = strtolower(trim($part));
            }
        }

        if (!in_array(strtolower($user->role), $normalized, true)) {
            return redirect()->route('dashboard.index')->with('error', 'Tidak punya akses.');
        }

        return $next($request);
    }
}