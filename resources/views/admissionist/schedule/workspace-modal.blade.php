<div class="modal fade schedule-modal" id="schedule-editor" tabindex="-1" data-bs-backdrop="static"
    aria-labelledby="schedule-editor-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="schedule-editor-form" novalidate>
                <div class="modal-header">
                    <div>
                        <p class="schedule-modal__eyebrow">Horario médico</p>
                        <h2 class="modal-title" id="schedule-editor-title">Nuevo horario</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="schedule-form-error" id="schedule-form-error" role="alert" hidden></div>
                    <input type="hidden" id="schedule-id" name="doctor_schedule_id_edit">
                    <input type="hidden" id="schedule-recurrence">

                    <div class="schedule-form-grid">
                        <label>
                            <span>Médico</span>
                            <select id="schedule-doctor" name="doctor_id" required>
                                <option value="">Selecciona un médico</option>
                                @foreach ($doctors as $doctor)
                                    <option value="{{ $doctor->id }}" data-specialty-id="{{ $doctor->specialty_id }}"
                                        data-specialty-name="{{ optional($doctor->specialty)->nombre }}">
                                        {{ $doctor->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Especialidad</span>
                            <input type="text" id="schedule-specialty" readonly>
                        </label>
                        <label>
                            <span>Sede</span>
                            <select id="schedule-site" name="site_id">
                                <option value="">Sin sede</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Fecha de referencia</span>
                            <input type="date" id="schedule-date" name="fecha_cita" required>
                        </label>
                        <section class="schedule-attention" aria-label="Horario de atención">
                            <p class="schedule-attention__title">Horario de atención</p>
                            <div class="schedule-attention__range">
                                <div class="schedule-time-field" data-schedule-time="schedule-start">
                                    <p class="schedule-time-field__title">Inicio de atención</p>
                                    <div class="schedule-time">
                                        <label>
                                            <span>Hora</span>
                                            <select id="schedule-start-hour" data-part="hour">
                                                @for ($hour = 0; $hour <= 23; $hour++)
                                                    <option value="{{ sprintf('%02d', $hour) }}">{{ sprintf('%02d', $hour) }}</option>
                                                @endfor
                                            </select>
                                        </label>
                                        <span class="schedule-time__colon" aria-hidden="true">:</span>
                                        <label>
                                            <span>Minuto</span>
                                            <select id="schedule-start-minute" data-part="minute">
                                                @for ($minute = 0; $minute <= 55; $minute += 5)
                                                    <option value="{{ sprintf('%02d', $minute) }}">{{ sprintf('%02d', $minute) }}</option>
                                                @endfor
                                                <option value="other">Otro</option>
                                            </select>
                                        </label>
                                        <label data-part="custom-wrap" hidden>
                                            <span>Otro</span>
                                            <input id="schedule-start-custom" data-part="custom" inputmode="numeric" maxlength="2" autocomplete="off">
                                        </label>
                                    </div>
                                    <p class="schedule-time__chips-label">Minutos rápidos</p>
                                    <div class="schedule-time__chips" role="group" aria-label="Minutos rápidos de inicio de atención">
                                        @for ($minute = 0; $minute <= 55; $minute += 5)
                                            <button type="button" data-minute="{{ sprintf('%02d', $minute) }}">{{ sprintf('%02d', $minute) }}</button>
                                        @endfor
                                    </div>
                                    <input type="hidden" id="schedule-start" name="hora_inicio" required>
                                </div>
                                <div class="schedule-time-field" data-schedule-time="schedule-end">
                                    <p class="schedule-time-field__title">Fin de atención</p>
                                    <div class="schedule-time">
                                        <label>
                                            <span>Hora</span>
                                            <select id="schedule-end-hour" data-part="hour">
                                                @for ($hour = 0; $hour <= 23; $hour++)
                                                    <option value="{{ sprintf('%02d', $hour) }}">{{ sprintf('%02d', $hour) }}</option>
                                                @endfor
                                            </select>
                                        </label>
                                        <span class="schedule-time__colon" aria-hidden="true">:</span>
                                        <label>
                                            <span>Minuto</span>
                                            <select id="schedule-end-minute" data-part="minute">
                                                @for ($minute = 0; $minute <= 55; $minute += 5)
                                                    <option value="{{ sprintf('%02d', $minute) }}">{{ sprintf('%02d', $minute) }}</option>
                                                @endfor
                                                <option value="other">Otro</option>
                                            </select>
                                        </label>
                                        <label data-part="custom-wrap" hidden>
                                            <span>Otro</span>
                                            <input id="schedule-end-custom" data-part="custom" inputmode="numeric" maxlength="2" autocomplete="off">
                                        </label>
                                    </div>
                                    <p class="schedule-time__chips-label">Minutos rápidos</p>
                                    <div class="schedule-time__chips" role="group" aria-label="Minutos rápidos de fin de atención">
                                        @for ($minute = 0; $minute <= 55; $minute += 5)
                                            <button type="button" data-minute="{{ sprintf('%02d', $minute) }}">{{ sprintf('%02d', $minute) }}</button>
                                        @endfor
                                    </div>
                                    <input type="hidden" id="schedule-end" name="hora_fin" required>
                                </div>
                            </div>
                        </section>
                        <label class="schedule-form-grid__duration">
                            <span>Duración programada por cita</span>
                            <select id="schedule-duration" name="duracion_cita" required>
                                @foreach ([10, 15, 20, 30, 45, 60] as $minutes)
                                    <option value="{{ $minutes }}">{{ $minutes }} minutos</option>
                                @endforeach
                            </select>
                            <small>No representa la duración clínica real de la atención.</small>
                        </label>
                    </div>

                    <div id="schedule-painted-dates" hidden>
                        <p id="schedule-preset-apply" hidden></p>
                        <p id="schedule-painted-count"></p>
                        <div id="schedule-painted-chips"></div>
                    </div>

                    <fieldset class="schedule-scope" id="schedule-scope">
                        <legend>Aplicar horario</legend>
                        <label><input type="radio" name="scope" value="single" checked> Solo este día</label>
                        <label><input type="radio" name="scope" value="selected"> Días seleccionados de esta semana</label>
                        <label><input type="radio" name="scope" value="weekly"> Patrón semanal recurrente</label>
                    </fieldset>

                    <div class="schedule-weekdays" id="schedule-weekdays" hidden>
                        <div class="schedule-weekdays__shortcuts">
                            <button type="button" data-weekdays="1,2,3,4,5">Lun–Vie</button>
                            <button type="button" data-weekdays="1,2,3,4,5,6,7">Toda la semana</button>
                            <button type="button" data-weekdays="6,7">Fin de semana</button>
                        </div>
                        <div class="schedule-weekdays__checks">
                            @foreach (\App\Models\DoctorSchedule::DIAS as $number => $day)
                                <label><input type="checkbox" name="weekdays[]" value="{{ $number }}"> {{ mb_substr($day, 0, 3) }}</label>
                            @endforeach
                        </div>
                        <p id="schedule-scope-help"></p>
                    </div>

                    <div class="schedule-overlap-status" id="schedule-overlap-status" role="status"></div>
                    <p class="schedule-edit-scope" id="schedule-edit-scope" hidden></p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="schedule-btn schedule-btn--danger" id="schedule-delete" hidden>Inactivar horario</button>
                    <span class="schedule-modal__spacer"></span>
                    <button type="button" class="schedule-btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="schedule-btn schedule-btn--primary" id="schedule-save">Revisar y guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
