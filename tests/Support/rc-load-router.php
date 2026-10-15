<?php
if(PHP_SAPI!=='cli-server'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'||!in_array((int)($_SERVER['SERVER_PORT']??0),[8028,8029],true)){http_response_code(403);exit;}
$root=dirname(__DIR__,2);require $root.'/vendor/autoload.php';$app=\Tests\Support\IsolatedRcSuiteQa::boot(true);
$app->bind(\App\Http\Middleware\VerifyCsrfToken::class,fn($app)=>new class($app,$app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {protected function runningUnitTests(){return false;}});
$queries=0;$sql=0;\Illuminate\Support\Facades\DB::listen(function($q)use(&$queries,&$sql){$queries++;$sql+=$q->time;});
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);$request=\Illuminate\Http\Request::capture();$start=hrtime(true);$response=$kernel->handle($request);
$response->headers->set('X-RC-QA-SQL-Count',(string)$queries);$response->headers->set('X-RC-QA-SQL-Ms',(string)round($sql,3));$response->headers->set('X-RC-QA-App-Ms',(string)round((hrtime(true)-$start)/1e6,3));
$response->headers->set('X-RC-QA-Peak-MiB',(string)round(memory_get_peak_usage(true)/1048576,2));$response->send();$kernel->terminate($request,$response);
