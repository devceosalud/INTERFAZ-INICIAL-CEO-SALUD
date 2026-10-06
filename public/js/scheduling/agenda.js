/*
 * MVP-2C — agenda operativa.
 * El backend resuelve disponibilidad; esta capa coordina filtros e interacción.
 */
document.addEventListener('DOMContentLoaded', function () {
    const board = document.getElementById('agenda-board');

    if (!board) {
        return;
    }

    const VIEW_TO_FULLCALENDAR = {
        dia: 'timeGridDay',
        semana: 'timeGridWeek',
        mes: 'dayGridMonth',
    };
    const MAX_SELECTED_DOCTORS = 12;
    const selectionModel = window.AgendaSelection;
    const lookupModel = window.AgendaPatientLookup;
    const draftModel = window.AgendaPatientDraft;
    const appointmentCreateModel = window.AgendaAppointmentCreate;
    const dayGridModel = window.AgendaDayGrid;
    const weekEventModel = window.AgendaWeekEvent;
    const weekBackgroundModel = window.AgendaWeekBackground;
    const weekSlotModel = window.AgendaWeekSlots;
    const DAY_FORMAT = new Intl.DateTimeFormat('es-PE', {
        weekday: 'short', day: '2-digit', month: 'short', year: 'numeric',
    });
    const MONTH_FORMAT = new Intl.DateTimeFormat('es-PE', { month: 'long', year: 'numeric' });

    const el = {
        site: document.getElementById('agenda-site'),
        specialty: document.getElementById('agenda-specialty'),
        date: document.getElementById('agenda-date'),
        prev: document.getElementById('agenda-prev'),
        next: document.getElementById('agenda-next'),
        today: document.getElementById('agenda-today'),
        viewButtons: Array.from(document.querySelectorAll('.agenda-btn--view')),
        doctorFilter: document.getElementById('agenda-doctor-filter'),
        doctorFilterSummary: document.getElementById('agenda-doctor-filter-summary'),
        doctorSearch: document.getElementById('agenda-doctor-search'),
        doctorReset: document.getElementById('agenda-doctor-reset'),
        doctorRows: Array.from(document.querySelectorAll('[data-doctor-row]')),
        doctorChecks: Array.from(document.querySelectorAll('.agenda-doctor-check')),
        compareToggle: document.getElementById('agenda-compare-toggle'),
        rangeLabel: document.getElementById('agenda-range-label'),
        compareHint: document.getElementById('agenda-compare-hint'),
        detailHint: document.getElementById('agenda-detail-hint'),
        loadState: document.getElementById('agenda-load-state'),
        dayGrid: document.getElementById('agenda-day-grid'),
        dayGridBody: document.getElementById('agenda-day-grid-body'),
        calendar: document.getElementById('agenda-calendar'),
        comparison: document.getElementById('agenda-comparison'),
        comparisonBody: document.getElementById('agenda-comparison-body'),
        comparisonOpen: document.getElementById('agenda-comparison-open'),
        miniMonth: document.getElementById('agenda-mini-month'),
        miniGrid: document.getElementById('agenda-mini-grid'),
        miniPrev: document.getElementById('agenda-mini-prev'),
        miniNext: document.getElementById('agenda-mini-next'),
        context: document.getElementById('agenda-context'),
        contextEmpty: document.getElementById('agenda-context-empty'),
        contextList: document.getElementById('agenda-context-list'),
        contextNote: document.getElementById('agenda-context-note'),
        contextState: document.getElementById('agenda-context-state'),
        contextDoctor: document.getElementById('agenda-context-doctor'),
        contextSpecialty: document.getElementById('agenda-context-specialty'),
        contextDate: document.getElementById('agenda-context-date'),
        contextTime: document.getElementById('agenda-context-time'),
        contextDuration: document.getElementById('agenda-context-duration'),
        contextSite: document.getElementById('agenda-context-site'),
        contextPatient: document.getElementById('agenda-context-patient'),
        contextService: document.getElementById('agenda-context-service'),
        contextPayment: document.getElementById('agenda-context-payment'),
        contextClinicalRecord: document.getElementById('agenda-context-clinical-record'),
        rowHead: document.getElementById('agenda-row-head'),
        quickMode: document.getElementById('agenda-quick-mode'),
        patientLookup: document.getElementById('agenda-patient-lookup'),
        documentType: document.getElementById('agenda-document-type'),
        documentNumber: document.getElementById('agenda-document-number'),
        documentSearch: document.getElementById('agenda-document-search'),
        lookupResult: document.getElementById('agenda-patient-lookup-result'),
        lookupClinicalRecord: document.getElementById('agenda-lookup-clinical-record'),
        patientRegister: document.getElementById('agenda-patient-register'),
        patientModal: document.getElementById('agenda-patient-modal'),
        patientModalTitle: document.getElementById('agenda-patient-modal-title'),
        patientDraft: document.getElementById('agenda-patient-draft'),
        draftType: document.getElementById('agenda-draft-type'),
        draftNumber: document.getElementById('agenda-draft-number'),
        draftNombre: document.getElementById('agenda-draft-nombre'),
        draftApellidoPaterno: document.getElementById('agenda-draft-apellido-paterno'),
        draftApellidoMaterno: document.getElementById('agenda-draft-apellido-materno'),
        draftPhonePrefix: document.getElementById('agenda-draft-phone-prefix'),
        draftTelefono: document.getElementById('agenda-draft-telefono'),
        draftEmail: document.getElementById('agenda-draft-email'),
        draftFechaNacimiento: document.getElementById('agenda-draft-fecha-nacimiento'),
        draftGenero: document.getElementById('agenda-draft-genero'),
        draftEstadoCivil: document.getElementById('agenda-draft-estado-civil'),
        draftDireccion: document.getElementById('agenda-draft-direccion'),
        draftChannel: document.getElementById('agenda-draft-channel'),
        draftInteractionMedium: document.getElementById('agenda-draft-interaction-medium'),
        draftOcupacion: document.getElementById('agenda-draft-ocupacion'),
        draftGradoInstruccion: document.getElementById('agenda-draft-grado-instruccion'),
        draftFamiliarContacto: document.getElementById('agenda-draft-familiar-contacto'),
        draftRegisterResponsible: document.getElementById('agenda-draft-register-responsible'),
        draftResponsible: document.getElementById('agenda-draft-responsible'),
        draftResponsibleRelationship: document.getElementById('agenda-draft-responsible-relationship'),
        draftResponsibleName: document.getElementById('agenda-draft-responsible-name'),
        draftResponsiblePhone: document.getElementById('agenda-draft-responsible-phone'),
        draftResponsibleDocumentType: document.getElementById('agenda-draft-responsible-document-type'),
        draftResponsibleDocumentNumber: document.getElementById('agenda-draft-responsible-document-number'),
        draftCommercialOwner: document.getElementById('agenda-draft-commercial-owner'),
        draftHce: document.getElementById('agenda-draft-hce'),
        draftHceNote: document.getElementById('agenda-draft-hce-note'),
        draftContextDoctor: document.getElementById('agenda-draft-context-doctor'),
        draftContextDate: document.getElementById('agenda-draft-context-date'),
        draftContextTime: document.getElementById('agenda-draft-context-time'),
        draftContextSite: document.getElementById('agenda-draft-context-site'),
        draftMessage: document.getElementById('agenda-draft-message'),
        draftRuc: document.getElementById('agenda-draft-ruc'),
        draftReniec: document.getElementById('agenda-draft-reniec'),
        draftCancel: document.getElementById('agenda-draft-cancel'),
        draftClose: document.getElementById('agenda-draft-close'),
        draftSave: document.getElementById('agenda-draft-save'),
        draftSaveSchedule: document.getElementById('agenda-draft-save-schedule'),
        patientTabs: Array.from(document.querySelectorAll('[data-patient-tab]')),
        patientPanels: Array.from(document.querySelectorAll('[data-patient-panel]')),
        quickPatientId: document.getElementById('agenda-quick-patient-id'),
        quickPatientIdDisplay: document.getElementById('agenda-quick-patient-id-display'),
        quickPatientState: document.getElementById('agenda-quick-patient-state'),
        quickDoctor: document.getElementById('agenda-quick-doctor'),
        quickSpecialty: document.getElementById('agenda-quick-specialty'),
        quickDate: document.getElementById('agenda-quick-date'),
        quickTime: document.getElementById('agenda-quick-time'),
        quickDuration: document.getElementById('agenda-quick-duration'),
        quickSite: document.getElementById('agenda-quick-site'),
        quickService: document.getElementById('agenda-quick-service'),
        quickPrice: document.getElementById('agenda-quick-price'),
        quickStatus: document.getElementById('agenda-quick-status'),
        quickPayment: document.getElementById('agenda-quick-payment'),
        quickClinicalRecord: document.getElementById('agenda-quick-clinical-record'),
        schedulerUser: document.getElementById('agenda-scheduler-user'),
        commercialOwner: document.getElementById('agenda-commercial-owner'),
        serviceSelect: document.getElementById('agenda-service-select'),
        responsibleSelect: document.getElementById('agenda-responsible-select'),
        appointmentSubmit: document.getElementById('agenda-appointment-submit'),
        quickMessage: document.getElementById('agenda-quick-message'),
        completeRegistration: document.getElementById('agenda-complete-registration'),
        completeRegistrationHelp: document.getElementById('agenda-complete-registration-help'),
        totalFree: document.getElementById('agenda-total-free'),
        totalBusy: document.getElementById('agenda-total-busy'),
        totalMinutes: document.getElementById('agenda-total-minutes'),
        overlapForm: document.getElementById('agenda-overlap-form'),
        overlapStart: document.getElementById('agenda-overlap-start'),
        overlapEnd: document.getElementById('agenda-overlap-end'),
        overlapResult: document.getElementById('agenda-overlap-result'),
        scheduleLink: document.getElementById('agenda-schedule-link'),
    };
    const hceSupportedTypes = JSON.parse(el.patientDraft.dataset.hceSupportedTypes || '[]');

    const state = {
        view: 'dia',
        date: board.dataset.today,
        miniAnchor: firstOfMonth(board.dataset.today),
        compare: false,
        comparisonDoctorId: null,
        legend: {},
        selection: null,
        payload: null,
        visibleEvents: [],
        selectedSlotEvent: null,
        requestController: null,
        identity: lookupModel.blank(),
        draft: null,
        appointmentBusy: false,
        serviceDoctorId: null,
        revealAppointmentId: null,
        holdingResponsible: false,
        responsibleBeforeExisting: '',
    };
    const authenticatedScheduler = el.schedulerUser
        ? el.schedulerUser.textContent.trim()
        : 'Usuario autenticado';
    const responsibleLockedByPermission = Boolean(el.responsibleSelect && el.responsibleSelect.disabled);
    const canWritePatients = el.patientDraft.dataset.canWrite === '1';
    const canCreateAppointments = board.dataset.canCreateAppointments === '1';
    const canReschedule = board.dataset.canReschedule === '1';
    const canAdditional = board.dataset.canAdditional === '1';
    const rescheduleForm = document.getElementById('agenda-reschedule-form');
    const offHoursStart = document.getElementById('agenda-off-hours-start');
    const draftService = document.getElementById('agenda-draft-service');
    const additionalStart = document.getElementById('agenda-additional-start');
    const doctorServices = JSON.parse(document.getElementById('agenda-doctor-services').textContent || '{}');

    const calendar = new FullCalendar.Calendar(el.calendar, {
        initialView: VIEW_TO_FULLCALENDAR.semana,
        initialDate: state.date,
        locale: 'es',
        // El bundle local no trae el locale `es`, así que firstDay queda en domingo.
        // Semana lo fuerza a lunes al renderizar; Mes lo devuelve a 0 para no cambiar su grilla.
        firstDay: 1,
        headerToolbar: false,
        height: 500,
        expandRows: false,
        allDaySlot: false,
        nowIndicator: true,
        slotEventOverlap: false,
        // Transparent free-slot hit events must not reject a drop before backend validation.
        eventOverlap: true,
        editable: canReschedule,
        eventDurationEditable: false,
        eventDrop: function (info) {
            const date = info.event.start;
            moveAppointment(info.event.extendedProps, toIso(date),
                String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0'), info.revert);
        },
        dayMaxEventRows: 3,
        moreLinkClick: 'popover',
        displayEventTime: false,
        eventOrder: 'start,doctor',
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
        // Cadencia exclusivamente visual. La altura real del evento sigue determinada por
        // start/end (15, 20, 30, 45, 60 minutos o cualquier duración válida del backend).
        slotDuration: '00:' + String(board.dataset.gridMinutes || 20).padStart(2, '0') + ':00',
        slotLabelInterval: '00:' + String(board.dataset.gridMinutes || 20).padStart(2, '0') + ':00',
        dayHeaderFormat: { weekday: 'short', day: '2-digit', month: '2-digit' },
        dayHeaderClassNames: selectedWeekDayClass,
        dayCellClassNames: selectedWeekDayClass,
        eventContent: renderEventContent,
        dateClick: function (info) {
            if (state.view === 'mes') {
                setView('dia');
                setDate(info.dateStr);
                return;
            }

            const context = availableContextAt(info.date);
            if (context) {
                selectInterval(context, null);
            }
        },
        eventDidMount: function (info) {
            const context = info.event.extendedProps;

            if (context.tipo_contexto === 'slot_libre') {
                info.el.setAttribute(
                    'aria-label',
                    [context.hora_inicio, context.hora_fin, context.minutos ? context.minutos + ' min' : '', context.doctor, context.fecha]
                        .filter(Boolean)
                        .join(', ')
                );
                info.el.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    selectInterval(context, null);
                });
                return;
            }

            if (context.tipo_contexto !== 'cita_existente') {
                return;
            }

            info.el.setAttribute(
                'aria-label',
                [
                    context.hora_inicio,
                    context.estado_cita,
                    context.estado_pagado,
                    context.historia_clinica,
                    context.paciente,
                    context.servicio,
                    context.doctor,
                    context.fecha,
                ].filter(Boolean).join(', ')
            );
            info.el.tabIndex = 0;
            info.el.addEventListener('click', function () {
                selectInterval(context, info.el);
            });
            info.el.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectInterval(context, info.el);
                }
            });
        },
    });

    calendar.render();

    function parseIso(value) {
        const parts = value.split('-').map(Number);
        return new Date(parts[0], parts[1] - 1, parts[2], 12, 0, 0);
    }

    function toIso(date) {
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0'),
        ].join('-');
    }

    function firstOfMonth(value) {
        const date = parseIso(value);
        return new Date(date.getFullYear(), date.getMonth(), 1, 12, 0, 0);
    }

    function selectedDoctorIds() {
        return el.doctorChecks.filter((check) => check.checked).map((check) => check.value);
    }

    function isComparing() {
        return state.compare && selectedDoctorIds().length > 1 && state.view !== 'mes';
    }

    function renderEventContent(info) {
        const context = info.event.extendedProps;
        const wrapper = document.createElement('span');

        wrapper.className = 'agenda-event';

        if (state.view === 'mes') {
            const line = document.createElement('span');
            const status = document.createElement('span');
            line.className = 'agenda-event__line';
            status.className = 'agenda-event__state';
            line.textContent = context.doctor || '';
            status.textContent = (context.libres || 0) + ' libres / ' + (context.ocupadas || 0) + ' ocupadas';
            const specials = context.especiales || {};
            if (specials.adicionales) { status.textContent += ' · ' + specials.adicionales + ' ADICIONAL'; }
            if (specials.fuera_horario) { status.textContent += ' · ' + specials.fuera_horario + ' FH'; }
            wrapper.appendChild(line);
            wrapper.appendChild(status);

            return { domNodes: [wrapper] };
        }

        if (context.tipo_contexto === 'slot_libre') {
            wrapper.classList.add('agenda-slot-hit__face');
            return { domNodes: [wrapper] };
        }

        if (context.tipo_contexto === 'cita_existente') {
            const presentation = weekEventModel.build(context);
            const primary = document.createElement('span');
            const time = document.createElement('strong');
            const patient = document.createElement('span');

            wrapper.classList.add('agenda-event--appointment', 'agenda-week-event');
            wrapper.classList.toggle('agenda-week-event--compact', presentation.compact);
            primary.className = 'agenda-week-event__primary';
            time.className = 'agenda-week-event__time';
            patient.className = 'agenda-week-event__patient';
            time.textContent = presentation.start;
            if (presentation.end) {
                const timeEnd = document.createElement('span');
                timeEnd.className = 'agenda-week-event__time-end';
                timeEnd.textContent = '–' + presentation.end;
                time.appendChild(timeEnd);
            }
            patient.textContent = (context.leyenda === 'ADICIONAL' ? 'ADICIONAL · ' : (context.leyenda === 'FUERA_HORARIO' ? 'FH · ' : '')) + presentation.patient;
            primary.appendChild(time);
            primary.appendChild(patient);
            wrapper.appendChild(primary);

            if (presentation.service) {
                const service = document.createElement('span');
                service.className = 'agenda-week-event__service';
                service.textContent = presentation.service;
                wrapper.appendChild(service);
            }

        } else {
            wrapper.classList.add('agenda-event--slot-background');
        }

        return { domNodes: [wrapper] };
    }

    function feedUrl() {
        const url = new URL(board.dataset.feed, window.location.origin);
        url.searchParams.set('vista', state.view);
        url.searchParams.set('fecha', state.date);

        if (el.site.value) {
            url.searchParams.set('site_id', el.site.value);
        }

        if (el.specialty.value) {
            url.searchParams.set('specialty_id', el.specialty.value);
        }

        selectedDoctorIds().forEach((id) => url.searchParams.append('doctor_id[]', id));

        return url.toString();
    }

    function normalise(value) {
        return (value || '').toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function applyDoctorVisibility() {
        const specialty = el.specialty.value;
        const search = normalise(el.doctorSearch.value);

        el.doctorRows.forEach((row) => {
            const matchesSpecialty = !specialty || row.dataset.specialty === specialty;
            const matchesSearch = !search || normalise(row.dataset.search).includes(search);
            row.hidden = !(matchesSpecialty && matchesSearch);
        });
    }

    function updateDoctorFilterSummary() {
        const selected = selectedDoctorIds();

        if (selected.length === 0) {
            const first = state.payload && state.payload.profesionales ? state.payload.profesionales[0] : null;
            el.doctorFilterSummary.textContent = first ? first.nombre : 'Sin médicos';
            return;
        }

        if (selected.length === 1) {
            const row = el.doctorRows.find((candidate) => candidate.dataset.doctorId === selected[0]);
            el.doctorFilterSummary.textContent = row
                ? row.querySelector('.agenda-doctor-row__name').textContent.trim()
                : '1 médico';
            return;
        }

        el.doctorFilterSummary.textContent = selected.length + ' médicos en comparación';
    }

    async function load() {
        if (state.requestController) {
            state.requestController.abort();
        }

        state.requestController = new AbortController();
        board.classList.add('is-loading');
        hideNotice();

        try {
            const response = await fetch(feedUrl(), {
                headers: { Accept: 'application/json' },
                signal: state.requestController.signal,
            });

            if (!response.ok) {
                throw new Error('El servidor respondió ' + response.status);
            }

            state.payload = await response.json();
            state.legend = {};
            (state.payload.leyenda || []).forEach((entry) => {
                state.legend[entry.clave] = entry;
            });

            clearSelection();
            renderRange();
            renderDoctors();
            renderMiniCalendar();
            renderMainSurface();
            renderTotals();
            updateDoctorFilterSummary();
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
                showNotice('No se pudo cargar la agenda. Revise la conexión e intente nuevamente.');
            }
        } finally {
            board.classList.remove('is-loading');
        }
    }

    function showNotice(message) {
        el.loadState.textContent = message;
        el.loadState.hidden = false;
    }

    function hideNotice() {
        el.loadState.hidden = true;
        el.loadState.textContent = '';
    }

    function renderRange() {
        el.rangeLabel.textContent = state.payload.rango.etiqueta;
        const shown = state.payload.profesionales_mostrados;
        const matching = state.payload.profesionales_coincidentes;
        el.compareHint.textContent = state.payload.truncado
            ? shown + ' de ' + matching + ' visibles'
            : shown + (shown === 1 ? ' médico visible' : ' médicos visibles');

        const first = effectiveProfessionals()[0];
        if (isComparing()) {
            document.getElementById('agenda-title').textContent = 'Comparación de carga · ' + selectedDoctorIds().length + ' médicos';
        } else if (state.view === 'mes') {
            document.getElementById('agenda-title').textContent = 'Resumen mensual';
        } else {
            document.getElementById('agenda-title').textContent = first
                ? 'Agenda horaria · ' + first.nombre
                : 'Agenda horaria';
        }

        if (isComparing()) {
            el.detailHint.textContent = 'Seleccione una fila y abra su agenda para operar sobre los intervalos.';
        } else if (state.view === 'dia') {
            el.detailHint.textContent = 'Vista principal: seleccione un intervalo para preparar el registro rápido.';
        } else if (state.view === 'semana') {
            el.detailHint.textContent = 'Vista secundaria para revisar continuidad semanal.';
        } else {
            el.detailHint.textContent = 'Resumen y navegación: seleccione un día para abrir su agenda.';
        }
    }

    function renderDoctors() {
        const byId = {};
        const selected = selectedDoctorIds();
        const defaultDoctorId = state.payload.profesionales[0]
            ? String(state.payload.profesionales[0].id)
            : null;

        (state.payload.profesionales || []).forEach((professional) => {
            byId[String(professional.id)] = professional;
        });

        el.doctorRows.forEach((row) => {
            const professional = byId[row.dataset.doctorId];
            const metrics = row.querySelector('[data-doctor-metrics]');
            row.classList.toggle('is-selected', row.querySelector('.agenda-doctor-check').checked);
            row.classList.toggle('is-focused', selected.length === 0 && row.dataset.doctorId === defaultDoctorId);
            row.classList.toggle('is-outside-result', !professional);

            if (!professional) {
                metrics.textContent = '—';
                metrics.title = 'Fuera del resultado actual';
                return;
            }

            metrics.textContent = professional.resumen.libres + ' L / ' + professional.resumen.ocupadas + ' O';
            metrics.title = professional.resumen.libres + ' libres, ' + professional.resumen.ocupadas + ' ocupadas';
        });
    }

    function renderMainSurface() {
        board.dataset.telemetryView = state.view;
        const comparing = isComparing();
        el.comparison.hidden = !comparing;
        el.dayGrid.hidden = comparing || state.view !== 'dia';
        el.calendar.hidden = comparing || state.view === 'dia';

        if (comparing) {
            renderComparison();
            return;
        }

        if (state.view === 'dia') {
            renderDayGrid();
            return;
        }

        renderCalendar();
    }

    function filteredEvents() {
        let events = state.payload.eventos || [];
        const effective = effectiveProfessionals();

        if (effective.length === 1 && state.payload.profesionales.length > 1) {
            const defaultDoctorId = String(effective[0].id);
            events = events.filter((event) => String(event.extendedProps.doctor_id) === defaultDoctorId);
        }

        return events;
    }

    function selectedWeekDayClass(arg) {
        if (state.view !== 'semana') {
            return [];
        }

        return toIso(arg.date) === state.date ? ['agenda-week-day--selected'] : [];
    }

    function renderCalendar() {
        calendar.setOption('firstDay', state.view === 'semana' ? 1 : 0);
        calendar.changeView(VIEW_TO_FULLCALENDAR[state.view], state.date);
        calendar.setOption('allDaySlot', state.view === 'mes');
        calendar.removeAllEvents();
        const visibleHours = applyVisibleHours();

        const events = filteredEvents();
        state.visibleEvents = events;
        events.forEach((event) => {
            if (event.extendedProps.tipo_contexto === 'slot_libre') {
                return;
            }

            const classNames = event.extendedProps.tipo_contexto === 'cita_existente'
                ? ['agenda-calendar-event--appointment']
                : [];
            calendar.addEvent(Object.assign({}, event, {
                classNames: classNames,
                startEditable: canReschedule && event.extendedProps.tipo_contexto === 'cita_existente',
                durationEditable: false,
            }));
        });

        if (state.view === 'semana') {
            weekBackgroundModel.build(
                effectiveProfessionals(),
                visibleHours.start,
                visibleHours.end
            ).forEach((event) => calendar.addEvent(event));
            weekSlotModel.hits(events).forEach((event) => calendar.addEvent(event));
        }

        resizeCalendar();
        window.requestAnimationFrame(() => calendar.updateSize());
    }

    function renderDayGrid() {
        const professional = effectiveProfessionals()[0] || null;
        const events = filteredEvents();
        const model = dayGridModel.build({
            date: state.date,
            gridMinutes: Number(board.dataset.gridMinutes || 20),
            professional: professional,
            events: events,
        });

        state.visibleEvents = events;
        el.dayGridBody.textContent = '';
        model.rows.forEach((row) => el.dayGridBody.appendChild(dayRow(row)));
        window.requestAnimationFrame(revealOperationalRow);
    }

    function revealOperationalRow() {
        const scroller = el.dayGrid;
        const exact = state.revealAppointmentId
            ? el.dayGridBody.querySelector('[data-appointment-id="' + state.revealAppointmentId + '"]')
            : null;
        const row = exact
            ? (exact.closest('.agenda-day-row') || exact)
            : el.dayGridBody.querySelector('.agenda-day-row--appointment, .agenda-day-row--available');

        if (!scroller || !row) {
            return;
        }

        const delta = row.getBoundingClientRect().top - scroller.getBoundingClientRect().top;
        scroller.scrollTop += delta;

        if (exact) {
            state.revealAppointmentId = null;
        }
    }

    function dayRow(row) {
        const node = document.createElement('div');
        node.className = 'agenda-day-row agenda-day-row--' + row.kind;
        node.dataset.rowStart = row.start;
        node.dataset.rowEnd = row.end;
        node.dataset.rowKind = row.kind;
        node.id = row.id;
        node.setAttribute('role', 'row');
        node.setAttribute('aria-label', row.start + ' a ' + row.end + ', ' + row.context.etiqueta);
        node.tabIndex = 0;

        if (row.items.length === 0) {
            node.appendChild(dayEntry(row, null));
        } else {
            row.items.forEach((item) => node.appendChild(dayEntry(row, item)));
            if (row.availableContext) {
                node.appendChild(dayEntry(Object.assign({}, row, { context: row.availableContext }), null));
            }
        }

        const activate = function () {
            const target = node.querySelector('.agenda-day-entry') || node;
            selectInterval(row.context, target);
        };
        node.addEventListener('click', activate);
        node.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });

        return node;
    }

    function dayEntry(row, item) {
        const entry = document.createElement('div');
        const context = item ? item.context : row.context;
        const continuation = item && item.kind === 'continuation';
        const appointment = item && item.kind === 'appointment';
        let values;

        entry.className = 'agenda-day-entry';
        entry.setAttribute('role', 'row');
        entry.dataset.contextType = context.tipo_contexto;

        if (appointment) {
            entry.classList.add('agenda-day-entry--appointment');
            entry.dataset.appointmentId = String(context.appointment_id);
            values = [
                context.hora_inicio,
                context.leyenda === 'ADICIONAL' ? 'ADICIONAL' : (context.leyenda === 'FUERA_HORARIO' ? 'FH' : 'Sí'),
                context.estado_pagado || '—',
                context.historia_clinica || '—',
                context.paciente || 'Paciente sin nombre',
            ];
        } else if (continuation) {
            entry.classList.add('agenda-day-entry--continuation');
            entry.dataset.appointmentId = String(context.appointment_id);
            values = [row.start, '', '', '', 'Continuación de cita · hasta ' + context.hora_fin];
        } else {
            values = [row.start, context.tipo_contexto === 'fuera_horario' ? 'FH' : '', '', '', row.items.length && row.availableContext ? 'Slot regular disponible' : ''];
            if (row.items.length && row.availableContext) {
                entry.classList.add('agenda-day-entry--available');
                entry.tabIndex = 0;
                entry.addEventListener('click', function (event) { event.stopPropagation(); selectInterval(context, entry); });
                entry.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); event.stopPropagation(); selectInterval(context, entry); }
                });
            }
        }

        if (item) {
            entry.style.setProperty('--day-row-bg', item.backgroundColor || '#e8eaed');
            entry.style.setProperty('--day-row-border', item.borderColor || '#3f4a59');
            entry.style.setProperty('--day-row-ink', item.textColor || '#27313a');
            entry.setAttribute('aria-label', [
                continuation ? 'Continuación' : context.hora_inicio,
                context.estado_cita,
                context.estado_pagado,
                context.historia_clinica,
                context.paciente,
            ].filter(Boolean).join(', '));
            entry.tabIndex = 0;
            entry.addEventListener('click', function (event) {
                event.stopPropagation();
                selectInterval(context, entry);
            });
            entry.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    event.stopPropagation();
                    selectInterval(context, entry);
                }
            });
        }

        values.forEach((value, index) => {
            const cell = document.createElement('span');
            cell.className = 'agenda-day-cell agenda-day-cell--' + index;
            cell.setAttribute('role', 'gridcell');
            cell.textContent = value;
            entry.appendChild(cell);
        });

        return entry;
    }

    function availableContextAt(date) {
        const day = toIso(date);
        const time = String(date.getHours()).padStart(2, '0')
            + ':' + String(date.getMinutes()).padStart(2, '0');

        const event = state.visibleEvents.find((candidate) => {
            const context = candidate.extendedProps || {};
            return context.tipo_contexto === 'slot_libre'
                && context.fecha === day
                && context.hora_inicio <= time
                && time < context.hora_fin;
        });

        return event ? event.extendedProps : null;
    }

    function renderComparison() {
        el.comparisonBody.textContent = '';
        const professionals = state.payload.profesionales || [];

        if (!state.comparisonDoctorId || !professionals.some((item) => String(item.id) === state.comparisonDoctorId)) {
            state.comparisonDoctorId = professionals[0] ? String(professionals[0].id) : null;
        }

        professionals.forEach((professional) => {
            const row = document.createElement('tr');
            const coverage = coverageFor(professional);
            row.dataset.doctorId = String(professional.id);
            row.tabIndex = 0;
            row.classList.toggle('is-active', row.dataset.doctorId === state.comparisonDoctorId);

            [
                professional.nombre,
                professional.especialidad || 'Sin especialidad',
                professional.resumen.libres,
                professional.resumen.ocupadas,
                professional.resumen.minutos_libres,
                coverage,
            ].forEach((value) => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });

            const activate = function () {
                state.comparisonDoctorId = row.dataset.doctorId;
                Array.from(el.comparisonBody.children).forEach((candidate) => {
                    candidate.classList.toggle('is-active', candidate === row);
                });
            };
            row.addEventListener('click', activate);
            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    activate();
                }
            });
            el.comparisonBody.appendChild(row);
        });
    }

    function coverageFor(professional) {
        const starts = [];
        const ends = [];
        (professional.dias || []).forEach((day) => {
            (day.slots || []).forEach((slot) => {
                starts.push(slot.inicio);
                ends.push(slot.fin);
            });
        });
        if (starts.length === 0) {
            return 'Sin horario';
        }
        starts.sort();
        ends.sort();
        return starts[0] + ' – ' + ends[ends.length - 1];
    }

    function applyVisibleHours() {
        const starts = [];
        const ends = [];

        effectiveProfessionals().forEach((professional) => {
            (professional.dias || []).forEach((day) => {
                (day.slots || []).forEach((slot) => {
                    starts.push(slot.inicio);
                    ends.push(slot.fin);
                });
            });
        });

        filteredEvents().forEach(function (event) {
            const c = event.extendedProps || {};
            if (c.tipo_contexto === 'cita_existente') { starts.push(c.hora_inicio); ends.push(c.hora_fin); }
        });
        if (starts.length === 0) {
            calendar.setOption('slotMinTime', '07:00:00');
            calendar.setOption('slotMaxTime', '20:00:00');
            return { start: '07:00', end: '20:00' };
        }

        starts.sort();
        ends.sort();
        const visibleStart = snapGridTime(starts[0] < '07:00' ? starts[0] : '07:00', false);
        const visibleEnd = snapGridTime(ends[ends.length - 1] > '20:00' ? ends[ends.length - 1] : '20:00', true);
        calendar.setOption('slotMinTime', visibleStart + ':00');
        calendar.setOption('slotMaxTime', visibleEnd + ':00');
        calendar.setOption('scrollTime', snapGridTime(starts[0], false) + ':00');

        return { start: visibleStart, end: visibleEnd };
    }

    function snapGridTime(value, up) {
        const parts = value.split(':').map(Number);
        const minutes = (parts[0] * 60) + (parts[1] || 0);
        const snapped = up
            ? Math.ceil(minutes / 20) * 20
            : Math.floor(minutes / 20) * 20;
        const hours = Math.min(23, Math.floor(snapped / 60));
        const mins = snapped % 60;

        return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
    }

    function resizeCalendar() {
        const center = el.calendar.parentElement;
        const centerStyle = window.getComputedStyle(center);
        const verticalPadding = parseFloat(centerStyle.paddingTop) + parseFloat(centerStyle.paddingBottom);
        const reservedHeight = Array.from(center.children).reduce(function (total, child) {
            if (child === el.calendar || child.hidden) {
                return total;
            }

            const style = window.getComputedStyle(child);
            return total
                + child.offsetHeight
                + parseFloat(style.marginTop)
                + parseFloat(style.marginBottom);
        }, verticalPadding);

        calendar.setOption('height', Math.max(390, center.clientHeight - reservedHeight));
    }

    function renderTotals() {
        const effective = effectiveProfessionals();
        const summary = effective.length === 1 && state.payload.profesionales.length > 1
            ? effective[0].resumen
            : state.payload.resumen;

        el.totalFree.textContent = summary.libres;
        el.totalBusy.textContent = summary.ocupadas;
        el.totalMinutes.textContent = summary.minutos_libres;
        const days = effective.flatMap((p) => p.dias || []);
        const count = (key) => days.reduce((sum, day) => sum + Number((day.especiales || {})[key] || 0), 0);
        document.getElementById('agenda-total-special').textContent = count('adicionales') + ' AD · ' + count('fuera_horario') + ' FH';
    }

    function effectiveProfessionals() {
        const professionals = state.payload ? (state.payload.profesionales || []) : [];

        if (!state.compare && state.view !== 'mes' && selectedDoctorIds().length === 0) {
            return professionals.slice(0, 1);
        }

        return professionals;
    }

    function renderMiniCalendar() {
        const selected = state.date;
        const today = board.dataset.today;
        const monthStart = new Date(state.miniAnchor.getFullYear(), state.miniAnchor.getMonth(), 1, 12);
        const offset = (monthStart.getDay() + 6) % 7;
        const firstCell = new Date(monthStart);
        firstCell.setDate(firstCell.getDate() - offset);

        el.miniMonth.textContent = MONTH_FORMAT.format(monthStart);
        el.miniGrid.textContent = '';

        for (let index = 0; index < 42; index += 1) {
            const day = new Date(firstCell);
            day.setDate(firstCell.getDate() + index);
            const iso = toIso(day);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'agenda-mini__day';
            button.textContent = day.getDate();
            button.dataset.date = iso;
            button.setAttribute('role', 'gridcell');
            button.setAttribute('aria-label', DAY_FORMAT.format(day));
            button.classList.toggle('is-outside', day.getMonth() !== monthStart.getMonth());
            button.classList.toggle('is-today', iso === today);
            button.classList.toggle('is-selected', iso === selected);
            button.addEventListener('click', function () {
                setDate(button.dataset.date);
            });
            el.miniGrid.appendChild(button);
        }
        loadMiniCapacity();
    }

    let capacityRequest = 0;
    async function loadMiniCapacity() {
        const request = ++capacityRequest;
        const doctor = effectiveProfessionals()[0];
        const cells = Array.from(el.miniGrid.children);
        if (!doctor || !cells.length) { return; }
        const url = new URL(board.dataset.capacityUrl, window.location.origin);
        url.searchParams.set('doctor_id', doctor.id);
        url.searchParams.set('from', cells[0].dataset.date);
        url.searchParams.set('to', cells[cells.length - 1].dataset.date);
        if (el.site.value) { url.searchParams.set('site_id', el.site.value); }
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) { throw new Error('Capacidad no disponible'); }
            const data = await response.json();
            if (request !== capacityRequest) { return; }
            const byDate = new Map(data.dias.map((day) => [day.fecha, day]));
            cells.forEach(function (cell) {
                const day = byDate.get(cell.dataset.date);
                if (!day || !['plomo','verde','amarillo','rojo'].includes(day.color)) { return; }
                cell.dataset.capacity = day.color;
                cell.title = doctor.nombre + ': ' + (day.color === 'plomo' ? 'Sin horario' : day.ocupacion_segura + '/' + day.capacidad_regular + ' intervalos con pago ≥50% (' + day.porcentaje + '%)');
                cell.setAttribute('aria-label', DAY_FORMAT.format(parseIso(day.fecha)) + '. ' + cell.title);
            });
        } catch (_) { if (request === capacityRequest) { el.miniMonth.title = 'Semáforo temporalmente no disponible'; } }
    }

    function clearSelection() {
        state.additionalMode = false;
        state.offHoursMode = false;
        if (rescheduleForm) { rescheduleForm.hidden = true; }
        if (additionalStart) { additionalStart.disabled = true; }
        if (offHoursStart) { offHoursStart.hidden = true; }
        state.selection = null;
        configureServices(null);
        document.querySelectorAll('.agenda-calendar-event--appointment.is-selected, .agenda-day-row.is-selected, .agenda-day-entry.is-selected')
            .forEach((node) => node.classList.remove('is-selected'));
        if (state.selectedSlotEvent) {
            state.selectedSlotEvent.remove();
            state.selectedSlotEvent = null;
        }
        el.context.dataset.empty = 'true';
        el.contextEmpty.hidden = false;
        el.contextList.hidden = true;
        el.contextNote.hidden = true;
        el.contextPatient.hidden = true;
        el.contextService.hidden = true;
        el.contextPayment.hidden = true;
        el.contextClinicalRecord.hidden = true;
        el.overlapResult.textContent = '';
        paintResponsible();
        syncQuickBase();
    }

    function syncQuickBase() {
        const professionals = effectiveProfessionals();
        const doctor = professionals[0];
        const base = selectionModel.empty({
            doctor: isComparing()
                ? professionals.length + ' médicos en comparación'
                : (doctor ? doctor.nombre : 'Sin médico disponible'),
            specialty: !isComparing() && doctor ? (doctor.especialidad || 'Sin especialidad') : '—',
            date: DAY_FORMAT.format(parseIso(state.date)),
            time: isComparing() ? 'Abra una agenda' : 'Seleccione un intervalo',
            site: el.site.value ? siteName(el.site.value) : 'Todas las sedes',
        });
        applyQuickState(base);
        el.quickMessage.classList.remove('is-ready');
        el.quickMessage.textContent = isComparing()
            ? 'Seleccione un médico de la comparación y abra su agenda para elegir un intervalo.'
            : 'Seleccione un intervalo disponible, un paciente y un servicio.';
    }

    function selectInterval(context, node) {
        state.additionalMode = false;
        state.offHoursMode = false;
        state.selection = context;
        if (offHoursStart) { offHoursStart.hidden = context.tipo_contexto !== 'fuera_horario'; }
        if (rescheduleForm) {
            rescheduleForm.hidden = context.tipo_contexto !== 'cita_existente';
            document.getElementById('agenda-reschedule-date').value = context.fecha || '';
            document.getElementById('agenda-reschedule-time').value = context.hora_inicio || '';
        }
        if (additionalStart) {
            additionalStart.disabled = !context.hora_inicio || context.tipo_contexto === 'fuera_horario';
        }
        if (context.tipo_contexto === 'cita_existente') {
            showStoredAppointment(context);
        } else {
            configureServices(context);
            paintResponsible();
        }
        document.querySelectorAll('.agenda-calendar-event--appointment.is-selected, .agenda-day-row.is-selected, .agenda-day-entry.is-selected')
            .forEach((event) => event.classList.remove('is-selected'));
        if (state.selectedSlotEvent) {
            state.selectedSlotEvent.remove();
            state.selectedSlotEvent = null;
        }

        if (node && node.classList) {
            node.classList.add('is-selected');
        } else if (context.tipo_contexto === 'slot_libre' && state.view !== 'dia') {
            state.selectedSlotEvent = calendar.addEvent({
                id: 'agenda-selected-slot',
                start: context.fecha + 'T' + context.hora_inicio + ':00',
                end: context.fecha + 'T' + context.hora_fin + ':00',
                display: 'background',
                classNames: ['agenda-selected-slot'],
                backgroundColor: 'rgba(14, 116, 144, .08)',
                extendedProps: { tipo_contexto: 'seleccion_slot' },
            });
        }

        const entry = state.legend[context.leyenda] || {};
        el.contextState.textContent = entry.etiqueta || context.estado || '';
        el.contextState.style.setProperty('--chip-bg', entry.fondo || '#f4f5f7');
        el.contextState.style.setProperty('--chip-fg', entry.color || '#1f2933');
        el.contextDoctor.textContent = context.doctor || '—';
        el.contextSpecialty.textContent = context.especialidad || '—';
        el.contextDate.textContent = context.fecha || '—';
        el.contextTime.textContent = context.hora_inicio ? context.hora_inicio + ' – ' + context.hora_fin : 'Resumen diario';
        el.contextDuration.textContent = context.minutos ? context.minutos + ' min' : '—';
        el.contextSite.textContent = siteName(context.site_id);
        el.contextPatient.textContent = context.paciente || '—';
        el.contextPatient.hidden = !context.paciente;
        el.contextService.textContent = context.servicio || '—';
        el.contextService.hidden = !context.servicio;
        el.contextPayment.textContent = 'Pago: ' + (context.estado_pagado || '—');
        el.contextPayment.hidden = context.tipo_contexto !== 'cita_existente';
        el.contextClinicalRecord.textContent = 'H.C.: ' + (context.historia_clinica || '—');
        el.contextClinicalRecord.hidden = context.tipo_contexto !== 'cita_existente';
        el.contextEmpty.hidden = true;
        el.contextList.hidden = false;
        el.contextNote.hidden = context.tipo_contexto === 'cita_existente';
        el.contextNote.textContent = context.tipo_contexto === 'fuera_horario'
            ? 'Sin horario de atención. El alta especial requiere la acción explícita Agendar fuera de horario.'
            : 'Intervalo disponible; todavía no se ha registrado una cita.';
        el.context.dataset.empty = 'false';

        const quick = selectionModel.fromContext(context, siteName(context.site_id));
        quick.date = context.fecha ? DAY_FORMAT.format(parseIso(context.fecha)) : '—';
        applyQuickState(quick);
        if (context.tipo_contexto !== 'cita_existente') {
            paintSelectedService();
        }

        if (context.tipo_contexto === 'cita_existente') {
            state.identity = lookupModel.blank();
            state.draft = null;
            paintDraft(draftModel.discard(scheduleSnapshot()));
            el.lookupResult.textContent = '';
            el.lookupResult.className = 'agenda-lookup__result';
            el.lookupClinicalRecord.textContent = quick.clinicalRecord;
            el.patientRegister.hidden = true;
            el.quickMessage.classList.remove('is-ready');
            el.quickMessage.textContent = context.responsable
                ? 'Responsable: ' + context.responsable + '. Puede abrir la ficha maestra del paciente.'
                : 'Cita identificada. Puede abrir la ficha maestra del mismo paciente.';
        } else if (context.tipo_contexto === 'fuera_horario') {
            el.quickMessage.classList.remove('is-ready');
            el.quickMessage.textContent = 'Usa Agendar fuera de horario para preparar una cita especial. El alta normal no está habilitada aquí.';
        } else {
            el.quickMessage.classList.toggle('is-ready', Boolean(context.seleccionable));
            el.quickMessage.textContent = context.seleccionable
                ? 'Horario listo. Seleccione paciente y servicio para agendar.'
                : 'Resumen no seleccionable. Abra un día para elegir un intervalo.';
        }

        if (context.hora_inicio) {
            el.overlapStart.value = context.hora_inicio;
            el.overlapEnd.value = context.hora_fin;
        }

        if (context.doctor_id && context.fecha) {
            el.scheduleLink.href = el.scheduleLink.href.split('?')[0]
                + '?doctor_id=' + context.doctor_id
                + '&fecha_cita=' + context.fecha;
        }

        updateAppointmentAction();
    }

    function applyQuickState(quick) {
        el.quickMode.textContent = quick.mode;
        el.quickDoctor.textContent = quick.doctor;
        el.quickSpecialty.textContent = quick.specialty;
        el.quickDate.textContent = quick.date;
        el.quickTime.textContent = quick.time;
        el.quickDuration.textContent = quick.duration;
        el.quickSite.textContent = quick.site;
        el.quickPatientId.value = quick.patientId;
        el.quickPatientIdDisplay.textContent = quick.patientId || '—';
        el.quickPatientState.textContent = quick.patient;
        el.quickService.textContent = quick.service;
        el.quickPrice.textContent = quick.price || '—';
        el.quickStatus.textContent = quick.status;
        el.quickPayment.textContent = quick.payment;
        el.quickClinicalRecord.textContent = quick.clinicalRecord;
        el.commercialOwner.textContent = quick.commercial || 'Sin asignar';
        if (el.schedulerUser) {
            el.schedulerUser.textContent = quick.creator || authenticatedScheduler;
        }
        el.completeRegistration.disabled = !quick.showCompleteRegistration;
        el.completeRegistration.hidden = !quick.showCompleteRegistration;
        el.completeRegistrationHelp.hidden = !quick.showCompleteRegistration;
        el.completeRegistration.classList.toggle('is-prepared', quick.showCompleteRegistration);

        if (quick.mode !== 'Cita existente' && state.identity && state.identity.status) {
            paintIdentity(state.identity);
        }

        updateAppointmentAction();
    }

    function paintIdentity(identity) {
        state.identity = identity;
        el.quickPatientId.value = identity.patientId || '';
        el.quickPatientIdDisplay.textContent = identity.patientId || '—';
        el.quickPatientState.textContent = identity.name || 'Sin paciente seleccionado';
        el.lookupClinicalRecord.textContent = identity.clinicalRecord || '—';
        el.lookupResult.textContent = identity.message || '';
        el.lookupResult.className = 'agenda-lookup__result'
            + (identity.status === 'found' ? ' is-found' : '')
            + (identity.status === 'not_found' ? ' is-missing' : '')
            + (identity.status === 'inactive' ? ' is-inactive' : '')
            + (identity.status === 'document_conflict' ? ' is-conflict' : '');
        el.patientRegister.hidden = !identity.showRegister || Boolean(state.draft && state.draft.open);
        el.patientRegister.disabled = !identity.showRegister;
        el.completeRegistration.hidden = identity.status !== 'found';
        el.completeRegistration.disabled = identity.status !== 'found';
        el.completeRegistrationHelp.hidden = identity.status !== 'found';

        if (identity.status !== 'not_found') {
            state.draft = null;
            paintDraft(draftModel.discard(scheduleSnapshot()));
        }

        updateAppointmentAction();
    }

    function showStoredAppointment(context) {
        if (!state.holdingResponsible) {
            state.responsibleBeforeExisting = el.responsibleSelect.value;
            state.holdingResponsible = true;
        }

        clearStoredResponsibleOption();
        state.serviceDoctorId = null;
        el.serviceSelect.textContent = '';

        const service = document.createElement('option');
        service.value = context.service_id ? String(context.service_id) : '';
        service.textContent = context.servicio || 'Servicio no registrado';
        service.selected = true;
        el.serviceSelect.appendChild(service);
        el.serviceSelect.disabled = true;

        if (context.responsible_user_id) {
            const id = String(context.responsible_user_id);
            let option = Array.from(el.responsibleSelect.options).find(function (item) {
                return item.value === id;
            });

            if (!option) {
                option = document.createElement('option');
                option.value = id;
                option.dataset.storedResponsible = '1';
                option.textContent = context.responsable || 'Comercial asignado';
                el.responsibleSelect.appendChild(option);
            }

            el.responsibleSelect.value = id;
        } else {
            el.responsibleSelect.value = '';
        }

        el.responsibleSelect.disabled = true;
    }

    function clearStoredResponsibleOption() {
        const stored = el.responsibleSelect.querySelector('option[data-stored-responsible]');
        if (stored) {
            stored.remove();
        }
    }

    function configureServices(context) {
        clearStoredResponsibleOption();
        if (state.holdingResponsible) {
            el.responsibleSelect.value = state.responsibleBeforeExisting;
            state.holdingResponsible = false;
            state.responsibleBeforeExisting = '';
        }
        el.responsibleSelect.disabled = responsibleLockedByPermission;
        const doctorId = context && context.tipo_contexto === 'slot_libre'
            ? String(context.doctor_id || '')
            : '';
        const previous = state.serviceDoctorId === doctorId ? el.serviceSelect.value : '';
        const services = doctorId && doctorServices[doctorId] ? doctorServices[doctorId] : [];

        state.serviceDoctorId = doctorId || null;
        el.serviceSelect.textContent = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = doctorId
            ? (services.length ? 'Seleccione un servicio' : 'Sin servicios activos')
            : 'Seleccione un intervalo';
        el.serviceSelect.appendChild(placeholder);

        services.forEach(function (service) {
            const option = document.createElement('option');
            option.value = String(service.service_id);
            option.textContent = service.nombre + (service.motivo ? ' — ' + service.motivo : '');
            option.disabled = service.disponible === false;
            option.dataset.price = service.precio === null ? '' : String(service.precio);
            el.serviceSelect.appendChild(option);
        });

        el.serviceSelect.disabled = !doctorId || services.length === 0;
        if (previous && services.some((service) => service.disponible !== false && String(service.service_id) === previous)) {
            el.serviceSelect.value = previous;
        } else if (services.filter((service) => service.disponible !== false).length === 1) {
            el.serviceSelect.value = String(services.find((service) => service.disponible !== false).service_id);
        }
        paintSelectedService();
    }

    function paintSelectedService() {
        const option = el.serviceSelect.selectedOptions[0];
        const selected = Boolean(el.serviceSelect.value && option);

        el.quickService.textContent = selected ? option.textContent.trim() : 'Pendiente de selección';
        el.quickPrice.textContent = selected && option.dataset.price !== ''
            ? 'S/ '+Number(option.dataset.price).toFixed(2)
            : '—';
        syncModalBooking();
        if (selected) { clearServiceError(); }
        updateAppointmentAction();
    }

    function paintResponsible() {
        const option = el.responsibleSelect.selectedOptions[0];
        el.commercialOwner.textContent = el.responsibleSelect.value && option
            ? option.textContent.trim()
            : 'Sin asignar';
    }

    function updateAppointmentAction() {
        const slot = state.selection
            && state.selection.tipo_contexto === 'slot_libre'
            && state.selection.seleccionable === true;
        const patient = Boolean(el.quickPatientId.value);
        el.appointmentSubmit.textContent = state.offHoursMode ? 'Agendar FUERA DE HORARIO' : (state.additionalMode ? 'Agendar cita adicional' : 'Agendar cita');
        el.appointmentSubmit.disabled = !(state.additionalMode ? canAdditional : canCreateAppointments)
            || state.appointmentBusy
            || !slot
            || !patient;
    }

    function scheduleSnapshot() {
        return {
            doctor: el.quickDoctor.textContent,
            specialty: el.quickSpecialty.textContent,
            date: el.quickDate.textContent,
            time: el.quickTime.textContent,
            site: el.quickSite.textContent,
        };
    }

    function showStoredDraftType(tipo) {
        Array.from(el.draftType.querySelectorAll('option[data-legacy-document]')).forEach(function (option) {
            if (option.value !== tipo) {
                option.remove();
            }
        });

        if (!tipo || Array.from(el.draftType.options).some(function (option) { return option.value === tipo; })) {
            return;
        }

        const option = document.createElement('option');
        option.value = tipo;
        option.textContent = tipo;
        option.dataset.legacyDocument = '1';
        el.draftType.appendChild(option);
    }

    function paintDraft(draft) {
        state.draft = draft && draft.open ? draft : null;
        el.patientModal.hidden = !state.draft;
        el.patientModal.setAttribute('aria-hidden', state.draft ? 'false' : 'true');
        document.body.classList.toggle('agenda-modal-open', Boolean(state.draft));
        el.draftRuc.hidden = !state.draft || state.draft.tipo !== 'RUC';
        el.draftReniec.hidden = !state.draft || !state.draft.reniecOffered;

        if (!state.draft) {
            el.draftMessage.textContent = '';
            return;
        }

        syncModalBooking();
        el.patientModalTitle.textContent = draft.patientId ? 'Editar paciente' : 'Registrar paciente';
        showStoredDraftType(draft.tipo);
        el.draftType.value = draft.tipo;
        el.draftNumber.value = draft.numero;
        el.draftNombre.value = draft.nombre;
        el.draftApellidoPaterno.value = draft.apellido_paterno;
        el.draftApellidoMaterno.value = draft.apellido_materno;
        el.draftPhonePrefix.value = draft.telefono_prefijo || '+51';
        el.draftTelefono.value = draft.telefono_numero || '';
        el.draftRegisterResponsible.checked = Boolean(draft.registrar_responsable);
        el.draftResponsible.hidden = !draft.registrar_responsable;
        el.draftResponsibleRelationship.value = draft.responsable_parentesco || 'PAPA';
        el.draftResponsibleName.value = draft.responsable_nombres || '';
        el.draftResponsiblePhone.value = draft.responsable_telefono || '';
        el.draftResponsibleDocumentType.value = draft.responsable_tipo_identificacion || 'DNI';
        el.draftResponsibleDocumentNumber.value = draft.responsable_numero_identidad || '';
        el.draftEmail.value = draft.email;
        el.draftFechaNacimiento.value = draft.fecha_nacimiento;
        el.draftGenero.value = draft.genero;
        el.draftEstadoCivil.value = draft.estado_civil;
        el.draftDireccion.value = draft.direccion;
        el.draftChannel.value = draft.channel_id || '';
        el.draftInteractionMedium.value = draft.interaction_medium_id || '';
        el.draftOcupacion.value = draft.ocupacion || '';
        el.draftGradoInstruccion.value = draft.grado_instruccion || '';
        el.draftFamiliarContacto.value = draft.familiar_contacto || '';
        el.draftCommercialOwner.value = draft.commercial_owner_id || '';
        if (!el.draftCommercialOwner.value && el.responsibleSelect.value) {
            el.draftCommercialOwner.value = el.responsibleSelect.value;
            state.draft.commercial_owner_id = el.responsibleSelect.value;
        }
        el.draftContextDoctor.textContent = draft.doctor || '—';
        el.draftContextDate.textContent = draft.date || '—';
        el.draftContextTime.textContent = draft.time || '—';
        el.draftContextSite.textContent = draft.site || '—';
        const preview = draftModel.hcePreview(draft, hceSupportedTypes);
        el.draftHce.textContent = preview.value;
        el.draftHceNote.textContent = preview.message;
        el.draftMessage.textContent = draft.message || '';
        el.draftSave.disabled = !canWritePatients || draft.tipo === 'SIN DOCUMENTOS';
        el.draftSaveSchedule.disabled = !canWritePatients || !canCreateAppointments || draft.tipo === 'SIN DOCUMENTOS';
        el.draftSaveSchedule.textContent = draft.patientId ? 'Guardar y continuar' : 'Guardar y agendar';
        window.requestAnimationFrame(function () {
            el.draftNombre.focus();
        });
    }

    function bindDraftField(input, field) {
        input.addEventListener('input', function () {
            if (!state.draft) {
                return;
            }

            state.draft = draftModel.edit(state.draft, field, input.value);
            refreshDraftMetadata();
        });
        input.addEventListener('change', function () {
            if (!state.draft) {
                return;
            }

            state.draft = draftModel.edit(state.draft, field, input.value);
            refreshDraftMetadata();
        });
    }

    function refreshDraftMetadata() {
        if (!state.draft) {
            return;
        }

        const preview = draftModel.hcePreview(state.draft, hceSupportedTypes);
        el.draftHce.textContent = preview.value;
        el.draftHceNote.textContent = preview.message;
        el.draftRuc.hidden = state.draft.tipo !== 'RUC';
        el.draftReniec.hidden = !state.draft.reniecOffered;
        el.draftSave.disabled = !canWritePatients || state.draft.tipo === 'SIN DOCUMENTOS';
        el.draftSaveSchedule.disabled = !canWritePatients || !canCreateAppointments || state.draft.tipo === 'SIN DOCUMENTOS';
    }

    function closePatientModal() {
        paintDraft(draftModel.cancel(state.draft, scheduleSnapshot()));
        el.patientRegister.hidden = !(state.identity && state.identity.showRegister);
        el.patientRegister.disabled = !(state.identity && state.identity.showRegister);
    }

    function patientValidationMessage(payload) {
        const errors = payload && payload.errors ? payload.errors : {};
        const field = Object.keys(errors)[0];

        return field && errors[field] && errors[field][0]
            ? errors[field][0]
            : 'Revise los datos obligatorios e intente nuevamente.';
    }

    async function openExistingPatient() {
        const patientId = el.quickPatientId.value;

        if (!patientId) {
            return;
        }

        el.completeRegistration.disabled = true;

        try {
            const url = el.patientDraft.dataset.detailTemplate.replace('__PATIENT__', encodeURIComponent(patientId));
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const payload = await response.json();

            if (!response.ok || !payload.patient) {
                throw new Error('No se pudo cargar la ficha.');
            }

            paintDraft(draftModel.openExisting(payload.patient, scheduleSnapshot()));
        } catch (error) {
            el.lookupResult.className = 'agenda-lookup__result is-conflict';
            el.lookupResult.textContent = 'No se pudo cargar la ficha del paciente.';
        } finally {
            el.completeRegistration.disabled = false;
        }
    }

    async function savePatient(attachToSchedule) {
        if (!state.draft || !canWritePatients) {
            return;
        }

        if (attachToSchedule && !requireService(true)) { return; }
        const emailError = draftModel.emailMessage(state.draft.email);
        if (emailError) {
            el.draftMessage.textContent = emailError;
            return;
        }

        if (state.draft.tipo === 'SIN DOCUMENTOS') {
            el.draftMessage.textContent = 'El identificador final para pacientes sin documentos sigue pendiente; no se guardó.';
            return;
        }

        const dniError = draftModel.dniMessage(state.draft);
        if (dniError) {
            el.draftMessage.textContent = dniError;
            return;
        }

        if (attachToSchedule) {
            try {
                appointmentCreateModel.payload(
                    state.selection,
                    state.draft.patientId || 1,
                    el.serviceSelect.value,
                    el.draftCommercialOwner.value
                );
            } catch (error) {
                el.draftMessage.textContent = error.message;
                return;
            }
        }

        const currentDraft = state.draft;
        const existing = Boolean(currentDraft.patientId);
        const url = existing
            ? el.patientDraft.dataset.updateTemplate.replace('__PATIENT__', encodeURIComponent(currentDraft.patientId))
            : el.patientDraft.dataset.storeEndpoint;

        el.draftSave.disabled = true;
        el.draftSaveSchedule.disabled = true;
        el.draftMessage.textContent = existing ? 'Guardando cambios…' : 'Registrando paciente…';

        try {
            const response = await fetch(url, {
                method: existing ? 'PUT' : 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(draftModel.toPayload(currentDraft)),
            });
            const payload = await response.json();

            if (!response.ok || !payload.patient) {
                throw new Error(patientValidationMessage(payload));
            }

            const ownerOption = el.draftCommercialOwner.selectedOptions[0];
            const ownerName = currentDraft.commercial_owner_id && ownerOption
                ? ownerOption.textContent.trim()
                : 'Pendiente de selección';
            const outcome = draftModel.saveOutcome(attachToSchedule, existing);
            const schedule = scheduleSnapshot();

            if (outcome.attach) {
                const identity = lookupModel.present({ status: 'found', patient: payload.patient }, schedule);
                identity.message = outcome.message;
                paintIdentity(identity);
                el.commercialOwner.textContent = ownerName;
                el.responsibleSelect.value = currentDraft.commercial_owner_id || '';
                paintResponsible();
                await createAppointment();
            } else {
                paintDraft(draftModel.discard(schedule));
                state.identity = lookupModel.blank();
                el.quickPatientId.value = '';
                el.quickPatientIdDisplay.textContent = '—';
                el.quickPatientState.textContent = 'Sin paciente seleccionado';
                el.lookupClinicalRecord.textContent = '—';
                el.lookupResult.className = 'agenda-lookup__result is-found';
                el.lookupResult.textContent = outcome.message;
            }
        } catch (error) {
            el.draftMessage.textContent = error.message || 'No se pudo guardar el paciente.';
        } finally {
            if (state.draft) {
                el.draftSave.disabled = !canWritePatients;
                el.draftSaveSchedule.disabled = !canWritePatients || !canCreateAppointments;
            }
        }
    }

    async function createAppointment() {
        if (state.appointmentBusy || !(state.additionalMode ? canAdditional : canCreateAppointments)) {
            return;
        }

        if (!requireService(Boolean(state.draft))) { return; }
        let requestPayload;
        try {
            requestPayload = appointmentCreateModel.payload(
                state.selection,
                el.quickPatientId.value,
                el.serviceSelect.value,
                el.responsibleSelect.value
            );
        } catch (error) {
            el.quickMessage.classList.remove('is-ready');
            el.quickMessage.textContent = error.message;
            if (state.draft) {
                el.draftMessage.textContent = error.message;
            }
            return;
        }

        state.appointmentBusy = true;
        updateAppointmentAction();
        el.draftSaveSchedule.disabled = true;
        el.quickMessage.classList.add('is-ready');
        el.quickMessage.textContent = 'Registrando cita…';

        try {
            const response = await fetch(state.offHoursMode ? board.dataset.offHoursStore : (state.additionalMode ? board.dataset.additionalStore : board.dataset.appointmentStore), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(requestPayload),
            });
            const payload = await response.json().catch(function () { return {}; });

            if (response.status === 409) {
                await load();
                const message = appointmentCreateModel.validationMessage(payload);
                showNotice(message);
                el.quickMessage.classList.remove('is-ready');
                el.quickMessage.textContent = message;
                return;
            }

            if (!response.ok || !payload.appointment) {
                throw new Error(appointmentCreateModel.validationMessage(payload));
            }

            state.identity = lookupModel.blank();
            state.draft = null;
            el.documentNumber.value = '';
            paintIdentity(state.identity);
            el.lookupResult.textContent = '';
            el.responsibleSelect.value = '';
            paintResponsible();
            paintDraft(draftModel.discard(scheduleSnapshot()));
            state.revealAppointmentId = payload.appointment && payload.appointment.appointment_id
                ? String(payload.appointment.appointment_id)
                : null;
            await load();
            showNotice('Cita registrada correctamente');
            el.quickMessage.classList.add('is-ready');
            el.quickMessage.textContent = 'Cita registrada correctamente';
        } catch (error) {
            el.quickMessage.classList.remove('is-ready');
            el.quickMessage.textContent = error.message || 'No se pudo registrar la cita.';
            if (state.draft) {
                el.draftMessage.textContent = el.quickMessage.textContent;
            }
        } finally {
            state.appointmentBusy = false;
            updateAppointmentAction();
            if (state.draft) {
                el.draftSaveSchedule.disabled = !canWritePatients || !canCreateAppointments;
            }
        }
    }

    function onDocumentEdited() {
        const next = lookupModel.edited(state.identity, el.documentType.value, el.documentNumber.value);

        if (next !== state.identity) {
            state.draft = null;
            paintIdentity(next);
        }
    }

    function siteName(siteId) {
        if (!siteId) {
            return 'Sin sede registrada';
        }

        const option = el.site.querySelector('option[value="' + siteId + '"]');
        return option ? option.textContent.trim() : String(siteId);
    }

    function setDate(value) {
        state.date = value;
        state.miniAnchor = firstOfMonth(value);
        el.date.value = value;
        load();
    }

    function setView(view) {
        state.view = view;
        board.classList.toggle('agenda-board--day', view === 'dia');
        board.classList.toggle('agenda-board--week', view === 'semana');
        el.viewButtons.forEach((button) => {
            const active = button.dataset.view === view;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function shift(direction) {
        const date = parseIso(state.date);

        if (state.view === 'dia') {
            date.setDate(date.getDate() + direction);
        } else if (state.view === 'semana') {
            date.setDate(date.getDate() + direction * 7);
        } else {
            date.setDate(1);
            date.setMonth(date.getMonth() + direction);
        }

        setDate(toIso(date));
    }

    const operationsPane = document.querySelector('.agenda-operations');
    const tools = document.querySelector('.agenda-tools');

    function releaseOperationsScroll() {
        if (!operationsPane) {
            return;
        }

        const top = operationsPane.scrollTop;
        operationsPane.classList.remove('is-time-picking');
        operationsPane.style.overflowY = 'hidden';
        void operationsPane.offsetHeight;
        operationsPane.style.overflowY = '';
        operationsPane.scrollTop = top;
    }

    [el.overlapStart, el.overlapEnd].forEach(function (input) {
        input.addEventListener('focus', function () {
            if (operationsPane) {
                operationsPane.classList.add('is-time-picking');
            }
        });
        input.addEventListener('blur', function () {
            window.requestAnimationFrame(releaseOperationsScroll);
        });
        input.addEventListener('change', releaseOperationsScroll);
    });

    if (tools) {
        tools.addEventListener('toggle', function () {
            window.requestAnimationFrame(releaseOperationsScroll);
        });
    }

    el.documentType.addEventListener('change', onDocumentEdited);
    el.documentNumber.addEventListener('input', onDocumentEdited);
    el.patientLookup.addEventListener('submit', async function (event) {
        event.preventDefault();

        const tipo = el.documentType.value.trim();
        const numero = el.documentNumber.value.trim();
        const schedule = scheduleSnapshot();

        paintIdentity(lookupModel.edited(null, tipo, numero));
        state.draft = null;

        if (!tipo || !numero) {
            el.lookupResult.textContent = 'Indique tipo y número de documento.';
            return;
        }

        el.documentSearch.disabled = true;

        try {
            const response = await fetch(el.patientLookup.dataset.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    tipo_identificacion: tipo,
                    numero_identidad: numero,
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                el.lookupResult.className = 'agenda-lookup__result is-conflict';
                el.lookupResult.textContent = 'No se pudo consultar el documento.';
                return;
            }

            const identity = lookupModel.present(data, schedule);

            if (identity.status === 'not_found') {
                identity.tipo = tipo;
                identity.numero = numero;
            }

            paintIdentity(identity);
        } catch (error) {
            el.lookupResult.className = 'agenda-lookup__result is-conflict';
            el.lookupResult.textContent = 'No se pudo consultar el documento.';
        } finally {
            el.documentSearch.disabled = false;
        }
    });

    el.patientDraft.addEventListener('submit', function (event) {
        event.preventDefault();
        savePatient(true);
    });
    el.patientRegister.addEventListener('click', function () {
        const draft = draftModel.open(state.identity, scheduleSnapshot());

        if (!draft.open) {
            return;
        }

        paintDraft(draft);
        el.patientRegister.hidden = true;
        el.quickPatientId.value = '';
        el.quickPatientIdDisplay.textContent = '—';
    });
    el.draftCancel.addEventListener('click', closePatientModal);
    el.draftClose.addEventListener('click', closePatientModal);
    el.patientModal.querySelector('[data-modal-close]').addEventListener('click', closePatientModal);
    el.draftSave.addEventListener('click', function () {
        savePatient(false);
    });
    el.appointmentSubmit.addEventListener('click', createAppointment);
    if (rescheduleForm) {
        rescheduleForm.querySelectorAll('[data-quick-minute]').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById('agenda-reschedule-time');
                const value = window.AgendaAppointmentActions.quickMinute(input.value, button.dataset.quickMinute);
                input.setCustomValidity(value ? '' : 'Escribe primero una hora válida (00 a 23).');
                if (value) { input.value = value; }
                input.focus(); input.reportValidity();
            });
        });
        document.getElementById('agenda-reschedule-time').addEventListener('input', function () { this.setCustomValidity(''); });
        rescheduleForm.addEventListener('submit', function (event) {
            event.preventDefault();
            moveAppointment(state.selection, document.getElementById('agenda-reschedule-date').value,
                document.getElementById('agenda-reschedule-time').value, function () {});
        });
    }
    if (offHoursStart) {
        offHoursStart.addEventListener('click', function () {
            if (!state.selection || state.selection.tipo_contexto !== 'fuera_horario') { return; }
            const context = Object.assign({}, state.selection, { tipo_contexto: 'slot_libre', seleccionable: true,
                site_id: el.site.value ? Number(el.site.value) : null });
            selectInterval(context, null);
            state.offHoursMode = true;
            el.quickMode.textContent = 'FUERA DE HORARIO';
            el.quickMessage.textContent = 'Cita especial: bloqueará este intervalo. Seleccione paciente y servicio.';
            updateAppointmentAction();
        });
    }
    if (additionalStart) {
        additionalStart.addEventListener('click', function () {
            const selected = state.selection;
            if (!selected) { return; }
            const professional = (state.payload.profesionales || []).find((p) => p.id === selected.doctor_id);
            const day = professional && (professional.dias || []).find((d) => d.fecha === selected.fecha);
            const slot = day && (day.slots || []).find((s) => s.inicio === selected.hora_inicio && s.site_id === selected.site_id);
            if (!slot) { showNotice('Seleccione el inicio de un intervalo real del horario médico.'); return; }
            state.identity = lookupModel.blank();
            state.draft = null;
            paintIdentity(state.identity);
            el.documentNumber.value = '';
            el.lookupResult.textContent = '';
            el.responsibleSelect.value = '';
            selectInterval(window.AgendaAppointmentActions.additionalContext(selected, slot), null);
            state.additionalMode = true;
            el.quickMode.textContent = 'Cita ADICIONAL';
            el.quickMessage.textContent = 'Adicional aceptada en Agenda; el pago se registra por separado. Seleccione paciente y servicio.';
            updateAppointmentAction();
        });
    }

    async function moveAppointment(context, date, time, revert) {
        if (state.appointmentBusy || !canReschedule) { revert(); return; }
        state.appointmentBusy = true;
        updateAppointmentAction();
        try {
            await window.AgendaAppointmentActions.reschedule(context, date, time, {
                confirm: (message) => window.confirm(message), revert: revert, refresh: load,
                send: async function (id, payload) {
                    const response = await fetch(board.dataset.rescheduleUrl + '/' + id + '/reschedule', {
                        method: 'PATCH', credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json().catch(() => ({}));
                    if (response.status === 409 && result.confirmation_required === true) { return result; }
                    if (!response.ok) { throw new Error(appointmentCreateModel.validationMessage(result)); }
                    return result;
                },
            });
        } catch (error) { showNotice(error.message); }
        finally { state.appointmentBusy = false; updateAppointmentAction(); }
    }
    function syncModalBooking() {
        if (!draftService) { return; }
        draftService.replaceChildren(...Array.from(el.serviceSelect.options).map((option) => option.cloneNode(true)));
        draftService.value = el.serviceSelect.value;
        draftService.disabled = el.serviceSelect.disabled;
        el.draftCommercialOwner.disabled = el.responsibleSelect.disabled;
    }
    function clearServiceError() {
        [el.serviceSelect, draftService].forEach((input) => input.setAttribute('aria-invalid', 'false'));
        document.getElementById('agenda-service-error').hidden = true;
        document.getElementById('agenda-draft-service-error').hidden = true;
    }
    function requireService(inModal) {
        const option = el.serviceSelect.selectedOptions[0];
        if (el.serviceSelect.value && option && !option.disabled) { clearServiceError(); return true; }
        const input = inModal ? draftService : el.serviceSelect;
        const error = document.getElementById(inModal ? 'agenda-draft-service-error' : 'agenda-service-error');
        error.hidden = false;
        input.setAttribute('aria-invalid', 'true');
        input.focus();
        (inModal ? el.draftMessage : el.quickMessage).textContent = 'Selecciona un servicio para agendar la cita.';
        return false;
    }
    draftService.addEventListener('change', function () { el.serviceSelect.value = draftService.value; paintSelectedService(); });
    el.serviceSelect.addEventListener('change', paintSelectedService);
    el.responsibleSelect.addEventListener('change', function () {
        paintResponsible();
        if (state.draft) {
            state.draft = draftModel.edit(state.draft, 'commercial_owner_id', el.responsibleSelect.value);
            el.draftCommercialOwner.value = el.responsibleSelect.value;
        }
    });
    el.completeRegistration.addEventListener('click', openExistingPatient);
    el.draftReniec.addEventListener('click', async function () {
        if (!state.draft || !state.draft.reniecOffered) {
            return;
        }

        el.draftReniec.disabled = true;

        try {
            const response = await fetch(el.patientDraft.dataset.reniecEndpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    tipo_identificacion: 'DNI',
                    numero_identidad: state.draft.numero,
                }),
            });
            const data = response.ok ? await response.json() : null;
            paintDraft(draftModel.applyReniec(state.draft, data));
        } catch (error) {
            paintDraft(draftModel.applyReniec(state.draft, null));
        } finally {
            el.draftReniec.disabled = false;
        }
    });
    [
        [el.draftNombre, 'nombre'],
        [el.draftApellidoPaterno, 'apellido_paterno'],
        [el.draftApellidoMaterno, 'apellido_materno'],
        [el.draftPhonePrefix, 'telefono_prefijo'],
        [el.draftTelefono, 'telefono_numero'],
        [el.draftResponsibleRelationship, 'responsable_parentesco'],
        [el.draftResponsibleName, 'responsable_nombres'],
        [el.draftResponsiblePhone, 'responsable_telefono'],
        [el.draftResponsibleDocumentType, 'responsable_tipo_identificacion'],
        [el.draftResponsibleDocumentNumber, 'responsable_numero_identidad'],
        [el.draftEmail, 'email'],
        [el.draftFechaNacimiento, 'fecha_nacimiento'],
        [el.draftGenero, 'genero'],
        [el.draftEstadoCivil, 'estado_civil'],
        [el.draftDireccion, 'direccion'],
        [el.draftType, 'tipo'],
        [el.draftNumber, 'numero'],
        [el.draftChannel, 'channel_id'],
        [el.draftInteractionMedium, 'interaction_medium_id'],
        [el.draftOcupacion, 'ocupacion'],
        [el.draftGradoInstruccion, 'grado_instruccion'],
        [el.draftFamiliarContacto, 'familiar_contacto'],
        [el.draftCommercialOwner, 'commercial_owner_id'],
    ].forEach(function (pair) {
        bindDraftField(pair[0], pair[1]);
    });
    el.draftCommercialOwner.addEventListener('change', function () {
        el.responsibleSelect.value = el.draftCommercialOwner.value;
        paintResponsible();
        updateAppointmentAction();
    });
    el.draftPhonePrefix.addEventListener('change', function () {
        if (state.draft) {
            state.draft = draftModel.edit(state.draft, 'telefono_sin_separar', false);
        }
    });
    el.draftTelefono.addEventListener('input', function () {
        if (state.draft) {
            state.draft = draftModel.edit(state.draft, 'telefono_sin_separar', false);
        }
    });
    el.draftRegisterResponsible.addEventListener('change', function () {
        if (!state.draft) {
            return;
        }

        state.draft = draftModel.edit(state.draft, 'registrar_responsable', el.draftRegisterResponsible.checked);
        el.draftResponsible.hidden = !el.draftRegisterResponsible.checked;
    });

    el.patientTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const selected = tab.dataset.patientTab;
            el.patientTabs.forEach(function (item) {
                const active = item.dataset.patientTab === selected;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            el.patientPanels.forEach(function (panel) {
                panel.hidden = panel.dataset.patientPanel !== selected;
            });
        });
    });

    el.overlapForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (!state.selection || !state.selection.hora_inicio) {
            el.overlapResult.className = 'agenda-overlap__result is-warning';
            el.overlapResult.textContent = 'Seleccione primero un intervalo horario.';
            return;
        }

        const url = new URL(el.overlapForm.dataset.endpoint, window.location.origin);
        url.searchParams.set('doctor_id', state.selection.doctor_id);
        url.searchParams.set('fecha_cita', state.selection.fecha);
        url.searchParams.set('hora_inicio', el.overlapStart.value);
        url.searchParams.set('hora_fin', el.overlapEnd.value);

        try {
            const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
            const data = await response.json();
            el.overlapResult.className = 'agenda-overlap__result '
                + (response.ok && !data.solapa ? 'is-clear' : 'is-warning');
            el.overlapResult.textContent = response.ok ? data.mensaje : 'Revise las horas indicadas.';
        } catch (error) {
            console.error(error);
            el.overlapResult.className = 'agenda-overlap__result is-warning';
            el.overlapResult.textContent = 'No se pudo comprobar el cruce.';
        }
    });

    el.prev.addEventListener('click', () => shift(-1));
    el.next.addEventListener('click', () => shift(1));
    el.today.addEventListener('click', () => setDate(board.dataset.today));
    el.date.addEventListener('change', function () {
        if (el.date.value) {
            setDate(el.date.value);
        }
    });

    el.viewButtons.forEach((button) => {
        button.addEventListener('click', function () {
            setView(button.dataset.view);
            load();
        });
    });

    el.doctorChecks.forEach((check) => {
        check.addEventListener('change', function () {
            if (!state.compare && check.checked) {
                el.doctorChecks.forEach((other) => {
                    if (other !== check) {
                        other.checked = false;
                    }
                });
            }

            if (selectedDoctorIds().length > MAX_SELECTED_DOCTORS) {
                check.checked = false;
                showNotice('Puede comparar hasta 12 médicos a la vez.');
                return;
            }

            updateDoctorFilterSummary();
            load();
        });
    });

    el.compareToggle.addEventListener('change', function () {
        state.compare = el.compareToggle.checked;
        if (!state.compare && selectedDoctorIds().length > 1) {
            const keep = state.comparisonDoctorId || selectedDoctorIds()[0];
            el.doctorChecks.forEach((check) => {
                check.checked = check.value === keep;
            });
        }
        load();
    });

    el.comparisonOpen.addEventListener('click', function () {
        if (!state.comparisonDoctorId) {
            return;
        }
        state.compare = false;
        el.compareToggle.checked = false;
        el.doctorChecks.forEach((check) => {
            check.checked = check.value === state.comparisonDoctorId;
        });
        load();
    });

    el.doctorReset.addEventListener('click', function () {
        state.compare = false;
        state.comparisonDoctorId = null;
        el.compareToggle.checked = false;
        el.doctorChecks.forEach((check) => {
            check.checked = false;
        });
        load();
    });

    el.doctorFilter.addEventListener('click', () => el.doctorSearch.focus());
    el.doctorSearch.addEventListener('input', applyDoctorVisibility);
    el.site.addEventListener('change', load);
    el.specialty.addEventListener('change', function () {
        el.doctorChecks.forEach((check) => {
            const row = check.closest('[data-doctor-row]');
            if (el.specialty.value && row.dataset.specialty !== el.specialty.value) {
                check.checked = false;
            }
        });
        applyDoctorVisibility();
        load();
    });

    el.miniPrev.addEventListener('click', function () {
        state.miniAnchor.setMonth(state.miniAnchor.getMonth() - 1);
        renderMiniCalendar();
    });
    el.miniNext.addEventListener('click', function () {
        state.miniAnchor.setMonth(state.miniAnchor.getMonth() + 1);
        renderMiniCalendar();
    });

    let resizeTimer = null;
    window.addEventListener('resize', function () {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(function () {
            if (!el.calendar.hidden) {
                resizeCalendar();
                calendar.updateSize();
            }
        }, 120);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && state.draft) {
            closePatientModal();
        }
    });

    applyDoctorVisibility();
    renderMiniCalendar();
    load();
});
