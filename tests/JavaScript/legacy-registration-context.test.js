const {test}=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
function fixture(){
 const fields=new Map(),requests=[];
 const field=id=>{if(!fields.has(id))fields.set(id,{value:'',checked:false,tagName:'INPUT',children:[],replaceChildren(){this.children=[];},appendChild(n){this.children.push(n);},setAttribute(k,v){this[k]=v;},after(n){fields.set(n.id,n);}});return fields.get(id);};
 field('patient_id').value='1';field('service_id').value='2';field('additional_rate_id').value='1';field('fecha_cita').value='2026-10-14';
 const modal={querySelector:s=>field(s.slice(1))};
 const document={querySelector:s=>s.startsWith('meta')?{content:'test-csrf'}:field(s.split('#').pop()),getElementById:id=>id==='appointmentModalCreate'?modal:fields.get(id),createElement:tag=>({tagName:tag.toUpperCase(),children:[],replaceChildren(){this.children=[];},appendChild(n){this.children.push(n);}})};
 const $=()=>({on(){},val(){return '';}});$.fn={};
 const sandbox={window:{addEventListener(){},location:{origin:'http://localhost'}},document,$,console:{log(){},error(){}},fetch:(url,options)=>new Promise(resolve=>requests.push({url,options,resolve}))};
 vm.createContext(sandbox);vm.runInContext(fs.readFileSync('public/js/admissionist/appointment/appointment.js','utf8'),sandbox);
 const reply=(request,body)=>request.resolve({ok:true,status:200,json:async()=>body});
 return {sandbox,requests,field,reply};
}
test('legacy patient switching invalidates the old quote and ignores a late patient identity',async()=>{
 const q=fixture();q.field('documento_paciente').value='70000001';const first=q.sandbox.buscarPacienteCita({target:{value:'70000001'}});
 assert.equal(q.field('patient_id').value,'');assert.equal(q.field('precio_programado').value,'');
 q.field('documento_paciente').value='70000002';const second=q.sandbox.buscarPacienteCita({target:{value:'70000002'}});
 q.reply(q.requests[1],{message:'encontrado',patient:{id:22,nombre:'QA B',apellido_paterno:'FICTICIO',apellido_materno:'LOCAL'}});await second;
 assert.equal(q.field('patient_id').value,22);assert.equal(JSON.parse(q.requests[2].options.body).patient_id,22);
 q.reply(q.requests[0],{message:'encontrado',patient:{id:11,nombre:'QA A',apellido_paterno:'FICTICIO',apellido_materno:'LOCAL'}});await first;
 assert.equal(q.field('patient_id').value,22);assert.match(q.field('nombre_paciente').value,/QA B/);
 q.reply(q.requests[2],{precio_programado:'120.00'});await new Promise(setImmediate);assert.equal(q.field('precio_programado').value,'120.00');
 q.field('documento_paciente').value='';await q.sandbox.buscarPacienteCita({target:{value:''}});assert.equal(q.field('patient_id').value,'');assert.equal(q.field('precio_programado').value,'');
});
test('latest legacy quote wins after date, service and rate changes',async()=>{
 const q=fixture();const first=q.sandbox.calcularPrecioCita();q.field('service_id').value='3';q.field('additional_rate_id').value='2';q.field('fecha_cita').value='2026-10-30';
 const second=q.sandbox.calcularPrecioCita();q.reply(q.requests[1],{precio_programado:'250.00'});await second;
 q.reply(q.requests[0],{precio_programado:'80.00'});await first;assert.equal(q.field('precio_programado').value,'250.00');
 assert.deepEqual(JSON.parse(q.requests[1].options.body),{patient_id:'1',service_id:'3',additional_rate_id:'2',fecha_cita:'2026-10-30',es_exonerado:false});
});
test('legacy free-text hour reuses server availability through a datalist and discards a stale hour',()=>{
 const q=fixture();q.field('hora_cita').value='08:00';q.sandbox.generarHorariosCita([{inicio:'09:00',fin:'10:00'}],true);
 assert.equal(q.field('hora_cita').value,'');assert.equal(q.field('hora_cita').list,'legacy-create-available-hours');
 assert.equal(q.field('legacy-create-available-hours').children[0].textContent,'09:00 - 10:00');
 q.field('hora_cita').value='09:00';q.sandbox.generarHorariosCita([{inicio:'09:00',fin:'10:00'}],true);assert.equal(q.field('hora_cita').value,'09:00');
});
