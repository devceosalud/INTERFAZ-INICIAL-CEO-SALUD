const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');

const source=fs.readFileSync(path.resolve(__dirname,'../../public/js/scheduling/admission-board.js'),'utf8');
const routes=fs.readFileSync(path.resolve(__dirname,'../../routes/scheduling.php'),'utf8');
const service=fs.readFileSync(path.resolve(__dirname,'../../app/Services/Scheduling/AdmissionTaskService.php'),'utf8');

test('Nicol tasks are per-appointment, editable only by admins and complete-only for staff',()=>{
    assert.match(source,/renderOperationalTasks\(payload\)/);
    assert.match(source,/can_manage_operational_tasks === true/);
    assert.match(source,/can_complete_operational_tasks === true/);
    assert.match(source,/const appointmentId = Number\(payload\.appointment\.id\)/);
    assert.match(source,/document\.createElement|const node=\(tag,text,className\)/);
    assert.match(source,/method:'POST',body:JSON\.stringify\(\{text,request_key:observationRequest\.key\}\)/);
    assert.match(source,/method:'PATCH',body:JSON\.stringify\(\{expected_completed:expected,completed:!expected\}\)/);
    assert.match(routes,/\/admission\/appointments\/\{appointmentId\}\/tasks'/);
    assert.match(routes,/\/tasks\/\{taskId\}\/completion'/);
});
test('Role and permission checks are server side and scoped by appointment visibility',()=>{
    assert.match(service,/visibleToAgendaUser\(\$actor->id\)/);
    assert.match(service,/hasRole\('ADMINISTRADOR'\)/);
    assert.match(service,/hasAnyRole\(\['ADMISION', 'COMERCIAL'\]\)/);
    assert.match(service,/public const MANAGE/);
    assert.match(service,/public const COMPLETE/);
    assert.match(service,/lockForUpdate\(\)/);
    assert.match(service,/appointment_admission_task_events/);
});
