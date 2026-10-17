<?php
// php -S 127.0.0.1:8097 -t public tests/Support/manual-reevaluation-qa-router.php
if(PHP_SAPI!=='cli-server'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(403);exit('Local QA only.');}
$root=dirname(__DIR__,2);$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
header("Content-Security-Policy: default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; connect-src 'self'; frame-src 'self'");
$asset=realpath($root.'/public'.$path);
if($path!=='/'&&$asset&&is_file($asset)&&str_starts_with($asset,realpath($root.'/public').DIRECTORY_SEPARATOR)
    &&strtolower(pathinfo($asset,PATHINFO_EXTENSION))!=='php')return false;
require $root.'/vendor/autoload.php';
$app=\Tests\Support\IsolatedManualReevaluationQa::boot();
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);$request=\Illuminate\Http\Request::capture();
$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
