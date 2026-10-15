const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),out=root+'/storage/app/qa-rc',read=p=>JSON.parse(fs.readFileSync(p,'utf8').replace(/^\uFEFF/,''));
const data=read(out+'/benchmark-accounts.json'),secret=read(out+'/browser-private.json');
const {request}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const percentile=(a,p)=>[...a].sort((x,y)=>x-y)[Math.ceil(a.length*p)-1];
(async()=>{const clients=[],report={size:data.size,started_at:new Date().toISOString(),users:8,http_workers:2,mode:'Sequential unchanged feed + selected detail revalidation, 8 independent sessions, real HTTP/CSRF; excludes availability, assets, TLS',results:[]};
try{
 for(const [i,user]of data.accounts.entries()){
  const api=await request.newContext({baseURL:'http://127.0.0.1:'+(i%2?8029:8028)});let html=await(await api.get('/')).text();let csrf=html.match(/<meta\s+name="csrf-token"\s+content="([^"]+)"/)[1];
  assert.equal((await api.post('/admin/SingIn',{form:{_token:csrf,email:user.email,password:secret.password},maxRedirects:0})).status(),302);
  html=await(await api.get('/scheduling-mvp/admission')).text();csrf=html.match(/<meta\s+name="csrf-token"\s+content="([^"]+)"/)[1];
  const c={api,csrf,revision:null,detailRevision:null,id:null};clients.push(c);
  const r=await api.post('/scheduling-mvp/admission/feed',{data:{fecha:'2026-10-15',hasta:'2026-10-19',page:1},headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});assert.equal(r.status(),200);const b=await r.json();assert.equal(b.total,data.size);c.revision=b.revision;c.id=b.rows[0].id;
  const d=await api.get('/scheduling-mvp/admission/appointments/'+c.id,{headers:{Accept:'application/json'}});assert.equal(d.status(),200);c.detailRevision=(await d.json()).revision;
 }
 const sample=async c=>{const start=performance.now(),responses=[];
  responses.push(await c.api.post('/scheduling-mvp/admission/feed',{data:{fecha:'2026-10-15',hasta:'2026-10-19',page:1,revision:c.revision},headers:{Accept:'application/json','X-CSRF-TOKEN':c.csrf}}));
  responses.push(await c.api.get('/scheduling-mvp/admission/appointments/'+c.id+'?revision='+c.detailRevision,{headers:{Accept:'application/json'}}));
  let sql=0,bytes=0,peak=0;for(const r of responses){assert.equal(r.status(),200);const text=await r.text();assert.equal(JSON.parse(text).unchanged,true);bytes+=Buffer.byteLength(text);sql+=+r.headers()['x-rc-qa-sql-count'];peak=Math.max(peak,+r.headers()['x-rc-qa-peak-mib']);}return {ms:performance.now()-start,sql,bytes,peak};};
 const unloaded=[];for(let i=0;i<20;i++)unloaded.push(await sample(clients[0]));const burst=[];for(let i=0;i<10;i++)burst.push(...await Promise.all(clients.map(sample)));
 for(const [load,s]of [['unloaded',unloaded],['8 simultaneous users',burst]])report.results.push({load,samples:s.length,p50_ms:+percentile(s.map(x=>x.ms),.5).toFixed(3),p95_ms:+percentile(s.map(x=>x.ms),.95).toFixed(3),max_sql_per_cycle:Math.max(...s.map(x=>x.sql)),max_bytes_per_cycle:Math.max(...s.map(x=>x.bytes)),peak_mib:Math.max(...s.map(x=>x.peak))});
 report.proposed_targets={p95_unloaded_ms:1800,p95_burst_ms:4500,max_sql_per_cycle:80,max_bytes_per_cycle:200000};report.meets_proposed_targets=report.results.every(r=>r.p95_ms<=(r.load==='unloaded'?1800:4500)&&r.max_sql_per_cycle<=80&&r.max_bytes_per_cycle<=200000);report.finished_at=new Date().toISOString();fs.writeFileSync(out+'/performance-cycle-'+data.size+'.json',JSON.stringify(report,null,2));console.log(JSON.stringify(report));if(!report.meets_proposed_targets)process.exitCode=1;
}finally{for(const c of clients)await c.api.dispose();}})().catch(e=>{console.error(String(e.message).split(secret.password).join('[REDACTED]'));process.exitCode=1;});