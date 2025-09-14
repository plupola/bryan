<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\UserSession;
use App\Models\EmailQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'first_name' => 'required',
            'last_name' => 'required',
            'invitation_token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate invitation token (this would typically be stored in a separate table)
        // For now, we'll assume it's a valid token that contains workspace info
        
        $user = User::create([
            'email' => $request->email,
            'password_hash' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'is_active' => true
        ]);

        // Add user to workspace based on invitation token
        // This is a simplified implementation
        $workspaceId = 1; // Would normally be extracted from token
        $roleId = 4; // Default guest role
        
        WorkspaceMember::create([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role_id' => $roleId,
            'status' => 'active'
        ]);

        // Generate JWT token
        $token = $this->generateJWT($user);

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

        public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is deactivated'], 403);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // **FIX**: Generate the JTI once, before creating the token.
        $jti = Str::uuid()->toString();

        // **FIX**: Pass the JTI to the generator function.
        $token = $this->generateJWT($user, $jti);

        // Create session record
        UserSession::create([
            'user_id' => $user->id,
            // **FIX**: Use the same JTI here.
            'token_jti' => $jti,
            // **NOTE**: Storing the raw token is redundant and a security risk.
            // I've removed the 'token' field from this array.
            // You should consider removing this column from your 'user_sessions' table.
            'expires_at' => now()->addHours(24),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }


    public function logout(Request $request)
    {
        $token = $request->bearerToken();
        
        if ($token) {
            try {
                // **FIX**: Decode the token to get the JTI for consistent revocation.
                $decoded = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
                
                if (isset($decoded->jti)) {
                    // **FIX**: Revoke the session using the JTI.
                    UserSession::where('token_jti', $decoded->jti)
                        ->update(['revoked_at' => now()]);
                }
            } catch (\Exception $e) {
                // Token is invalid, no action needed but you could log this.
            }
        }

        return response()->json(['message' => 'Logged out successfully']);
    }


    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Generate reset token
            $resetToken = Str::random(64);
            
            // Store reset token (you'd need a password_resets table)
            // PasswordReset::updateOrCreate(...)
            
            // Queue email
            EmailQueue::create([
                'recipient_email' => $user->email,
                'subject' => 'Password Reset Request',
                'body_html' => view('emails.password_reset', [
                    'user' => $user,
                    'reset_link' => url("/reset-password?token=$resetToken")
                ])->render(),
                'body_text' => "Please use this link to reset your password: " . 
                              url("/reset-password?token=$resetToken"),
                'status' => 'pending'
            ]);
        }

        return response()->json(['message' => 'If the email exists, a reset link has been sent']);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate token (you'd check against password_resets table)
        $isValidToken = true; // This would be actual validation logic
        
        if (!$isValidToken) {
            return response()->json(['message' => 'Invalid or expired reset token'], 400);
        }

        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->update([
            'password_hash' => Hash::make($request->password)
        ]);

        // Delete the used reset token
        // PasswordReset::where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successfully']);
    }

    public function changePassword(Request $request)
    {
        $user = auth()->user();
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json(['message' => 'Current password is incorrect'], 400);
        }

        $user->update([
            'password_hash' => Hash::make($request->new_password)
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function twoFactorVerify(Request $request)
    {
        // 2FA implementation would go here
        return response()->json(['message' => '2FA verified']);
    }

    public function twoFactorSetup(Request $request)
    {
        // 2FA setup implementation would go here
        return response()->json(['message' => '2FA setup initiated']);
    }

    public function twoFactorDisable(Request $request)
    {
        // 2FA disable implementation would go here
        return response()->json(['message' => '2FA disabled']);
    }

    private function generateJWT($user, $jti)
    {
        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24), // 24 hours
            // **FIX**: Use the provided JTI.
            'jti' => $jti
        ];

        // **FIX**: Use the dedicated JWT secret from the new config file.
        return JWT::encode($payload, config('jwt.secret'), 'HS256');
    }
}