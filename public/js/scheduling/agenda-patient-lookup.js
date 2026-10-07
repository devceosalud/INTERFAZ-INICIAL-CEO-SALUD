(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.AgendaPatientLookup = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function blank() {
        return {
            status: '',
            patientId: '',
            tipo: '',
            numero: '',
            name: '',
            clinicalRecord: '',
            message: '',
            showRegister: false,
        };
    }

    function text(value) {
        return value === null || value === undefined ? '' : String(value).trim();
    }

    /**
     * A document edit drops the previous patient before the next lookup.
     * The same type and number keep the identity already confirmed.
     */
    function edited(current, tipo, numero) {
        tipo = text(tipo);
        numero = text(numero);

        if (current && current.tipo === tipo && current.numero === numero && current.status) {
            return current;
        }

        const cleared = blank();
        cleared.tipo = tipo;
        cleared.numero = numero;

        return cleared;
    }

    function fullName(patient) {
        return [patient.nombre, patient.apellido_paterno, patient.apellido_materno]
            .map(text)
            .filter(function (part) { return part !== ''; })
            .join(' ');
    }

    function matchesDocument(identity, tipo, numero) {
        return Boolean(identity && identity.status === 'found' && identity.patientId && text(numero)
            && identity.tipo === text(tipo) && identity.numero === text(numero));
    }


    /**
     * Applies a local lookup without replacing the schedule already chosen.
     */
    function present(response, schedule) {
        schedule = schedule || {};
        const kept = {
            doctor: schedule.doctor,
            specialty: schedule.specialty,
            date: schedule.date,
            time: schedule.time,
        };
        const status = response && response.status;
        const patient = response && response.patient;

        if (status === 'found' && patient) {
            return Object.assign(kept, {
                status: 'found',
                patientId: String(patient.patient_id),
                tipo: text(patient.tipo_identificacion),
                numero: text(patient.numero_identidad),
                name: fullName(patient),
                clinicalRecord: text(patient.historia_clinica) || '—',
                message: 'Paciente registrado',
                showRegister: false,
            });
        }

        if (status === 'inactive' && patient) {
            return Object.assign(kept, {
                status: 'inactive',
                patientId: '',
                tipo: text(patient.tipo_identificacion),
                numero: text(patient.numero_identidad),
                name: fullName(patient),
                clinicalRecord: text(patient.historia_clinica) || '—',
                message: 'Paciente registrado, actualmente inactivo.',
                showRegister: false,
            });
        }

        if (status === 'document_conflict') {
            return Object.assign(kept, blank(), {
                status: 'document_conflict',
                message: 'El número de documento ya está registrado con otro tipo de identificación.',
                showRegister: false,
            });
        }

        return Object.assign(kept, blank(), {
            status: 'not_found',
            message: 'Paciente no registrado',
            showRegister: true,
        });
    }

    return {
        blank: blank,
        edited: edited,
        present: present,
        matchesDocument: matchesDocument,
    };
}));
