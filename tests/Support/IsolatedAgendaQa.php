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
        if (file_exists($root.'/.env')) { throw new \RuntimeException('QA requires a separate checkout without .env.'); }
        $values = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8021',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '33317',
            'DB_DATABASE' => 'ceosalud_qa_entrega1', 'DB_USERNAME' => 'agenda_qa', 'DB_PASSWORD' => 'local-qa-only',
            'DATABASE_URL' => '', 'DB_SOCKET' => '', 'CACHE_DRIVER' => 'array', 'LOG_CHANNEL' => 'null',
            'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BROADCAST_DRIVER' => 'null', 'BCRYPT_ROUNDS' => '4',
            'SESSION_DRIVER' => $browser ? 'file' : 'array', 'SESSION_COOKIE' => 'agenda_qa_only',
            'SCHEDULING_MVP_ENABLED' => 'true', 'SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT' => 'false',
            'TELESCOPE_ENABLED' => 'false'];
        foreach (['CONFIG', 'EVENTS', 'PACKAGES', 'ROUTES', 'SERVICES'] as $cache) {
            $values['APP_'.$cache.'_CACHE'] = 'bootstrap/cache/qa-final-'.strtolower($cache).'.php';
        }
        foreach ($values as $name => $value) { putenv($name.'='.$value); $_ENV[$name] = $_SERVER[$name] = $value; }
        $app = require $root.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $connection = config('database.connections.mysql');
        if (!$app->environment('testing') || config('database.default') !== 'mysql'
            || $connection['host'] !== '127.0.0.1' || (string) $connection['port'] !== '33317'
            || $connection['database'] !== 'ceosalud_qa_entrega1' || $connection['username'] !== 'agenda_qa'
            || !empty($connection['url']) || !empty($connection['unix_socket'])) {
            throw new \RuntimeException('QA refused a connection outside the dedicated local instance.');
        }
        $identity = DB::selectOne('SELECT DATABASE() AS db, @@port AS port, @@datadir AS dir');
        $normalize = fn ($path) => strtolower(rtrim(str_replace('\\', '/', $path), '/'));
        if ($identity->db !== 'ceosalud_qa_entrega1' || (int) $identity->port !== 33317
            || $normalize($identity->dir) !== $normalize($root.'/storage/app/qa-final/mysql-runtime')) {
            throw new \RuntimeException('QA refused an unowned database runtime.');
        }
        Http::preventStrayRequests(); Mail::fake(); Queue::fake(); Notification::fake();
        return $app;
    }
}
