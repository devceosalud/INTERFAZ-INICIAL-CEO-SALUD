const {test}=require('node:test'),assert=require('node:assert/strict');
const {rowActivation}=require('../../public/js/scheduling/admission-board.js');
const event=(type,key,interactive=false)=>({type,key,target:{closest:()=>interactive?{}:null}});
test('patient rows accept click, Enter and Space without consuming other keys',()=>{
 assert.equal(rowActivation(event('click')),true);for(const key of ['Enter',' '])assert.equal(rowActivation(event('keydown',key)),true);
 for(const key of ['Tab','Escape','ArrowDown','a'])assert.equal(rowActivation(event('keydown',key)),false);
});
test('nested links, buttons and inputs retain their own interaction',()=>{
 for(const type of ['click','keydown'])for(const key of ['Enter',' '])assert.equal(rowActivation(event(type,key,true)),false);
});

const {pdfOptions}=require('../../public/js/scheduling/appointment-patient-documents.js');
test('private PDF rendering forbids eval and XFA and bounds image resources without streaming external data',()=>{
 const bytes=new Uint8Array([37,80,68,70]),options=pdfOptions(bytes);assert.equal(options.data,bytes);
 assert.equal(options.isEvalSupported,false);assert.equal(options.enableXfa,false);assert.equal(options.disableAutoFetch,true);assert.equal(options.disableRange,true);assert.ok(options.maxImageSize<=16000000);assert.ok(options.canvasMaxAreaInBytes<=16777216);
});
