<?php
// Loopback-only isolated OCR QA; never deployed as an ERP route.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(403); exit; }
$root = dirname(__DIR__, 2);
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header("Content-Security-Policy: default-src 'none'; script-src 'self' 'wasm-unsafe-eval'; worker-src 'self'; connect-src 'self'; img-src 'self' blob:; style-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$routes=['/'=>__DIR__.'/ocr-browser-qa.html','/qa.js'=>__DIR__.'/ocr-browser-qa.js','/qa.css'=>__DIR__.'/ocr-browser-qa.css'];
$target=$routes[$uri]??null;
if (!$target && str_starts_with($uri,'/vendor/appointment-ocr/')) $target=$root.'/public'.$uri;
if (!$target && $uri==='/appointment-receipt-ocr.js') $target=$root.'/public/js/scheduling/appointment-receipt-ocr.js';
if (!$target && preg_match('~^/fixture/(yape\.png|bcp\.jpg|ambiguous\.png|unreadable\.png)$~D',$uri,$m)) $target=$root.'/tests/Fixtures/receipts/'.$m[1];
$real=$target?realpath($target):false;
$allowed=$real && (str_starts_with($real,realpath($root.'/public/vendor/appointment-ocr').DIRECTORY_SEPARATOR) || $real===realpath($root.'/public/js/scheduling/appointment-receipt-ocr.js') || in_array($real,array_map('realpath',$routes),true) || str_starts_with($real,realpath($root.'/tests/Fixtures/receipts').DIRECTORY_SEPARATOR));
if (!$allowed || !is_file($real)) { http_response_code(404); exit; }
$ext=pathinfo($real,PATHINFO_EXTENSION);
header('Content-Type: '.(['html'=>'text/html; charset=utf-8','js'=>'application/javascript','css'=>'text/css','png'=>'image/png','jpg'=>'image/jpeg','gz'=>'application/octet-stream','json'=>'application/json'][$ext]??'text/plain'));
readfile($real);
