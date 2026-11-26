<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SiteContent extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function branding(): array
    {
        $default = ['icon_path' => null, 'version' => 'ccis-1'];
        try {
            $value = self::where('key', 'site.branding')->value('value');
            return is_array($value) ? array_replace($default, $value) : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    public static function defaults(): array
    {
        return [
            'landing.carousel_slides' => [
                [
                    'eyebrow' => 'One Portal',
                    'title' => 'One Portal for the CCIS Faculty Community',
                    'body' => 'Your faculty community, thoughtfully brought together. Stay informed, track contributions, and keep your records close.',
                    'image_path' => null,
                ],
                [
                    'eyebrow' => 'Contributions',
                    'title' => 'A clearer way to track faculty contributions.',
                    'body' => 'Pay externally, then keep payment proof, Treasurer verification and private receipts together in one secure workspace.',
                    'image_path' => null,
                ],
                [
                    'eyebrow' => 'Secure Access',
                    'title' => 'Security built around every faculty account.',
                    'body' => 'Two-factor authentication, trusted browsers and role-based access. Confidence at every sign-in.',
                    'image_path' => null,
                ],
            ],
            'landing.portal_preview' => [
                'metrics' => [
                    ['title' => 'Faculty', 'body' => 'Active members'],
                    ['title' => 'Secure', 'body' => 'Session access'],
                ],
                'bars' => [100, 82, 64],
            ],
            'landing.about' => [
                'eyebrow' => 'About FacultyConnect',
                'title' => 'A focused portal for CCIS faculty coordination.',
                'body' => 'FacultyConnect brings the everyday work of the CCIS Faculty Club into one shared space. From club announcements to verified contributions, faculty members can keep up with their community with confidence.',
                'strip' => ['University identity', 'Faculty accounts', 'Protected dashboard'],
            ],
            'landing.features' => [
                ['icon' => 'FP', 'title' => 'Faculty Profiles', 'status' => 'Available'],
                ['icon' => 'CP', 'title' => 'Collections & Payments', 'status' => 'Available'],
                ['icon' => 'IN', 'title' => 'Inventory Management', 'status' => 'Coming Soon'],
                ['icon' => 'TR', 'title' => 'Water Collection Reports', 'status' => 'Available'],
                ['icon' => 'FC', 'title' => 'Faculty Concerns', 'status' => 'Coming Soon'],
                ['icon' => 'AI', 'title' => 'AI Faculty Assistant', 'status' => 'Coming Soon'],
            ],
            'landing.benefits' => [
                ['title' => 'Centralized', 'body' => 'One place for CCIS Faculty Club access.'],
                ['title' => 'Secure', 'body' => 'Session-based authentication and CSRF protection.'],
                ['title' => 'Transparent', 'body' => 'Prepared for clear payments, reports, and updates.'],
                ['title' => 'Accessible', 'body' => 'Responsive pages for desktop, tablet, and mobile.'],
            ],
            'landing.cta' => [
                'title' => 'Ready to Connect with FacultyConnect?',
                'login_label' => 'Login',
                'register_label' => 'Create Account',
            ],
        ];
    }

    public static function landing(): array
    {
        $defaults = self::defaults();

        try {
            if (! Schema::hasTable('site_contents')) {
                return self::normalizeLanding($defaults);
            }

            $stored = self::query()
                ->whereIn('key', array_keys($defaults))
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
            return self::normalizeLanding($defaults);
        }

        foreach ($stored as $key => $value) {
            $defaults[$key] = self::mergeValue($defaults[$key], $value);
        }

        return self::normalizeLanding($defaults);
    }

    public static function saveLanding(array $content): void
    {
        foreach (self::defaults() as $key => $defaultValue) {
            self::updateOrCreate(
                ['key' => $key],
                ['value' => self::mergeValue($defaultValue, $content[$key] ?? $defaultValue)]
            );
        }
    }

    private static function mergeValue(array $defaultValue, mixed $storedValue): array
    {
        if (! is_array($storedValue)) {
            return $defaultValue;
        }

        if (array_is_list($defaultValue)) {
            return array_values(array_filter($storedValue, fn ($item) => is_array($item)));
        }

        return array_replace_recursive($defaultValue, $storedValue);
    }

    private static function normalizeLanding(array $items): array
    {
        return [
            'carousel_slides' => $items['landing.carousel_slides'],
            'portal_preview' => $items['landing.portal_preview'],
            'about' => $items['landing.about'],
            'features' => $items['landing.features'],
            'benefits' => $items['landing.benefits'],
            'cta' => $items['landing.cta'],
        ];
    }
}
