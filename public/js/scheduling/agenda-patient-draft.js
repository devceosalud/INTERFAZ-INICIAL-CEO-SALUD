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
    var DOCUMENT_CODES = {
        DNI: '01',
        'CARNET EXTRANJERIA': '02',
        PASAPORTE: '03',
        PTP: '04',
        TAM: '05',
        SALVOCONDUCTO: '06',
        'SIN DOCUMENTOS': '99',
    };

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
            channel_id: '',
            interaction_medium_id: '',
            ocupacion: '',
            grado_instruccion: '',
            familiar_contacto: '',
            commercial_owner_id: '',
            commercial_owner_name: '',
            historia_clinica: '',
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

    function openExisting(patient, schedule) {
        patient = patient || {};

        return Object.assign(scheduleOf(schedule), empty(
            patient.tipo_identificacion,
            patient.numero_identidad
        ), patient, {
            open: true,
            patientId: text(patient.id || patient.patient_id),
            reniecOffered: false,
            manual: {},
            message: 'Ficha existente cargada. Revise los datos antes de guardar.',
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
        if (field === 'tipo') {
            next.reniecOffered = text(value) === 'DNI';
        }

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

    function hcePreview(draft) {
        if (text(draft && draft.patientId) !== '') {
            return {
                value: text(draft.historia_clinica) || '—',
                message: 'HCE almacenada; no se reformatea.',
                pending: false,
            };
        }

        var type = text(draft && draft.tipo);
        var number = text(draft && draft.numero);
        var code = DOCUMENT_CODES[type] || '';

        if (type === 'SIN DOCUMENTOS') {
            return { value: '99-…', message: 'Identificador final pendiente; no se puede guardar todavía.', pending: true };
        }

        if (code === '') {
            return { value: '—', message: 'Este tipo no tiene prefijo HCE propuesto.', pending: true };
        }

        return {
            value: code + '-' + (number || '…'),
            message: number ? 'Previsualización provisional; todavía no se persiste.' : 'Complete el documento.',
            pending: !number,
        };
    }

    function saveOutcome(attachToSchedule, existing) {
        if (attachToSchedule) {
            return {
                attach: true,
                message: 'Paciente guardado y asociado al Registro rápido. La cita todavía no se crea en este MVP.',
            };
        }

        return {
            attach: false,
            message: existing
                ? 'Paciente actualizado sin asociarlo al agendamiento actual.'
                : 'Paciente registrado sin asociarlo al agendamiento actual.',
        };
    }

    function toPayload(draft) {
        return {
            tipo_identificacion: text(draft.tipo),
            numero_identidad: text(draft.numero),
            nombre: text(draft.nombre),
            apellido_paterno: text(draft.apellido_paterno),
            apellido_materno: text(draft.apellido_materno),
            telefono: text(draft.telefono) || null,
            genero: text(draft.genero),
            fecha_nacimiento: text(draft.fecha_nacimiento) || null,
            channel_id: text(draft.channel_id) || null,
            email: text(draft.email) || null,
            direccion: text(draft.direccion) || null,
            estado_civil: text(draft.estado_civil) || null,
            ocupacion: text(draft.ocupacion) || null,
            grado_instruccion: text(draft.grado_instruccion) || null,
            familiar_contacto: text(draft.familiar_contacto) || null,
            interaction_medium_id: text(draft.interaction_medium_id) || null,
        };
    }

    return {
        canOpen: canOpen,
        open: open,
        openExisting: openExisting,
        cancel: cancel,
        discard: discard,
        edit: edit,
        applyReniec: applyReniec,
        hcePreview: hcePreview,
        saveOutcome: saveOutcome,
        toPayload: toPayload,
    };
}));
