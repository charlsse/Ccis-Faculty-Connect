<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminContentController extends Controller
{
    public function edit(Request $request): View
    {
        return view('dashboard.content-manager', [
            'content' => SiteContent::landing(),
            'currentDate' => now()->format('F j, Y'),
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $request->validate([
            'restore_icon' => ['nullable', 'boolean'],
            'site_icon' => ['required_without:restore_icon', 'nullable', 'file', 'image',
                'mimes:png', 'mimetypes:image/png', 'extensions:png', 'max:512',
                'dimensions:min_width=180,min_height=180,max_width=512,max_height=512,ratio=1'],
        ]);
        if (! $request->hasFile('site_icon') && ! $request->boolean('restore_icon')) {
            return back()->withErrors(['site_icon' => 'Choose a PNG icon or restore the default.']);
        }

        $version = (string) Str::uuid();
        $path = null;
        if ($request->hasFile('site_icon')) {
            $path = $request->file('site_icon')->storeAs('site-branding', $version.'.png', 'public');
            if (! $path) {
                return back()->withErrors(['site_icon' => 'The icon could not be saved. Please try again.']);
            }
        }
        try {
            SiteContent::updateOrCreate(['key' => 'site.branding'], [
                'value' => ['icon_path' => $path, 'version' => $version],
            ]);
        } catch (\Throwable $error) {
            if ($path) Storage::disk('public')->delete($path);
            throw $error;
        }

        // Keep earlier uploads: replacement cannot break in-flight requests or other content.
        return back()->with('status', $path ? 'Website icon has been updated.' : 'Default website icon restored.');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'carousel_slides' => ['required', 'array', 'min:1', 'max:5'],
            'carousel_slides.*.eyebrow' => ['required', 'string', 'max:80'],
            'carousel_slides.*.title' => ['required', 'string', 'max:160'],
            'carousel_slides.*.visual' => ['nullable', Rule::in(['workspace', 'contributions', 'security'])],
            'carousel_slides.*.body' => ['required', 'string', 'max:260'],
            'carousel_slides.*.existing_image_path' => ['nullable', 'string', 'max:255'],
            'carousel_slides.*.remove_image' => ['nullable', Rule::in(['1'])],
            'carousel_images' => ['nullable', 'array'],
            'carousel_images.*' => ['nullable', 'image', 'max:3072'],
            'portal_preview.metrics' => ['required', 'array', 'min:1', 'max:4'],
            'portal_preview.metrics.*.title' => ['required', 'string', 'max:60'],
            'portal_preview.metrics.*.body' => ['required', 'string', 'max:100'],
            'portal_preview.bars' => ['required', 'array', 'min:1', 'max:5'],
            'portal_preview.bars.*' => ['required', 'integer', 'min:20', 'max:100'],
            'about.eyebrow' => ['required', 'string', 'max:80'],
            'about.title' => ['required', 'string', 'max:160'],
            'about.body' => ['required', 'string', 'max:600'],
            'about.strip' => ['required', 'array', 'min:1', 'max:5'],
            'about.strip.*' => ['required', 'string', 'max:80'],
            'features' => ['required', 'array', 'min:1', 'max:8'],
            'features.*.icon' => ['required', 'string', 'max:4'],
            'features.*.title' => ['required', 'string', 'max:100'],
            'features.*.status' => ['required', 'string', 'max:80'],
            'benefits' => ['required', 'array', 'min:1', 'max:6'],
            'benefits.*.title' => ['required', 'string', 'max:80'],
            'benefits.*.body' => ['required', 'string', 'max:180'],
            'cta.title' => ['required', 'string', 'max:160'],
            'cta.login_label' => ['required', 'string', 'max:40'],
        ]);

        $slides = [];
        foreach ($validated['carousel_slides'] as $index => $slide) {
            $imagePath = $slide['existing_image_path'] ?? null;

            if (($slide['remove_image'] ?? null) === '1') {
                $this->deleteManagedImage($imagePath);
                $imagePath = null;
            }

            if ($request->hasFile("carousel_images.$index")) {
                $this->deleteManagedImage($imagePath);
                $imagePath = $this->storeCarouselImage($request->file("carousel_images.$index"));
            }

            $slides[] = [
                'eyebrow' => trim($slide['eyebrow']),
                'title' => trim($slide['title']),
                'body' => trim($slide['body']),
                'image_path' => $imagePath,
                'visual' => $slide['visual'] ?? ['workspace', 'contributions', 'security'][$index % 3],
            ];
        }

        SiteContent::saveLanding([
            'landing.carousel_slides' => $slides,
            'landing.portal_preview' => [
                'metrics' => array_map(fn ($metric) => [
                    'title' => trim($metric['title']),
                    'body' => trim($metric['body']),
                ], $validated['portal_preview']['metrics']),
                'bars' => array_map('intval', $validated['portal_preview']['bars']),
            ],
            'landing.about' => [
                'eyebrow' => trim($validated['about']['eyebrow']),
                'title' => trim($validated['about']['title']),
                'body' => trim($validated['about']['body']),
                'strip' => array_map('trim', $validated['about']['strip']),
            ],
            'landing.features' => array_map(fn ($feature) => [
                'icon' => Str::upper(trim($feature['icon'])),
                'title' => trim($feature['title']),
                'status' => trim($feature['status']),
            ], $validated['features']),
            'landing.benefits' => array_map(fn ($benefit) => [
                'title' => trim($benefit['title']),
                'body' => trim($benefit['body']),
            ], $validated['benefits']),
            'landing.cta' => [
                'title' => trim($validated['cta']['title']),
                'login_label' => trim($validated['cta']['login_label']),
                'register_label' => SiteContent::landing()['cta']['register_label'] ?? 'Contact Administrator',
            ],
        ]);

        return back()->with('status', 'Landing page content has been updated.');
    }

    private function storeCarouselImage($file): string
    {
        $directory = public_path('images/content');
        File::ensureDirectoryExists($directory);

        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $filename = ($filename ?: 'carousel').'-'.Str::random(8).'.'.$file->getClientOriginalExtension();

        $file->move($directory, $filename);

        return 'images/content/'.$filename;
    }

    private function deleteManagedImage(?string $imagePath): void
    {
        if (! $imagePath || ! Str::startsWith($imagePath, 'images/content/')) {
            return;
        }

        $fullPath = public_path($imagePath);

        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }
}
