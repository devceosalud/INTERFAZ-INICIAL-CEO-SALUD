<?php
// Owned local runtime only. No environment-provided connection or production fallback.
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\IsolatedRcQa::boot();
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only.');
$action=$argv[1]??'';
if($action==='migrate'){
    \Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
    echo \Illuminate\Support\Facades\Artisan::output();
}elseif($action==='snapshot'){
    $rows=[];
    foreach(\Illuminate\Support\Facades\DB::select('SHOW TABLES') as $row){
        $table=array_values((array)$row)[0];
        if(!preg_match('/\A[a-z0-9_]+\z/',$table))throw new RuntimeException('Unexpected table.');
        $data=\Illuminate\Support\Facades\DB::table($table)->get()->map(fn($r)=>(array)$r)->all();
        $canonical=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$data);sort($canonical);
        $rows[$table]=['count'=>count($data),'sha256'=>hash('sha256',implode("\n",$canonical))];
    }
    echo json_encode(['tables'=>$rows,'database'=>'ceosalud_qa_rc','port'=>33322,'version'=>\Illuminate\Support\Facades\DB::selectOne('SELECT VERSION() version')->version],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
}else throw new RuntimeException('Unknown read/safe-migration action.');
