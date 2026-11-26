<?php

namespace App\Services;

use App\Models\WaterCollection;
use ZipArchive;

/** Small, dependency-free OOXML export. All user-supplied cells are explicit text. */
class CollectionReportExcel
{
    private function xml(string $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function cell(string $address, mixed $value, int $style = 0, bool $number = false): string
    {
        return $number
            ? '<c r="'.$address.'" s="'.$style.'"><v>'.(float) $value.'</v></c>'
            : '<c r="'.$address.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string) $value).'</t></is></c>';
    }

    private function row(int $row, array $values, int $style = 0, int $height = 28, array $numeric = []): string
    {
        $xml = '<row r="'.$row.'" ht="'.$height.'" customHeight="1">';
        foreach ($values as $i => $value) {
            $currency = in_array($i, $numeric, true);
            $isNumeric = $currency || is_int($value);
            $xml .= $this->cell(chr(65 + $i).$row, $value, $currency ? ($style === 1 ? 7 : ($style === 5 ? 6 : 4)) : $style, $isNumeric);
        }

        return $xml.'</row>';
    }

    private function sheet(string $rows, array $widths, array $merges, string $area, bool $landscape, ?string $filter = null): string
    {
        $columns = '';
        foreach ($widths as $i => $width) {
            $columns .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }
        $merged = '<mergeCells count="'.count($merges).'">';
        foreach ($merges as $range) {
            $merged .= '<mergeCell ref="'.$range.'"/>';
        }
        $merged .= '</mergeCells>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="'.$area.'"/>'
            .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="24"/><cols>'.$columns.'</cols><sheetData>'.$rows.'</sheetData>'
            .($filter ? '<autoFilter ref="'.$filter.'"/>' : '').$merged
            .'<printOptions horizontalCentered="1" gridLines="0"/><pageMargins left="0.3" right="0.3" top="0.45" bottom="0.45" header="0.2" footer="0.2"/>'
            .'<pageSetup paperSize="9" orientation="'.($landscape ? 'landscape' : 'portrait').'" fitToWidth="1" fitToHeight="'.($landscape ? '0' : '1').'"/>'
            .'<headerFooter><oddFooter>&amp;LFacultyConnect | CCIS Faculty Club&amp;RPage &amp;P of &amp;N</oddFooter></headerFooter></worksheet>';
    }

    public function render(WaterCollection $collection): string
    {
        $members = $collection->members()->with('user', 'receipt', 'collection', 'latestSubmission')->orderBy('id')->get();
        $expected = $paid = $waived = $outstanding = 0;
        $counts = array_fill_keys(['paid', 'pending', 'unpaid', 'rejected', 'waived'], 0);
        $amounts = $counts;
        $title = $collection->title;
        $meta = 'Period '.$collection->period.'   |   Due '.$collection->due_date->format('M j, Y');
        $generated = 'Generated '.now()->format('M j, Y H:i').'   |   '.config('app.timezone');
        $rows = $this->row(1, ['FACULTYCONNECT  /  CCIS FACULTY CLUB'], 1, 30)
            .$this->row(2, [$title], 2, 42).$this->row(3, [$meta], 3)
            .$this->row(4, [$generated], 3)
            .$this->row(5, ['Payment details may be unverified. Only verified payments count as collected. Amounts in PHP.'], 3, 26)
            .$this->row(6, ['Faculty member', 'Assigned', 'Paid', 'Method / reference', 'Status', 'Payment date', 'Verification / Treasurer', 'Receipt'], 1, 32);
        $n = 7;
        foreach ($members as $member) {
            $receipt = $member->receipt;
            $submission = $member->latestSubmission;
            $amount = WaterPaymentService::cents($member->assigned_amount);
            $expected += $amount;
            if ($member->status === 'paid') {
                $paid += $amount;
            } elseif ($member->status === 'waived') {
                $waived += $amount;
            } else {
                $outstanding += $amount;
            }
            $counts[$member->status] = ($counts[$member->status] ?? 0) + 1;
            $amounts[$member->status] = ($amounts[$member->status] ?? 0) + $amount;
            $method = $receipt?->payment_method ?? $submission?->payment_method;
            $method = ['qr' => 'Treasurer QR', 'cash' => 'Cash', 'paymongo_test' => 'PayMongo TEST'][$method] ?? '—';
            $reference = $receipt?->payment_reference ?? $submission?->reference_number;
            $date = $receipt?->payment_date ?? $submission?->paid_at;
            $values = [$member->user->full_name, (float) $member->assigned_amount, (float) ($receipt?->amount ?? 0),
                $method.($reference ? "\n".$reference : ''), $member->status_label,
                $date?->format("M j, Y\nH:i") ?? '—',
                $receipt ? $receipt->verified_at->format('M j, Y H:i')."\n".($receipt->snapshot['officer_name'] ?? '') : 'Not verified',
                $receipt?->receipt_number ?? '—'];
            // Allow long names/references to wrap rather than clip in print.
            $lines = max(3, (int) ceil(mb_strlen($values[0]) / 22), (int) ceil(mb_strlen($values[3]) / 20), (int) ceil(mb_strlen($values[6]) / 23));
            $rows .= $this->row($n, $values, $n % 2 ? 0 : 5, max(52, $lines * 15), [1, 2]);
            $n++;
        }
        if ($members->isEmpty()) {
            $rows .= $this->row($n++, ['No assigned members.'], 3);
        }
        $lastData = $n - 1;
        $rows .= $this->row($n, ['TOTAL', $expected / 100, $members->sum(fn ($m) => (float) ($m->receipt?->amount ?? 0)), '', '', '', '', ''], 1, 30, [1, 2]);

        $summary = $this->row(1, ['FACULTYCONNECT'], 1, 32).$this->row(2, ['Collection summary'], 2, 32)
            .$this->row(3, [$title], 2, 50).$this->row(4, [$meta], 3).$this->row(5, [$generated], 3)
            .$this->row(7, ['Financial summary', '', 'PHP'], 1)
            .$this->row(8, ['Expected contributions', '', $expected / 100], 0, 30, [2])
            .$this->row(9, ['Collected / verified', '', $paid / 100], 5, 30, [2])
            .$this->row(10, ['Outstanding', '', $outstanding / 100], 0, 30, [2])
            .$this->row(11, ['Waived', '', $waived / 100], 5, 30, [2])
            .$this->row(13, ['Collection rate', '', $expected > $waived ? number_format(100 * $paid / ($expected - $waived), 1).'%' : 'Not applicable'], 2, 32)
            .$this->row(14, ['Verified collected divided by expected less waivers.'], 3, 28)
            .$this->row(16, ['Member status', 'Members', 'Assigned PHP'], 1)
            .$this->row(17, ['Paid / Verified', $counts['paid'], $amounts['paid'] / 100], 0, 28, [2])
            .$this->row(18, ['Pending Verification', $counts['pending'], $amounts['pending'] / 100], 5, 28, [2])
            .$this->row(19, ['Unpaid', $counts['unpaid'], $amounts['unpaid'] / 100], 0, 28, [2])
            .$this->row(20, ['Needs Attention', $counts['rejected'], $amounts['rejected'] / 100], 5, 28, [2])
            .$this->row(21, ['Waived', $counts['waived'], $amounts['waived'] / 100], 0, 28, [2])
            .$this->row(23, ['Expected includes waivers. Outstanding excludes paid and waived obligations. Pending submissions are not collected funds.'], 3, 52)
            .$this->row(25, ['Snapshot at export time. The Transactions sheet contains the supporting records.'], 3, 38);
        $sheets = [
            $this->sheet($summary, [34, 13, 23], ['A1:C1', 'A2:C2', 'A3:C3', 'A4:C4', 'A5:C5', 'A7:B7', 'A8:B8', 'A9:B9', 'A10:B10', 'A11:B11', 'A13:B13', 'A14:C14', 'A23:C23', 'A25:C25'], 'A1:C25', false),
            $this->sheet($rows, [25, 12, 12, 23, 20, 18, 25, 27], ['A1:H1', 'A2:H2', 'A3:H3', 'A4:H4', 'A5:H5'], 'A1:H'.$n, true, 'A6:H'.$lastData),
        ];
        $path = tempnam(sys_get_temp_dir(), 'fc-report-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create report.');
        }
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Cannot open report.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="Summary" sheetId="1" r:id="rId1"/><sheet name="Transactions" sheetId="2" r:id="rId2"/></sheets><definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">Summary!$A$1:$C$25</definedName><definedName name="_xlnm.Print_Area" localSheetId="1">Transactions!$A$1:$H$'.$n.'</definedName><definedName name="_xlnm.Print_Titles" localSheetId="1">Transactions!$1:$6</definedName></definedNames></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', $this->styles());
            foreach ($sheets as $i => $sheet) {
                $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $sheet);
            }
            $zip->close();

            return file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function styles(): string
    {
        $base = ' borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/>';
        $xfs = '';
        foreach ([[0, 0, 0], [1, 2, 0], [2, 0, 0], [3, 0, 0], [0, 0, 164], [0, 3, 0], [0, 3, 164], [1, 2, 164]] as [$font,$fill,$format]) {
            $xfs .= '<xf fontId="'.$font.'" fillId="'.$fill.'" numFmtId="'.$format.'" applyNumberFormat="1" applyFill="1" applyFont="1"'.$base.'</xf>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00;[Red](#,##0.00);&quot;—&quot;"/></numFmts><fonts count="4"><font><sz val="11"/><color rgb="FF203A30"/><name val="Arial"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font><font><b/><sz val="16"/><color rgb="FF173E32"/><name val="Arial"/></font><font><sz val="10"/><color rgb="FF52635A"/><name val="Arial"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF173E32"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0F4EE"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="8">'.$xfs.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
