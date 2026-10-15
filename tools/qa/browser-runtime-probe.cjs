const fs=require('node:fs'),path=require('node:path');
const bundle=path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules');
const {chromium}=require(path.join(bundle,'playwright'));
const candidates=[chromium.executablePath(),'C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'];
const executablePath=candidates.find(p=>fs.existsSync(p));
if(!executablePath)throw Error('No installed Chromium executable. No browser download attempted.');
(async()=>{
 const browser=await chromium.launch({executablePath,headless:true});
 const context=await browser.newContext({viewport:{width:390,height:844},isMobile:true,deviceScaleFactor:1});
 const page=await context.newPage();await page.goto('about:blank');
 const viewport=await page.evaluate(()=>({width:innerWidth,height:innerHeight,dpr:devicePixelRatio}));
 console.log(JSON.stringify({browser:browser.version(),executablePath,viewport},null,2));
 await context.close();await browser.close();
})().catch(e=>{console.error(e.message);process.exitCode=1;});
