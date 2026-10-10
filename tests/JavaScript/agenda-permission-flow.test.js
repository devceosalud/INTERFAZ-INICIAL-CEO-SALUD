const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const guidance = require('../../public/js/scheduling/agenda-guidance');
const form = require('../../public/js/scheduling/agenda-operational-form');
const source = fs.readFileSync(path.resolve(__dirname,'../../public/js/scheduling/agenda-operational-workspace.js'),'utf8');

function fixture(fetcher, canCreate=true) {
    const elements = new Map(), calls = [], busy = [], notices = [];
    const element = () => {
        const e = {value:'',disabled:false,hidden:false,files:[],checked:false,open:false,textContent:'',listeners:{},
            dataset:{},classList:{add(){},remove(){}},focus(){},scrollIntoView(){},replaceChildren(){},appendChild(){},append(){},setAttribute(){},
            addEventListener(name,fn){e.listeners[name]=fn;}};
        return e;
    };
    const document = {getElementById(id){if(!elements.has(id)){elements.set(id,element());}return elements.get(id);},
        querySelector(){return {content:'fictitious-csrf'};},createElement:element};
    let selected = {tipo_contexto:'slot_libre',seleccionable:true,doctor_id:1,fecha:'2026-10-10',hora_inicio:'08:00',minutos:30};
    let uuid=0;
    const context = {window:{AgendaOperationalForm:form,AgendaGuidance:guidance,setTimeout(){},
        crypto:{randomUUID:()=>`00000000-0000-4000-8000-${String(++uuid).padStart(12,'0')}`}},document,
        FormData:class {constructor(){this.entries=[];}append(key,value){this.entries.push([key,value]);}},
        fetch:async(url,options)=>{calls.push({url,options});return fetcher(url,options,calls.length);}};
    vm.runInNewContext(source,context);
    const get = id => document.getElementById('agenda-op-'+id);
    get('price').value='100.00';get('amount').value='0';get('method').value='EFECTIVO';
    const workspace=context.window.AgendaOperationalWorkspace({endpoint:'/registrations',base:'/appointments',patientTemplate:'/patients/__PATIENT__',
        canCreate,context:()=>selected,payload:()=>({patient_id:1,doctor_id:1,service_id:1}),error:b=>b.message||'error',
        busy:value=>busy.push(value),notice:message=>notices.push(message),registered(){},paymentUpdated(){}});
    return {workspace,get,calls,busy,notices,select:value=>{selected=value;}};
}
const response=(status,body)=>({ok:status<400,status,json:async()=>body});
const payload=call=>JSON.parse(call.options.body.entries.find(([name])=>name==='payload')[1]);
const registration={pending:false,patientId:1,serviceId:1,ownerId:''};

test('doble envío mientras se guarda genera una sola petición y bloquea las acciones de dinero',async()=>{
    let release;
    const f=fixture(()=>new Promise(resolve=>{release=resolve;}));
    const first=f.workspace.register(registration);
    await f.workspace.register(registration);
    assert.equal(f.calls.length,1);assert.equal(f.get('submit-payment').disabled,true);assert.equal(f.get('confirm-reservation').disabled,true);
    release(response(201,{}));await first;
    assert.deepEqual(f.busy,[true,false]);assert.equal(f.get('submit-payment').disabled,false);
});

test('rechazo 422 permite corregir con otra UUID; resultado incierto conserva la UUID',async()=>{
    const rejected=fixture((url,options,index)=>response(index===1?422:201,{message:'Importe rechazado'}));
    await rejected.workspace.register(registration);
    rejected.get('amount').value='50';await rejected.workspace.register(registration);
    assert.notEqual(payload(rejected.calls[0]).request_key,payload(rejected.calls[1]).request_key);
    const uncertain=fixture((url,options,index)=>{if(index===1){throw new Error('Network response lost');}return response(201,{});});
    await uncertain.workspace.register(registration);await uncertain.workspace.register(registration);
    assert.equal(payload(uncertain.calls[0]).request_key,payload(uncertain.calls[1]).request_key);
});

test('reserva financiada se confirma sin otro cobro aunque el operador no pueda registrar adelantos',async()=>{
    const position={precio:'100.00',pago_real:'50.00',saldo:'50.00',asegurada:true,es_exonerado:false,can_edit_notes:true};
    const f=fixture((url,options)=>{
        if(url.endsWith('/economy')){return response(200,position);}
        if(url.endsWith('/documents')){return response(200,{documents:[],can_write:false});}
        if(url.startsWith('/patients')){return response(200,{patient:{patient_id:1}});}
        return response(200,{appointment:{appointment_id:12,estado_agenda:'CONFIRMADA',tipo_agendamiento:'REGULAR'},economy:position});
    });
    f.get('amount').disabled=true;f.get('submit-payment').disabled=true;
    const selected={tipo_contexto:'cita_existente',appointment_id:12,patient_id:1,estado_agenda:'PENDIENTE_CONFIRMACION'};
    f.select(selected);await f.workspace.selectionChanged(selected);
    assert.equal(f.get('confirm-reservation').disabled,false);
    await f.get('confirm-reservation').listeners.click();
    const sent=payload(f.calls.find(c=>c.options.method==='POST'));
    assert.equal(sent.confirm,true);assert.equal(sent.payment.amount,'0.00');
    assert.equal(f.get('submit-payment').disabled,true);
});

test('reserva sin 50% o sin CREATE muestra requisitos y conserva confirmación bloqueada',async()=>{
    for(const canCreate of [true,false]){
        const f=fixture(url=>response(200,url.endsWith('/economy')?
            {precio:'100',pago_real:'49.99',saldo:'50.01',asegurada:false,can_edit_notes:true}:
            url.endsWith('/documents')?{documents:[]}:{patient:{patient_id:1}}),canCreate);
        const selected={tipo_contexto:'cita_existente',appointment_id:12,patient_id:1,estado_agenda:'PENDIENTE_CONFIRMACION'};
        f.select(selected);await f.workspace.selectionChanged(selected);
        assert.equal(f.get('confirm-reservation').disabled,true);
        assert.match(f.get('confirmation-reason').textContent,canCreate?/50%/:/appointment.create/);
    }
});
const position={precio:'100.00',pago_real:'0.00',saldo:'100.00',asegurada:false,es_exonerado:false,can_edit_notes:true};
test('registrar adelanto envía confirm false y el conflicto conserva selección e intención sin otro envío',async()=>{
    for(const conflict of [false,true]){
        const f=fixture((url,options)=>{
            if(url.endsWith('/economy'))return response(200,conflict?{...position,pago_real:'50.00',saldo:'50.00',asegurada:true}:position);
            if(url.endsWith('/documents'))return response(200,{documents:[],can_write:false});
            if(url.startsWith('/patients'))return response(200,{patient:{patient_id:1}});
            return conflict?response(409,{message:'El horario seleccionado ya no se encuentra disponible.'}):response(200,{appointment:{appointment_id:12,estado_agenda:'PENDIENTE_CONFIRMACION',tipo_agendamiento:'REGULAR'},economy:{...position,pago_real:'50.00',saldo:'50.00',asegurada:true}});
        });
        const selected={tipo_contexto:'cita_existente',appointment_id:12,patient_id:1,estado_agenda:'PENDIENTE_CONFIRMACION',tipo_agendamiento:'REGULAR'};
        f.select(selected);await f.workspace.selectionChanged(selected);f.get('amount').value='50';
        await f.get(conflict?'confirm-reservation':'submit-payment').listeners.click();
        const posts=f.calls.filter(c=>c.options.method==='POST');assert.equal(posts.length,1);
        assert.equal(payload(posts[0]).confirm,conflict);assert.equal(payload(posts[0]).payment.amount,conflict?'0.00':'50.00');
        assert.equal(selected.estado_agenda,'PENDIENTE_CONFIRMACION');assert.equal(selected.tipo_agendamiento,'REGULAR');
        if(conflict){
            assert.match(f.notices.join(' '),/horario seleccionado/);
            await f.get('confirm-reservation').listeners.click();
            const retry=f.calls.filter(c=>c.options.method==='POST');
            assert.equal(retry.length,2);assert.equal(payload(retry[0]).request_key,payload(retry[1]).request_key);
            assert.equal(payload(retry[1]).confirm,true);assert.equal(payload(retry[1]).payment.amount,'0.00');
        }
    }
});
