<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
if(PHP_SAPI!=='cli'||file_exists(dirname(__DIR__,2).'/.env'))throw new RuntimeException('Separate checkout required');
$root=dirname(__DIR__,2);$pdo=new PDO('mysql:host=127.0.0.1;port=33323','root','rc-suite-root-local-only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$i=$pdo->query('SELECT @@port port,@@datadir dir,VERSION() version')->fetch(PDO::FETCH_ASSOC);
$norm=fn($s)=>strtolower(rtrim(str_replace('\\','/',$s),'/'));
if((int)$i['port']!==33323||!str_starts_with($i['version'],'11.8.9-MariaDB')||$norm($i['dir'])!==$norm(dirname($root).'/.rc-suite-mariadb-1189/data'))throw new RuntimeException('Unowned QA server refused');
$marker=$root.'/storage/app/qa-stage-a/owned-schema.json';
$exists=$pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='ceosalud_qa_agenda_a'")->fetchColumn();
if($exists){if(!file_exists($marker))throw new RuntimeException('Existing schema has no ownership marker; no changes');echo 'Existing owned stage A schema preserved'.PHP_EOL;exit;}
if($pdo->query("SELECT COUNT(*) FROM mysql.user WHERE User='agenda_stage_a_qa'")->fetchColumn())throw new RuntimeException('QA user collision; no changes');
$pdo->exec('CREATE DATABASE ceosalud_qa_agenda_a CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec("CREATE USER 'agenda_stage_a_qa'@'127.0.0.1' IDENTIFIED BY 'agenda-stage-a-local-only'");
$pdo->exec("GRANT ALL PRIVILEGES ON ceosalud_qa_agenda_a.* TO 'agenda_stage_a_qa'@'127.0.0.1'");
file_put_contents($marker,json_encode(['schema'=>'ceosalud_qa_agenda_a','port'=>33323,'created_at'=>gmdate('c'),'data'=>'synthetic only','root_version'=>$i['version']]));
echo 'Created separate empty synthetic schema; previous RC schemas unchanged'.PHP_EOL;