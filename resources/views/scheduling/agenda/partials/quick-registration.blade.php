<section class="agenda-operations__section agenda-quick" id="agenda-quick-registration"
    aria-labelledby="agenda-quick-title">
    <div class="agenda-pane-head">
        <div>
            <h2 class="agenda-section-title" id="agenda-quick-title">Registro rápido</h2>
            <p>Contexto preparado; sin guardado en MVP-2C</p>
        </div>
        <span class="agenda-quick__mode" id="agenda-quick-mode">Sin selección</span>
    </div>

    <div class="agenda-quick__body">
        <div class="agenda-quick__patient">
            <label class="agenda-field">
                <span class="agenda-field__label">DNI</span>
                <input class="agenda-field__input" id="agenda-quick-dni" type="text"
                    placeholder="Disponible en el flujo de registro" disabled>
            </label>
            <p>
                <span>Paciente</span>
                <strong id="agenda-quick-patient-state">Sin paciente seleccionado</strong>
            </p>
            <input type="hidden" id="agenda-quick-patient-id">
        </div>

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
            <div><dt>Operador</dt><dd>{{ auth()->user()->name ?? auth()->user()->nombre ?? 'Usuario autenticado' }}</dd></div>
        </dl>

        <p class="agenda-quick__message" id="agenda-quick-message">
            Seleccione médico, fecha e intervalo disponible. Esta pantalla no crea ni modifica citas.
        </p>

        <button type="button" class="agenda-btn agenda-complete-registration"
            id="agenda-complete-registration" disabled hidden
            title="La verificación por DNI y el completado funcional pertenecen a MVP-3">
            Completar registro
        </button>
        <p class="agenda-complete-registration__help" id="agenda-complete-registration-help" hidden>
            Disponible funcionalmente en MVP-3 — DNI + paciente rápido/completar registro.
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
