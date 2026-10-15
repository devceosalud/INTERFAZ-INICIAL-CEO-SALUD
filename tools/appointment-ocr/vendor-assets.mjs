import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import {fileURLToPath} from 'node:url';
const here=path.dirname(fileURLToPath(import.meta.url)),dest=path.resolve(here,'../../public/vendor/appointment-ocr');
const sha=bytes=>crypto.createHash('sha256').update(bytes).digest('hex');
if(process.argv.includes('--verify')){
 const manifest=JSON.parse(await fs.readFile(path.join(dest,'manifest.json'),'utf8'));
 for(const asset of manifest.assets){
  const target=path.resolve(dest,asset.path);if(!target.startsWith(dest+path.sep))throw new Error('Invalid manifest path');
  const bytes=await fs.readFile(target);if(bytes.length!==asset.bytes||sha(bytes)!==asset.sha256)throw new Error('Asset checksum mismatch: '+asset.path);
 }
 console.log('OCR assets verified: '+manifest.assets.length);process.exit(0);
}
const packages=[['tesseract.js','7.0.0'],['tesseract.js-core','7.0.0'],['@tesseract.js-data/spa','1.0.0']];
for(const [name,version]of packages){
 const pkg=JSON.parse(await fs.readFile(path.join(here,'node_modules',name,'package.json'),'utf8'));
 if(pkg.version!==version)throw new Error('Unexpected version: '+name);
}
const coreNames=['tesseract-core','tesseract-core-lstm','tesseract-core-simd','tesseract-core-simd-lstm','tesseract-core-relaxedsimd','tesseract-core-relaxedsimd-lstm'];
const pairs=[['tesseract.js/dist/tesseract.min.js','tesseract.min.js'],['tesseract.js/dist/worker.min.js','worker.min.js'],
 ['tesseract.js/LICENSE.md','LICENSE.tesseract.js'],['tesseract.js-core/LICENSE','LICENSE.tesseract.js-core'],
 ['@tesseract.js-data/spa/package.json','spa-package.json'],['@tesseract.js-data/spa/4.0.0_best_int/spa.traineddata.gz','lang/spa.traineddata.gz'],
 ...coreNames.map(name=>['tesseract.js-core/'+name+'.wasm.js','core/'+name+'.wasm.js'])];
const assets=[];
for(const [source,file]of pairs){
 const bytes=await fs.readFile(path.join(here,'node_modules',source));
 await fs.mkdir(path.dirname(path.join(dest,file)),{recursive:true});await fs.writeFile(path.join(dest,file),bytes);
 assets.push({path:file,bytes:bytes.length,sha256:sha(bytes)});
}
const license=await fs.readFile(path.join(dest,'LICENSE.traineddata'));
assets.push({path:'LICENSE.traineddata',bytes:license.length,sha256:sha(license)});
await fs.writeFile(path.join(dest,'manifest.json'),JSON.stringify({
 tesseract_js:'7.0.0',tesseract_core:'7.0.0',spa_data:'1.0.0',
 source:'npm registry; pinned lockfile in tools/appointment-ocr',
 traineddata_license_source:'https://github.com/naptha/tessdata/blob/gh-pages/LICENSE',assets
},null,2)+'\n');
console.log('OCR assets prepared: '+assets.length);
