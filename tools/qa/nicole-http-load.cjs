const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),root=path.resolve(__dirname,'../..'),out=root+'/storage/app/qa-rc';
const json=p=>JSON.parse(fs.readFileSync(p,'utf8').replace(/^\uFEFF/,''));const data=json(out+'/benchmark-accounts.json'),secret=json(out+'/browser-private.json');
const {request}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const percentile=(values,p)=>[...values].sort((a,b)=>a-b)[Math.ceil(values.length*p)-1];
(async()=>{const clients=[];const report={size:data.size,started_at:new Date().toISOString(),users:8,http_workers:2,rounds:10,mode:'8 independent cookie jars; simultaneous bursts; real loopback HTTP, fresh PHP request bootstrap and CSRF; no TLS',proposed_targets:{p95_unloaded_ms:1500,p95_burst_ms:4000,max_body_bytes:100000,max_sql:40},results:[]};
try{
 for(const [i,user]of data.accounts.entries()){
  const api=await request.newContext({baseURL:'http://127.0.0.1:'+(i%2?8029:8028)});const login=await api.get('/');let html=await login.text();const token=html.match(/<meta\s+name="csrf-token"\s+content="([^"]+)"/)[1];
  const logged=await api.post('/admin/SingIn',{form:{_token:token,email:user.email,password:secret.password},maxRedirects:0});assert.equal(logged.status(),302);
  const panel=await api.get('/scheduling-mvp/admission');assert.equal(panel.status(),200);html=await panel.text();const csrf=html.match(/<meta\s+name="csrf-token"\s+content="([^"]+)"/)[1];clients.push({api,csrf,revision:null});
 }
 const sample=async(client,mode)=>{const start=performance.now();const r=await client.api.post('/scheduling-mvp/admission/feed',{data:{fecha:'2026-10-15',hasta:'2026-10-19',page:1,...(mode==='unchanged'?{revision:client.revision}:{})},headers:{Accept:'application/json','X-CSRF-TOKEN':client.csrf}});assert.equal(r.status(),200);const text=await r.text(),body=JSON.parse(text),headers=r.headers();if(mode==='full')assert.equal(body.total,data.size);else assert.equal(body.unchanged,true);client.revision=body.revision;return {ms:performance.now()-start,bytes:Buffer.byteLength(text),sql:Number(headers['x-rc-qa-sql-count']),sql_ms:Number(headers['x-rc-qa-sql-ms']),app_ms:Number(headers['x-rc-qa-app-ms']),peak_mib:Number(headers['x-rc-qa-peak-mib'])};};
 for(const c of clients)await sample(c,'full');
 for(const mode of ['full','unchanged']){
  const unloaded=[];for(let i=0;i<20;i++)unloaded.push(await sample(clients[0],mode));
  const burst=[];for(let i=0;i<10;i++)burst.push(...await Promise.all(clients.map(c=>sample(c,mode))));
  const summarize=(name,s)=>({mode,load:name,samples:s.length,p50_ms:+percentile(s.map(v=>v.ms),.5).toFixed(3),p95_ms:+percentile(s.map(v=>v.ms),.95).toFixed(3),max_ms:+Math.max(...s.map(v=>v.ms)).toFixed(3),max_sql:Math.max(...s.map(v=>v.sql)),max_bytes:Math.max(...s.map(v=>v.bytes)),peak_mib:Math.max(...s.map(v=>v.peak_mib))});
  report.results.push(summarize('unloaded',unloaded),summarize('8 simultaneous users',burst));
 }
 report.meets_proposed_targets=report.results.every(r=>r.p95_ms<=(r.load==='unloaded'?1500:4000)&&r.max_sql<=40&&r.max_bytes<=100000);report.finished_at=new Date().toISOString();fs.writeFileSync(out+'/performance-http-'+data.size+'.json',JSON.stringify(report,null,2));console.log(JSON.stringify(report));
}finally{for(const c of clients)await c.api.dispose();}})().catch(e=>{console.error(String(e.message).split(secret.password).join('[REDACTED]'));process.exitCode=1;});
