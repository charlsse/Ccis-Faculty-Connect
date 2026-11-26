<?php

namespace App\Http\Controllers;

use App\Services\PortalNotifications;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationController extends Controller
{
    public function index(Request $request, PortalNotifications $notifications)
    {
        $filter = $request->validate(['filter' => 'nullable|in:all,unread,collections,announcements,security'])['filter'] ?? 'all';
        $entries = $notifications->entries($request->user())->filter(fn ($entry) => $filter === 'all' || ($filter === 'unread' ? ! $entry['read_at'] : $entry['category'] === $filter))->values();
        $page = max(1, (int) $request->input('page', 1));
        return view('dashboard.notifications', ['filter' => $filter, 'entries' => new LengthAwarePaginator($entries->forPage($page, 15), $entries->count(), 15, $page, ['path' => $request->url(), 'query' => $request->query()])]);
    }

    public function read(Request $request, string $notification, PortalNotifications $notifications)
    {
        $entry = $notifications->entries($request->user())->firstWhere('id', $notification);
        abort_unless($entry, 404);
        $notifications->markRead($request->user(), collect([$entry]));
        return $request->boolean('open') ? redirect($entry['url']) : back();
    }

    public function readAll(Request $request, PortalNotifications $notifications)
    {
        $notifications->markRead($request->user(), $notifications->entries($request->user()));
        return back()->with('status', 'Notifications marked as read.');
    }

    public function feed(Request $request, PortalNotifications $notifications)
    {
        $entries = $notifications->entries($request->user());
        $states = \App\Models\WaterMember::whereHas('collection', fn ($q) => $q->visibleTo($request->user()))
            ->when(!$request->user()->hasPermission('payments.view'), fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderBy('id')->get(['id', 'status', 'updated_at'])->toJson();
        $demo = '';
        if ((!app()->environment('testing') || config('database.connections.xendit_demo.database') === ':memory:')
            && \Illuminate\Support\Facades\Schema::connection('xendit_demo')->hasTable('demo_attempts')) {
            $demo = \App\Models\XenditDemoAttempt::where('source_database', config('xendit_demo.main_database'))
                ->when(!$request->user()->hasPermission('payments.view'), fn ($q) => $q->where('user_id', $request->user()->id))
                ->orderBy('id')->get(['id', 'status', 'updated_at'])->toJson();
        }
        $revision = hash('sha256', $entries->map(fn ($entry) => [$entry['id'], (string) $entry['read_at']])->toJson().$states.$demo);
        return response()->json([
            'unread' => $entries->whereNull('read_at')->count(), 'revision' => $revision,
            'html' => view('dashboard.partials.notification-list', ['portalNotices' => $entries])->render(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
