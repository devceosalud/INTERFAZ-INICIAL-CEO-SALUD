(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.PatientWorkspace = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function text(value) {
        return value === null || value === undefined ? '' : String(value).trim();
    }

    function hcePreview(documentType, documentNumber, supportedDocumentTypes) {
        var type = text(documentType);
        var number = text(documentNumber);
        var supported = Array.isArray(supportedDocumentTypes)
            && supportedDocumentTypes.indexOf(type) !== -1;

        if (type === 'SIN DOCUMENTOS') {
            return {
                value: '—',
                pending: true,
                message: 'La regla HCE para pacientes sin documentos está pendiente de negocio.',
            };
        }

        if (!supported) {
            return { value: '—', pending: true, message: 'Este tipo todavía no tiene una regla HCE aprobada.' };
        }

        if (number === '') {
            return {
                value: '—',
                pending: true,
                message: 'Complete el documento; la HCE se asignará al guardar.',
            };
        }

        return {
            value: 'Se asignará al guardar',
            pending: true,
            message: 'El backend devolverá la HCE real persistida.',
        };
    }

    function listState(selectedPatientId, scrollTop) {
        return {
            surface: 'list',
            selectedPatientId: text(selectedPatientId),
            scrollTop: Number(scrollTop) || 0,
        };
    }

    function openNew(previous) {
        return {
            surface: 'record',
            mode: 'new',
            patientId: '',
            previous: previous || listState('', 0),
        };
    }

    function openExisting(previous, patient) {
        return {
            surface: 'record',
            mode: 'existing',
            patientId: text(patient && patient.id),
            patient: patient || {},
            previous: previous || listState('', 0),
        };
    }

    function back(recordState) {
        return recordState && recordState.previous
            ? recordState.previous
            : listState('', 0);
    }

    function canConsultReniec(mode, documentType) {
        return mode === 'new' && text(documentType) === 'DNI';
    }

    function blankForm() {
        return {
            patientId: '',
            tipo_identificacion: 'DNI',
            numero_identidad: '',
            nombre: '',
            apellido_paterno: '',
            apellido_materno: '',
            telefono_prefijo: '+51',
            telefono_numero: '',
            telefono_sin_separar: false,
            email: '',
            fecha_nacimiento: '',
            genero: '',
            estado_civil: '',
            direccion: '',
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
            notice: '',
        };
    }

    function afterCreate() {
        return {
            surface: 'list',
            mode: 'created',
            patientId: '',
            notice: '',
        };
    }

    function placeCreated(rows, patient) {
        var id = text(patient && patient.id);
        var rest = (rows || []).filter(function (row) {
            return text(row && row.id) !== id;
        });

        rest.unshift({
            id: id,
            highlight: true,
            registro: '—',
            hce: text(patient && patient.historia_clinica) || '—',
            documento: text(patient && patient.tipo_identificacion) + ' ' + text(patient && patient.numero_identidad),
            nombre: [patient && patient.apellido_paterno, patient && patient.apellido_materno, patient && patient.nombre]
                .map(text)
                .filter(Boolean)
                .join(' '),
            fecha: '—',
            estado: text(patient && patient.estado) || 'ACTIVO',
        });

        return rest;
    }

    return {
        hcePreview: hcePreview,
        listState: listState,
        openNew: openNew,
        openExisting: openExisting,
        back: back,
        canConsultReniec: canConsultReniec,
        blankForm: blankForm,
        afterCreate: afterCreate,
        placeCreated: placeCreated,
    };
}));
