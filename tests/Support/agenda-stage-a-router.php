<?php
if(PHP_SAPI!=='cli-server'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'||!in_array((int)($_SERVER['SERVER_PORT']??0),[8086,8087],true)){http_response_code(403);exit;}
$root=dirname(__DIR__,2);
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval' blob:; worker-src 'self' blob:; connect-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; font-src 'self' data:; frame-src 'self'; object-src 'none'");
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$asset=realpath($root.'/public'.$path);
if($path!=='/'&&$asset&&is_file($asset)&&str_starts_with($asset,realpath($root.'/public').DIRECTORY_SEPARATOR)&&strtolower(pathinfo($asset,PATHINFO_EXTENSION))!=='php')return false;
require $root.'/vendor/autoload.php';$app=\Tests\Support\IsolatedAgendaStageAQa::boot(true);config(['scheduling.pilot_payment_without_manual_cash_shift'=>true]);
$app->bind(\App\Http\Middleware\VerifyCsrfToken::class,fn($app)=>new class($app,$app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {protected function runningUnitTests(){return false;}});
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);$request=\Illuminate\Http\Request::capture();$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);