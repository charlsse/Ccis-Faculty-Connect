<?php

namespace App\Services;

use App\Models\WaterReceipt;
use Dompdf\Dompdf;
use Dompdf\Options;

class WaterReceiptPdf
{
    public function render(WaterReceipt $receipt): string
    {
        return $this->renderView('water.receipt-pdf', compact('receipt'));
    }

    public function renderDemo(\App\Models\XenditDemoAttempt $attempt, \App\Models\User $faculty): string
    {
        abort_unless($attempt->status === 'confirmed', 404);
        return $this->renderView('water.demo.receipt-pdf', compact('attempt', 'faculty'));
    }

    private function renderView(string $view, array $data): string
    {
        $directory = storage_path('app/private/water/pdf-cache');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('chroot', $directory);
        $options->set('tempDir', $directory);
        $options->set('fontCache', $directory);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
