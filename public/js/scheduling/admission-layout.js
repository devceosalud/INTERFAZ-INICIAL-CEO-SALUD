(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else document.addEventListener('DOMContentLoaded',()=>{let storage;try{storage=root.localStorage;}catch(e){}api.start(document,storage);});}(typeof window!=='undefined'?window:globalThis,function(){
    'use strict';const key='ceosalud.admission.summary.collapsed.v1';
    function start(doc,storage){const board=doc.getElementById('admission-board'),toggle=doc.getElementById('admission-summary-toggle'),content=doc.getElementById('admission-summary-content');if(!board||!toggle||!content)return;let collapsed=false;try{collapsed=storage?.getItem(key)==='1';}catch(e){}
        function render(){board.dataset.summaryCollapsed=String(collapsed);content.hidden=collapsed;toggle.setAttribute('aria-expanded',String(!collapsed));toggle.setAttribute('aria-label',collapsed?'Mostrar resumen de atención':'Ocultar resumen de atención');toggle.querySelector('span').textContent=collapsed?'Mostrar resumen':'Ocultar resumen';toggle.querySelector('[aria-hidden]').textContent=collapsed?'›':'‹';}
        toggle.addEventListener('click',()=>{collapsed=!collapsed;render();try{storage?.setItem(key,collapsed?'1':'0');}catch(e){}});render();return {collapsed:()=>collapsed};
    }return {start,key};
}));
