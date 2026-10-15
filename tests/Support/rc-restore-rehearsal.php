<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\IsolatedRcQa::boot();
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only');
use Illuminate\Support\Facades\DB;
function fingerprint(PDO $pdo):array{
    $tables=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
        if(!preg_match('/\A[a-z0-9_]+\z/',$table))throw new RuntimeException('Unexpected identifier');
        $rows=$pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);$canonical=[];
        foreach($rows as $row){ksort($row);$canonical[]=json_encode(array_map(fn($v)=>$v===null?null:(string)$v,$row),JSON_THROW_ON_ERROR);}
        sort($canonical);$tables[$table]=['count'=>count($rows),'sha256'=>hash('sha256',implode("\n",$canonical))];
    }ksort($tables);return $tables;
}
$action=$argv[1]??'';$folder=realpath($argv[2]??'');$allowed=realpath(storage_path('app/qa-rc'));
if(!$folder||!str_starts_with(strtolower($folder),strtolower($allowed.DIRECTORY_SEPARATOR)))throw new RuntimeException('Owned evidence folder required');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
if($action==='prepare'){
    $root=new PDO('mysql:host=127.0.0.1;port=33323;charset=utf8mb4','root','rc-suite-root-local-only',$options);
    $identity=$root->query('SELECT @@port port,@@datadir dir,VERSION() version')->fetch(PDO::FETCH_ASSOC);
    $normalize=fn($v)=>strtolower(rtrim(str_replace('\\','/',$v),'/'));
    if((int)$identity['port']!==33323||!str_starts_with($identity['version'],'11.8.9-MariaDB')||$normalize($identity['dir'])!==$normalize(dirname(base_path()).'/.rc-suite-mariadb-1189/data'))throw new RuntimeException('Restore runtime identity mismatch');
    $suffix=bin2hex(random_bytes(6));$schema='ceosalud_rc_restore_'.$suffix;$user='rc_restore_'.$suffix;$password=bin2hex(random_bytes(24));
    $root->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $root->exec('CREATE USER '.$root->quote($user)."@'127.0.0.1' IDENTIFIED BY ".$root->quote($password));
    $root->exec('GRANT ALL PRIVILEGES ON `'.$schema.'`.* TO '.$root->quote($user)."@'127.0.0.1'");
    file_put_contents($folder.'/private-restore.json',json_encode(compact('schema','user','password')));
    file_put_contents($folder.'/private-restore.cnf',"[client]\nhost=127.0.0.1\nport=33323\nprotocol=tcp\nuser=$user\npassword=$password\n");
    file_put_contents($folder.'/source-fingerprint.json',json_encode(fingerprint(DB::connection()->getPdo()),JSON_PRETTY_PRINT));
    echo 'Prepared a new, empty, independently owned restore schema; no existing database changed.'.PHP_EOL;
}elseif($action==='verify'){
    $c=json_decode(file_get_contents($folder.'/private-restore.json'),true,512,JSON_THROW_ON_ERROR);
    if(!preg_match('/\Aceosalud_rc_restore_[a-f0-9]{12}\z/',$c['schema']))throw new RuntimeException('Restore schema rejected');
    $pdo=new PDO('mysql:host=127.0.0.1;port=33323;dbname='.$c['schema'].';charset=utf8mb4',$c['user'],$c['password'],$options);
    $expected=json_decode(file_get_contents($folder.'/source-fingerprint.json'),true,512,JSON_THROW_ON_ERROR);$actual=fingerprint($pdo);
    if($expected!==$actual)throw new RuntimeException('Restored table fingerprints differ');
    $proofs=$pdo->query('SELECT private_path FROM appointment_documents WHERE private_path IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
    $report=['passed'=>true,'source_port'=>33322,'restore_port'=>33323,'version'=>$pdo->query('SELECT VERSION()')->fetchColumn(),'tables_verified'=>count($actual),'rows_verified'=>array_sum(array_column($actual,'count')),'retained_document_references'=>count($proofs),'tables'=>$actual];
    file_put_contents($folder.'/restoration-result.json',json_encode($report,JSON_PRETTY_PRINT));echo 'Restored every table with identical row counts and canonical SHA256 fingerprints.'.PHP_EOL;
}else throw new RuntimeException('Unsupported action');
