const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const {readResponse}=require('../../public/js/scheduling/admission-board.js');
class Element{
 constructor(tag='div',text=''){this.tag=tag;this.text=text;this.children=[];this.dataset={};this.listeners={};this.style={setProperty(){}};this.classList={add(){}};}
 get textContent(){return this.text+this.children.map(c=>c.textContent).join(' ');}
 set textContent(value){this.text=String(value);this.children=[];}
 appendChild(e){this.children.push(e);return e;}append(...items){items.forEach(e=>this.appendChild(e));}
 replaceChildren(...items){this.children=[];this.text='';this.append(...items);}
 addEventListener(type,fn){this.listeners[type]=fn;}setAttribute(){}
 querySelectorAll(selector){const tags=selector.split(',');return this.children.flatMap(e=>[...(tags.includes(e.tag)||(selector==='input:checked'&&e.tag==='input'&&e.checked)?[e]:[]),...e.querySelectorAll(selector)]);}
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
async function settle(){for(let i=0;i<6;i++)await tick();}
const response=(body,status=200)=>({status,ok:status>=200&&status<300,headers:{get:()=> 'application/json'},json:async()=>body});
function detail(id=1,state='EN_ESPERA',actions=['start']){
 return {appointment:{id,patient:'PACIENTE FICTICIO '+id,fecha:'2026-10-21',hora:'12:20',service:'Consulta',estado_cita:state,reevaluation:{pending:false,ready:false},presentation:{badges:[]}},
 revision:'r'.repeat(64),actions,procedures:{completed_count:0,pending_count:0,requested_count:0,items:[],can_select:false},events:[]};
}
function medicalUi({confirm=()=>true,care=async()=>response(detail(1,'EN_ATENCION',['finish','indicate'])),details=async id=>response(detail(id)),rows=[1],feed=null}={}){
 const ids=Object.fromEntries(['filters','notice','rows','page','prev','next','detail','back'].map(id=>['medical-'+id,new Element()]));
 const board=new Element();board.dataset={feed:'/medical/feed',base:'/medical/appointments'};
 const doc={querySelector:s=>s==='#medical-board'?board:s==='meta[name=csrf-token]'?{content:'FICTICIO-CSRF'}:null,
  getElementById:id=>ids[id],createElement:tag=>new Element(tag)};
 const posts=[];let key=0;
 const root={document:doc,confirm,prompt:()=> 'Indicación FICTICIA',AdmissionBoard:{readResponse},crypto:{randomUUID:()=> 'UUID-FICTICIO-'+(++key)},
  FormData:class{*[Symbol.iterator](){yield ['fecha','2026-10-21'];}},
  fetch:async(url,options)=>{
   if(url==='/medical/feed'&&feed)return feed();
   if(url==='/medical/feed')return response({rows:rows.map(id=>({...detail(id).appointment,presentation:{fondo:'#eef',badges:[]}})),page:1,last_page:1,total:rows.length});
   if(url.includes('/care/')){posts.push({url,data:JSON.parse(options.body)});return care(url,options);}
   return details(Number(url.split('/').pop()));
  }};
 vm.runInNewContext(fs.readFileSync('public/js/scheduling/medical-board.js','utf8'),{window:root,globalThis:root});
 const clickRow=async index=>{ids['medical-rows'].children[index].listeners.click();await settle();};
 const action=key=>ids['medical-detail'].querySelectorAll('button').find(b=>b.dataset.medicalAction===key);
 return {root,ids,posts,clickRow,action};
}
test('cancelled physician confirmation sends no mutation',async()=>{
 const ui=medicalUi({confirm:()=>false});await settle();await ui.clickRow(0);ui.action('start').listeners.click();await settle();assert.equal(ui.posts.length,0);
});
test('an uncertain physician response reuses the operation UUID and exact review',async()=>{
 let calls=0;const ui=medicalUi({care:async()=>{if(++calls===1)throw new Error('Offline');return response(detail(1,'EN_ATENCION',['finish','indicate']));}});
 await settle();await ui.clickRow(0);ui.action('start').listeners.click();await settle();ui.action('start').listeners.click();await settle();
 assert.equal(ui.posts.length,2);assert.deepEqual(ui.posts[0].data,ui.posts[1].data);assert.equal(ui.posts[0].data.expected_state,'EN_ESPERA');
});
test('double click cannot create two simultaneous clinical requests',async()=>{
 let release;const ui=medicalUi({care:()=>new Promise(resolve=>{release=resolve;})});
 await settle();await ui.clickRow(0);const button=ui.action('start');button.listeners.click();button.listeners.click();assert.equal(ui.posts.length,1);
 release(response(detail(1,'EN_ATENCION',['finish'])));await settle();assert.ok(ui.action('finish'));
});
test('newly rendered controls stay disabled until the successful save refresh finishes',async()=>{
 let feeds=0,release,confirms=0;
 const payload={rows:[{...detail().appointment,presentation:{fondo:'#eef',badges:[]}}],page:1,last_page:1,total:1};
 const ui=medicalUi({confirm:()=>{confirms++;return true;},feed:()=>++feeds===1?Promise.resolve(response(payload)):new Promise(resolve=>{release=resolve;})});
 await settle();await ui.clickRow(0);ui.action('start').listeners.click();await settle();
 assert.equal(ui.action('finish').disabled,true);ui.action('finish').listeners.click();assert.equal(ui.posts.length,1);assert.equal(confirms,1);
 release(response(payload));await settle();assert.equal(ui.action('finish').disabled,false);
});
test('medical controls only display effective backend actions',async()=>{
 const ui=medicalUi({details:async()=>response(detail(1,'EN_ATENCION',[]))});await settle();await ui.clickRow(0);
 assert.equal(ui.ids['medical-detail'].querySelectorAll('button').length,0);
});
test('late previous patient detail cannot replace the latest selection',async()=>{
 let old;const ui=medicalUi({rows:[1,2],details:id=>id===1?new Promise(resolve=>{old=resolve;}):Promise.resolve(response(detail(2)))});
 await settle();ui.ids['medical-rows'].children[0].listeners.click();await tick();await ui.clickRow(1);
 old(response(detail(1)));await settle();assert.match(ui.ids['medical-detail'].textContent,/PACIENTE FICTICIO 2/);assert.doesNotMatch(ui.ids['medical-detail'].textContent,/PACIENTE FICTICIO 1/);
});
test('internal server details never appear in the medical page',async()=>{
 const ui=medicalUi({care:async()=>response({message:'SQL PRIVATE PATIENT'},500)});await settle();await ui.clickRow(0);ui.action('start').listeners.click();await settle();
 assert.doesNotMatch(ui.ids['medical-notice'].textContent,/SQL|PRIVATE|PATIENT/);
});
function linkageUi({confirm=()=>true,fetch=async()=>response({message:'Guardado'})}={}){
 const status=new Element(),button=new Element('button'),calls=[];
 const form=new Element('form');form.dataset={url:'/user/7/doctor-link',version:'0',current:'',currentLabel:'Sin vínculo'};form.reportValidity=()=>true;form.querySelector=()=>button;
 form.elements={doctor_id:{value:'3',selectedOptions:[{textContent:'MEDICO FICTICIO'}]},reason:{value:'Verificación FICTICIA'},identity_confirmed:{checked:true}};
 let uuid=0;const root={document:{querySelector:s=>s==='#user-doctor-link'?form:s==='#user-doctor-link-status'?status:s==='meta[name=csrf-token]'?{content:'FICTICIO-CSRF'}:null,getElementById:()=>status},
  confirm,crypto:{randomUUID:()=> 'UUID-FICTICIO-'+(++uuid)},location:{reload(){}},
  fetch:async(url,options)=>{calls.push(JSON.parse(options.body));return fetch(url,options);}};
 vm.runInNewContext(fs.readFileSync('public/js/admin/master/user/doctor-link.js','utf8'),{window:root,globalThis:root});
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
