'use strict';
const test=require('node:test'),assert=require('node:assert/strict');
const history=require('../../public/js/scheduling/agenda-reschedule-history.js');
const grid=require('../../public/js/scheduling/agenda-day-grid.js');
const item={event_id:4,appointment_id:9,doctor_id:2,from_date:'2026-10-21',from_time:'12:20',from_end:'13:00',duration_minutes:40,
    to_date:'2026-10-24',to_time:'12:00',current_date:'2026-10-26',current_time:'12:40'};
function element(){return {dataset:{},attributes:{},children:[],listeners:{},setAttribute(k,v){this.attributes[k]=v;},appendChild(n){this.children.push(n);},addEventListener(k,fn){this.listeners[k]=fn;}};}
const doc={createElement:element};
test('extended annotation intersects only original doctor, date and covered rows',()=>{
    assert.equal(history.forRow([item],2,'2026-10-21','12:20','12:40').length,1);
    assert.equal(history.forRow([item],'2','2026-10-21','12:40','13:00').length,1);
    for(const [doctor,date,start,end]of [[3,'2026-10-21','12:20','12:40'],[2,'2026-10-24','12:20','12:40'],[2,'2026-10-21','13:00','13:20']])
        assert.equal(history.forRow([item],doctor,date,start,end).length,0);
});
test('legacy history without duration remains anchored at its original time',()=>{
    const legacy={...item,from_end:'12:20',duration_minutes:0};
    assert.equal(history.forRow([legacy],2,item.from_date,'12:20','12:40').length,1);
    assert.equal(history.forRow([legacy],2,item.from_date,'12:40','13:00').length,0);
});
test('marker is read-only, stops row activation and opens only current appointment',()=>{
    let opened=null;const marker=history.marker(doc,item,'available',i=>opened=i);
    assert.equal(marker.dataset.appointmentId,undefined);assert.equal(marker.dataset.contextType,undefined);
    assert.match(marker.children[0].textContent,/REPROGRAMADA → 24\/10\/2026, 12:00/);
    assert.match(marker.children[1].textContent,/Cita vigente: 26\/10\/2026, 12:40/);
    assert.match(marker.children[1].textContent,/liberado y disponible/);
    let stopped=0;marker.listeners.click({stopPropagation(){stopped++;}});marker.listeners.keydown({stopPropagation(){stopped++;}});assert.equal(stopped,2);
    marker.children[2].listeners.click({preventDefault(){}});assert.equal(opened,item);
});
test('reused occupied interval is never labelled available by historical marker',()=>{
    const marker=history.marker(doc,item,'occupied',()=>{});
    assert.doesNotMatch(marker.children[1].textContent,/liberado y disponible/);
    assert.match(history.marker(doc,item,'off-hours',()=>{}).children[1].textContent,/fuera del horario/);
});
test('history changes visible bounds only: free context and occupancy rows stay identical',()=>{
    const options={date:item.from_date,professional:{id:2,dias:[{fecha:item.from_date,slots:[{inicio:'12:20',fin:'12:40',estado:'DISPONIBLE',minutos:20}]}]},events:[]};
    const before=grid.build(options),after=grid.build({...options,history:[item]});
    assert.deepEqual(after,before);const free=after.rows.find(r=>r.start==='12:20');assert.equal(free.context.seleccionable,true);assert.equal(free.context.tipo_contexto,'slot_libre');
    const outside={...item,from_time:'05:20',from_end:'06:00'};const expanded=grid.build({...options,history:[outside]});
    assert.equal(expanded.rows[0].start,'05:20');assert.equal(expanded.rows[0].context.seleccionable,false);
});
test('week/month indicators only append DOM badges and are replaceable on refresh',()=>{
    const target=element(),removed=[];let shown;const host={querySelectorAll(selector){
        if(selector==='.agenda-history-count')return[{remove(){removed.push(true);}}];
        assert.equal(selector,'[data-date="2026-10-21"]');return [{querySelector(){return target;}}];
    }};
    history.calendarIndicators(doc,host,{items:[item]},['2'],day=>shown=day);
    assert.equal(removed.length,1);assert.equal(target.children.length,1);assert.equal(target.children[0].textContent,'↪ 1');
    target.children[0].listeners.click({preventDefault(){},stopPropagation(){}});assert.equal(shown,'2026-10-21');
    assert.match(target.children[0].attributes['aria-label'],/sin ocupación/);
});
