const test=require('node:test'),assert=require('node:assert/strict');
const queue=require('../../public/js/scheduling/operational-reevaluations.js');
test('all exams ready with confirmation outstanding belongs only to ready, while retaining the operational stage',()=>{
 const row={id:70,review:{status:'READY'},awaiting_confirmation:true,procedures:[{completed:true},{completed:true},{completed:true}]};
 const groups=queue.groupRows([row]);assert.equal(groups.active.length,1);assert.equal(groups.pre_pending.length,0);assert.equal(groups.pending.length,0);
 assert.equal(groups.active[0].awaiting_confirmation,true);assert.equal(Object.values(groups).flat().length,1);
});
