<?php

namespace App\Http\Controllers;

use App\Services\PrivacyDocumentation;
use Illuminate\Http\Request;

class PrivacyDocumentationController extends Controller
{
    public function index(Request $request, PrivacyDocumentation $documents)
    {
        return response()->view('privacy.index', [
            'published' => $documents->published(),
            'internal' => $request->user()->isAdmin() ? $documents->documents() : [],
            'acknowledgmentActive' => $documents->acknowledgmentPolicies() !== null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function publicIndex(PrivacyDocumentation $documents)
    {
        return response()->view('privacy.public', ['published' => $documents->published()])->header('Cache-Control', 'no-store');
    }

    public function published(string $slug, PrivacyDocumentation $documents)
    {
        $document = $documents->published()[$slug] ?? null;
        abort_unless($document, 404);
        return response()->view('privacy.document', ['document' => $document, 'html' => $documents->html($document), 'internal' => false])
            ->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    public function draft(Request $request, string $slug, PrivacyDocumentation $documents)
    {
        // Defense in depth in addition to the route's role middleware.
        abort_unless($request->user()?->isAdmin(), 403);
        $document = $documents->document($slug);
        abort_unless($document, 404);
        return response()->view('privacy.document', ['document' => $document, 'html' => $documents->html($document), 'internal' => true])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
