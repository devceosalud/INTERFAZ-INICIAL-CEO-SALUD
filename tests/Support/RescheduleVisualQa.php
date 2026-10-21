<?php
namespace Tests\Support;

/** Local fictional browser only. Never consumes .env or a configurable database connection. */
final class RescheduleVisualQa
{
    public static function boot()
    {
        $root=dirname(__DIR__,2);
        if(is_file($root.'/.env'))throw new \RuntimeException('QA refuses a checkout containing .env.');
        $db=$root.'/storage/app/qa-reschedule-visual/browser.sqlite';
        if(!is_file($db))throw new \RuntimeException('Create the dedicated empty QA database first.');
        $values=['APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_KEY'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_URL'=>'http://127.0.0.1:8099','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$db,'DATABASE_URL'=>'','DB_FOREIGN_KEYS'=>'true',
            'CACHE_DRIVER'=>'array','LOG_CHANNEL'=>'null','MAIL_MAILER'=>'array','QUEUE_CONNECTION'=>'sync','BROADCAST_DRIVER'=>'null',
            'BCRYPT_ROUNDS'=>'4','SESSION_DRIVER'=>'file','SESSION_COOKIE'=>'reschedule_visual_qa','SESSION_DOMAIN'=>'','SESSION_SECURE_COOKIE'=>'false',
            'SCHEDULING_MVP_ENABLED'=>'true','SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT'=>'false','TELESCOPE_ENABLED'=>'false'];
        foreach(['CONFIG','EVENTS','PACKAGES','ROUTES','SERVICES'] as $cache)$values['APP_'.$cache.'_CACHE']='bootstrap/cache/qa-reschedule-'.strtolower($cache).'.php';
        foreach(['AQPFACT_URL_DNI','AQPFACT_URL_RUC','APISPERU_URL','APISPERU_DNI_URL','APISPERU_RUC_URL','HTTSMS_URL','HTTPSMS_URL','TEXTBEE_URL'] as $key)$values[$key]='http://127.0.0.1:9/qa-disabled';
        foreach(['AQPFACT_TOKEN','APISPERU_DNI_TOKEN','APISPERU_RUC_TOKEN','HTTPSMS_API_KEY','TEXTBEE_API_KEY','TEXTBEE_DEVICE_ID'] as $key)$values[$key]='test-only-disabled';
        foreach($values as $key=>$value){putenv($key.'='.$value);$_ENV[$key]=$_SERVER[$key]=$value;}
        $app=require $root.'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        if(!$app->environment('testing')||config('database.default')!=='sqlite'||config('database.connections.sqlite.database')!==$db||config('database.connections.sqlite.url'))
            throw new \RuntimeException('QA connection identity mismatch.');
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-21 09:00','America/Lima'));
        config(['session.expire_on_close'=>true]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();\Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Queue::fake();\Illuminate\Support\Facades\Notification::fake();
        return $app;
    }
}
