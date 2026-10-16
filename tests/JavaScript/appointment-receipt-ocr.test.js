const test=require('node:test');
const assert=require('node:assert/strict');
const ocr=require('../../public/js/scheduling/appointment-receipt-ocr.js');
test('Yape suggests amount and labeled operation without approving payment',()=>{
 assert.deepEqual(ocr.parse('Yape BCP\nS/ 75.00\nNúmero de operación: 45000123\n14/10/2026 09:35'),{amount:'75.00',operation:'45000123',bank:'YAPE',date:'2026-10-14',time:'09:35',needs_review:true,ambiguous:false});
});
test('BCP and comma decimals retain leading operation zeros',()=>{
 const r=ocr.parse('BCP\nPEN 250,00\nNro. de operación 00654321');
 assert.equal(r.amount,'250.00'); assert.equal(r.operation,'00654321'); assert.equal(r.bank,'BCP');
});
test('ambiguous amounts and operations stay empty for manual review',()=>{
 const r=ocr.parse('Yape S/75.00 S/90.00\nOperación 45000123\nOperación 99112233');
 assert.equal(r.amount,null);assert.equal(r.operation,null);assert.equal(r.ambiguous,true);
});
test('unlabeled phone DNI or date never becomes operation',()=>{
 const r=ocr.parse('987654321 12345678 14/10/2026\nCódigo de seguridad 123456\nS/0.00');
 assert.equal(r.operation,null);assert.equal(r.amount,null);assert.equal(r.needs_review,true);
});
test('invalid dates and ambiguous banks are not invented',()=>{
 const r=ocr.parse('BCP BBVA\n31/02/2026 14/10/2026\n29:99');
 assert.equal(r.date,'2026-10-14');assert.equal(r.time,null);assert.equal(r.bank,null);
});
test('oversized unsupported and empty uploads fail before loading a worker',async()=>{
 let calls=0;const e=ocr.createEngine({origin:'https://qa.test',workerFactory:()=>{calls++;}});
 for(const file of [{type:'application/pdf',size:100},{type:'image/png',size:0},{type:'image/jpeg',size:ocr.MAX_BYTES+1}])await assert.rejects(e.recognize(file));
 assert.equal(calls,0);
});
test('OCR assets must stay on same origin',()=>{
 assert.throws(()=>ocr.createEngine({origin:'https://qa.test',basePath:'https://third-party.test/ocr/'}),/mismo origen/);
});
test('worker reads locally and terminates; no raw OCR text is returned',async()=>{
 let terminated=0;
 const e=ocr.createEngine({origin:'https://qa.test',workerFactory:async(lang,oem,options)=>{
 assert.equal(lang,'spa');assert.equal(oem,1);assert.equal(options.cacheMethod,'write');assert.equal(options.workerBlobURL,false);
 for(const k of ['workerPath','corePath','langPath'])assert.equal(new URL(options[k]).origin,'https://qa.test');
 return {recognize:async()=>({data:{text:'Yape S/75.00\nOperación 45000123\nNOMBRE PRIVADO',confidence:93}}),terminate:async()=>{terminated++;}};
 }});
 const r=await e.recognize({type:'image/png',size:100});assert.equal(r.amount,'75.00');assert.equal(r.needs_review,true);assert.equal(r.confidence,93);
 assert.equal('text' in r,false);assert.equal(JSON.stringify(r).includes('PRIVADO'),false);assert.equal(terminated,1);
});
test('obsolete asynchronous result is discarded after cancel',async()=>{
 let resolve, started;
 const startedPromise=new Promise(r=>started=r), result=new Promise(r=>resolve=r);
 const e=ocr.createEngine({origin:'https://qa.test',workerFactory:async()=>({recognize:()=>{started();return result;},terminate:async()=>{}})});
 const reading=e.recognize({type:'image/png',size:100});
 const rejected=assert.rejects(reading,/descartada/);
 await startedPromise;await e.cancel();resolve({data:{text:'BCP S/250.00'}});await rejected;
});
test('runtime failures use a generic message without receipt or server details',async()=>{
 const e=ocr.createEngine({origin:'https://qa.test',workerFactory:async()=>{throw new Error('SECRET receipt backend');}});
 await assert.rejects(e.recognize({type:'image/png',size:100}),error=>!error.message.includes('SECRET')&&error.message.includes('manualmente'));
});

test('Tiny Yape S/50 and OCR-confused glyphs suggest 50 without confusing operation',()=>{
 for(const txt of [
    'Yape\n¡Yapeaste!\nS/50\nNúmero de operación: 22536760',
    'Yapeaste\nS/SO\nNro. de operación 22536760',
    'Yapeaste\nS/\n50\nNro. de operación 22536760',
    'Yape\nS/. 50\nNro. de operación 22536760',
 ]) {
   const result=ocr.parse(txt);
   assert.equal(result.amount,'50.00',txt);
   assert.equal(result.operation,'22536760',txt);
 }
});
test('Non-amount numbers cannot become money without a currency or labeled amount',()=>{
 const r=ocr.parse('Yape\nTeléfono 987654321\nNro. de operación 22536760\nSaldo disponible S/ 300.00');
 assert.equal(r.amount,null);assert.equal(r.operation,'22536760');
});
test('Parsing raw Yape text never returns the cardholder or private text',()=>{
 const r=ocr.parse('Yape\nYapeaste\nS/50\nCliente PRIVADO\nNro. de operación 22536760');
 assert.equal(JSON.stringify(r).includes('PRIVADO'),false);
});
