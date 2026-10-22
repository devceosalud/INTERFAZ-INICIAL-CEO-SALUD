<?php
/** Read-only file verification. Run only through an authorized deployment/preflight procedure. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if($argc!==3){fwrite(STDERR,"Usage: php reschedule-preflight.php APP_ROOT MANIFEST.json\n");exit(1);}
$root=realpath($argv[1]);$manifestPath=realpath($argv[2]);
if(!$root||!is_dir($root)||!$manifestPath||!is_file($manifestPath)){fwrite(STDERR,"Invalid root/manifest.\n");exit(1);}
$manifest=json_decode(file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
if(($manifest['format']??null)!=='RESCHEDULE_RUNTIME_V1'||!isset($manifest['files'])||($manifest['text_normalization']??'')!=='CRLF_TO_LF')throw new RuntimeException('Unsupported manifest.');
$problems=[];$checked=0;
foreach($manifest['files'] as $path=>$expected){
    if(!preg_match('~\A(?:app/|bootstrap/app\.php\z|config/|database/migrations/|resources/|routes/|public/(?:js/|css/|assets/|vendor/(?:appointment-ocr|ceosalud-alerts)/)|composer\.(?:json|lock)\z)~D',$path)
        ||str_contains($path,'..')||str_contains($path,'\\')||str_contains($path,'/./'))throw new RuntimeException('Manifest contains an unapproved path.');
    if($expected!==null&&!preg_match('/\A[a-f0-9]{64}\z/',$expected))throw new RuntimeException('Invalid fingerprint.');
    $file=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);$checked++;
    if($expected===null){if(file_exists($file))$problems[]=['path'=>$path,'result'=>'UNEXPECTED_FILE'];continue;}
    $resolved=realpath($file);
    if(!$resolved||!is_file($resolved)){$problems[]=['path'=>$path,'result'=>'MISSING'];continue;}
    if(!str_starts_with(strtolower($resolved),strtolower($root.DIRECTORY_SEPARATOR))){$problems[]=['path'=>$path,'result'=>'LINK_OUTSIDE_ROOT'];continue;}
    $bytes=file_get_contents($resolved);
    if(!hash_equals($expected,hash('sha256',str_replace("\r\n","\n",$bytes))))$problems[]=['path'=>$path,'result'=>'CONTENT_DRIFT'];
}
echo json_encode(['phase'=>$manifest['phase']??null,'reference'=>$manifest['reference']??null,'checked'=>$checked,'mismatches'=>$problems,
    'result'=>$problems?'BLOCKED':'MATCH','writes'=>0,'database_access'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit($problems?2:0);
