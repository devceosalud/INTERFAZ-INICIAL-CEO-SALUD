<div class="agenda-patient-modal" id="agenda-patient-modal" hidden aria-hidden="true">
    <div class="agenda-patient-modal__backdrop" data-modal-close></div>
    <section class="agenda-patient-dialog" role="dialog" aria-modal="true" aria-labelledby="agenda-patient-modal-title">
        <header class="agenda-patient-dialog__head">
            <div>
                <p class="agenda-patient-dialog__eyebrow">Agenda / Paciente</p>
                <h2 id="agenda-patient-modal-title">Registrar paciente</h2>
            </div>
            <div class="agenda-patient-dialog__hce">
                <span>HCE</span>
                <strong id="agenda-draft-hce">—</strong>
                <small id="agenda-draft-hce-note">La HCE real se confirma después de guardar</small>
            </div>
            <button type="button" class="agenda-patient-dialog__close" id="agenda-draft-close" aria-label="Cerrar">×</button>
        </header>

        <form id="agenda-patient-draft" autocomplete="off"
            data-reniec-endpoint="{{ route('scheduling.mvp.agenda.reniec-lookup') }}"
            data-detail-template="{{ url('/patients/__PATIENT__') }}"
            data-store-endpoint="{{ route('patients.operational.store') }}"
            data-update-template="{{ url('/patients/__PATIENT__') }}"
            data-hce-supported-types='@json(\App\Support\Patients\PatientClinicalHistoryNumber::supportedDocumentTypes())'
            data-can-write="{{ $canWritePatients ? '1' : '0' }}">
            <div class="agenda-patient-tabs" role="tablist" aria-label="Datos del paciente">
                <button type="button" role="tab" aria-selected="true" class="is-active" data-patient-tab="essential">Datos esenciales</button>
                <button type="button" role="tab" aria-selected="false" data-patient-tab="complete">Datos completos</button>
            </div>

            <div class="agenda-patient-dialog__body">
                <p class="agenda-patient-dialog__notice" id="agenda-draft-note">
                    La HCE definitiva es asignada y devuelta por el backend al registrar al paciente.
                </p>
                <p class="agenda-patient-dialog__notice is-warning" id="agenda-draft-ruc" hidden>
                    RUC se registra manualmente, no utiliza RENIEC y todavía no tiene una regla HCE aprobada.
                </p>

                <section class="agenda-patient-tabpanel" data-patient-panel="essential">
                    <div class="agenda-patient-form-grid">
                        <label class="agenda-field">
                            <span class="agenda-field__label">Tipo de documento</span>
                            <select class="agenda-field__input" id="agenda-draft-type">
                                @foreach (\App\Http\Controllers\Patients\OperationalPatientController::DOCUMENT_TYPES as $documentType)
                                    <option value="{{ $documentType }}">{{ $documentType }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Número de documento</span>
                            <input class="agenda-field__input" id="agenda-draft-number" type="text" maxlength="255">
                        </label>
                        <div class="agenda-patient-reniec">
                            <button type="button" class="agenda-btn" id="agenda-draft-reniec" hidden>Consultar RENIEC</button>
                        </div>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Nombre</span>
                            <input class="agenda-field__input" id="agenda-draft-nombre" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Apellido paterno</span>
                            <input class="agenda-field__input" id="agenda-draft-apellido-paterno" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Apellido materno</span>
                            <input class="agenda-field__input" id="agenda-draft-apellido-materno" type="text" maxlength="255">
                        </label>
                        <div class="agenda-field">
                            <span class="agenda-field__label">Celular</span>
                            <div class="agenda-phone">
                                <select class="agenda-field__input" id="agenda-draft-phone-prefix" aria-label="Código país">
                                    @foreach ($phonePrefixes as $prefix => $country)
                                        <option value="{{ $prefix }}">{{ $country }} {{ $prefix }}</option>
                                    @endforeach
                                </select>
                                <input class="agenda-field__input" id="agenda-draft-telefono" type="tel" maxlength="32" inputmode="numeric" placeholder="999888777">
                            </div>
                        </div>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Género</span>
                            <select class="agenda-field__input" id="agenda-draft-genero">
                                <option value="">Seleccionar</option>
                                <option value="HOMBRE">HOMBRE</option>
                                <option value="MUJER">MUJER</option>
                            </select>
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Fecha de nacimiento</span>
                            <input class="agenda-field__input" id="agenda-draft-fecha-nacimiento" type="date">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Canal de captación</span>
                            <select class="agenda-field__input" id="agenda-draft-channel">
                                <option value="">Sin indicar</option>
                                @foreach ($channels as $channel)
                                    <option value="{{ $channel->id }}">{{ $channel->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </section>

                <section class="agenda-patient-tabpanel" data-patient-panel="complete" hidden>
                    <div class="agenda-patient-form-grid">
                        <label class="agenda-field agenda-field--pending">
                            <span class="agenda-field__label">Celular alternativo</span>
                            <input class="agenda-field__input" type="text" disabled placeholder="Pendiente de persistencia">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Correo electrónico</span>
                            <input class="agenda-field__input" id="agenda-draft-email" type="email" maxlength="255">
                        </label>
                        <label class="agenda-field agenda-patient-form-grid__wide">
                            <span class="agenda-field__label">Dirección</span>
                            <input class="agenda-field__input" id="agenda-draft-direccion" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Estado civil</span>
                            <select class="agenda-field__input" id="agenda-draft-estado-civil">
                                <option value="">Sin indicar</option>
                                @foreach ($civilStatuses as $civilStatus)
                                    <option value="{{ $civilStatus }}">{{ $civilStatus }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Ocupación</span>
                            <input class="agenda-field__input" id="agenda-draft-ocupacion" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Grado de instrucción</span>
                            <input class="agenda-field__input" id="agenda-draft-grado-instruccion" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Familiar de contacto</span>
                            <input class="agenda-field__input" id="agenda-draft-familiar-contacto" type="text" maxlength="255">
                        </label>
                        <label class="agenda-field agenda-field--pending">
                            <span class="agenda-field__label">Teléfono familiar</span>
                            <input class="agenda-field__input" type="text" disabled placeholder="Pendiente de persistencia">
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Medio de interacción</span>
                            <select class="agenda-field__input" id="agenda-draft-interaction-medium">
                                <option value="">Sin indicar</option>
                                @foreach ($interactionMedia as $medium)
                                    <option value="{{ $medium->id }}">{{ $medium->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="agenda-field">
                            <span class="agenda-field__label">Atribución comercial</span>
                            <select class="agenda-field__input" id="agenda-draft-commercial-owner">
                                <option value="">Sin asignar</option>
                                @foreach ($commercialUsers as $commercial)
                                    <option value="{{ $commercial->id }}">{{ $commercial->name }}</option>
                                @endforeach
                            </select>
                            <small>Se transporta en el borrador; persistencia pendiente.</small>
                        </label>
                        <div class="agenda-field agenda-patient-form-grid__wide">
                            <label class="agenda-check">
                                <input type="checkbox" id="agenda-draft-register-responsible">
                                <span>Registrar responsable o acompañante</span>
                            </label>
                            <div id="agenda-draft-responsible" hidden>
                                <div class="agenda-patient-form-grid">
                                    <label class="agenda-field">
                                        <span class="agenda-field__label">Parentesco</span>
                                        <select class="agenda-field__input" id="agenda-draft-responsible-relationship">
                                            @foreach ($relationships as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="agenda-field">
                                        <span class="agenda-field__label">Nombre completo</span>
                                        <input class="agenda-field__input" id="agenda-draft-responsible-name" type="text" maxlength="255">
                                    </label>
                                    <label class="agenda-field">
                                        <span class="agenda-field__label">Teléfono</span>
                                        <input class="agenda-field__input" id="agenda-draft-responsible-phone" type="tel" maxlength="255">
                                    </label>
                                    <label class="agenda-field">
                                        <span class="agenda-field__label">Tipo de documento</span>
                                        <select class="agenda-field__input" id="agenda-draft-responsible-document-type">
                                            @foreach ($documentTypes as $documentType)
                                                <option value="{{ $documentType }}">{{ $documentType }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="agenda-field">
                                        <span class="agenda-field__label">Número de documento</span>
                                        <input class="agenda-field__input" id="agenda-draft-responsible-document-number" type="text" maxlength="255">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <label class="agenda-field agenda-field--pending agenda-patient-form-grid__wide">
                            <span class="agenda-field__label">Observaciones clínicas opcionales</span>
                            <textarea class="agenda-field__input" disabled placeholder="Pendiente del modelo clínico / HCE"></textarea>
                        </label>
                    </div>
                </section>

                <div class="agenda-patient-dialog__schedule">
                    <span>Médico: <strong id="agenda-draft-context-doctor">—</strong></span>
                    <span>Fecha: <strong id="agenda-draft-context-date">—</strong></span>
                    <span>Hora: <strong id="agenda-draft-context-time">—</strong></span>
                    <span>Sede: <strong id="agenda-draft-context-site">—</strong></span>
                </div>
                <p class="agenda-draft__message" id="agenda-draft-message" role="status" aria-live="polite"></p>
            </div>

            <footer class="agenda-patient-dialog__actions">
                <span id="agenda-patient-write-policy">
                    {{ $canWritePatients ? 'Guardado habilitado para Admisión, Recepción y Comercial.' : 'Solo lectura: guardar requiere Admisión, Recepción o Comercial.' }}
                </span>
                <button type="button" class="agenda-btn" id="agenda-draft-cancel">Cancelar</button>
                <button type="button" class="agenda-btn" id="agenda-draft-save" @disabled(!$canWritePatients)>Guardar sin agendar</button>
                <button type="submit" class="agenda-btn agenda-btn--primary" id="agenda-draft-save-schedule" @disabled(!$canWritePatients)>
                    Guardar y agendar
                </button>
            </footer>
        </form>
    </section>
</div>
