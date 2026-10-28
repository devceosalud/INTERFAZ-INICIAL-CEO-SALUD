<?php
namespace App\Services\WaitingRoom;

use App\Models\{Appointment,Site};
use App\Services\Scheduling\AppointmentReevaluationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache,DB,Crypt};
use Illuminate\Support\Str;

final class WaitingRoomSnapshot
{
    private string $key;
    private array $installation;
    private string $day;
    private function ref(string $kind, $value): string
    {
        return $kind.'_'.hash_hmac('sha256',implode('|',[$kind,$this->installation['screen_ref'],$this->installation['site_id'],$value]),$this->key);
    }
    public function make(array $installation,array $configuration):array {
        $key='wr-projection:'.hash('sha256',($installation['screen_ref']??'').':'.($installation['site_id']??''));$lock=Cache::lock($key,5);
        if(!$lock->get())throw new IntegrationFailure('projection_busy');
        try {if(config('database.default')==='sqlite'&&app()->environment(['integration','testing']))DB::connection()->getPdo()->exec('PRAGMA busy_timeout=750');
            return $this->build($installation,$configuration);
        }finally{$lock->release();}
    }
    private function build(array $installation, array $configuration): array
    {
        $this->key = (string)config('waiting_room.reference_key');
        abort_unless(strlen($this->key)>=32 && !empty($installation['site_id']) && InstallationConfiguration::label($installation['screen_ref']??null,100),503);
        $policy = app(InstallationConfiguration::class)->privacy($configuration,$installation);
        $this->installation=$installation;
        $this->day=now('America/Lima')->toDateString();
        $now=CarbonImmutable::now('UTC');
        $site=Site::whereKey($installation['site_id'])->where('estado','ACTIVO')->first(['id','nombre']);
        abort_unless($site && InstallationConfiguration::label($site->nombre),503);
        $assignments=collect($configuration['assignments']??[])->filter(fn($row)=>
            ($row['site_id']??null)===$site->id && ($row['day']??null)===$this->day && ($row['verified']??false)===true
            && InstallationConfiguration::label($row['room_ref']??null,100) && preg_match('/^[0-9]{1,3}$/D',(string)($row['room_label']??''))
            && InstallationConfiguration::label($row['professional_label']??null,120) && InstallationConfiguration::label($row['professional_speech_label']??null,120));
        if($assignments->isEmpty())throw new IntegrationFailure('jornada_not_configured');
        // Ambiguous physical assignments fail closed instead of inventing a destination.
        abort_if($assignments->duplicates('doctor_id')->isNotEmpty() || $assignments->duplicates('room_ref')->isNotEmpty() || $assignments->map(fn($r)=>(int)$r['room_label'])->duplicates()->isNotEmpty(),503);
        // Pin verified destinations for this screen/jornada. Editing an active day's mapping
        // must fail closed instead of silently redirecting an already audited call.
        $assignmentKey='wr-assignments:'.$this->ref('screen',$installation['screen_ref']).':'.$this->day;
        $assignmentHash=hash('sha256',json_encode($assignments->map(fn($r)=>array_diff_key($r,['operational'=>true]))->values()->all(),JSON_THROW_ON_ERROR));
        Cache::add($assignmentKey,$assignmentHash,172800);
        abort_unless(hash_equals((string)Cache::get($assignmentKey),$assignmentHash),503);
        $assignments=$assignments->filter(fn($r)=>($r['operational']??true)===true)->values();
        $activeDoctors=\App\Models\Doctor::whereIn('id',$assignments->pluck('doctor_id'))->where('estado','ACTIVO')->pluck('id')->map(fn($id)=>(int)$id)->all();
        if($assignments->contains(fn($r)=>!in_array((int)$r['doctor_id'],$activeDoctors,true)))throw new IntegrationFailure('professional_unavailable');
        $watermark=DB::table('appointments as a')->join('patients as p','p.id','=','a.patient_id')->join('doctors as d','d.id','=','a.doctor_id')
            ->where('a.site_id',$site->id)->where('a.fecha_cita',$this->day)->where('a.attendance_mode','PRESENCIAL')->whereIn('a.doctor_id',$assignments->pluck('doctor_id'))
            ->whereIn('a.estado_agenda',['CONFIRMADA','LEGADO'])->whereIn('a.estado_cita',['EN_ESPERA','LLAMANDO','EN_ATENCION'])
            ->when(($policy['mode']??null)==='fixtures-only',fn($q)=>$q->whereIn('a.patient_id',$policy['fixture_patient_ids']??[]))
            ->select(['a.id','a.patient_id','a.doctor_id','a.estado_cita','a.hora_llegada','a.hora_atencion','a.hora_atendido','a.hora_salida','a.updated_at','p.nombre','p.apellido_paterno','p.apellido_materno','p.estado as patient_state','d.estado as doctor_state'])
            ->selectRaw('(SELECT MAX(e.id) FROM appointment_events e WHERE e.appointment_id=a.id) as event_watermark')->orderBy('a.id')->limit(config('waiting_room.max_appointments')+1)->get();
        if($watermark->count()>config('waiting_room.max_appointments'))throw new IntegrationFailure('capacity_exceeded');
        $fingerprint=hash('sha256',json_encode([$this->day,$site->nombre,$policy,$assignments->all(),$watermark],JSON_THROW_ON_ERROR));
        $payloadKey='wr-payload:'.$this->ref('screen',$installation['screen_ref']);$cached=Cache::get($payloadKey);
        if(is_array($cached)&&hash_equals($cached['fingerprint'],$fingerprint)){
            try{$body=json_decode(Crypt::decryptString($cached['body']),true,32,JSON_THROW_ON_ERROR);app('request')->attributes->set('wr_projection_hit',true);return $this->stamp($body);}
            catch(\Throwable $e){Cache::forget($payloadKey);}
        }
        return DB::transaction(function() use($site,$assignments,$policy,$now,$fingerprint,$payloadKey) {
            $appointments=Appointment::query()->select(['id','patient_id','doctor_id','site_id','fecha_cita','attendance_mode','estado_agenda','estado_cita','hora_llegada','hora_llamado','hora_atencion','hora_atendido','hora_salida'])
                ->where('site_id',$site->id)->where('fecha_cita',$this->day)->where('attendance_mode','PRESENCIAL')
                ->whereIn('estado_agenda',['CONFIRMADA','LEGADO'])->whereIn('estado_cita',['EN_ESPERA','LLAMANDO','EN_ATENCION'])
                ->whereNull('hora_atendido')->whereNull('hora_salida')->whereNotNull('hora_llegada')
                ->whereIn('doctor_id',$assignments->pluck('doctor_id'))
                ->with(['patient'=>fn($q)=>$q->select(['id','nombre','apellido_paterno','apellido_materno','estado']),
                    'doctor'=>fn($q)=>$q->select(['id','nombre','estado']),
                    'events'=>fn($q)=>$q->select(['id','appointment_id','event_type','metadata','occurred_at'])->whereIn('event_type',['ADMISION_ESTADO_ACTUALIZADO','ADMISION_ESTADO_REVERTIDO',AppointmentReevaluationService::MEDICAL_INDICATED,AppointmentReevaluationService::MEDICAL_WITHDRAWN,AppointmentReevaluationService::OPERATIONAL_CONFIRMED,AppointmentReevaluationService::MEDICAL_RESUMED])->orderByDesc('id')])
                ->orderBy('hora_llegada')->orderBy('id')->limit(config('waiting_room.max_appointments')+1)->get();
            abort_if($appointments->count()>config('waiting_room.max_appointments'),503);
            $rows=[];
            foreach($appointments as $a){
                if(!$a->patient || $a->patient->estado!=='ACTIVO' || !$a->doctor || $a->doctor->estado!=='ACTIVO')continue;
                if(($policy['mode']??null)==='fixtures-only' && !in_array((int)$a->patient_id,array_map('intval',$policy['fixture_patient_ids']??[]),true))continue;
                $arrival=CarbonImmutable::parse($a->hora_llegada,config('app.timezone'));
                if($arrival->setTimezone('America/Lima')->toDateString()!==$this->day || $arrival->greaterThan($now->addSecond()))continue;
                $arrivalEvent=$a->events->first(fn($e)=>$e->event_type==='ADMISION_ESTADO_ACTUALIZADO' && ($e->metadata['to']??null)==='EN_ESPERA' && ($e->metadata['timestamp_field']??null)==='hora_llegada');
                if(!$arrivalEvent || !$arrivalEvent->occurred_at || $arrivalEvent->occurred_at->copy()->setTimezone('America/Lima')->toDateString()!==$this->day)continue;
                foreach(['medicalReevaluationEvent'=>[AppointmentReevaluationService::MEDICAL_INDICATED,AppointmentReevaluationService::MEDICAL_WITHDRAWN],
                    'operationalConfirmationEvent'=>[AppointmentReevaluationService::OPERATIONAL_CONFIRMED],
                    'medicalReturnEvent'=>[AppointmentReevaluationService::MEDICAL_RESUMED]] as $relation=>$types){
                    $a->setRelation($relation,$a->events->first(fn($e)=>in_array($e->event_type,$types,true)));
                }
                if($a->estado_cita==='EN_ATENCION' && AppointmentReevaluationService::indicated($a) && !AppointmentReevaluationService::resumed($a))continue;
                $latest=$a->events->first(fn($e)=>in_array($e->event_type,['ADMISION_ESTADO_ACTUALIZADO','ADMISION_ESTADO_REVERTIDO'],true));
                if($a->estado_cita==='EN_ATENCION'){
                    $start=$a->events->first(fn($e)=>$e->event_type==='ADMISION_ESTADO_ACTUALIZADO'
                        && ($e->metadata['to']??null)==='EN_ATENCION' && ($e->metadata['timestamp_field']??null)==='hora_atencion');
                    if(!$a->hora_atencion||!$start||!$start->occurred_at||$start->occurred_at->copy()->setTimezone('America/Lima')->toDateString()!==$this->day)continue;
                }
                $call=$a->estado_cita==='LLAMANDO' && $latest && $latest->event_type==='ADMISION_ESTADO_ACTUALIZADO' && ($latest->metadata['to']??null)==='LLAMANDO' && $latest->occurred_at ? $latest : null;
                if($a->estado_cita==='LLAMANDO' && (!$call || $call->occurred_at->greaterThan($now->addSecond()) || $call->occurred_at->copy()->setTimezone('America/Lima')->toDateString()!==$this->day))continue;
                $given=trim($a->patient->nombre);$paternal=trim($a->patient->apellido_paterno);$maternal=trim($a->patient->apellido_materno);
                if(!InstallationConfiguration::label($given,80)||!InstallationConfiguration::label($paternal,80)||!InstallationConfiguration::label($maternal,80))continue;
                $display=mb_substr($given,0,1).'. '.$paternal.' '.$maternal;
                $rows[]=['appointment'=>$a,'call'=>$call,'display'=>$display,'parts'=>[mb_substr($given,0,1).'.',$paternal,$maternal],
                    'speech'=>($policy['speech_mode']??null)==='full_given_names'?$given.' '.$paternal.' '.$maternal:null];
            }
            $labelKey=fn($label)=>mb_strtolower(preg_replace('/\s+/u',' ',trim($label)));
            $labels=array_count_values(array_map(fn($r)=>$labelKey($r['display']),$rows));
            $patientIds=array_count_values(array_map(fn($r)=>$r['appointment']->patient_id,$rows));
            $rooms=[];$calls=[];$callOrder=[];
            foreach($assignments as $assignment){
                $roomRef=$this->ref('room',$assignment['room_ref']);$patients=[];
                foreach($rows as $row){
                    $a=$row['appointment'];
                    // No unsafe disambiguator is fabricated for homonyms or concurrent duplicates.
                    if($labels[$labelKey($row['display'])]>1 || $patientIds[$a->patient_id]>1 || (int)$a->doctor_id!==(int)$assignment['doctor_id'])continue;
                    $subject=$this->ref('subject',$a->id);
                    $state=['EN_ESPERA'=>'waiting','LLAMANDO'=>'calling','EN_ATENCION'=>'attention'][$a->estado_cita];
                    $patients[]=['subject_ref'=>$subject,'display_label'=>$row['display'],'display_parts'=>$row['parts'],'state'=>$state];
                    if($row['call']){
                        $event=$row['call'];$callOrder[$this->ref('event',$event->id)]=$event->id;
                        $calls[]=['event_ref'=>$this->ref('event',$event->id),'subject_ref'=>$subject,'room_ref'=>$roomRef,
                            'display_label'=>$row['display'],'display_parts'=>$row['parts'],'speech_label'=>$row['speech'],
                            'speech_authorized'=>$row['speech']!==null,'room_label'=>$assignment['room_label'],
                            'professional_label'=>$assignment['professional_label'],'professional_speech_label'=>$assignment['professional_speech_label'],
                            'occurred_at'=>$event->occurred_at->copy()->setTimezone('UTC')->toIso8601String(),
                            'valid_until'=>$event->occurred_at->copy()->addSeconds(120)->setTimezone('UTC')->toIso8601String()];
                    }
                }
                $rooms[]=['room_ref'=>$roomRef,'room_label'=>$assignment['room_label'],
                    'professional_ref'=>$this->ref('professional',$assignment['doctor_id']),'professional_label'=>$assignment['professional_label'],'patients'=>$patients];
            }
            usort($calls,fn($a,$b)=>strcmp($a['occurred_at'],$b['occurred_at'])?:$callOrder[$a['event_ref']]<=>$callOrder[$b['event_ref']]);
            $body=['schema'=>'ceo.waiting-room.v2','screen_ref'=>$this->ref('screen',$this->installation['screen_ref']),
                'site'=>['ref'=>$this->ref('site',$site->id),'label'=>$site->nombre],
                'jornada'=>['day'=>$this->day,'timezone'=>'America/Lima'],
                'generated_at'=>$now->toIso8601String(),'valid_until'=>$now->addSeconds(config('waiting_room.lease_seconds'))->toIso8601String(),
                'freshness'=>'fresh','operating_count'=>$assignments->count(),'rooms'=>$rooms,'calls'=>$calls];
            $body=$this->stamp($body);Cache::put($payloadKey,['fingerprint'=>$fingerprint,'body'=>Crypt::encryptString(json_encode($body,JSON_THROW_ON_ERROR))],60);return $body;
        });
    }
    private function stamp(array $body):array {
        // Filter every lease, including cache hits. Never mutate the appointment.
        $now=CarbonImmutable::now('UTC');$expired=[];
        $body['calls']=array_values(array_filter($body['calls'],function($call)use($now,&$expired){
            if(CarbonImmutable::parse($call['valid_until'])->lessThanOrEqualTo($now)){$expired[$call['subject_ref']]=true;return false;}
            return true;
        }));
        foreach($body['rooms'] as &$room)$room['patients']=array_values(array_filter($room['patients'],fn($p)=>$p['state']!=='calling'||!isset($expired[$p['subject_ref']])));
        unset($room);
        $body['revision']=hash('sha256',json_encode([$body['screen_ref'],$body['site'],$body['jornada'],$body['operating_count'],$body['rooms'],$body['calls']],JSON_THROW_ON_ERROR));
        $key='wr-stream:'.$body['screen_ref'];$stream=Cache::get($key,['epoch'=>(string)Str::uuid(),'sequence'=>0,'revision'=>null]);
        if(($stream['revision']??null)!==$body['revision']){$stream['sequence']++;$stream['revision']=$body['revision'];Cache::put($key,$stream,86400);}
        $body['sequence']=$stream['sequence'];$body['stream_ref']=$this->ref('stream',$stream['epoch']);
        $now=CarbonImmutable::now('UTC');$body['generated_at']=$now->toIso8601String();$body['valid_until']=$now->addSeconds(config('waiting_room.lease_seconds'))->toIso8601String();return $body;
    }

}
