<?php
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only');
require dirname(__DIR__,2).'/vendor/autoload.php';
$dir=dirname(__DIR__,2).'/storage/app/qa-reschedule-visual';
$existing=file_exists($dir.'/browser.sqlite');
if($existing && (($argv[1]??'')!=='--append-recovery' || !file_exists($dir.'/recovery-extra-fixture.json')))throw new RuntimeException('Existing QA preserved. Explicit owned recovery cohort required.');
if(file_exists($dir.'/colors-fixture.json'))throw new RuntimeException('Existing colors preserved.');
if(!is_dir($dir))mkdir($dir,0700,true);if(!$existing)touch($dir.'/browser.sqlite');
\Tests\Support\RescheduleVisualQa::boot();if(!$existing)\Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
$helper=new class{use \Tests\Concerns\BuildsAdmissionColorData;public function build(){return $this->admissionColorFixture();}};
$f=\Illuminate\Support\Facades\DB::transaction(fn()=>$helper->build());$password=bin2hex(random_bytes(24));$accounts=[];
foreach(['admin','commercial','actor','medical'] as $role){$u=$f[$role];$email='colors-'.$role.'@example.invalid';
    $u->forceFill(['name'=>'QA FICTICIO '.strtoupper($role),'email'=>$email,'password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();$accounts[$role]=$email;}
file_put_contents($dir.'/colors-secret.json',json_encode(['password'=>$password,'accounts'=>$accounts]));
file_put_contents($dir.'/colors-fixture.json',json_encode(['doctor_id'=>$f['catalog']['doctor']->id,'cases'=>$f['cases'],'date'=>'2026-10-21'],JSON_PRETTY_PRINT));
echo "Thirteen fictional visual cases, four isolated profiles; no production access.\n";
