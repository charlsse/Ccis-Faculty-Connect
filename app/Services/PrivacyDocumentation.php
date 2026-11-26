<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PrivacyDocumentation
{
    public function documents(): array
    {
        return config('privacy-documents.documents', []);
    }

    public function document(string $slug): ?array
    {
        $document = $this->documents()[$slug] ?? null;
        if (! $document || basename($document['file']) !== $document['file']) return null;
        $path = resource_path('privacy/'.$document['file']);
        if (! is_file($path)) return null;
        $body = file_get_contents($path);

        return [...$document, 'slug' => $slug, 'body' => $body, 'sha256' => hash('sha256', $body)];
    }

    public function published(): array
    {
        $published = [];
        foreach (array_keys($this->documents()) as $slug) {
            $document = $this->document($slug);
            if ($document && $this->isPublished($document)) $published[$slug] = $document;
        }
        return $published;
    }

    public function isPublished(array $document): bool
    {
        if (($document['audience'] ?? '') !== 'public' || ($document['status'] ?? '') !== 'published'
            || ! empty($document['open_items']) || str_contains($document['version'] ?? '', 'draft')) return false;
        $approval = $document['approval'] ?? [];
        foreach (['reviewed_by', 'evidence', 'approved_at', 'effective_at', 'sha256'] as $field) {
            if (! is_string($approval[$field] ?? null) || trim($approval[$field]) === '') return false;
        }
        foreach (config('privacy-documents.required_reviews') as $review) {
            if (($approval['checks'][$review] ?? false) !== true) return false;
        }
        try {
            if (Carbon::parse($approval['approved_at'])->isFuture() || Carbon::parse($approval['effective_at'])->isFuture()) return false;
        } catch (\Throwable) { return false; }

        return hash_equals($approval['sha256'], $document['sha256']);
    }

    public function acknowledgmentPolicies(): ?array
    {
        if (config('privacy-documents.acknowledgment_enabled') !== true) return null;
        $documents = $this->published();
        if (! isset($documents['privacy-notice'], $documents['terms-of-use'])) return null;
        return ['privacy' => $documents['privacy-notice'], 'terms' => $documents['terms-of-use']];
    }

    public function html(array $document): string
    {
        return Str::markdown($document['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
