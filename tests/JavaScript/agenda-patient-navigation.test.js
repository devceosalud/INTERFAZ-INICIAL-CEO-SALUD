const test = require('node:test');
const assert = require('node:assert/strict');
const navigation = require('../../public/js/scheduling/agenda-patient-navigation');

test('Completar ficha carries only internal IDs and agenda context, never identity or arbitrary return URL', () => {
    const url = navigation.patientUrl(4818, { appointment_id: 203, doctor_id: 1, fecha: '2026-10-09',
        dni: '12345678', name: 'PRUEBA LOCAL', return_url: 'https://evil.invalid/' });
    assert.equal(url, '/patients?patient_id=4818&from_agenda=1&appointment_id=203&doctor_id=1&agenda_date=2026-10-09');
    assert.throws(() => navigation.patientUrl('DNI-12345678'));
});
test('Agenda return restores valid doctor/date/appointment and ignores malformed values', () => {
    assert.deepEqual(navigation.agendaContext('?doctor_id=1&fecha=2026-10-09&appointment_id=203'),
        { doctor_id: '1', fecha: '2026-10-09', appointment_id: '203' });
    assert.deepEqual(navigation.agendaContext('?doctor_id=-1&fecha=2026-02-31&appointment_id=script'),
        { doctor_id: '', fecha: '', appointment_id: '' });
});
