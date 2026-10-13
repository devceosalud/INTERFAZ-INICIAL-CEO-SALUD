const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../public/js/admissionist/calendario/calendario.js'),'utf8');
test('popover heredado trata identidad y notas como texto y mantiene saneamiento HTML',()=>{
 const helper=source.slice(0,source.indexOf('document.addEventListener'));const sandbox={};vm.runInNewContext(helper+'\nglobalThis.escape=legacyCalendarText;',sandbox);
 assert.equal(sandbox.escape('<img src=x onerror="alert(1)"> & \'QA\''),'&lt;img src=x onerror=&quot;alert(1)&quot;&gt; &amp; &#39;QA&#39;');
 assert.equal(sandbox.escape(null),'');assert.match(source,/sanitize: true/);
 assert.match(source,/legacyCalendarText\(info.event.extendedProps.motivo_consulta\)/);
});
test('editor heredado hidrata y conserva duración doble sin inferirla de históricos',()=>{
 const body=source.slice(source.indexOf('            const durationChoice='),source.indexOf("            $('#appointmentModalEdit #appointment_id')"));
 for(const slots of [2,1,null]){const field={checked:false,disabled:false};vm.runInNewContext(body,{eventComun:{duration_slots:slots},document:{querySelector:()=>field}});
 assert.equal(field.checked,slots===2);assert.equal(field.disabled,true);}
});
