<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $members = User::select('id', 'faculty_id', 'first_name', 'middle_name', 'last_name', 'email', 'department', 'position')
            ->where('status', User::STATUS_ACTIVE)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where(function ($query) use ($request): void {
                    $search = '%'.$request->string('search').'%';
                    $query->where('last_name', 'like', $search)->orWhere('first_name', 'like', $search)
                        ->orWhere('department', 'like', $search);
                });
            })->orderBy('last_name')->paginate(20)->withQueryString();

        return view('dashboard.members.directory', ['members' => $members, 'currentDate' => now()->format('F j, Y')]);
    }
}
