(function () {
    'use strict';

    var root = document.getElementById('patients-workspace');
    var workspace = window.PatientWorkspace;

    if (!root || !workspace) {
        return;
    }

    var listSurface = document.getElementById('patients-list-surface');
    var recordSurface = document.getElementById('patients-record-surface');
    var tableRegion = document.getElementById('patients-table-region');
    var addButton = document.getElementById('patient-add');
    var backButton = document.getElementById('patient-back');
    var notice = document.getElementById('patient-record-notice');
    var hce = document.getElementById('patient-context-hce');
    var hceNote = document.getElementById('patient-hce-note');
    var status = document.getElementById('patient-context-status');
    var modeNote = document.getElementById('patient-context-mode');
    var reniecWrap = document.getElementById('patient-reniec-wrap');
    var reniecButton = document.getElementById('patient-reniec');
    var saveButton = document.getElementById('patient-save');
    var recordForm = document.getElementById('patient-record-form');
    var contextMenu = document.getElementById('patients-context-menu');
    var contextOpen = document.getElementById('patient-context-open');
    var contextAdd = document.getElementById('patient-context-add');
    var recordId = document.getElementById('patient-record-id');
    var documentType = document.getElementById('patient-document-type');
    var documentNumber = document.getElementById('patient-document-number');
    var selectedRow = null;
    var contextRow = null;
    var state = workspace.listState('', 0);
    var dirty = {};
    var hydrating = false;
    var canWrite = root.dataset.canWrite === '1';

    if (!canWrite) {
        Array.from(document.querySelectorAll('#patient-record-form input, #patient-record-form select'))
            .forEach(function (control) {
                control.disabled = true;
            });
        reniecButton.disabled = true;
    }

    var fields = {
        nombre: document.getElementById('patient-name'),
        apellido_paterno: document.getElementById('patient-paternal-name'),
        apellido_materno: document.getElementById('patient-maternal-name'),
        telefono: document.getElementById('patient-phone'),
        email: document.getElementById('patient-email'),
        fecha_nacimiento: document.getElementById('patient-birth-date'),
        genero: document.getElementById('patient-gender'),
        estado_civil: document.getElementById('patient-civil-status'),
        direccion: document.getElementById('patient-address'),
        channel_id: document.getElementById('patient-channel'),
        interaction_medium_id: document.getElementById('patient-interaction-medium'),
        ocupacion: document.getElementById('patient-occupation'),
        grado_instruccion: document.getElementById('patient-education'),
        familiar_contacto: document.getElementById('patient-family-contact'),
    };

    function value(valueToSet) {
        return valueToSet === null || valueToSet === undefined ? '' : String(valueToSet);
    }

    function setNotice(message, isError) {
        notice.textContent = message;
        notice.classList.toggle('is-error', Boolean(isError));
    }

    function snapshotList() {
        return workspace.listState(
            selectedRow ? selectedRow.dataset.patientId : '',
            tableRegion.scrollTop
        );
    }

    function selectRow(row) {
        if (!row || !row.dataset.patientId) {
            return;
        }

        if (selectedRow) {
            selectedRow.classList.remove('is-selected');
            selectedRow.setAttribute('aria-selected', 'false');
        }

        selectedRow = row;
        selectedRow.classList.add('is-selected');
        selectedRow.setAttribute('aria-selected', 'true');
    }

    function showRecord() {
        listSurface.hidden = true;
        recordSurface.hidden = false;
        backButton.focus();
    }

    function showList() {
        recordSurface.hidden = true;
        listSurface.hidden = false;
        state = workspace.back(state);
        tableRegion.scrollTop = state.scrollTop;

        if (state.selectedPatientId) {
            var row = tableRegion.querySelector('[data-patient-id="' + state.selectedPatientId + '"]');
            if (row) {
                selectRow(row);
                row.focus({ preventScroll: true });
                return;
            }
        }

        addButton.focus();
    }

    function hideContextMenu() {
        contextMenu.hidden = true;
        contextRow = null;
    }

    function showContextMenu(event, row) {
        contextRow = row || null;
        contextOpen.hidden = !contextRow;
        contextMenu.hidden = false;
        contextMenu.style.left = Math.min(event.clientX, window.innerWidth - 210) + 'px';
        contextMenu.style.top = Math.min(event.clientY, window.innerHeight - 130) + 'px';
        (contextRow ? contextOpen : contextAdd).focus();
    }

    function setFields(patient) {
        hydrating = true;
        documentType.value = value(patient.tipo_identificacion) || 'DNI';
        documentNumber.value = value(patient.numero_identidad);
        Object.keys(fields).forEach(function (field) {
            fields[field].value = value(patient[field]);
        });
        hydrating = false;
    }

    function clearFields() {
        setFields({ tipo_identificacion: 'DNI' });
        recordId.value = '';
        dirty = {};
    }

    function renderHce() {
        if (state.mode === 'existing') {
            hce.textContent = value(state.patient && state.patient.historia_clinica) || '—';
            hceNote.textContent = 'Valor almacenado, sin reformatear';
            return;
        }

        var preview = workspace.hcePreview(documentType.value, documentNumber.value);
        hce.textContent = preview.value;
        hceNote.textContent = preview.message;
    }

    function renderReniec() {
        reniecWrap.hidden = !workspace.canConsultReniec(state.mode, documentType.value);
    }

    function openNew() {
        state = workspace.openNew(snapshotList());
        clearFields();
        status.textContent = 'NUEVO';
        modeNote.textContent = 'Ficha nueva';
        saveButton.textContent = 'Registrar paciente';
        renderHce();
        renderReniec();
        setNotice(canWrite
            ? 'Complete los datos y use Registrar paciente para guardar.'
            : 'Acceso de solo lectura. Completar o corregir la ficha corresponde a Admisión, Recepción y Comercial.', false);
        showRecord();
    }

    async function openExisting(row) {
        selectRow(row);
        var previous = snapshotList();
        var patientId = row.dataset.patientId;
        var url = root.dataset.detailTemplate.replace('__PATIENT__', encodeURIComponent(patientId));

        setNotice('Cargando ficha…', false);

        try {
            var response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('No se pudo cargar la ficha.');
            }

            var payload = await response.json();
            var patient = payload.patient || {};
            state = workspace.openExisting(previous, patient);
            recordId.value = value(patient.id);
            dirty = {};
            setFields(patient);
            status.textContent = value(patient.estado) || '—';
            modeNote.textContent = 'Ficha maestra existente';
            saveButton.textContent = 'Guardar cambios';
            renderHce();
            renderReniec();
            setNotice(canWrite
                ? 'Ficha cargada. Guardar cambios actualiza únicamente datos maestros soportados.'
                : 'Consulta de ficha existente en modo de solo lectura.', false);
            showRecord();
        } catch (error) {
            setNotice('No se pudo cargar la ficha del paciente. Intente nuevamente.', true);
        }
    }

    function markDirty(event) {
        if (hydrating) {
            return;
        }

        var field = event.currentTarget.dataset.patientField;
        if (field) {
            dirty[field] = true;
        }
    }

    async function consultReniec() {
        if (!workspace.canConsultReniec(state.mode, documentType.value)) {
            return;
        }

        if (documentNumber.value.trim() === '') {
            setNotice('Ingrese un DNI antes de consultar RENIEC.', true);
            documentNumber.focus();
            return;
        }

        reniecButton.disabled = true;
        setNotice('Consultando RENIEC…', false);

        try {
            var response = await fetch(root.dataset.reniecUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    tipo_identificacion: 'DNI',
                    numero_identidad: documentNumber.value.trim(),
                }),
            });
            var payload = await response.json();

            if (!response.ok) {
                throw new Error('RENIEC no disponible.');
            }

            var draft = { manual: Object.assign({}, dirty) };
            Object.keys(fields).forEach(function (field) {
                draft[field] = fields[field].value;
            });

            var next = window.AgendaPatientDraft.applyReniec(draft, payload);
            hydrating = true;
            Object.keys(fields).forEach(function (field) {
                fields[field].value = value(next[field]);
            });
            hydrating = false;
            setNotice(next.message, payload.status !== 'prefilled');
        } catch (error) {
            setNotice('No se pudieron obtener datos de RENIEC. Puede continuar manualmente.', true);
        } finally {
            reniecButton.disabled = false;
        }
    }

    function patientPayload() {
        var payload = {
            tipo_identificacion: documentType.value,
            numero_identidad: documentNumber.value.trim(),
        };

        Object.keys(fields).forEach(function (field) {
            payload[field] = fields[field].value.trim();
            if (payload[field] === '') {
                payload[field] = null;
            }
        });

        return payload;
    }

    function firstValidationMessage(payload) {
        var errors = payload && payload.errors ? payload.errors : {};
        var key = Object.keys(errors)[0];
        return key && errors[key] && errors[key][0]
            ? errors[key][0]
            : 'Revise los datos obligatorios e intente nuevamente.';
    }

    async function savePatient(event) {
        event.preventDefault();

        if (!canWrite) {
            setNotice('Completar o corregir la ficha corresponde a Admisión, Recepción y Comercial.', true);
            return;
        }

        if (documentType.value === 'SIN DOCUMENTOS') {
            setNotice('El identificador final para pacientes sin documentos sigue pendiente de negocio; no se guardó.', true);
            return;
        }

        var isExisting = state.mode === 'existing' && recordId.value !== '';
        var url = isExisting
            ? root.dataset.updateTemplate.replace('__PATIENT__', encodeURIComponent(recordId.value))
            : root.dataset.storeUrl;

        saveButton.disabled = true;
        setNotice(isExisting ? 'Guardando cambios…' : 'Registrando paciente…', false);

        try {
            var response = await fetch(url, {
                method: isExisting ? 'PUT' : 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(patientPayload()),
            });
            var payload = await response.json();

            if (!response.ok) {
                throw new Error(firstValidationMessage(payload));
            }

            var patient = payload.patient || {};
            state.mode = 'existing';
            state.patient = patient;
            state.patientId = value(patient.id);
            recordId.value = value(patient.id);
            status.textContent = value(patient.estado) || 'ACTIVO';
            modeNote.textContent = 'Ficha maestra existente';
            saveButton.textContent = 'Guardar cambios';
            renderHce();
            renderReniec();
            setNotice(payload.message || 'Paciente guardado correctamente.', false);
        } catch (error) {
            setNotice(error.message || 'No se pudo guardar el paciente.', true);
        } finally {
            saveButton.disabled = !canWrite;
        }
    }

    tableRegion.addEventListener('click', function (event) {
        var row = event.target.closest('[data-patient-id]');
        if (row) {
            selectRow(row);
        }
    });

    tableRegion.addEventListener('contextmenu', function (event) {
        event.preventDefault();
        var row = event.target.closest('[data-patient-id]');
        if (row) {
            selectRow(row);
        }
        showContextMenu(event, row);
    });

    tableRegion.addEventListener('keydown', function (event) {
        var row = event.target.closest('[data-patient-id]');
        if (row && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            openExisting(row);
        }
    });

    addButton.addEventListener('click', openNew);
    backButton.addEventListener('click', showList);
    reniecButton.addEventListener('click', consultReniec);
    recordForm.addEventListener('submit', savePatient);
    contextOpen.addEventListener('click', function () {
        var row = contextRow;
        hideContextMenu();
        if (row) {
            openExisting(row);
        }
    });
    contextAdd.addEventListener('click', function () {
        hideContextMenu();
        openNew();
    });
    document.addEventListener('click', function (event) {
        if (!contextMenu.hidden && !contextMenu.contains(event.target)) {
            hideContextMenu();
        }
    });

    documentType.addEventListener('change', function () {
        dirty.tipo_identificacion = true;
        renderHce();
        renderReniec();
    });
    documentNumber.addEventListener('input', function () {
        dirty.numero_identidad = true;
        renderHce();
    });

    Object.keys(fields).forEach(function (field) {
        fields[field].dataset.patientField = field;
        fields[field].addEventListener('input', markDirty);
        fields[field].addEventListener('change', markDirty);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !recordSurface.hidden) {
            showList();
        } else if (event.key === 'Escape') {
            hideContextMenu();
        }
    });
}());
