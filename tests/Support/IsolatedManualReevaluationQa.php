<?php
namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/** Disposable localhost SQLite fixture; never reads a developer .env or production DB. */
final class IsolatedManualReevaluationQa
{
    public static function boot()
    {
        $root=dirname(__DIR__,2);
        if(file_exists($root.'/.env')) throw new \RuntimeException('QA requires a checkout without .env.');
        $db=$root.'/storage/app/hotfix-qa/browser.sqlite';
        if(!is_file($db)) throw new \RuntimeException('Create the disposable QA SQLite file first.');
        $values=['APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_URL'=>'http://127.0.0.1:8097',
            'APP_KEY'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$db,'DATABASE_URL'=>'','DB_FOREIGN_KEYS'=>'true',
            'CACHE_DRIVER'=>'array','LOG_CHANNEL'=>'null','MAIL_MAILER'=>'array','QUEUE_CONNECTION'=>'sync',
            'BROADCAST_DRIVER'=>'null','BCRYPT_ROUNDS'=>'4','SESSION_DRIVER'=>'file',
            'SESSION_COOKIE'=>'hotfix_manual_qa_only','SESSION_DOMAIN'=>'','SESSION_SECURE_COOKIE'=>'false',
            'SCHEDULING_MVP_ENABLED'=>'true','SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT'=>'false'];
        foreach(['CONFIG','EVENTS','PACKAGES','ROUTES','SERVICES'] as $cache)
            $values['APP_'.$cache.'_CACHE']='bootstrap/cache/manual-qa-'.strtolower($cache).'.php';
        foreach($values as $name=>$value){putenv($name.'='.$value);$_ENV[$name]=$_SERVER[$name]=$value;}
        $app=require $root.'/bootstrap/app.php';$app->make(Kernel::class)->bootstrap();
        if(!$app->environment('testing')||config('database.default')!=='sqlite'
            ||config('database.connections.sqlite.database')!==$db||config('database.connections.sqlite.url'))
            throw new \RuntimeException('QA refused a database outside its local SQLite fixture.');
        Http::preventStrayRequests();Mail::fake();Queue::fake();Notification::fake();
        return $app;
    }
}
