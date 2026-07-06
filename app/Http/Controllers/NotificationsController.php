<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json($notifications, 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'event_type' => ['required', Rule::in([
                'payment_received',
                'invoice_issued',
                'invoice_overdue',
                'maintenance_update',
                'vacate_notice',
                'listing_update',
                'account_status',
            ])],
            'message' => ['required', 'string', 'max:255'],
            'related_url' => ['nullable', 'string', 'max:255'],
            'delivery_channel' => ['nullable', Rule::in(['in_app', 'sms', 'email', 'whatsapp'])],
        ]);

        $data['related_url'] = $data['related_url'] ?? null;
        $data['delivery_channel'] = $data['delivery_channel'] ?? 'in_app';

        $data['is_read'] = false;
        $data['read_at'] = null;

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('notifications')->insertGetId($data);

        $notification = DB::table('notifications')->find($id);

        return response()->json($notification, 201);
    }

    public function show(Request $request, string $id)
    {
        $notification = DB::table('notifications')->find($id);

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        if ($notification->user_id != $request->user()->id) {
            return response()->json(['message' => 'You can only view your own notifications.'], 403);
        }

        return response()->json($notification);
    }

    public function update(Request $request, string $id)
    {
        $notification = DB::table('notifications')->find($id);

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'event_type' => ['required', Rule::in([
                'payment_received',
                'invoice_issued',
                'invoice_overdue',
                'maintenance_update',
                'vacate_notice',
                'listing_update',
                'account_status',
            ])],
            'message' => ['required', 'string', 'max:255'],
            'related_url' => ['nullable', 'string', 'max:255'],
            'delivery_channel' => ['nullable', Rule::in(['in_app', 'sms', 'email', 'whatsapp'])],
        ]);

        $data['related_url'] = $data['related_url'] ?? null;
        $data['delivery_channel'] = $data['delivery_channel'] ?? 'in_app';
        $data['updated_at'] = now();

        DB::table('notifications')->where('id', $id)->update($data);

        $notification = DB::table('notifications')->find($id);

        return response()->json($notification);
    }

    public function destroy(string $id)
    {
        $notification = DB::table('notifications')->find($id);

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        DB::table('notifications')->where('id', $id)->delete();

        return response()->json(null, 204);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = DB::table('notifications')->find($id);

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        if ($notification->user_id != $request->user()->id) {
            return response()->json(['message' => 'You can only update your own notifications.'], 403);
        }

        DB::table('notifications')->where('id', $id)->update([
            'is_read' => true,
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        $notification = DB::table('notifications')->find($id);

        return response()->json($notification);
    }

    public function markAllRead(Request $request)
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}