const fs=require('node:fs'),{launch}=require('./browser-harness.cjs'),{helpers}=require('./browser-helpers.cjs');
(async()=>{const qa=await launch('document-layout');try{const {page}=await qa.context('nicole'),h=helpers(qa),ids=JSON.parse(fs.readFileSync(qa.out+'/jornada-ids.json','utf8'));await h.agenda(page);await h.select(page,ids.a);await page.locator('#agenda-op-documents-panel > summary').click();await page.locator('#agenda-op-add-documents').scrollIntoViewIfNeeded();
 console.log(JSON.stringify(await page.evaluate(()=>{
  const ids=['agenda-op-add-documents','agenda-op-receipt-time','agenda-op-receipt-review','agenda-op-documents-panel'];const data=ids.map(id=>{const e=document.getElementById(id),r=e.getBoundingClientRect(),c=getComputedStyle(e);return {id,rect:r.toJSON(),position:c.position,display:c.display,flex:c.flex,height:c.height,overflow:c.overflow,visibility:c.visibility};});
  const b=document.getElementById(ids[0]).getBoundingClientRect();return {data,hit:document.elementsFromPoint(b.x+b.width/2,b.y+b.height/2).map(e=>({id:e.id,tag:e.tagName,class:e.className})).slice(0,8)};
 }),null,2));await qa.finish();}catch(e){await qa.fail(e);}})();
