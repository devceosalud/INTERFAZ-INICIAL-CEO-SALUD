<?php
namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/** Dedicated QA instance only; the normal SQLite test safety guard remains unchanged. */
final class IsolatedAgendaQa
{
    public static function boot(bool $browser = false)
    {
        $root = dirname(__DIR__, 2);
        // Three concrete disposable runtimes only; no arbitrary environment connection is accepted.
        $equivalent = getenv('AGENDA_QA_RUNTIME') === 'equivalent-11.8.9';
        $rules = getenv('AGENDA_QA_RUNTIME') === 'rules-11.8.9';
        $port = $rules ? '33320' : ($equivalent ? '33319' : '33318');
        $database = $rules ? 'ceosalud_qa_rules' : ($equivalent ? 'ceosalud_qa_mvp_equivalent' : 'ceosalud_qa_urgente1');
        $username = $rules ? 'agenda_rules' : ($equivalent ? 'agenda_mvp_eq' : 'agenda_urgent');
        $password = $rules ? 'rules-local-only' : ($equivalent ? 'mvp-equivalent-only' : 'urgent-local-qa-only');
        $dataDir = $equivalent ? '/storage/app/qa-final/equivalent/mysql-runtime' : '/storage/app/qa-final/mysql-runtime';
        if (file_exists($root.'/.env')) { throw new \RuntimeException('QA requires a separate checkout without .env.'); }
        $values = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8022',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => $port,
            'DB_DATABASE' => $database, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $password,
            'DATABASE_URL' => '', 'DB_SOCKET' => '', 'CACHE_DRIVER' => 'array', 'LOG_CHANNEL' => 'null',
            'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BROADCAST_DRIVER' => 'null', 'BCRYPT_ROUNDS' => '4',
            'SESSION_DRIVER' => $browser ? 'file' : 'array', 'SESSION_COOKIE' => 'agenda_urgent_only',
            'SCHEDULING_MVP_ENABLED' => 'true', 'SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT' => 'false',
            'TELESCOPE_ENABLED' => 'false'];
        foreach (['CONFIG', 'EVENTS', 'PACKAGES', 'ROUTES', 'SERVICES'] as $cache) {
            $values['APP_'.$cache.'_CACHE'] = 'bootstrap/cache/'.($equivalent ? 'qa-equivalent-' : 'qa-final-').strtolower($cache).'.php';
        }
        foreach ($values as $name => $value) { putenv($name.'='.$value); $_ENV[$name] = $_SERVER[$name] = $value; }
        $app = require $root.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $connection = config('database.connections.mysql');
        if (!$app->environment('testing') || config('database.default') !== 'mysql'
            || $connection['host'] !== '127.0.0.1' || (string) $connection['port'] !== $port
            || $connection['database'] !== $database || $connection['username'] !== $username
            || !empty($connection['url']) || !empty($connection['unix_socket'])) {
            throw new \RuntimeException('QA refused a connection outside the dedicated local instance.');
        }
        $identity = DB::selectOne('SELECT DATABASE() AS db, @@port AS port, @@datadir AS dir, VERSION() AS version');
        $normalize = fn ($path) => strtolower(rtrim(str_replace('\\', '/', $path), '/'));
        if ($identity->db !== $database || (int) $identity->port !== (int) $port
            || $normalize($identity->dir) !== $normalize($rules ? dirname($root).'/.rules-mariadb-1189/data' : $root.$dataDir)) {
            throw new \RuntimeException('QA refused an unowned database runtime.');
        }
        if (($equivalent || $rules) && !str_starts_with($identity->version, '11.8.9-MariaDB')) {
            throw new \RuntimeException('Equivalent QA requires MariaDB 11.8.9.');
        }
        Http::preventStrayRequests(); Mail::fake(); Queue::fake(); Notification::fake();
        return $app;
    }
}
