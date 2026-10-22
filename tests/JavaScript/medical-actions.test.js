const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const {readResponse,latest}=require('../../public/js/scheduling/admission-board.js');
class Element{
 constructor(tag='div',text=''){this.tag=tag;this.text=text;this.children=[];this.dataset={};this.listeners={};this.style={setProperty(){}};this.classList={add(){}};}
 get textContent(){return this.text+this.children.map(c=>c.textContent).join(' ');}
 set textContent(value){this.text=String(value);this.children=[];}
 appendChild(e){this.children.push(e);return e;}append(...items){items.forEach(e=>this.appendChild(e));}
 replaceChildren(...items){this.children=[];this.text='';this.append(...items);}
 addEventListener(type,fn){this.listeners[type]=fn;}setAttribute(){}
 querySelectorAll(selector){return this.children.flatMap(e=>[...(e.tag===selector?[e]:[]),...e.querySelectorAll(selector)]);}
}
const response=(body,status=200)=>({status,ok:status>=200&&status<300,headers:{get:()=> 'application/json'},json:async()=>body});
const source=fs.readFileSync('public/js/scheduling/admission-board.js','utf8');
const actionSource=source.slice(source.indexOf('        const medicalLabels='),source.indexOf('        async function confirmExoneration()'));
const renderSource=source.slice(source.indexOf("            const actions=node('div',null,'admission-care-actions');"),source.indexOf('            if(payload.exoneration_confirmation)'));
function detail(actions=['start'],pending=false){return {appointment:{id:1,estado_cita:'EN_ESPERA',reevaluation:{pending}},
 clinical:{actions,revision:'r'.repeat(64),care_url:'/admission/appointments/1/medical-care'},
 without_deposit_confirmation:{url:'/admission/appointments/1/confirm-without-deposit',revision:'r'.repeat(64)}};}
function medicalUi({confirm=async()=>({isConfirmed:true,value:'MOTIVO FICTICIO'}),save=async()=>response({}),load=async()=>{}}={}){
 const posts=[],notices=[];let uuid=0;
 const context={window:{Swal:{fire:confirm}},crypto:{randomUUID:()=> 'UUID-FICTICIO-'+(++uuid)},
  notice:text=>notices.push(text),node:(tag,text)=>new Element(tag,text),
  request:async(url,options)=>{posts.push({url,data:JSON.parse(options.body)});return readResponse(await save(url,options));},
  refresh:load,initial:detail()};
 vm.runInNewContext(`let busy=false,loading=false,confirming=false,selected=1,detail=initial;
  async function load(id){loading=true;try{await refresh(id);}finally{loading=false;}}
  ${actionSource}
  function render(payload){${renderSource}return actions;}
  this.api={action:medicalAction,confirmWithoutDeposit,render,setDetail:value=>{detail=value;selected=value.appointment.id;}};`,context);
 return {...context.api,posts,notices};
}
test('cancelled physician confirmation sends no mutation from Admission',async()=>{
 const ui=medicalUi({confirm:async()=>({isConfirmed:false})});await ui.action('start');assert.equal(ui.posts.length,0);
});
test('an uncertain physician response reuses the operation UUID and exact review',async()=>{
 let calls=0;const ui=medicalUi({save:async()=>{if(++calls===1)throw new Error('Offline');return response({});}});
 await ui.action('start');await ui.action('start');assert.equal(ui.posts.length,2);assert.deepEqual(ui.posts[0],ui.posts[1]);
});
test('double click cannot create two simultaneous clinical requests',async()=>{
 let release;const ui=medicalUi({save:()=>new Promise(resolve=>{release=resolve;})});
 const first=ui.action('start');ui.action('start');await new Promise(r=>setImmediate(r));assert.equal(ui.posts.length,1);
 release(response({}));await first;
});
test('controls cannot mutate again until successful save refresh finishes',async()=>{
 let release;const ui=medicalUi({load:()=>new Promise(resolve=>{release=resolve;})});
 const first=ui.action('start');await new Promise(r=>setImmediate(r));ui.setDetail(detail(['finish']));
 await ui.action('finish');assert.equal(ui.posts.length,1);release();await first;
});
test('medical controls display only effective backend actions in the Admission panel',()=>{
 const ui=medicalUi();const panel=ui.render(detail([]));assert.equal(panel.children.filter(x=>x.dataset.medicalAction).length,0);
 const buttons=ui.render(detail(['finish','indicate'])).children.filter(x=>x.dataset.medicalAction);
 assert.deepEqual(buttons.map(x=>x.dataset.medicalAction),['finish','indicate']);
});
test('late previous patient detail cannot replace latest Admission selection',async()=>{
 const request=latest();let release,shown=null;
 const first=request.run(()=>new Promise(resolve=>{release=resolve;}),value=>{shown=value;});
 await request.run(async()=>2,value=>{shown=value;});release(1);await first;assert.equal(shown,2);
});
test('internal server details never appear in Admission clinical errors',async()=>{
 const ui=medicalUi({save:async()=>response({message:'SQL PRIVATE PATIENT'},500)});await ui.action('start');
 assert.equal(ui.notices.length,1);assert.doesNotMatch(ui.notices[0],/SQL|PRIVATE|PATIENT/);
});
test('pending reevaluation hides finish and explains restriction until backend reports ready',()=>{
 const ui=medicalUi(),pending=ui.render(detail(['withdraw'],true));
 assert.equal(pending.children.filter(x=>x.dataset.medicalAction==='finish').length,0);assert.match(pending.textContent,/No se puede finalizar/);
 const ready=ui.render(detail(['finish','withdraw']));assert.equal(ready.children.filter(x=>x.dataset.medicalAction==='finish').length,1);
 assert.doesNotMatch(ready.textContent,/No se puede finalizar/);assert.equal(ui.posts.length,0);
});
test('canceling without-deposit confirmation creates no operation',async()=>{
 const ui=medicalUi({confirm:async()=>({isConfirmed:false})});await ui.confirmWithoutDeposit();assert.equal(ui.posts.length,0);
});
test('without-deposit retries preserve reason, review and UUID without sending payment',async()=>{
 let n=0;const ui=medicalUi({save:async()=>{if(++n===1)throw new Error('Offline');return response({});}});
 await ui.confirmWithoutDeposit();await ui.confirmWithoutDeposit();assert.deepEqual(ui.posts[0],ui.posts[1]);
 assert.equal(ui.posts[0].data.confirmed,true);assert.equal(ui.posts[0].data.reason,'MOTIVO FICTICIO');assert.equal(ui.posts[0].data.payment,undefined);
});
test('changing patient while clinical confirmation is open cannot send a stale action',async()=>{
 let release;const ui=medicalUi({confirm:()=>new Promise(resolve=>{release=resolve;})});const action=ui.action('start');
 const next=detail();next.appointment.id=2;ui.setDetail(next);release({isConfirmed:true});await action;assert.equal(ui.posts.length,0);
});
function linkageUi({confirm=()=>true,fetch=async()=>response({message:'Guardado'})}={}){
 const status=new Element(),button=new Element('button'),calls=[];
 const form=new Element('form');form.dataset={url:'/user/7/doctor-link',version:'0',current:'',currentLabel:'Sin vÃ­nculo'};form.reportValidity=()=>true;form.querySelector=()=>button;
 const doctorListeners={};
 form.elements={doctor_id:{value:'',selectedOptions:[{textContent:'MEDICO FICTICIO'}],
   isConnected:true,addEventListener:(type,fn)=>{doctorListeners[type]=fn;},
   closest:()=>null,classList:{remove(){}}},reason:{value:'VerificaciÃ³n FICTICIA'},identity_confirmed:{checked:true}};
 form.querySelectorAll=()=>[];form.contains=()=>false;
 let uuid=0;const root={document:{querySelector:s=>s==='#user-doctor-link'?form:s==='#user-doctor-link-status'?status:s==='meta[name=csrf-token]'?{content:'FICTICIO-CSRF'}:null,getElementById:()=>status},
  confirm,crypto:{randomUUID:()=> 'UUID-FICTICIO-'+(++uuid)},location:{reload(){}},
  addEventListener(){},fetch:async(url,options)=>{calls.push(JSON.parse(options.body));return fetch(url,options);}};
 vm.runInNewContext(fs.readFileSync('public/js/admin/master/user/doctor-link.js','utf8'),{window:root,globalThis:root});
 // Simulate an explicit physician selection by the administrator after the form loads.
 form.elements.doctor_id.value='3';doctorListeners.change();
  return {form,status,calls,submit:()=>form.listeners.submit({preventDefault(){}})};
}
test('cancelled administrator identity review does not persist a link',async()=>{
 const ui=linkageUi({confirm:()=>false});await ui.submit();assert.equal(ui.calls.length,0);
});
test('link retries retain the UUID and never leak server diagnostics',async()=>{
 let n=0;const ui=linkageUi({fetch:async()=>++n===1?response({message:'SQL SECRET PATIENT'},500):response({message:'Guardado'})});
 await ui.submit();assert.doesNotMatch(ui.status.textContent,/SQL|SECRET|PATIENT/);await ui.submit();
 assert.equal(ui.calls.length,2);assert.deepEqual(ui.calls[0],ui.calls[1]);assert.equal(ui.calls[0].doctor_id,3);assert.equal(ui.calls[0].expected_doctor_id,null);
});
