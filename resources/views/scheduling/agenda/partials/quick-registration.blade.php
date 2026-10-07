<section data-ui-zone="booking" class="agenda-operations__section agenda-quick" id="agenda-quick-registration"
    aria-labelledby="agenda-quick-title">
    <div class="agenda-pane-head">
        <div>
            <h2 class="agenda-section-title" id="agenda-quick-title">Registro rápido</h2>
            <p>Registra ahora. Completa la ficha o el adelanto después.</p>
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
            <p><span>Celular</span><strong id="agenda-patient-phone-summary">—</strong></p>
            <input type="hidden" id="agenda-quick-patient-id" value="">
        </div>

        <button type="button" class="agenda-btn" id="agenda-patient-register" disabled hidden
            title="Abre el alta del paciente dentro de Agenda.">
            Registrar paciente
        </button>

        <dl class="agenda-quick__context agenda-fast-context">
            <div class="agenda-context-secondary"><dt>ID paciente</dt><dd id="agenda-quick-patient-id-display">—</dd></div>
            <div><dt>Médico</dt><dd id="agenda-quick-doctor">—</dd></div>
            <div class="agenda-context-secondary"><dt>Especialidad</dt><dd id="agenda-quick-specialty">—</dd></div>
            <div><dt>Fecha</dt><dd id="agenda-quick-date">—</dd></div>
            <div><dt>Hora</dt><dd id="agenda-quick-time">Seleccione un intervalo</dd></div>
            <div class="agenda-context-secondary"><dt>Duración</dt><dd id="agenda-quick-duration">—</dd></div>
            <div class="agenda-context-secondary"><dt>Sede</dt><dd id="agenda-quick-site">—</dd></div>
            <div class="agenda-context-secondary"><dt>Servicio</dt><dd id="agenda-quick-service">Pendiente de selección</dd></div>
            <div class="agenda-context-secondary"><dt>Precio normal</dt><dd id="agenda-quick-price">—</dd></div>
            <div><dt>Estado</dt><dd id="agenda-quick-status">Sin cita</dd></div>
            <div><dt>Pago</dt><dd id="agenda-quick-payment">—</dd></div>
            <div class="agenda-context-secondary"><dt>H.C.</dt><dd id="agenda-quick-clinical-record">—</dd></div>
            <div class="agenda-context-secondary"><dt>Quién agenda</dt><dd id="agenda-scheduler-user">{{ auth()->user()->name ?? 'Usuario autenticado' }}</dd></div>
            <div class="agenda-context-secondary"><dt>Comercial dueño</dt><dd id="agenda-commercial-owner">Sin asignar</dd></div>
        </dl>

        <div class="agenda-quick__booking-fields">
            <label class="agenda-field">
                <span class="agenda-field__label"><span>Servicio <b class="agenda-required-mark" aria-hidden="true">*</b></span><small class="agenda-required-badge">Obligatorio</small></span>
                <select class="agenda-field__input" id="agenda-service-select" aria-required="true" aria-describedby="agenda-service-error" disabled>
                    <option value="">Seleccione un intervalo</option>
                </select>
            </label>
            <label class="agenda-field">
                <span class="agenda-field__label">Comercial dueño</span>
                <select class="agenda-field__input" id="agenda-responsible-select" @disabled(!$canAssignResponsible)>
                    <option value="">{{ $autoOwner ? 'Automático: '.auth()->user()->name : 'Sin asignar' }}</option>
                    @foreach ($commercialUsers as $commercial)
                        <option value="{{ $commercial->id }}">{{ $commercial->name }}</option>
                    @endforeach
                </select>
                @unless ($canAssignResponsible)
                    <small>{{ $autoOwner ? 'Se asigna automáticamente al usuario autenticado.' : 'Requiere permiso para asignar responsable.' }}</small>
                @endunless
            </label>
        </div>

        <p id="agenda-service-error" class="agenda-field-error" role="alert" hidden>Selecciona un servicio para agendar la cita.</p>

        <p class="agenda-fast-success" id="agenda-registration-result" role="status" hidden></p>

        <p class="agenda-quick__message" id="agenda-quick-message">
            Seleccione médico, fecha, intervalo disponible, paciente y servicio.
        </p>

        <p class="agenda-guidance" id="agenda-booking-help">Guardar reserva: guarda el seguimiento sin confirmar el horario. Agendar cita: confirma el horario con al menos 50% de adelanto.</p>
        <p class="agenda-action-notice" id="agenda-action-notice" role="status" hidden></p>
        <div class="agenda-fast-actions">
        @if($canCreateAppointments)<span class="agenda-tip" id="agenda-pending-tip"><button id="agenda-pending-start" class="agenda-btn agenda-btn--primary" type="button" disabled>Guardar reserva</button></span>@endif
        <span class="agenda-tip" id="agenda-submit-tip"><button type="button" class="agenda-btn" id="agenda-appointment-submit" @disabled(!$canCreateAppointments)>
            Agendar cita
        </button></span>

        </div>
        <div class="agenda-secondary-actions"><span class="agenda-tip" id="agenda-payment-tip"><button id="agenda-open-payment" class="agenda-btn" type="button" disabled>Registrar adelanto</button></span>
        <button type="button" class="agenda-btn agenda-complete-registration" id="agenda-complete-registration" disabled hidden>Completar ficha</button></div>
        <p id="agenda-complete-registration-help" class="agenda-guidance" hidden>Completa los datos del paciente. Luego puedes volver a Agenda.</p>
        <div id="agenda-op-workspace-host">@include('scheduling.agenda.partials.operational-registration')</div>
        @if ($canCreateAppointments)
            <span class="agenda-tip" id="agenda-off-hours-tip"><button type="button" class="agenda-btn agenda-btn--off-hours" id="agenda-off-hours-start" hidden>Agendar fuera de horario</button></span>
        @endif
        @if ($canCreateAdditional)
            <span class="agenda-tip" id="agenda-additional-tip"><button type="button" class="agenda-btn agenda-btn--additional" id="agenda-additional-start" disabled>+ Cita adicional</button></span>
        @endif
        @if ($canRescheduleAppointments)
            <form id="agenda-reschedule-form" hidden>
                <p>Reprogramar la cita seleccionada, conservando paciente, servicio y precio.</p>
                <label class="agenda-field"><span>Fecha destino</span><input id="agenda-reschedule-date" class="agenda-field__input" type="date" required></label>
                <label class="agenda-field"><span>Hora destino</span><input id="agenda-reschedule-time" class="agenda-field__input" type="text" inputmode="numeric" placeholder="HH:MM" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" maxlength="5" aria-describedby="agenda-reschedule-time-help" required></label>
                <div class="agenda-minute-shortcuts" aria-label="Minutos rápidos">
                    <button type="button" class="agenda-btn" data-quick-minute="00">00</button>
                    <button type="button" class="agenda-btn" data-quick-minute="20">20</button>
                    <button type="button" class="agenda-btn" data-quick-minute="40">40</button>
                </div>
                <small id="agenda-reschedule-time-help">Hora de 24 horas: HH:MM. Puedes escribir cualquier minuto.</small>
                <p id="agenda-reschedule-error" class="agenda-field-error" role="alert" hidden></p>
                <button type="submit" class="agenda-btn">Reprogramar cita</button>
            </form>
        @endif

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
