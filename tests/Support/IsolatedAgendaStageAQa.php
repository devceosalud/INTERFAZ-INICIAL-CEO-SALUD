<?php
namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/** Dedicated disposable runtime; never falls back to a developer or production database. */
final class IsolatedAgendaStageAQa
{
    public static function boot(bool $browser=false)
    {
        $root=dirname(__DIR__,2);
        if(file_exists($root.'/.env'))throw new \RuntimeException('QA requires a separate checkout without .env.');
        $env=['APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_URL'=>'http://127.0.0.1:8086',
            'APP_KEY'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>'33323','DB_DATABASE'=>'ceosalud_qa_agenda_a',
            'DB_USERNAME'=>'agenda_stage_a_qa','DB_PASSWORD'=>'agenda-stage-a-local-only','DATABASE_URL'=>'','DB_SOCKET'=>'',
            'CACHE_DRIVER'=>'array','LOG_CHANNEL'=>'null','MAIL_MAILER'=>'array','QUEUE_CONNECTION'=>'sync','BROADCAST_DRIVER'=>'null',
            'BCRYPT_ROUNDS'=>'4','SESSION_DRIVER'=>$browser?'file':'array','SESSION_COOKIE'=>'agenda_stage_a_qa_only',
            'SESSION_DOMAIN'=>'','SESSION_SECURE_COOKIE'=>'false','SCHEDULING_MVP_ENABLED'=>'true',
            'SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT'=>'false','TELESCOPE_ENABLED'=>'false'];
        foreach(['CONFIG','EVENTS','PACKAGES','ROUTES','SERVICES'] as $cache)$env['APP_'.$cache.'_CACHE']='bootstrap/cache/qa-stage-a-suite-'.strtolower($cache).'.php';
        foreach(['AQPFACT_URL_DNI','AQPFACT_URL_RUC','APISPERU_URL','APISPERU_DNI_URL','APISPERU_RUC_URL','HTTSMS_URL','HTTPSMS_URL','TEXTBEE_URL'] as $name)$env[$name]='http://127.0.0.1:9/qa-disabled';
        foreach(['AQPFACT_TOKEN','APISPERU_DNI_TOKEN','APISPERU_RUC_TOKEN','HTTPSMS_API_KEY','TEXTBEE_API_KEY','TEXTBEE_DEVICE_ID'] as $name)$env[$name]='test-only-disabled';
        foreach($env as $name=>$value){putenv($name.'='.$value);$_ENV[$name]=$_SERVER[$name]=$value;}
        $app=require $root.'/bootstrap/app.php';$app->make(Kernel::class)->bootstrap();
        $c=config('database.connections.mysql');
        if(!$app->environment('testing')||config('database.default')!=='mysql'||$c['host']!=='127.0.0.1'||(int)$c['port']!==33323
            ||$c['database']!=='ceosalud_qa_agenda_a'||$c['username']!=='agenda_stage_a_qa'||!empty($c['url'])||!empty($c['unix_socket']))
            throw new \RuntimeException('Agenda Stage A QA refused an unowned connection.');
        $identity=DB::selectOne('SELECT DATABASE() AS db,@@port AS port,@@datadir AS dir,VERSION() AS version');
        $normalize=fn($value)=>strtolower(rtrim(str_replace('\\','/',$value),'/'));
        if($identity->db!=='ceosalud_qa_agenda_a'||(int)$identity->port!==33323||!str_starts_with($identity->version,'11.8.9-MariaDB')
            ||$normalize($identity->dir)!==$normalize(dirname($root).'/.rc-suite-mariadb-1189/data'))
            throw new \RuntimeException('Agenda Stage A QA requires its own data directory and MariaDB 11.8.9.');
        Http::preventStrayRequests();Mail::fake();Queue::fake();Notification::fake();
        return $app;
    }
}
