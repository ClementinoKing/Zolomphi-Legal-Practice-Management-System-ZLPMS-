<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->string('filter')->value();
        $filter = in_array($filter, ['all', 'unread'], true) ? $filter : 'all';
        $notificationQuery = $request->user()->notifications();

        if ($filter === 'unread') {
            $notificationQuery->whereNull('read_at');
        }

        $notificationHistory = $notificationQuery
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => $this->notificationPayload($notification));

        return Inertia::render('notifications/index', [
            'notificationHistory' => $notificationHistory,
            'filters' => ['filter' => $filter],
            'counts' => [
                'all' => $request->user()->notifications()->count(),
                'unread' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /** @return array<string, mixed> */
    private function notificationPayload(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->data['title'],
            'body' => $notification->data['body'],
            'url' => $notification->data['url'],
            'type' => $notification->data['type'],
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}
