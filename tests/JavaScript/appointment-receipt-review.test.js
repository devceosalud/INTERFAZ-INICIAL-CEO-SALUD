const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
function harness(recognize=async()=>({amount:'75.00',operation:'45000123',bank:'YAPE',date:'2026-10-14',time:'09:35'})){
 const nodes=[],picker=[],events=[];
 function node(tag){let value='';const n={tag,children:[],handlers:{},style:{},hidden:false,disabled:false,checked:false,files:[],
  append(...children){this.children.push(...children);},replaceChildren(){this.children=[];},setAttribute(k,v){this[k]=v;},removeAttribute(k){delete this[k];},
  addEventListener(k,h){(this.handlers[k]||=[]).push(h);},dispatchEvent(e){for(const h of this.handlers[e.type]||[])h(e);},
  async trigger(k){for(const h of this.handlers[k]||[])await h({type:k});}};
 Object.defineProperty(n,'value',{get:()=>value,set:v=>{value=v;if(tag==='file'&&v==='')n.files=[];}});
 nodes.push(n);return n;
 }
 const host=node('host'),input=node('file'),paymentFields=Object.fromEntries(['amount','method','operation','origin'].map(k=>[k,node('input')]));
 paymentFields.amount.value='0';paymentFields.method.value='EFECTIVO';
 paymentFields.method.addEventListener('change',()=>events.push('method-change'));
 const jq=field=>({selectpicker:action=>picker.push([field,action])});jq.fn={selectpicker(){}};
 const window={AppointmentReceiptOCR:{createEngine:()=>({recognize,cancel:async()=>{}})},AgendaGuidance:require('../../public/js/scheduling/agenda-guidance'),jQuery:jq};
 vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/scheduling/appointment-receipt-review.js'),'utf8'),{
  window,document:{createElement:node,createTextNode:text=>({textContent:text})},URL:{createObjectURL:()=> 'blob:qa',revokeObjectURL(){}},Event:class{constructor(type){this.type=type;}}});
 const api=window.AppointmentReceiptReview({host,input,prefix:'qa',paymentFields});
 const find=text=>nodes.find(n=>n.textContent===text),id=key=>nodes.find(n=>n.id==='qa-'+key);
 return {api,input,paymentFields,nodes,picker,events,find,id,async select(files){input.files=files;await input.trigger('change');}};
}
test('OCR suggestions need explicit use and final human review; edits invalidate it',async()=>{
 const h=harness();await h.select([{name:'yape.png',type:'image/png',size:100},{name:'detail.png',type:'image/png',size:100}]);
 await h.find('Leer imagen con OCR').trigger('click');
 assert.equal(h.paymentFields.amount.value,'0');assert.equal(h.id('proof-reviewed').checked,false);
 await h.find('Usar sugerencias en el pago').trigger('click');
 assert.equal(h.paymentFields.amount.value,'75.00');assert.equal(h.paymentFields.method.value,'YAPE');assert.deepEqual(h.events,['method-change']);
 assert.ok(h.picker.some(([field,action])=>field===h.paymentFields.method&&action==='refresh'));
 const body={files:[],append(k,v){this.files.push([k,v]);}},payload={};
 assert.throws(()=>h.api.append(body,payload),/revisión humana/);
 h.id('proof-reviewed').checked=true;h.api.append(body,payload);
 assert.equal(body.files.length,2);assert.equal(payload.proof_review.source,'BROWSER_OCR');assert.equal(payload.proof_review.reviewed,true);
 assert.equal(payload.proof_review.date,'2026-10-14');
 h.paymentFields.amount.value='70.00';h.paymentFields.amount.dispatchEvent({type:'input'});
 assert.throws(()=>h.api.append({append(){}},{}),/revisión humana/);
 await h.api.reset();assert.equal(h.input.files.length,0);assert.equal(h.api.hasChanges(),false);
});
test('manual contingency preserves files and discards an obsolete OCR response',async()=>{
 let resolve,started;const start=new Promise(r=>started=r),reading=new Promise(r=>resolve=r);
 const h=harness(async()=>{started();return reading;});
 await h.select([{name:'qa.png',type:'image/png',size:100}]);
 const pending=h.find('Leer imagen con OCR').trigger('click');await start;
 await h.find('Detener OCR / completar manualmente').trigger('click');
 assert.equal(h.input.files.length,1);
 resolve({amount:'250.00',operation:'OLD',bank:'BCP'});await pending;
 assert.equal(h.paymentFields.amount.value,'0');
 h.paymentFields.amount.value='50';h.id('proof-reviewed').checked=true;
 const payload={};h.api.append({append(){}},payload);assert.equal(payload.proof_review.source,'MANUAL');
});
test('PDF is reviewed manually and never sent to the OCR worker',async()=>{
 let calls=0;const h=harness(async()=>{calls++;});
 await h.select([{name:'qa.pdf',type:'application/pdf',size:100}]);
 assert.equal(h.find('Leer imagen con OCR').disabled,true);
 h.paymentFields.amount.value='20';h.id('proof-reviewed').checked=true;
 const payload={};h.api.append({append(){}},payload);assert.equal(payload.proof_review.source,'MANUAL');assert.equal(calls,0);
});
test('every legacy consumer loads local OCR and validation before the review widget',()=>{
 for(const profile of ['admin','admissionist','receptionist'])for(const module of ['patient','appointment']){
  const source=fs.readFileSync(path.join(__dirname,'../../resources/views',profile,module,'index.blade.php'),'utf8');
  assert.match(source,/vendor\/appointment-ocr\/tesseract\.min\.js/);
  assert.ok(source.indexOf('agenda-guidance.js')<source.indexOf('appointment-receipt-review.js'));
  assert.ok(source.indexOf('appointment-receipt-review.js')<source.indexOf('js/admissionist/tesseract/tesseract.js'));
  assert.doesNotMatch(source,/cdn\.jsdelivr\.net\/npm\/tesseract/);
 }
 const form=fs.readFileSync(path.join(__dirname,'../../resources/views/admissionist/appointment/crud/create.blade.php'),'utf8');
 assert.match(form,/<option value="TRANSFERENCIA">TRANSFERENCIA<\/option>/);
});
