<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OptionalAuthJWT
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        // **FIX**: If no token, just continue. Do not return an error.
        if (!$token) {
            return $next($request);
        }
        
        try {
            $decoded = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));

            $user = User::find($decoded->sub);

            // **FIX**: If user is invalid or session is revoked, just continue without setting the user.
            if ($user && $user->is_active) {
                 if (isset($decoded->jti)) {
                    $session = UserSession::where('token_jti', $decoded->jti)
                        ->where('revoked_at', null)
                        ->where('expires_at', '>', now())
                        ->first();
                    
                    if ($session) {
                        // All checks passed, set the user.
                        Auth::setUser($user);
                    }
                }
            }
        } catch (\Exception $e) {
            // If token is invalid/expired, do nothing and proceed.
        }

        return $next($request);
    }
}