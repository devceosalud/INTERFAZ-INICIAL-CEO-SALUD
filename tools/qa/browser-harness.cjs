const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),cp=require('node:child_process');
const rcRoot=path.resolve(__dirname,'../..'),out=path.join(rcRoot,'storage/app/qa-rc');
const json=p=>JSON.parse(fs.readFileSync(p,'utf8').replace(/^\uFEFF/,''));
const fixture=json(path.join(out,'browser-fixtures.json')),secret=json(path.join(out,'browser-private.json'));
const {chromium}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const php=path.resolve(rcRoot,'../entrega-urgente-1/storage/app/qa-final/equivalent/php-8.3.35/php.exe');
const base='http://127.0.0.1:8026';
const workers=[['admin2','ADMIN SECUNDARIO QA','ADMINISTRADOR'],['commercialA','COMERCIAL A QA','COMERCIAL'],['commercialB','COMERCIAL B QA','COMERCIAL'],['nicole','NICOLE ADMISION QA','ADMISION'],['reception','RECEPCION QA','RECEPCION'],['cashier','CAJA QA','CAJA']].map(([key,name,role],i)=>({key,name,role,dni:String(91000001+i),email:'rc-'+key.toLowerCase()+'@example.invalid'}));
function readback(){return JSON.parse(cp.execFileSync(php,['tests/Support/rc-readback.php'],{cwd:rcRoot,encoding:'utf8'}));}
function redact(text){return String(text).split(secret.password).join('[REDACTED]');}
async function launch(stage){
 const archive=path.join(out,'attempts',stage+'-'+Date.now());
 for(const name of ['browser-'+stage+'.json','failure-'+stage+'.png','failure-'+stage+'-visible.txt']){const previous=path.join(out,name);if(fs.existsSync(previous)){fs.mkdirSync(archive,{recursive:true});fs.copyFileSync(previous,path.join(archive,name));}}
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true,args:['--disable-background-networking','--disable-component-update','--disable-sync','--host-resolver-rules=MAP * 0.0.0.0, EXCLUDE 127.0.0.1, EXCLUDE localhost']});
 const report={stage,started_at:new Date().toISOString(),browser:browser.version(),cases:[],requests:[],blocked_external_hosts:[],contexts:{}};
 const contexts=new Map();let activePage;
 const save=()=>fs.writeFileSync(path.join(out,'browser-'+stage+'.json'),JSON.stringify(report,null,2));
 async function context(key,options={}){
  const stateFile=path.join(out,'private-session-'+key+'.json');
  const context=await browser.newContext({viewport:{width:1366,height:900},timezoneId:'America/Lima',...(fs.existsSync(stateFile)?{storageState:stateFile}:{}),...options});
  context.setDefaultTimeout(15000);context.setDefaultNavigationTimeout(45000);
  await context.route('**/*',route=>{const url=new URL(route.request().url());if(['127.0.0.1','localhost'].includes(url.hostname)||['data:','blob:'].includes(url.protocol))return route.continue();report.blocked_external_hosts=[...new Set([...report.blocked_external_hosts,url.hostname])];return route.abort('blockedbyclient');});
  context.on('response',response=>{const request=response.request();if(!['127.0.0.1','localhost'].includes(new URL(response.url()).hostname))return;
   const pathname=new URL(response.url()).pathname;if(!/\.(js|css|png|jpg|svg|woff2?|wasm|gz)$/.test(pathname))report.requests.push({method:request.method(),path:pathname,status:response.status()});});
  const page=await context.newPage();page.on('pageerror',error=>{report.page_errors??=[];report.page_errors.push({url:new URL(page.url()).pathname,error:redact(error.stack||error.message)});});
  contexts.set(key,{context,page,stateFile});return {context,page};
 }
 async function step(name,page,work){activePage=page;const start=Date.now();try{const result=await work();report.cases.push({name,result:'PASS',ms:Date.now()-start});save();console.log('PASS '+name);return result;}catch(error){report.cases.push({name,result:'FAIL',ms:Date.now()-start,error:redact(error.message)});if(page){await page.screenshot({path:path.join(out,'failure-'+stage+'.png'),fullPage:true}).catch(()=>{});fs.writeFileSync(path.join(out,'failure-'+stage+'-visible.txt'),redact(await page.locator('body').innerText().catch(()=>'')));}save();throw error;}}
 async function login(page,email){await page.goto(base+'/');await page.locator('input[name=email]').fill(email);await page.locator('input[name=password]').fill(secret.password);await Promise.all([page.waitForURL('**/dashboard'),page.getByRole('button',{name:'Iniciar sesión',exact:true}).click()]);}
 async function finish(){for(const {context,stateFile}of contexts.values())await context.storageState({path:stateFile});report.finished_at=new Date().toISOString();save();await browser.close();}
 async function fail(error){console.error('QA FAILED: '+redact(error.message));if(activePage)await activePage.screenshot({path:path.join(out,'failure-'+stage+'.png'),fullPage:true}).catch(()=>{});save();await browser.close();process.exitCode=1;}
 return {browser,report,context,step,login,finish,fail,fixture,workers,secret,base,out,readback,assert};
}
module.exports={launch,readback,fixture,workers,secret,base,out};
