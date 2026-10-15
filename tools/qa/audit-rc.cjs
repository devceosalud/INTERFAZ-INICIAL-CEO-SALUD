const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process'),crypto=require('node:crypto');
const root=path.resolve(__dirname,'../..'),out=path.join(root,'storage/app/qa-rc'),php=path.resolve(root,'../entrega-urgente-1/storage/app/qa-final/equivalent/php-8.3.35/php.exe');
const git=args=>cp.execFileSync('git',args,{cwd:root,encoding:'utf8'});
const changed=[...new Set([...git(['diff','--name-only','origin/main']).trim().split(/\r?\n/),...git(['ls-files','--others','--exclude-standard']).trim().split(/\r?\n/)])].filter(f=>f&&fs.existsSync(path.join(root,f)));
const report={head:git(['rev-parse','HEAD']).trim(),branch:git(['branch','--show-current']).trim(),base:git(['rev-parse','origin/main']).trim(),files:changed,lint:{php:0,javascript:0,failures:[]},sensitive_findings:[]};
for(const file of changed){const full=path.join(root,file);if(!fs.statSync(full).isFile())continue;
 if(file.endsWith('.php')){const p=cp.spawnSync(php,['-l',full],{encoding:'utf8'});report.lint.php++;if(p.status!==0)report.lint.failures.push({file,kind:'PHP',message:(p.stdout+p.stderr).slice(0,500)});}
 if(/\.(js|cjs)$/.test(file)&&!file.startsWith('public/vendor/')){const p=cp.spawnSync(process.execPath,['--check',full],{encoding:'utf8'});report.lint.javascript++;if(p.status!==0)report.lint.failures.push({file,kind:'JS',message:p.stderr.slice(0,500)});}
 if(/\.(php|js|cjs|json|md|yml|yaml|ps1|sql|xml|css|txt)$/.test(file)&&fs.statSync(full).size<2e6){
 for(const [i,line]of fs.readFileSync(full,'utf8').split(/\r?\n/).entries())for(const [kind,pattern]of Object.entries({private_key:/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/,github_token:/\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{50,})\b/,aws_access_key:/\bAKIA[A-Z0-9]{16}\b/}))if(pattern.test(line))report.sensitive_findings.push({file,line:i+1,kind});}
}
function clarity(text){const index=text.indexOf('(function(c,l,a,r,i,t,y)');if(index<0)throw Error('Clarity block not found');return text.slice(text.lastIndexOf('<script',index),text.indexOf('</script>',index)+9).replace(/\r\n/g,'\n');}
const f='resources/views/layouts/app.blade.php',before=clarity(git(['show','origin/main:'+f])),after=clarity(fs.readFileSync(path.join(root,f),'utf8'));
report.clarity={unchanged:before===after,sha256:crypto.createHash('sha256').update(after).digest('hex'),privacy_approved:false};
const appFiles=[...new Set([...git(['ls-files','app','config','routes','resources','database/migrations','public','composer.json','composer.lock']).trim().split(/\r?\n/),...changed.filter(f=>/^(app|config|routes|resources|database\/migrations|public)\//.test(f))])].filter(f=>f&&fs.existsSync(path.join(root,f))).sort();
report.application_sha256=crypto.createHash('sha256').update(appFiles.map(f=>f+'\0'+crypto.createHash('sha256').update(fs.readFileSync(path.join(root,f))).digest('hex')).join('\n')).digest('hex');
report.migrations=changed.filter(f=>f.startsWith('database/migrations/'));report.passed=!report.lint.failures.length&&!report.sensitive_findings.length&&report.clarity.unchanged;
fs.writeFileSync(path.join(out,'static-rc.json'),JSON.stringify(report,null,2));console.log(JSON.stringify({...report,files:changed.length},null,2));if(!report.passed)process.exitCode=1;
