<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterMember;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $paymentQuery = Payment::query();

        if (! $user->hasPermission('payments.view')) {
            $paymentQuery->where('user_id', $user->id);
        }

        $recentPayments = (clone $paymentQuery)
            ->with('member')
            ->orderedForPortal()
            ->limit(5)
            ->get();

        $currentMonthPaid = (clone $paymentQuery)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $pendingPayments = (clone $paymentQuery)->where('status', 'pending')->count();
        $paymentRecords = (clone $paymentQuery)->count();
        $publishedAnnouncements = Announcement::query()->published()->forAudience($user)->count();
        $waterQuery = WaterMember::whereHas('collection', fn ($q) => $q->visibleTo($user))
            ->when(! $user->hasPermission('payments.view'), fn ($q) => $q->where('user_id', $user->id));

        return view('dashboard.index', [
            'personalWater' => WaterMember::where('user_id', $user->id)
                ->whereHas('collection', fn ($q) => $q->visibleTo($user))
                ->with(['collection', 'latestSubmission'])
                ->orderByRaw("case when status in ('unpaid', 'rejected', 'pending') then 0 else 1 end")
                ->latest('assigned_at')->limit(3)->get(),
            'waterSummary' => WaterCollectionController::summary($waterQuery),
            'user' => $user,
            'currentDate' => Carbon::now()->format('F j, Y'),
            'announcements' => Announcement::query()
                ->published()
                ->forAudience($user)
                ->orderedForPortal()
                ->limit(3)
                ->get(),
            'recentPayments' => $recentPayments,
            'currentMonthPaid' => $currentMonthPaid,
            'pendingPayments' => $pendingPayments,
            'paymentRecords' => $paymentRecords,
            'publishedAnnouncements' => $publishedAnnouncements,
            'activeMembers' => $user->hasPermission('payments.view')
                ? User::where('role', User::ROLE_FACULTY)->where('status', 'active')->count()
                : null,
        ]);
    }

    public function comingSoon(Request $request, string $module): View
    {
        $titles = [
            'profile' => 'My Profile',
            'collections' => 'Collections & Payments',
            'inventory' => 'Inventory',
            'concerns' => 'Faculty Concerns',
            'reports' => 'Reports',
            'ai-assistant' => 'AI Assistant',
            'announcements' => 'Announcements',
            'settings' => 'Settings',
        ];

        return view('dashboard.coming-soon', [
            'user' => $request->user(),
            'currentDate' => Carbon::now()->format('F j, Y'),
            'module' => $module,
            'title' => $titles[$module] ?? Str::of($module)->replace('-', ' ')->title(),
        ]);
    }
}
