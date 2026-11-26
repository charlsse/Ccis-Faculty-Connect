<?php
// Syntax and breakpoint-policy audit only; never a substitute for browser layout measurements.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/packages/autoload.php';
$result = ['browser_layout_test' => false, 'files' => [], 'width_policy_coverage' => []];
try {
    foreach (['responsive.css', 'payment-receipt.css'] as $file) {
        $css = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents(dirname(__DIR__).'/public/css/'.$file));
        $parser = new Sabberworm\CSS\Parser($css, Sabberworm\CSS\Settings::create()->withLenientParsing(false));
        $document = $parser->parse();
        $media = [];
        foreach ($document->getContents() as $block) {
            if ($block instanceof Sabberworm\CSS\CSSList\AtRuleBlockList && $block->atRuleName() === 'media') $media[] = $block->atRuleArgs();
        }
        $result['files'][$file] = ['strict_parse_passed' => true, 'media_queries' => $media];
    }
    foreach ([320,375,390,430,768,1024,1366,1920] as $width) {
        $active = [];
        foreach ($result['files']['responsive.css']['media_queries'] as $query) {
            if (preg_match('/^\(max-width:\s*(\d+)px\)$/', $query, $match) && $width <= (int)$match[1]) $active[] = $query;
        }
        $result['width_policy_coverage'][$width] = ['shared_base_rules' => true, 'matching_width_queries' => $active];
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR, 'Responsive CSS parsing failed: '.$error->getMessage().PHP_EOL); exit(1); }
