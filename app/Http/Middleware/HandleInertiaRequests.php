<?php

namespace App\Http\Middleware;

use App\CurrencyConverter;
use App\Http\Resources\TimeTrackerResource;
use App\Models\GeneralSetting;
use App\Models\TimeTracker;
use App\Support\PracticeAccessCatalog;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $user?->loadMissing('roles.assignedPermissions');
        $permissionKeys = collect(array_keys(PracticeAccessCatalog::permissions()))
            ->filter(fn (string $permission): bool => $user?->hasPermission($permission) ?? false)
            ->values()
            ->all();
        $user?->makeHidden('roles');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'canAccessAdministration' => $user?->can('access-administration') ?? false,
                'permissions' => $permissionKeys,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currency' => function (): array {
                $currency = GeneralSetting::query()->value('currency');

                return [
                    'base' => CurrencyConverter::BaseCurrency,
                    'code' => is_string($currency) ? $currency : CurrencyConverter::BaseCurrency,
                    'rates' => app(CurrencyConverter::class)->rates(),
                ];
            },
            'notifications' => function () use ($request): array {
                $user = $request->user();

                if ($user === null) {
                    return ['unread_count' => 0, 'recent' => []];
                }

                return [
                    'unread_count' => $user->unreadNotifications()->count(),
                    'recent' => $user->notifications()
                        ->latest()
                        ->limit(5)
                        ->get()
                        ->map(fn (DatabaseNotification $notification): array => [
                            'id' => $notification->id,
                            'title' => $notification->data['title'],
                            'body' => $notification->data['body'],
                            'url' => $notification->data['url'],
                            'type' => $notification->data['type'],
                            'read_at' => $notification->read_at?->toIso8601String(),
                            'created_at' => $notification->created_at->toIso8601String(),
                        ])->all(),
                ];
            },
            'timeTracker' => function () use ($request): ?array {
                $user = $request->user();

                if ($user === null) {
                    return null;
                }

                $tracker = TimeTracker::query()
                    ->where('user_id', $user->id)
                    ->with('matter.client')
                    ->first();

                return $tracker === null
                    ? null
                    : TimeTrackerResource::make($tracker)->resolve();
            },
        ];
    }
}
