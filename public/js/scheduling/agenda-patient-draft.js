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
            telefono_prefijo: '+51',
            telefono_numero: '',
            telefono_sin_separar: false,
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
            registrar_responsable: false,
            responsable_parentesco: 'PAPA',
            responsable_nombres: '',
            responsable_telefono: '',
            responsable_tipo_identificacion: 'DNI',
            responsable_numero_identidad: '',
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

        var opened = Object.assign(scheduleOf(schedule), empty(
            patient.tipo_identificacion,
            patient.numero_identidad
        ), patient, {
            open: true,
            patientId: text(patient.id || patient.patient_id),
            reniecOffered: false,
            manual: {},
            message: 'Ficha existente cargada. Revise los datos antes de guardar.',
        });
        var phoneApi = typeof globalThis !== 'undefined' ? globalThis.PatientPhone : null;
        var phone = phoneApi
            ? phoneApi.split(patient.telefono)
            : { parsed: false, prefijo: '+51', numero: '', raw: text(patient.telefono) };

        opened.telefono_prefijo = phone.prefijo;
        opened.telefono_numero = phone.parsed ? phone.numero : phone.raw;
        opened.telefono_sin_separar = !phone.parsed;
        opened.registrar_responsable = Boolean(patient.responsable);
        if (patient.responsable) {
            opened.responsable_parentesco = patient.responsable.parentezco || 'PAPA';
            opened.responsable_nombres = patient.responsable.nombres || '';
            opened.responsable_telefono = patient.responsable.telefono || '';
            opened.responsable_tipo_identificacion = patient.responsable.tipo_identificacion || 'DNI';
            opened.responsable_numero_identidad = patient.responsable.numero_identidad || '';
        }

        return opened;
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

    function hcePreview(draft, supportedDocumentTypes) {
        if (text(draft && draft.patientId) !== '') {
            return {
                value: text(draft.historia_clinica) || '—',
                message: 'HCE almacenada; no se reformatea.',
                pending: false,
            };
        }

        var type = text(draft && draft.tipo);
        var number = text(draft && draft.numero);
        var supported = Array.isArray(supportedDocumentTypes)
            && supportedDocumentTypes.indexOf(type) !== -1;

        if (type === 'SIN DOCUMENTOS') {
            return { value: '—', message: 'La regla HCE para pacientes sin documentos está pendiente; no se puede guardar todavía.', pending: true };
        }

        if (!supported) {
            return { value: '—', message: 'Este tipo todavía no tiene una regla HCE aprobada.', pending: true };
        }

        return {
            value: number ? 'Se asignará al guardar' : '—',
            message: number ? 'El backend devolverá la HCE real persistida.' : 'Complete el documento; la HCE se asignará al guardar.',
            pending: true,
        };
    }

    function saveOutcome(attachToSchedule, existing) {
        if (attachToSchedule) {
            return {
                attach: true,
                message: 'Paciente guardado y asociado al Registro rápido. Preparando la cita.',
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
            telefono_prefijo: text(draft.telefono_prefijo) || '+51',
            telefono_numero: text(draft.telefono_numero) || null,
            telefono_sin_separar: Boolean(draft.telefono_sin_separar),
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
            registrar_responsable: Boolean(draft.registrar_responsable),
            responsable_parentesco: text(draft.responsable_parentesco) || null,
            responsable_nombres: text(draft.responsable_nombres) || null,
            responsable_telefono: text(draft.responsable_telefono) || null,
            responsable_tipo_identificacion: text(draft.responsable_tipo_identificacion) || null,
            responsable_numero_identidad: text(draft.responsable_numero_identidad) || null,
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
        emailMessage: function (value) {
            var phoneApi = typeof globalThis !== 'undefined' ? globalThis.PatientPhone : null;

            return phoneApi ? phoneApi.emailMessage(value) : '';
        },
    };
}));
