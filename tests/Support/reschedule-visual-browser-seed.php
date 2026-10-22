<?php
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only.');
require dirname(__DIR__,2).'/vendor/autoload.php';
$dir=dirname(__DIR__,2).'/storage/app/qa-reschedule-visual';
if(is_file($dir.'/browser.sqlite'))throw new RuntimeException('Existing QA database preserved. Use a separate clean checkout for a fresh cohort.');
if(!is_dir($dir))mkdir($dir,0700,true);
touch($dir.'/browser.sqlite');$app=\Tests\Support\RescheduleVisualQa::boot();
\Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
$fixture=new class {use \Tests\Concerns\BuildsRescheduleVisualData;public function build(){return $this->rescheduleVisualFixture();}};
$data=$fixture->build();$password=bin2hex(random_bytes(24));
$data['actor']->forceFill(['name'=>'ADMISION FICTICIA QA','email'=>'reschedule-qa@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
file_put_contents($dir.'/browser-secret.json',json_encode(['email'=>$data['actor']->email,'password'=>$password]));
file_put_contents($dir.'/browser-fixture.json',json_encode(['appointment_id'=>$data['a']->id,'doctor_id'=>$data['a']->doctor_id,'patient_id'=>$data['a']->patient_id,
    'service_id'=>$data['a']->service_id,'actor_id'=>$data['actor']->id,'date'=>'2026-10-21'],JSON_PRETTY_PRINT));
echo "Local fictional browser cohort created. Credentials stay in ignored storage only.\n";
