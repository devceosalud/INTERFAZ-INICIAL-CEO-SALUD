const test=require('node:test'),assert=require('node:assert/strict');
const {groupRows}=require('../../public/js/scheduling/operational-reevaluations');
const row=(id,status,done,total,origin=null)=>({id,review:{status,origin},procedures:Array.from({length:total},(_,i)=>({completed:i<done}))});
test('la cola distingue listos autorizados, parciales pendientes y estados fuera de la cola activa',()=>{
 const grouped=groupRows([row(1,'NOT_READY',0,2),row(2,'NOT_READY',1,2),row(3,'READY',1,2,'MANUAL'),row(4,'READY',2,2,'AUTOMATIC'),row(5,'SUSPENDED',0,2),row(6,'WITHDRAWN',2,2),row(7,'INACTIVE',2,2)]);
 assert.deepEqual(grouped.active.map(r=>r.id),[3,4]);assert.deepEqual(grouped.pending.map(r=>r.id),[1,2]);assert.deepEqual(grouped.history.map(r=>r.id),[5,6,7]);
});
test('una corrección automática conserva orden e historial sin presentar el parcial como listo',()=>{
 const before=groupRows([row(10,'READY',2,2,'AUTOMATIC'),row(11,'READY',3,3,'AUTOMATIC')]);assert.deepEqual(before.active.map(r=>r.id),[10,11]);
 const after=groupRows([row(10,'NOT_READY',1,2,'AUTOMATIC'),row(11,'READY',3,3,'AUTOMATIC')]);assert.deepEqual(after.active.map(r=>r.id),[11]);assert.deepEqual(after.pending.map(r=>r.id),[10]);
});
