<?php
// Included only by the loopback CLI QA router, never by Laravel production routes.
if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403); exit('Local QA only.');
}
$width = (int) ($_GET['width'] ?? 0); $height = (int) ($_GET['height'] ?? 0);
if (!in_array([$width, $height], [[1920,1080],[1366,768],[1024,768],[390,844]], true)) {
    http_response_code(400); exit('Use an approved QA viewport.');
}
$root = dirname(__DIR__, 2);
$fixtureFile = $root.'/storage/app/qa-visual/followup-fixtures.json';
if (!is_file($fixtureFile)) { $fixtureFile = $root.'/storage/app/qa-final/browser-fixtures.json'; }
$fixture = is_file($fixtureFile) ? json_decode(file_get_contents($fixtureFile), true) : [];
$date = (string) ($_GET['fecha'] ?? $fixture['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) { http_response_code(400); exit('Invalid QA date.'); }
header('Cache-Control: no-store');
?><!doctype html><html lang="es"><head><meta charset="utf-8"><title>Agenda QA viewport</title>
<style>html,body{margin:0;padding:0;background:#fff;}iframe{display:block;border:0;}</style></head>
<body><iframe id="qa-viewport" title="Agenda QA <?= $width ?> x <?= $height ?>"
    width="<?= $width ?>" height="<?= $height ?>" src="/scheduling-mvp/agenda?fecha=<?= rawurlencode($date) ?>"></iframe></body></html>
