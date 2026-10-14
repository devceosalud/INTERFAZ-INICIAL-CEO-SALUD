const {test}=require('node:test'),assert=require('node:assert/strict');
const {changeState}=require('../../public/js/admin/master/user/user.js');
const reply=(status,body,type='application/json',redirected=false)=>({status,ok:status>=200&&status<300,redirected,headers:{get:()=>type},json:async()=>body});
test('access action sends an explicit desired state, expected epoch, stable UUID and CSRF',async()=>{
    const payload={active:false,expected_version:3,request_key:'same-request-uuid',reason:'QA reason'},calls=[];
    const fetcher=async(url,options)=>{calls.push({url,options});return reply(200,{code:1});};
    await changeState('/admin/user/2/access-state',payload,'csrf-qa',fetcher);await changeState('/admin/user/2/access-state',payload,'csrf-qa',fetcher);
    assert.equal(calls.length,2);for(const call of calls){assert.equal(call.options.method,'POST');assert.equal(call.options.credentials,'same-origin');assert.equal(call.options.headers['X-CSRF-TOKEN'],'csrf-qa');assert.deepEqual(JSON.parse(call.options.body),payload);}
});
test('session and HTML responses are not rendered as account details',async()=>{
    for(const r of [reply(401,'PRIVATE','text/html'),reply(200,'PRIVATE','text/html',true)])await assert.rejects(changeState('/x',{},'t',async()=>r),/Inicia sesión/);
    await assert.rejects(changeState('/x',{},'t',async()=>reply(200,'PRIVATE','text/html')),error=>!error.message.includes('PRIVATE'));
});
test('permission and concurrent epoch conflicts remain explicit',async()=>{
    await assert.rejects(changeState('/x',{},'t',async()=>reply(403,{message:'PRIVATE'})),/No tienes autorización/);
    await assert.rejects(changeState('/x',{},'t',async()=>reply(409,{message:'El acceso cambió.'})),{message:'El acceso cambió.'});
});
test('server or malformed JSON errors never disclose internal details or claim no change happened',async()=>{
    await assert.rejects(changeState('/x',{},'t',async()=>reply(500,{message:'SQL PRIVATE'})),error=>/confirmar el resultado/.test(error.message)&&!error.message.includes('PRIVATE'));
    const broken=reply(200,{});broken.json=async()=>{throw new Error('PRIVATE HTML');};
    await assert.rejects(changeState('/x',{},'t',async()=>broken),error=>!error.message.includes('PRIVATE'));
});

const {latestLookup}=require('../../public/js/admin/master/user/user.js');
test('an older user lookup cannot replace a newer worker identity',async()=>{
    const context=latestLookup(),painted=[];let finishOld;
    const old=context.run(()=>new Promise(resolve=>{finishOld=resolve;}),data=>painted.push(data.id));
    await context.run(async()=>({id:22}),data=>painted.push(data.id));finishOld({id:11});
    assert.equal(await old,false);assert.deepEqual(painted,[22]);
});
test('closing the editor discards late user lookup responses',async()=>{
    const context=latestLookup();let complete;let painted=false;
    const pending=context.run(()=>new Promise(resolve=>{complete=resolve;}),()=>{painted=true;});
    context.cancel();complete({id:11});assert.equal(await pending,false);assert.equal(painted,false);
});
