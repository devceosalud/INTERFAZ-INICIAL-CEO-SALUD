<?php
namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/** Exact, disposable local runtime. Never accepts arbitrary connection configuration. */
final class IsolatedAgendaV2Qa
{
    public static function boot(bool $browser=false)
    {
        $root=dirname(__DIR__,2);
        $ci=getenv('GITHUB_ACTIONS')==='true' && getenv('AGENDA_V2_CI_OWNED')==='DISPOSABLE_SERVICE_ONLY' && PHP_OS_FAMILY==='Linux';
        if($ci && $browser)throw new \RuntimeException('CI runtime is suite-only.');
        // Separate, fixed Windows recovery port. Same strict schema, user, version
        // and checkout-owned datadir checks; never accepts a configurable DSN.
        $recovery=getenv('CEO_RECOVERY_LOCAL_QA')==='OWNED_LOOPBACK_33342' && PHP_OS_FAMILY==='Windows';
        $port=$recovery?33342:33324;
        if(file_exists($root.'/.env'))throw new \RuntimeException('QA requires an isolated checkout without .env.');
        $db=$browser?'ceosalud_qa_agenda_v2_browser':'ceosalud_qa_agenda_v2_suite';
        $user=$browser?'agenda_v2_browser':'agenda_v2_suite';
        $values=['APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_URL'=>'http://127.0.0.1:8092',
            'APP_KEY'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>(string)$port,'DB_DATABASE'=>$db,
            'DB_USERNAME'=>$user,'DB_PASSWORD'=>'v2-local-only','DATABASE_URL'=>'','DB_SOCKET'=>'',
            'CACHE_DRIVER'=>'array','LOG_CHANNEL'=>'null','MAIL_MAILER'=>'array','QUEUE_CONNECTION'=>'sync',
            'BROADCAST_DRIVER'=>'null','BCRYPT_ROUNDS'=>'4','SESSION_DRIVER'=>$browser?'file':'array',
            'SESSION_COOKIE'=>'agenda_v2_qa_only','SESSION_DOMAIN'=>'','SESSION_SECURE_COOKIE'=>'false',
            'SCHEDULING_MVP_ENABLED'=>$browser?'true':'false','SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT'=>'false',
            'TELESCOPE_ENABLED'=>'false'];
        foreach(['CONFIG','EVENTS','PACKAGES','ROUTES','SERVICES'] as $key)$values['APP_'.$key.'_CACHE']='bootstrap/cache/qa-v2-'.($browser?'browser-':'suite-').strtolower($key).'.php';
        foreach(['AQPFACT_URL_DNI','AQPFACT_URL_RUC','APISPERU_URL','APISPERU_DNI_URL','APISPERU_RUC_URL','HTTSMS_URL','HTTPSMS_URL','TEXTBEE_URL'] as $key)$values[$key]='http://127.0.0.1:9/qa-disabled';
        foreach(['AQPFACT_TOKEN','APISPERU_DNI_TOKEN','APISPERU_RUC_TOKEN','HTTPSMS_API_KEY','TEXTBEE_API_KEY','TEXTBEE_DEVICE_ID'] as $key)$values[$key]='test-only-disabled';
        if(!$browser)foreach(array_keys($values) as $key){
            $inherited=getenv($key);
            if(str_contains($key,'URL') && is_string($inherited) && str_starts_with($inherited,'http://127.0.0.1:9/'))$values[$key]=$inherited;
        }
        foreach($values as $key=>$value){putenv($key.'='.$value);$_ENV[$key]=$_SERVER[$key]=$value;}
        $app=require $root.'/bootstrap/app.php';$app->make(Kernel::class)->bootstrap();
        $c=config('database.connections.mysql');
        if(!$app->environment('testing')||config('database.default')!=='mysql'||$c['host']!=='127.0.0.1'||(int)$c['port']!==$port
            ||$c['database']!==$db||$c['username']!==$user||!empty($c['url'])||!empty($c['unix_socket']))throw new \RuntimeException('V2 QA refused an unowned connection.');
        $identity=DB::selectOne('SELECT DATABASE() db,@@port port,@@datadir dir,VERSION() version');
        $normalize=fn($v)=>strtolower(rtrim(str_replace('\\','/',$v),'/'));
        if($identity->db!==$db||(int)$identity->port!==($ci?3306:$port)||!str_starts_with($identity->version,'11.8.9-MariaDB')
            ||$normalize($identity->dir)!==$normalize($ci?'/var/lib/mysql':$root.'/storage/app/qa-v2/mariadb'))throw new \RuntimeException('V2 QA identity mismatch.');
        // Browser replays may use the original fictional cohort's day without changing any
        // appointment or weakening same-day eligibility. Never active in PHP suites or production.
        $clock=$root.'/storage/app/qa-v2/browser-clock.json';
        if($browser && is_file($clock)) {
            $qaClock=json_decode(file_get_contents($clock),true,512,JSON_THROW_ON_ERROR);
            if(($qaClock['scope']??'')!=='LOCAL_FICTITIOUS_CLOCK_ONLY'
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$qaClock['date']??''))throw new \RuntimeException('Invalid QA replay clock.');
            $instant=\Carbon\Carbon::createFromFormat('!Y-m-d',$qaClock['date'],'America/Lima');
            if(!$instant || $instant->format('Y-m-d')!==$qaClock['date'])throw new \RuntimeException('Invalid QA replay day.');
            \Carbon\Carbon::setTestNow($instant->setTime(23,59));
            // Session cookies avoid browser expiry in the real clock while replaying a prior day.
            // Authentication, CSRF and server-side session checks remain enabled.
            config(['session.expire_on_close'=>true]);
        }
        Http::preventStrayRequests();Mail::fake();Queue::fake();Notification::fake();
        return $app;
    }
}
