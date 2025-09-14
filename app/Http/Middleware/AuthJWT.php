<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;

class AuthJWT
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = $this->getTokenFromRequest($request);
            
            if (!$token) {
                return response()->json(['message' => 'Token not provided'], 401);
            }
            // **FIX**: Use the dedicated JWT secret for decoding.
            $decoded = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
            
            // Check if token is expired
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return response()->json(['message' => 'Token expired'], 401);
            }

            // Check if user exists and is active
            $user = User::find($decoded->sub);
            if (!$user || !$user->is_active) {
                return response()->json(['message' => 'User not found or inactive'], 401);
            }

            // Check if session is valid
            if (isset($decoded->jti)) {
                $session = UserSession::where('token_jti', $decoded->jti)
                    ->where('revoked_at', null)
                    ->where('expires_at', '>', now())
                    ->first();

                if (!$session) {
                    return response()->json(['message' => 'Invalid session'], 401);
                }
            }

            // **FIX**: Set the user on Laravel's default guard for the request.
            // This makes auth()->user() and $request->user() work correctly.
            Auth::setUser($user);

       } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        return $next($request);
    }

    private function getTokenFromRequest(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token && $request->has('token')) {
            $token = $request->input('token');
        }

        return $token;
    }
}