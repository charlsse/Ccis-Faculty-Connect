<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Models\WaterNotice;
use App\Notifications\PortalNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class PortalNotifications
{
    public function available(): bool
    {
        return Schema::hasTable('notifications');
    }

    public function syncAnnouncements(User $user, ?Announcement $only = null): void
    {
        if (! $this->available() || $user->status !== User::STATUS_ACTIVE) {
            return;
        }
        // Refresh/navigation also catches scheduled publications without a worker.
        Announcement::published()->forAudience($user)->where('published_at', '>=', $user->created_at)
            ->when($only, fn ($query) => $query->whereKey($only->id))
            ->each(function (Announcement $announcement) use ($user) {
                $hash = md5('announcement:'.$announcement->id.':'.$user->id);
                $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20);
                // A stable primary key makes concurrent refreshes idempotent.
                DB::table('notifications')->insertOrIgnore([
                    'id' => $id, 'type' => PortalNotification::class,
                    'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id,
                    'data' => json_encode(['category' => 'announcements', 'announcement_id' => $announcement->id]),
                    'created_at' => $announcement->published_at, 'updated_at' => now(),
                ]);
            });
    }

    public function security(User $user, string $message, bool $limitRepeated = false): void
    {
        if ($this->available()) {
            if ($limitRepeated && $user->notifications()->where('data->message', $message)->where('created_at', '>=', now()->subHour())->exists()) {
                return;
            }
            $user->notify(new PortalNotification(['category' => 'security', 'message' => $message]));
        }
    }

    public function entries(User $user): Collection
    {
        $this->syncAnnouncements($user);
        $water = WaterNotice::where('user_id', $user->id)
            ->whereHas('collection', fn ($q) => $q->visibleTo($user))->with('collection')->get()
            ->filter(fn ($notice) => empty($notice->context['required_permission']) || $user->hasPermission($notice->context['required_permission']))
            ->map(fn ($notice) => [
                'id' => 'water-'.$notice->id, 'category' => 'collections', 'title' => $notice->context['title'] ?? 'Collection update',
                'message' => $notice->message, 'read_at' => $notice->read_at, 'created_at' => $notice->created_at,
                'url' => $this->noticeUrl($notice),
            ]);
        if (! $this->available()) {
            return $water->sortByDesc('created_at')->values();
        }
        $announcements = Announcement::published()->forAudience($user)->get()->keyBy('id');
        $native = $user->notifications()->get()->map(function ($notice) use ($announcements) {
            $data = $notice->data;
            if (($data['category'] ?? '') === 'announcements') {
                $announcement = $announcements->get($data['announcement_id'] ?? null);
                if (! $announcement) {
                    return null;
                }
                $title = 'New announcement';
                $message = $announcement->title;
                $url = route('announcements.show', $announcement);
            } elseif (($data['category'] ?? '') === 'security') {
                $title = 'Security alert';
                $message = $data['message'];
                $url = route('security.show');
            } else {
                return null;
            }
            return ['id' => $notice->id, 'category' => $data['category'], 'title' => $title,
                'message' => $message, 'url' => $url, 'read_at' => $notice->read_at, 'created_at' => $notice->created_at];
        })->filter();
        return $water->concat($native)->sortByDesc('created_at')->values();
    }

    public function markRead(User $user, Collection $entries): void
    {
        $ids = $entries->pluck('id');
        WaterNotice::where('user_id', $user->id)->whereIn('id', $ids->filter(fn ($id) => str_starts_with($id, 'water-'))->map(fn ($id) => substr($id, 6)))
            ->whereNull('read_at')->update(['read_at' => now()]);
        if ($this->available()) {
            $user->notifications()->whereIn('id', $ids->reject(fn ($id) => str_starts_with($id, 'water-')))->whereNull('read_at')->update(['read_at' => now()]);
        }
    }

    private function noticeUrl(WaterNotice $notice): string
    {
        $allowed = ['water.show', 'water.checkout', 'water.submission', 'water.report', 'water.demo.show', 'water.online.show'];
        $name = $notice->context['route'] ?? null;
        return in_array($name, $allowed, true) ? route($name, $notice->context['parameters'] ?? []) : route('water.show', $notice->water_collection_id);
    }
}
