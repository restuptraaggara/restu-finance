<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Fallback untuk environment testing jika menggunakan header X-User-Id
        if (!$user && app()->environment('testing')) {
            $userId = $request->header('X-User-Id');
            if ($userId) {
                $user = \App\Models\User::find($userId);
            }
        }

        if (!$user || !$user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Khusus administrator.',
            ], 403);
        }

        return $next($request);
    }
}