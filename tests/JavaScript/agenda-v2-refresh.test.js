const {test}=require('node:test');
const assert=require('node:assert/strict');
const {create}=require('../../public/js/scheduling/agenda-refresh');
function harness(run,enabled=()=>true,onError=()=>{}) {
    let next=0;const tasks=new Map();
    const poll=create({run,enabled,onError,schedule:(fn,delay)=>{tasks.set(++next,{fn,delay});return next;},clear:id=>tasks.delete(id)});
    return {poll,tasks,async tick(){const [id,task]=tasks.entries().next().value;tasks.delete(id);return task.fn();}};
}
test('slow polling never overlaps and resumes from completion without catch-up bursts',async()=>{
    let resolve,calls=0;const h=harness(()=>{calls++;return new Promise(r=>resolve=r);});
    h.poll.start(true);const running=h.tick();assert.equal(calls,1);assert.equal(h.tasks.size,0);
    h.poll.start(true);h.poll.start(true);assert.equal(h.tasks.size,0);
    resolve();await running;assert.equal(h.tasks.size,1);assert.equal([...h.tasks.values()][0].delay,5000);
    h.poll.stop();assert.equal(h.tasks.size,0);
});
test('hidden tab or active editor skips refresh without fetching and restarts once',async()=>{
    let enabled=false,calls=0;const h=harness(()=>{calls++;},()=>enabled);
    h.poll.start();await h.tick();assert.equal(calls,0);assert.equal(h.tasks.size,1);
    enabled=true;await h.tick();assert.equal(calls,1);h.poll.stop();assert.equal(h.tasks.size,0);
    h.poll.start(true);h.poll.start(true);assert.equal(h.tasks.size,1);await h.tick();assert.equal(calls,2);h.poll.stop();
});
test('network failure is reported and does not stop future polling',async()=>{
    let errors=0,calls=0;const h=harness(()=>{calls++;if(calls===1)throw new Error('offline');},()=>true,()=>errors++);
    h.poll.start();await h.tick();assert.equal(errors,1);assert.equal(h.tasks.size,1);
    await h.tick();assert.equal(calls,2);h.poll.stop();
});
test('pagehide during an in-flight request prevents scheduling another request',async()=>{
    let resolve;const h=harness(()=>new Promise(r=>resolve=r));h.poll.start();const running=h.tick();
    h.poll.stop();resolve();await running;assert.equal(h.tasks.size,0);
});
