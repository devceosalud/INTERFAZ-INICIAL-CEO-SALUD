const {test}=require('node:test');
const assert=require('node:assert/strict');
const {polling}=require('../../public/js/scheduling/admission-board');
function clock(){let id=0;const tasks=new Map();return {tasks,schedule(fn,delay){tasks.set(++id,{fn,delay});return id;},clear(key){tasks.delete(key);},async next(){const [key,task]=tasks.entries().next().value;tasks.delete(key);return task.fn();}};}
test('polls at 5000 ms, never overlaps, and an immediate resume cannot duplicate a running request',async()=>{
    const timer=clock();let calls=0,finish;
    const poll=polling({...timer,run:()=>{calls++;return new Promise(resolve=>{finish=resolve;});}});
    poll.start();assert.equal(timer.tasks.values().next().value.delay,5000);
    const pending=timer.next();assert.equal(calls,1);assert.equal(timer.tasks.size,0);
    poll.start(true);assert.equal(timer.tasks.size,0);finish();await pending;
    assert.equal(timer.tasks.size,1);assert.equal(timer.tasks.values().next().value.delay,5000);poll.stop();
});
test('hidden or editing view makes no request; returning to it refreshes immediately',async()=>{
    const timer=clock();let enabled=false,calls=0;
    const poll=polling({...timer,enabled:()=>enabled,run:async()=>calls++});
    poll.start();await timer.next();assert.equal(calls,0);
    poll.stop();assert.equal(timer.tasks.size,0);enabled=true;poll.start(true);
    assert.equal(timer.tasks.values().next().value.delay,0);await timer.next();assert.equal(calls,1);poll.stop();
});
test('leaving the module stops the timer and a pending completion cannot restart it',async()=>{
    const timer=clock();let finish;
    const poll=polling({...timer,run:()=>new Promise(resolve=>{finish=resolve;})});
    poll.start();const pending=timer.next();poll.stop();finish();await pending;assert.equal(timer.tasks.size,0);
});
test('removed module stops polling, and transient failures are reported without replacing stored data',async()=>{
    const timer=clock();let alive=true,stored={total:12},errors=[];
    const poll=polling({...timer,alive:()=>alive,run:async()=>{throw Error('offline');},onError:error=>errors.push(error.message)});
    poll.start();await timer.next();assert.deepEqual(errors,['offline']);assert.deepEqual(stored,{total:12});
    assert.equal(timer.tasks.size,1);alive=false;await timer.next();assert.equal(timer.tasks.size,0);
});
