const {test}=require('node:test');
const assert=require('node:assert/strict');
const {latest,bookingLabel,time,readResponse}=require('../../public/js/scheduling/admission-board.js');
const {journeySteps,exonerationConfirmationFailure}=require('../../public/js/scheduling/admission-board.js');
const {admissionBackground}=require('../../public/js/scheduling/admission-board.js');
test('active remote care uses the approved admission turquoise without manufacturing physical presence',()=>{
    const presentation={fringe:{key:'SIN_PAGO_REAL',color:'#ffffff'},present:false,badges:[{clave:'TELECONSULTA'}]};
    const appointment={attendance_mode:'TELECONSULTA',estado_cita:'EN_ATENCION',hora_llegada:null};
    const before=JSON.stringify({presentation,appointment});assert.equal(admissionBackground(presentation,appointment),'#74dccb');
    assert.equal(JSON.stringify({presentation,appointment}),before);
});
test('remote turquoise preserves higher red orange and departed presentation priorities',()=>{
    const remote={attendance_mode:'TELECONSULTA',estado_cita:'EN_ATENCION'};
    for(const [key,color]of [['DESACTIVADA','#fecaca'],['REEMBOLSO_SOLICITADO','#fecaca'],['LISTO_REEVALUACION','#fed7aa'],['SALIDA_REGISTRADA','#99f6e4']])
        assert.equal(admissionBackground({fringe:{key,color}},remote),color==='#99f6e4'?'#74dccb':color);
    assert.equal(admissionBackground({fringe:{key:'SIN_PAGO_REAL',color:'#ffffff'},operational_reevaluation_ready:true},remote),'#ffffff');
});
test('remote background override does not recolor bookings finished care or onsite appointments',()=>{
    const p={fringe:{key:'PROGRAMADO',color:'#fdf1d9'}};
    for(const state of ['PROGRAMADO','CONFIRMADO','ATENDIDO','CANCELADO'])assert.equal(admissionBackground(p,{attendance_mode:'TELECONSULTA',estado_cita:state}),'#fdf1d9');
    assert.equal(admissionBackground(p,{attendance_mode:'PRESENCIAL',estado_cita:'EN_ATENCION'}),'#fdf1d9');
});
test('remote care timeline has clinical start and finish without suggesting physical steps',()=>{
    const steps=journeySteps('TELECONSULTA');assert.deepEqual(Object.keys(steps),['inicio','fin']);
    for(const key of ['llegada','llamado','salida'])assert.equal(steps[key],undefined);
});
test('onsite and historical default timelines keep their physical arrival and departure steps',()=>{
    for(const mode of ['PRESENCIAL',null,undefined])assert.deepEqual(Object.keys(journeySteps(mode)),['llegada','llamado','inicio','fin','salida']);
});
test('pending booking is never labelled confirmed because payment or care changed',()=>{
    assert.match(bookingLabel('PENDIENTE_CONFIRMACION'),/sin confirmar/);
    assert.equal(bookingLabel('CONFIRMADA'),'Horario confirmado');
    assert.equal(bookingLabel('LEGADO'),'Agenda heredada');
    assert.notEqual(bookingLabel('CONFIRMADO'),'Horario confirmado');
});
test('missing or invalid timestamps never manufacture a care time',()=>{
    assert.equal(time(null),'Sin registro');assert.equal(time('invalid'),'Sin registro');
    assert.equal(time('2026-10-14T15:35:00Z'),'10:35');
});
test('late patient selection and filter responses cannot overwrite latest panel',async()=>{
    const gate=latest(),commits=[];let resolveOld;
    const old=gate.run(()=>new Promise(resolve=>{resolveOld=resolve;}),value=>commits.push(value));
    await gate.run(async()=>({patient:'CURRENT',saldo:'50.00'}),value=>commits.push(value));
    resolveOld({patient:'PREVIOUS',saldo:'0.00'});assert.equal(await old,false);
    assert.deepEqual(commits,[{patient:'CURRENT',saldo:'50.00'}]);
});
test('clearing detail prevents a late private appointment response from reappearing',async()=>{
    const gate=latest();let complete;let painted=false;
    const pending=gate.run(()=>new Promise(resolve=>{complete=resolve;}),()=>{painted=true;});
    gate.cancel();complete('old');assert.equal(await pending,false);assert.equal(painted,false);
});

const response=(status,body,type='application/json',redirected=false)=>({status,ok:status>=200&&status<300,redirected,headers:{get:()=>type},json:async()=>body});
test('session HTML and redirects use a generic message without rendering server content',async()=>{
    await assert.rejects(readResponse(response(200,'PRIVATE HTML','text/html')),/Inicia sesión/);
    await assert.rejects(readResponse(response(200,{},'application/json',true)),/Inicia sesión/);
});
test('server errors never expose SQL or private response details',async()=>{
    await assert.rejects(readResponse(response(500,{message:'SQL PRIVATE PATIENT',trace:'SECRET'})),error=>!error.message.includes('PRIVATE')&&!error.message.includes('SQL'));
    await assert.rejects(readResponse(response(404,{message:'PRIVATE APPOINTMENT'})),{message:'La cita no está disponible.'});
});
test('business validation and permission failures remain actionable',async()=>{
    await assert.rejects(readResponse(response(422,{errors:{state:['La cita cambió.']}})),{message:'La cita cambió.'});
    await assert.rejects(readResponse(response(403,{message:'PRIVATE'})),/No tienes permiso/);
});
test('a malformed JSON success cannot leak the response fragment',async()=>{
    const broken=response(200,{});broken.json=async()=>{throw new Error('PRIVATE HTML FRAGMENT');};
    await assert.rejects(readResponse(broken),error=>!error.message.includes('PRIVATE'));
});

test('exoneration slot conflict offers authorized, nonautomatic resolutions and keeps reservation pending',()=>{
    const error='El horario seleccionado ya no se encuentra disponible. Actualiza la agenda y selecciona otro horario.';
    const message=exonerationConfirmationFailure(error);
    assert.match(message,/reserva exonerada sigue pendiente/);
    assert.match(message,/reprogramar a un horario libre/);
    assert.match(message,/Resolver como ADICIONAL/);
    assert.match(message,/usuario con permisos/);
    assert.match(message,/requiere confirmarla después/);
    assert.match(message,/no se registraron pagos/);
});

test('exoneration failures unrelated to slot occupancy preserve their original message',()=>{
    for(const message of ['La cita cambió. Revisa sus datos antes de registrar el pago.','No tienes permiso para esta operación.','Error en la validación económica.'])
        assert.equal(exonerationConfirmationFailure(message),message);
});
