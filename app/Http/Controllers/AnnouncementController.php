<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $query = Announcement::query()->with('author');

        if (! $request->user()->hasPermission('announcements.manage')) {
            $query->published()->forAudience($request->user());
        }

        $announcements = $query->orderedForPortal()->paginate(8);

        return view('dashboard.announcements.index', [
            'announcements' => $announcements,
            'currentDate' => now()->format('F j, Y'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('announcements.manage'), 403);

        $data = $this->validatedData($request);
        $data['created_by'] = $request->user()->id;
        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['published_at'] = $request->filled('published_at') ? $request->date('published_at') : now();

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        Announcement::create($data);

        return back()->with('status', 'Announcement has been published.');
    }

    public function show(Request $request, Announcement $announcement): View
    {
        if (! $request->user()->hasPermission('announcements.manage')) {
            abort_unless($announcement->published_at && $announcement->published_at->lte(now()), 404);
            abort_if($announcement->expires_at && $announcement->expires_at->lt(now()), 404);
            abort_unless(Announcement::whereKey($announcement->id)->forAudience($request->user())->exists(), 404);
        }

        return view('dashboard.announcements.show', [
            'announcement' => $announcement->load('author'),
            'currentDate' => now()->format('F j, Y'),
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('announcements.manage'), 403);

        $data = $this->validatedData($request);
        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['published_at'] = $request->filled('published_at') ? $request->date('published_at') : null;

        if ($request->boolean('remove_image')) {
            $this->deleteImage($announcement->image_path);
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($announcement->image_path);
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        $announcement->update($data);

        return back()->with('status', 'Announcement has been updated.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('announcements.manage'), 403);

        $this->deleteImage($announcement->image_path);
        $announcement->delete();

        return redirect()->route('announcements.index')->with('status', 'Announcement has been removed.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'max:60'],
            'audience' => ['required', Rule::in(Announcement::audiences())],
            'priority' => ['required', Rule::in(['normal', 'important', 'urgent'])],
            'body' => ['required', 'string', 'max:4000'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:published_at'],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_pinned' => ['nullable', 'boolean'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }

    private function storeImage($file): string
    {
        $directory = public_path('images/announcements');
        File::ensureDirectoryExists($directory);

        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $filename = ($filename ?: 'announcement').'-'.Str::random(8).'.'.$file->getClientOriginalExtension();

        $file->move($directory, $filename);

        return 'images/announcements/'.$filename;
    }

    private function deleteImage(?string $imagePath): void
    {
        if (! $imagePath || ! Str::startsWith($imagePath, 'images/announcements/')) {
            return;
        }

        $fullPath = public_path($imagePath);

        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
}
