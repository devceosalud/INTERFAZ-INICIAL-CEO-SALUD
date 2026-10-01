(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.AgendaPatientDraft = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    var IDENTITY_FIELDS = [
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'fecha_nacimiento',
        'genero',
        'estado_civil',
        'direccion',
    ];

    function text(value) {
        return value === null || value === undefined ? '' : String(value).trim();
    }

    function scheduleOf(schedule) {
        schedule = schedule || {};

        return {
            doctor: schedule.doctor,
            specialty: schedule.specialty,
            date: schedule.date,
            time: schedule.time,
            site: schedule.site,
        };
    }

    function blankFields() {
        return {
            nombre: '',
            apellido_paterno: '',
            apellido_materno: '',
            telefono: '',
            email: '',
            fecha_nacimiento: '',
            genero: '',
            estado_civil: '',
            direccion: '',
            motivo: '',
        };
    }

    function empty(tipo, numero) {
        tipo = text(tipo);

        return Object.assign({
            open: false,
            patientId: '',
            tipo: tipo,
            numero: text(numero),
            reniecOffered: tipo === 'DNI',
            manual: {},
            message: '',
        }, blankFields());
    }

    function canOpen(identity) {
        return Boolean(identity) && identity.status === 'not_found' && identity.showRegister === true;
    }

    function open(identity, schedule) {
        if (!canOpen(identity)) {
            return Object.assign(scheduleOf(schedule), { open: false, patientId: '' });
        }

        return Object.assign(scheduleOf(schedule), empty(identity.tipo, identity.numero), {
            open: true,
            patientId: '',
        });
    }

    function cancel(draft, schedule) {
        return Object.assign(scheduleOf(schedule), empty(draft && draft.tipo, draft && draft.numero), {
            open: false,
            patientId: '',
        });
    }

    function discard(schedule) {
        return Object.assign(scheduleOf(schedule), empty('', ''), {
            open: false,
            patientId: '',
        });
    }

    function edit(draft, field, value) {
        var next = Object.assign({}, draft);
        next.manual = Object.assign({}, draft.manual || {});
        next[field] = value;
        next.manual[field] = true;
        next.patientId = '';

        return next;
    }

    function applyReniec(draft, response) {
        var next = Object.assign({}, draft, { patientId: '' });
        next.manual = Object.assign({}, draft.manual || {});

        if (!response || response.status !== 'prefilled' || !response.identity) {
            next.message = 'No se pudieron obtener datos de RENIEC. Puede continuar con el registro manual.';

            return next;
        }

        IDENTITY_FIELDS.forEach(function (field) {
            if (next.manual[field]) {
                return;
            }

            var value = response.identity[field];

            if (value !== null && value !== undefined && text(value) !== '') {
                next[field] = value;
            }
        });
        next.message = 'Datos encontrados en RENIEC. Revise y complete antes de continuar.';

        return next;
    }

    return {
        canOpen: canOpen,
        open: open,
        cancel: cancel,
        discard: discard,
        edit: edit,
        applyReniec: applyReniec,
    };
}));
