const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const source=path.join(__dirname,'node_modules/sweetalert2');
const pkg=JSON.parse(fs.readFileSync(path.join(source,'package.json'),'utf8'));
if(pkg.version!=='11.22.4')throw Error('Unexpected alert version; no assets copied.');
const destination=path.resolve(__dirname,'../../public/vendor/ceosalud-alerts');fs.mkdirSync(destination,{recursive:true});
const files=[['dist/sweetalert2.all.min.js','sweetalert2.all.min.js'],['LICENSE','LICENSE']];
const manifest={package:'sweetalert2',version:pkg.version,license:pkg.license,upstream:'https://github.com/sweetalert2/sweetalert2',files:[]};
for(const [input,output]of files){const bytes=fs.readFileSync(path.join(source,input));fs.writeFileSync(path.join(destination,output),bytes);manifest.files.push({file:output,size:bytes.length,sha256:crypto.createHash('sha256').update(bytes).digest('hex')});}
fs.writeFileSync(path.join(destination,'manifest.json'),JSON.stringify(manifest,null,2)+'\n');console.log(JSON.stringify(manifest,null,2));
