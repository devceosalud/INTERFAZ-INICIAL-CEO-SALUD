@extends('layouts.app')

@section('body_class', 'agenda-viewport')

@section('css_data')
    <link href="{{ asset('assets/vendor/fullcalendar/css/main.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/scheduling/agenda.css') }}" rel="stylesheet">
@endsection

@section('body')
    @include('templates.preloader')

    {{-- Usa el modo compacto que el shell ya trae; no modifica el layout de otros módulos. --}}
    <div id="main-wrapper" class="menu-toggle agenda-shell-compact">
        @include('templates.nav-header')
        @include('templates.chat-box')
        @include('templates.header')
        @include('templates.sidebar')

        <div class="content-body agenda-page">
            <div class="container-fluid agenda-page__container">
                <main class="agenda-board agenda-board--day" id="agenda-board"
                    data-feed="{{ route('scheduling.mvp.agenda.feed') }}"
                    data-today="{{ $today }}"
                    data-grid-minutes="20">

                    @include('scheduling.agenda.partials.toolbar')

                    <div class="agenda-workspace">
                        <aside class="agenda-operations" aria-label="Selección y preparación de cita">
                            @include('scheduling.agenda.partials.professionals')
                            @include('scheduling.agenda.partials.mini-calendar')
                            @include('scheduling.agenda.partials.quick-registration')
                        </aside>

                        <section class="agenda-center" aria-labelledby="agenda-title">
                            <div class="agenda-center__head">
                                <div>
                                    <h2 class="agenda-section-title" id="agenda-title">Agenda horaria</h2>
                                    <p class="agenda-range" id="agenda-range-label" aria-live="polite"></p>
                                </div>

                                <dl class="agenda-kpis" aria-label="Resumen del periodo">
                                    <div>
                                        <dt>Libres</dt>
                                        <dd id="agenda-total-free">—</dd>
                                    </div>
                                    <div>
                                        <dt>Ocupadas</dt>
                                        <dd id="agenda-total-busy">—</dd>
                                    </div>
                                    <div>
                                        <dt>Min. libres</dt>
                                        <dd id="agenda-total-minutes">—</dd>
                                    </div>
                                </dl>
                            </div>

                            @include('scheduling.agenda.partials.context')

                            <div class="agenda-notice" id="agenda-load-state" role="status" aria-live="polite" hidden></div>

                            <section class="agenda-comparison" id="agenda-comparison" aria-labelledby="agenda-comparison-title" hidden>
                                <div class="agenda-comparison__head">
                                    <div>
                                        <h3 id="agenda-comparison-title">Comparación de médicos</h3>
                                        <p>Resumen de carga para decidir qué agenda abrir. No comprime médicos en la grilla horaria.</p>
                                    </div>
                                    <button type="button" class="agenda-btn" id="agenda-comparison-open">Abrir agenda seleccionada</button>
                                </div>
                                <div class="agenda-comparison__scroll">
                                    <table class="agenda-comparison__table">
                                        <thead>
                                            <tr>
                                                <th scope="col">Médico</th>
                                                <th scope="col">Especialidad</th>
                                                <th scope="col">Libres</th>
                                                <th scope="col">Ocupadas</th>
                                                <th scope="col">Min. libres</th>
                                                <th scope="col">Cobertura</th>
                                            </tr>
                                        </thead>
                                        <tbody id="agenda-comparison-body"></tbody>
                                    </table>
                                </div>
                            </section>

                            <div class="agenda-calendar-surface">
                                <section class="agenda-day-grid" id="agenda-day-grid"
                                    aria-label="Agenda operativa diaria">
                                    <div class="agenda-row-head" id="agenda-row-head" role="row"
                                        aria-label="Columnas de la agenda diaria">
                                        <span role="columnheader">Hora</span>
                                        <span role="columnheader">Citado</span>
                                        <span role="columnheader">Pago</span>
                                        <span role="columnheader">H.C.</span>
                                        <span role="columnheader">Apellidos y nombres</span>
                                    </div>

                                    <div class="agenda-day-grid__body" id="agenda-day-grid-body" role="rowgroup"></div>
                                </section>

                                <div id="agenda-calendar" class="agenda-calendar" hidden></div>
                            </div>

                            <div class="agenda-legend" aria-label="Leyenda de estados">
                                @foreach ($legend as $entry)
                                    <span class="agenda-legend__item">
                                        <span class="agenda-legend__marker"
                                            style="--key-color: {{ $entry['color'] }}; --key-bg: {{ $entry['fondo'] }}"
                                            aria-hidden="true"></span>
                                        <strong>{{ $entry['etiqueta'] }}</strong>
                                    </span>
                                @endforeach
                            </div>
                            <p class="agenda-center__foot" id="agenda-detail-hint"></p>
                        </section>
                    </div>
                </main>
            </div>
        </div>

        @include('templates.footer')
    </div>
@endsection

@section('script_data')
    <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
    <script src="{{ asset('assets/js/custom.min.js') }}"></script>
    <script src="{{ asset('assets/js/deznav-init.js') }}"></script>
    <script src="{{ asset('assets/vendor/fullcalendar/js/main.min.js') }}"></script>
    <script src="{{ asset('js/scheduling/agenda-selection.js') }}"></script>
    <script src="{{ asset('js/scheduling/agenda-day-grid.js') }}"></script>
    <script src="{{ asset('js/scheduling/agenda-week-event.js') }}"></script>
    <script src="{{ asset('js/scheduling/agenda-week-background.js') }}"></script>
    <script src="{{ asset('js/scheduling/agenda-patient-lookup.js') }}"></script>
    <script src="{{ asset('js/scheduling/agenda-patient-draft.js') }}"></script>
    <script src="{{ asset('js/scheduling/agenda.js') }}"></script>
@endsection
