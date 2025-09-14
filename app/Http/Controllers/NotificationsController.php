<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    /**
     * Display a listing of notifications.
     */
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        if (request()->wantsJson() || request()->hasHeader('HX-Request')) {
            return response()->json($notifications);
        }

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Get notification preferences for the authenticated user.
     */
    public function getPreferences()
    {
        $user = Auth::user();
        $preferences = $user->notificationPreferences ?? [];

        if (request()->wantsJson() || request()->hasHeader('HX-Request')) {
            return response()->json($preferences);
        }

        return view('notifications.preferences', compact('preferences'));
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'email_notifications' => 'sometimes|boolean',
            'browser_notifications' => 'sometimes|boolean',
            'daily_digest' => 'sometimes|boolean',
            'document_updates' => 'sometimes|boolean',
            'task_assignments' => 'sometimes|boolean',
            'comment_mentions' => 'sometimes|boolean',
        ]);

        $user = Auth::user();
        $user->notificationPreferences = array_merge(
            (array) $user->notificationPreferences,
            $validated
        );
        $user->save();

        if ($request->wantsJson() || $request->hasHeader('HX-Request')) {
            return response()->json([
                'message' => 'Preferences updated successfully',
                'preferences' => $user->notificationPreferences
            ]);
        }

        return back()->with('success', 'Notification preferences updated.');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead()
    {
        $user = Auth::user();
        $user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $unreadCount = $user->unreadNotifications()->count();

        if (request()->wantsJson() || request()->hasHeader('HX-Request')) {
            return response()->json([
                'message' => 'All notifications marked as read',
                'unread_count' => $unreadCount
            ]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Get unread notifications count.
     */
    public function getUnreadCount()
    {
        $count = Auth::user()->unreadNotifications()->count();

        if (request()->wantsJson() || request()->hasHeader('HX-Request')) {
            return response()->json(['count' => $count]);
        }

        return $count;
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead($notificationId)
    {
        $notification = Auth::user()->notifications()->findOrFail($notificationId);
        
        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        $unreadCount = Auth::user()->unreadNotifications()->count();

        if (request()->wantsJson() || request()->hasHeader('HX-Request')) {
            return response()->json([
                'message' => 'Notification marked as read',
                'unread_count' => $unreadCount
            ]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    /**
     * Get notifications for dropdown display.
     */
    public function dropdown()
    {
        $notifications = Auth::user()
            ->notifications()
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $unreadCount = Auth::user()->unreadNotifications()->count();

        if (request()->wantsJson() || request()->hasHeader('HX-Request')) {
            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
        }

        return view('partials.notifications-dropdown', compact('notifications', 'unreadCount'));
    }
}