<section class="agenda-operations__section agenda-quick" id="agenda-quick-registration"
    aria-labelledby="agenda-quick-title">
    <div class="agenda-pane-head">
        <div>
            <h2 class="agenda-section-title" id="agenda-quick-title">Registro rápido</h2>
            <p>Identificación y ficha conectadas; la cita se guarda en una fase posterior</p>
        </div>
        <span class="agenda-quick__mode" id="agenda-quick-mode">Sin selección</span>
    </div>

    <div class="agenda-quick__body">
        <form class="agenda-lookup" id="agenda-patient-lookup" autocomplete="off"
            data-endpoint="{{ route('scheduling.mvp.agenda.patient-lookup') }}">
            <label class="agenda-field">
                <span class="agenda-field__label">Tipo documento</span>
                <select class="agenda-field__input" id="agenda-document-type" name="tipo_identificacion">
                    <option value="DNI">DNI</option>
                    <option value="CARNET EXTRANJERIA">Carné de extranjería</option>
                    <option value="PTP">PTP</option>
                    <option value="TAM">TAM</option>
                    <option value="RUC">RUC</option>
                    <option value="PASAPORTE">Pasaporte</option>
                    <option value="SALVOCONDUCTO">Salvoconducto</option>
                    <option value="SIN DOCUMENTOS">Sin documentos</option>
                </select>
            </label>
            <label class="agenda-field">
                <span class="agenda-field__label">Número documento</span>
                <input class="agenda-field__input" id="agenda-document-number" name="numero_identidad"
                    type="text" inputmode="text" maxlength="255" autocomplete="off" spellcheck="false">
            </label>
            <button type="submit" class="agenda-btn agenda-btn--primary" id="agenda-document-search">Buscar</button>
        </form>

        <p class="agenda-lookup__result" id="agenda-patient-lookup-result" role="status" aria-live="polite"></p>

        <div class="agenda-quick__patient">
            <p>
                <span>Paciente</span>
                <strong id="agenda-quick-patient-state">Sin paciente seleccionado</strong>
            </p>
            <p>
                <span>H.C.E.</span>
                <strong id="agenda-lookup-clinical-record">—</strong>
            </p>
            <input type="hidden" id="agenda-quick-patient-id" value="">
        </div>

        <button type="button" class="agenda-btn" id="agenda-patient-register" disabled hidden
            title="Abre el alta del paciente dentro de Agenda.">
            Registrar paciente
        </button>

        <dl class="agenda-quick__context">
            <div><dt>ID paciente</dt><dd id="agenda-quick-patient-id-display">—</dd></div>
            <div><dt>Médico</dt><dd id="agenda-quick-doctor">—</dd></div>
            <div><dt>Especialidad</dt><dd id="agenda-quick-specialty">—</dd></div>
            <div><dt>Fecha</dt><dd id="agenda-quick-date">—</dd></div>
            <div><dt>Hora</dt><dd id="agenda-quick-time">Seleccione un intervalo</dd></div>
            <div><dt>Duración</dt><dd id="agenda-quick-duration">—</dd></div>
            <div><dt>Sede</dt><dd id="agenda-quick-site">—</dd></div>
            <div><dt>Servicio</dt><dd id="agenda-quick-service">Pendiente de selección</dd></div>
            <div><dt>Estado</dt><dd id="agenda-quick-status">Sin cita</dd></div>
            <div><dt>Pago</dt><dd id="agenda-quick-payment">—</dd></div>
            <div><dt>H.C.</dt><dd id="agenda-quick-clinical-record">—</dd></div>
            <div><dt>Quién agenda</dt><dd id="agenda-scheduler-user">{{ auth()->user()->name ?? 'Usuario autenticado' }}</dd></div>
            <div><dt>Comercial dueño</dt><dd id="agenda-commercial-owner">Pendiente de selección</dd></div>
        </dl>

        <p class="agenda-quick__message" id="agenda-quick-message">
            Seleccione médico, fecha e intervalo disponible. Esta pantalla no crea ni modifica citas.
        </p>

        <button type="button" class="agenda-btn agenda-complete-registration"
            id="agenda-complete-registration" disabled hidden
            title="Abre la ficha maestra del paciente dentro de Agenda">
            Completar registro
        </button>
        <p class="agenda-complete-registration__help" id="agenda-complete-registration-help" hidden>
            Actualiza la ficha maestra sin salir de la Agenda.
        </p>

        <details class="agenda-tools">
            <summary>Validar cruce de horario</summary>
            <form class="agenda-overlap" id="agenda-overlap-form"
                data-endpoint="{{ route('scheduling.mvp.schedule.overlap') }}">
                <div class="agenda-overlap__row">
                    <label class="agenda-field">
                        <span class="agenda-field__label">Inicio</span>
                        <input type="time" class="agenda-field__input" id="agenda-overlap-start" required>
                    </label>
                    <label class="agenda-field">
                        <span class="agenda-field__label">Fin</span>
                        <input type="time" class="agenda-field__input" id="agenda-overlap-end" required>
                    </label>
                </div>
                <button type="submit" class="agenda-btn agenda-btn--primary" id="agenda-overlap-submit">Comprobar cruce</button>
            </form>
            <p class="agenda-overlap__result" id="agenda-overlap-result" role="status" aria-live="polite"></p>
            <a class="agenda-link" href="{{ route('admissionit.doctor.schedule.index') }}" id="agenda-schedule-link">
                Configurar horarios médicos
            </a>
        </details>
    </div>
</section>
