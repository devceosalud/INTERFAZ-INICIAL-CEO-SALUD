<?php
namespace Tests\Support;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{DB,Http,Mail,Queue,Notification};
/** Opt-in, checkout-owned disposable DB. Never accepts arbitrary DSNs or a clinical .env. */
final class WaitingRoomMariaDbQa
{
    public static function boot(bool $integration=false)
    {
        $root=dirname(__DIR__,2);$runtime=realpath(dirname($root).'/runtime/clinical-review-20261028');
        if(PHP_OS_FAMILY!=='Windows'||file_exists($root.'/.env')||!$runtime)throw new \RuntimeException('Isolated checkout required.');
        $file=$runtime.'/mysql-qa.json';$c=json_decode(file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
        if(($c['marker']??null)!=='WR_DISPOSABLE_DB_20261028'||$c['host']!=='127.0.0.1'||$c['port']!==13307
            ||$c['database']!=='wr_fixture_20261028'||$c['username']!=='wr_fixture')throw new \RuntimeException('Unowned QA configuration.');
        $v=['APP_ENV'=>$integration?'integration':'testing','APP_DEBUG'=>'false','APP_KEY'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_URL'=>'http://127.0.0.1:4191','APP_CONFIG_CACHE'=>'bootstrap/cache/wr-mariadb-config.php',
            'DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>'13307','DB_DATABASE'=>$c['database'],'DB_USERNAME'=>$c['username'],'DB_PASSWORD'=>$c['password'],
            'DATABASE_URL'=>'','DB_SOCKET'=>'','CACHE_DRIVER'=>$integration?'file':'array','SESSION_DRIVER'=>'array',
            'MAIL_MAILER'=>'array','QUEUE_CONNECTION'=>'sync','LOG_CHANNEL'=>'null','TELESCOPE_ENABLED'=>'false','BCRYPT_ROUNDS'=>'4',
            'WR_INTEGRATION_ENABLED'=>'true','WR_ALLOW_LOOPBACK_HTTP'=>'true','WR_CONFIG_FILE'=>$runtime.'/erp-config.json',
            'WR_REFERENCE_KEY'=>hash('sha256','fictitious-reference-key-20261028'),'SCHEDULING_MVP_ENABLED'=>'true'];
        foreach($v as $k=>$val){putenv($k.'='.$val);$_ENV[$k]=$_SERVER[$k]=$val;}
        $app=require $root.'/bootstrap/app.php';$app->make(Kernel::class)->bootstrap();
        $identity=DB::selectOne('SELECT DATABASE() db,@@port port,@@datadir dir,VERSION() version');
        $n=fn($s)=>strtolower(rtrim(str_replace('\\','/',$s),'/'));
        if($identity->db!==$c['database']||(int)$identity->port!==13307||$n($identity->dir)!==$n($runtime.'/mariadb')
            ||!str_contains($identity->version,'MariaDB'))throw new \RuntimeException('QA DB identity mismatch.');
        if(!$integration){Http::preventStrayRequests();Mail::fake();Queue::fake();Notification::fake();}
        return $app;
    }
}
