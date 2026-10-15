<?php
if(PHP_SAPI!=='cli-server'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(403);exit('Local QA only.');}
$root=dirname(__DIR__,2);
// Test-environment egress isolation only. The installed Clarity source stays byte-for-byte unchanged.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval' blob:; worker-src 'self' blob:; connect-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; font-src 'self' data:; frame-src 'self'; object-src 'none'");
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$asset=realpath($root.'/public'.$path);
if($path!=='/'&&$asset&&is_file($asset)&&str_starts_with($asset,realpath($root.'/public').DIRECTORY_SEPARATOR)&&strtolower(pathinfo($asset,PATHINFO_EXTENSION))!=='php')return false;
require $root.'/vendor/autoload.php';$app=\Tests\Support\IsolatedRcQa::boot(true);
$fixtures=json_decode(file_get_contents($root.'/storage/app/qa-rc/browser-fixtures.json'),true,512,JSON_THROW_ON_ERROR);
config(['scheduling.pilot_payment_without_manual_cash_shift'=>true,'scheduling.quiroz_referral'=>$fixtures['quiroz'],
    'logging.default'=>'rc_audit','logging.channels.rc_audit'=>['driver'=>'single','path'=>$root.'/storage/app/qa-rc/audit.log','level'=>'notice']]);
// Laravel's testing environment normally bypasses CSRF. Browser preproduction must exercise it.
$app->bind(\App\Http\Middleware\VerifyCsrfToken::class,fn($app)=>new class($app,$app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {
    protected function runningUnitTests(){return false;}
});
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);$request=\Illuminate\Http\Request::capture();
$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
