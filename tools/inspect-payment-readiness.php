<?php
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
    'gateway_enabled' => config('payment_gateway.enabled') === true,
    'gateway_table_present' => \Illuminate\Support\Facades\Schema::hasTable('water_gateway_attempts'),
    'category_column_present' => \Illuminate\Support\Facades\Schema::hasColumn('water_collections', 'category'),
    'published_collections' => \App\Models\WaterCollection::where('status', 'published')->count(),
    'active_receiving_accounts' => \App\Models\WaterPaymentAccount::where('is_active', true)->count(),
    'active_accounts_with_readable_qr' => \App\Models\WaterPaymentAccount::where('is_active', true)->get()->filter(fn ($a) => \Illuminate\Support\Facades\Storage::disk('local')->exists($a->qr_image_path))->count(),
    'active_accounts_created_by_treasurer' => \App\Models\WaterPaymentAccount::where('is_active', true)->whereIn('created_by', \App\Models\User::where('role', 'treasurer')->select('id'))->count(),
], JSON_PRETTY_PRINT).PHP_EOL;
