<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Display the user's profile.
     */
    public function show()
    {
        $user = Auth::user()->load('company');
        
        return view('profile.show', compact('user'));
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'locale' => ['required', 'string', 'max:10'],
            'time_zone' => ['required', 'string', 'max:64'],
            'company_id' => ['nullable', 'exists:companies,id']
        ]);

        $user->update($validated);
        
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user
            ]);
        }
        
        return back()->with('success', 'Profile updated successfully');
    }

    /**
     * Update the user's avatar.
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048']
        ]);
        
        $user = Auth::user();
        
        // Delete old avatar if exists
        if ($user->avatar_url) {
            Storage::delete($user->avatar_url);
        }
        
        $path = $request->file('avatar')->store('avatars', 'public');
        
        $user->update(['avatar_url' => $path]);
        
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Avatar updated successfully',
                'avatar_url' => Storage::url($path)
            ]);
        }
        
        return back()->with('success', 'Avatar updated successfully');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        
        $user = Auth::user();
        $user->update([
            'password_hash' => Hash::make($request->password)
        ]);
        
        // Invalidate other sessions if needed
        UserSession::where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->update(['revoked_at' => now()]);
        
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Password updated successfully']);
        }
        
        return back()->with('success', 'Password updated successfully');
    }

    /**
     * Get user's active sessions.
     */
    public function getSessions()
    {
        $sessions = UserSession::where('user_id', Auth::id())
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->orderBy('created_at', 'desc')
            ->get();
        
        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json(['sessions' => $sessions]);
        }
        
        return view('profile.sessions', compact('sessions'));
    }

    /**
     * Invalidate a specific session.
     */
    public function invalidateSession($sessionId)
    {
        $session = UserSession::where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();
            
        $session->update(['revoked_at' => now()]);
        
        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json(['message' => 'Session invalidated successfully']);
        }
        
        return back()->with('success', 'Session invalidated successfully');
    }

    /**
     * Get user menu data (for Alpine.js/HTMX navigation).
     */
    public function menu()
    {
        $user = Auth::user()->load(['workspaces' => function($query) {
            $query->where('is_archived', false)
                  ->withCount(['documents' => function($q) {
                      $q->where('is_deleted', false);
                  }]);
        }]);
        
        $unreadNotifications = $user->notifications()
            ->whereNull('read_at')
            ->count();
            
        $data = [
            'user' => $user,
            'workspaces' => $user->workspaces,
            'unread_notifications' => $unreadNotifications
        ];
        
        if (request()->wantsJson() || request()->is('api/*')) {
            return response()->json($data);
        }
        
        // For HTMX requests, return a partial
        if (request()->header('HX-Request')) {
            return view('partials.user-menu', $data);
        }
        
        return response()->json($data);
    }
}