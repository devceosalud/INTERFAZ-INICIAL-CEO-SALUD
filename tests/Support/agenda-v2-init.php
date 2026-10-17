<?php
// Creates only previously nonexistent synthetic schemas/users, on a verified owned local server.
if(PHP_SAPI!=='cli'||getenv('AGENDA_V2_QA_INIT')!=='NEW_SYNTHETIC_RUNTIME')throw new RuntimeException('Explicit synthetic initialization required.');
$root=dirname(__DIR__,2);
if(file_exists($root.'/.env'))throw new RuntimeException('No .env allowed.');
$pdo=new PDO('mysql:host=127.0.0.1;port=33324','root','v2-root-local-only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$id=$pdo->query('SELECT @@port port,@@datadir dir,VERSION() version')->fetch(PDO::FETCH_ASSOC);
$normalize=fn($v)=>strtolower(rtrim(str_replace('\\','/',$v),'/'));
if((int)$id['port']!==33324||!str_starts_with($id['version'],'11.8.9-MariaDB')
    ||$normalize($id['dir'])!==$normalize($root.'/storage/app/qa-v2/mariadb'))throw new RuntimeException('Runtime identity mismatch.');
foreach(['suite','browser'] as $kind){
    $db='ceosalud_qa_agenda_v2_'.$kind;$user='agenda_v2_'.$kind;
    $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE USER '$user'@'127.0.0.1' IDENTIFIED BY 'v2-local-only'");
    $pdo->exec("GRANT ALL PRIVILEGES ON `$db`.* TO '$user'@'127.0.0.1'");
}
echo "Created exclusively owned synthetic v2 schemas.\n";
