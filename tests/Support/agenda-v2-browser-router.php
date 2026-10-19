<?php
if(PHP_SAPI!=='cli-server'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'||(int)($_SERVER['SERVER_PORT']??0)!==8092){http_response_code(403);exit;}
$root=dirname(__DIR__,2);
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval' blob:; worker-src 'self' blob:; connect-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; font-src 'self' data:; frame-src 'self'; object-src 'none'");
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$asset=realpath($root.'/public'.$path);
if($path!=='/'&&$asset&&is_file($asset)&&str_starts_with($asset,realpath($root.'/public').DIRECTORY_SEPARATOR)&&strtolower(pathinfo($asset,PATHINFO_EXTENSION))!=='php')return false;
require $root.'/vendor/autoload.php';$app=\Tests\Support\IsolatedAgendaV2Qa::boot(true);
// Browser tests exercise the real CSRF middleware, unlike PHPUnit's synthetic request workers.
$app->bind(\App\Http\Middleware\VerifyCsrfToken::class,fn($app)=>new class($app,$app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {protected function runningUnitTests(){return false;}});
// Local-only timing diagnostics contain no parameters, identity or clinical content.
$qaStarted=microtime(true);$qaSqlMs=0;$qaSqlCount=0;
\Illuminate\Support\Facades\DB::listen(function($query)use(&$qaSqlMs,&$qaSqlCount){$qaSqlMs+=$query->time;$qaSqlCount++;});
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);$request=\Illuminate\Http\Request::capture();$response=$kernel->handle($request);$response->headers->set('Server-Timing','app;dur='.round((microtime(true)-$qaStarted)*1000,2).', sql;dur='.round($qaSqlMs,2).';desc="'.$qaSqlCount.' queries"');$response->send();$kernel->terminate($request,$response);
