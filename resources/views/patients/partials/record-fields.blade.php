                            <input type="hidden" id="patient-record-id" value="">

                            <fieldset class="patients-block">
                                <legend>Identificación</legend>
                                <div class="patients-form-grid patients-form-grid--identity-document">
                                    <label class="patients-field">
                                        <span>Tipo documento</span>
                                        <select data-native-select id="patient-document-type">
                                            @foreach ($documentTypes as $documentType)
                                                <option value="{{ $documentType }}">{{ $documentType }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="patients-field">
                                        <span>Número documento</span>
                                        <input type="text" id="patient-document-number" maxlength="255" autocomplete="off">
                                    </label>
                                    <div class="patients-reniec-action" id="patient-reniec-wrap">
                                        <button class="patients-btn" type="button" id="patient-reniec">Consultar RENIEC</button>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Identidad</legend>
                                <div class="patients-form-grid patients-form-grid--three">
                                    <label class="patients-field">
                                        <span>Nombre</span>
                                        <input type="text" id="patient-name" maxlength="255">
                                    </label>
                                    <label class="patients-field">
                                        <span>Apellido paterno</span>
                                        <input type="text" id="patient-paternal-name" maxlength="255">
                                    </label>
                                    <label class="patients-field">
                                        <span>Apellido materno</span>
                                        <input type="text" id="patient-maternal-name" maxlength="255">
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Contacto</legend>
                                <div class="patients-form-grid patients-form-grid--two">
                                    <div class="patients-field">
                                        <span>Teléfono</span>
                                        <div class="patients-phone">
                                            <select data-native-select id="patient-phone-prefix" aria-label="Código país">
                                                @foreach ($phonePrefixes as $prefix => $country)
                                                    <option value="{{ $prefix }}" @selected($prefix === '+51')>{{ $country }} {{ $prefix }}</option>
                                                @endforeach
                                            </select>
                                            <input type="tel" id="patient-phone" maxlength="32" inputmode="numeric" placeholder="999888777" autocomplete="off">
                                        </div>
                                    </div>
                                    <label class="patients-field">
                                        <span>Email</span>
                                        <input type="email" id="patient-email" maxlength="255" placeholder="persona@dominio.com" autocomplete="off">
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Datos operativos</legend>
                                <div class="patients-form-grid patients-form-grid--three">
                                    <label class="patients-field"><span>Celular secundario · opcional</span><input id="patient-phone-secondary" type="tel" maxlength="32"></label>
                                    <label class="patients-field">
                                        <span>Canal de captación</span>
                                        <select data-native-select id="patient-channel">
                                            <option value="">Sin indicar</option>
                                            @foreach ($channels as $channel)
                                                <option value="{{ $channel->id }}">{{ $channel->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="patients-field">
                                        <span>Medio de contacto</span>
                                        <select data-native-select id="patient-interaction-medium">
                                            <option value="">Sin indicar</option>
                                            @foreach ($interactionMedia as $medium)
                                                <option value="{{ $medium->id }}">{{ $medium->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="patients-field">
                                        <span>Ocupación</span>
                                        <input type="text" id="patient-occupation" maxlength="255">
                                    </label>
                                    <label class="patients-field">
                                        <span>Grado de instrucción</span>
                                        <input type="text" id="patient-education" maxlength="255">
                                    </label>
                                    <label class="patients-field">
                                        <span>Familiar de contacto</span>
                                        <input type="text" id="patient-family-contact" maxlength="255">
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Datos personales</legend>
                                <div class="patients-form-grid patients-form-grid--personal">
                                    <label class="patients-field">
                                        <span>Fecha de nacimiento</span>
                                        <input type="date" id="patient-birth-date">
                                    </label>
                                    <label class="patients-field">
                                        <span>Género</span>
                                        <select data-native-select id="patient-gender">
                                            <option value="">Seleccionar</option>
                                            <option value="HOMBRE">HOMBRE</option>
                                            <option value="MUJER">MUJER</option>
                                        </select>
                                    </label>
                                    <label class="patients-field">
                                        <span>Estado civil</span>
                                        <select data-native-select id="patient-civil-status">
                                            <option value="">Sin indicar</option>
                                            @foreach ($civilStatuses as $civilStatus)
                                                <option value="{{ $civilStatus }}">{{ $civilStatus }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="patients-field patients-field--address">
                                        <span>Dirección</span>
                                        <input type="text" id="patient-address" maxlength="255">
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block">
                                <legend>Responsable o acompañante</legend>
                                <label class="patients-check">
                                    <input type="checkbox" id="patient-register-responsible">
                                    <span>Registrar responsable o acompañante</span>
                                </label>
                                <p class="patients-help">Los pacientes menores de 18 años deben registrar un responsable o acompañante adulto. Desmarcar esta opción no elimina un responsable ya registrado.</p>
                                <div id="patient-responsible" hidden>
                                    <div class="patients-form-grid patients-form-grid--three">
                                        <label class="patients-field">
                                            <span>Parentesco</span>
                                            <select data-native-select id="patient-responsible-relationship">
                                                @foreach ($relationships as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="patients-field">
                                            <span>Nombre completo</span>
                                            <input type="text" id="patient-responsible-name" maxlength="255" autocomplete="off">
                                        </label>
                                        <label class="patients-field">
                                            <span>Teléfono</span>
                                            <input type="tel" id="patient-responsible-phone" maxlength="255" autocomplete="off">
                                        </label>
                                        <label class="patients-field">
                                            <span>Tipo de documento</span>
                                            <select data-native-select id="patient-responsible-document-type">
                                                @foreach ($documentTypes as $documentType)
                                                    <option value="{{ $documentType }}">{{ $documentType }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="patients-field">
                                            <span>Número de documento</span>
                                            <input type="text" id="patient-responsible-document-number" maxlength="255" autocomplete="off">
                                        </label>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="patients-block patients-block--pending">
                                <legend>Contexto / esta atención</legend>
                                <div class="patients-form-grid patients-form-grid--two">
                                    <label class="patients-field">
                                        <span>Fecha</span>
                                        <input type="date" id="patient-encounter-date" value="{{ $filters['fecha'] }}" disabled>
                                    </label>
                                    <label class="patients-field">
                                        <span>Motivo</span>
                                        <input type="text" id="patient-encounter-reason" placeholder="Pendiente de modelo de atención" disabled>
                                    </label>
                                </div>
                                <p>No se persiste: el sistema heredado no contiene todavía una entidad de atención ambulatoria.</p>
                            </fieldset>
