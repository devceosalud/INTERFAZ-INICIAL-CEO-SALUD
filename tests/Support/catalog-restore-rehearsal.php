<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = \Tests\Support\IsolatedCatalogQa::boot(true);
use Illuminate\Support\Facades\DB;
if (PHP_SAPI !== 'cli' || getenv('CATALOG_QA_RESTORE') !== 'NEW_SYNTHETIC_DATABASE') {
    throw new RuntimeException('Explicit synthetic rehearsal required');
}
$target = 'ceosalud_qa_catalog_restore';
if (($argv[1] ?? '') === 'init') {
    $pdo = new PDO('mysql:host=127.0.0.1;port=33323', 'root', getenv('CATALOG_LOCAL_ROOT_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE USER 'catalog_restore_qa'@'127.0.0.1' IDENTIFIED BY 'catalog-restore-local-only'");
    $pdo->exec("GRANT ALL PRIVILEGES ON `{$target}`.* TO 'catalog_restore_qa'@'127.0.0.1'");
    echo 'New owned synthetic restoration target created' . PHP_EOL;
    exit;
}
$targetPdo = new PDO('mysql:host=127.0.0.1;port=33323;dbname=' . $target, 'catalog_restore_qa', 'catalog-restore-local-only', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $targetPdo->query('SELECT DATABASE() db,@@port port,VERSION() version')->fetch(PDO::FETCH_ASSOC);
if ($identity['db'] !== $target || (int) $identity['port'] !== 33323 || !str_starts_with($identity['version'], '11.8.9-MariaDB')) {
    throw new RuntimeException('Unexpected restore target');
}
$tables = DB::select("SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
$report = ['source' => 'ceosalud_qa_catalog', 'restored_to' => $target, 'tables' => [], 'private_files' => []];
foreach ($tables as $table) {
    $name = $table->name;
    if (!preg_match('/^[a-z0-9_]+$/', $name)) {
        throw new RuntimeException('Unexpected table name');
    }
    $original = DB::select('SELECT * FROM `' . $name . '`');
    $restored = $targetPdo->query('SELECT * FROM `' . $name . '`')->fetchAll(PDO::FETCH_OBJ);
    $fingerprint = function ($rows) {
        $rows = array_map(fn($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows);
        sort($rows);
        return hash('sha256', implode("\n", $rows));
    };
    if ($fingerprint($original) !== $fingerprint($restored)) {
        throw new RuntimeException('Restore mismatch in ' . $name);
    }
    $report['tables'][] = ['table' => $name, 'rows' => count($original), 'sha256' => $fingerprint($original)];
}
foreach (DB::table('appointment_documents')->whereNotNull('private_path')->pluck('private_path') as $private) {
    $source = \Illuminate\Support\Facades\Storage::disk('local')->path($private);
    $source = realpath($source);
    $prefix = realpath(storage_path('app')) . DIRECTORY_SEPARATOR;
    if (!$source || !str_starts_with($source, $prefix)) {
        throw new RuntimeException('Private fixture outside owned workspace');
    }
    $destination = storage_path('app/qa-catalog/restore-private/' . basename($source));
    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0777, true);
    }
    copy($source, $destination);
    if (hash_file('sha256', $source) !== hash_file('sha256', $destination)) {
        throw new RuntimeException('Private fixture mismatch');
    }
    $report['private_files'][] = ['file' => basename($source), 'sha256' => hash_file('sha256', $source)];
}
file_put_contents(storage_path('app/qa-catalog/restore-rehearsal.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Verified ' . count($tables) . ' restored tables and ' . count($report['private_files']) . ' private synthetic files' . PHP_EOL;
