const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process');
const root=path.resolve(__dirname,'../..'),out=path.join(root,'storage/app/qa-rc');
const stages=process.argv.slice(2);if(!stages.length)throw Error('Specify existing browser stages');
for(const stage of stages){
 if(!/^[a-z-]+$/.test(stage))throw Error('Invalid stage');
 const file=path.join(__dirname,'browser-'+stage+'.cjs');if(!fs.existsSync(file))throw Error('Missing stage '+stage);
 const log=path.join(out,'run-'+stage+'-'+Date.now()+'.log'),fd=fs.openSync(log,'w');
 console.log('RUN '+stage);const result=cp.spawnSync(process.execPath,[file],{cwd:root,stdio:['ignore',fd,fd]});fs.closeSync(fd);
 console.log(fs.readFileSync(log,'utf8'));if(result.status!==0){process.exitCode=result.status||1;break;}
}
