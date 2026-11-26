<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiteIconController extends Controller
{
    public function __invoke(Request $request)
    {
        $branding = SiteContent::branding();
        $path = $branding['icon_path'];
        $headers = [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $request->query('v') === $branding['version']
                ? 'public, max-age=31536000, immutable' : 'no-cache',
        ];
        // Only serve the current server-managed icon; never a path from a request.
        if (is_string($path) && preg_match('/\Asite-branding\/[a-f0-9-]+\.png\z/', $path)
            && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path, 'site-icon.png', $headers, 'inline');
        }

        return response()->file(public_path('images/site-icon.png'), $headers);
    }
}
