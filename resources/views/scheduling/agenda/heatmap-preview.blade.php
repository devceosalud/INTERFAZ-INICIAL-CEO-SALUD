<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Agenda · referencia visual sanitizada</title>
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fullcalendar/css/main.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/erp-shell.css') }}">
    <link rel="stylesheet" href="{{ asset('css/scheduling/agenda.css') }}">
</head>
<body class="agenda-viewport">
<div id="main-wrapper" class="show menu-toggle agenda-shell-compact">
    <div class="erp-shell-brand"><span class="erp-shell-brand__link"><img src="{{ asset('assets/images/logo-full.png') }}" alt="CEO Salud"></span></div>
    <div class="erp-shell-header"><div class="erp-shell-actions"><span class="erp-shell-user__trigger">Vista de demostración</span></div></div>
    <nav class="erp-shell-nav" aria-label="Navegación de demostración"><ul class="erp-nav">
        @foreach(['Inicio','Pacientes','Responsables','Citas','Horarios'] as $name)
        <li class="erp-nav__module"><button class="erp-nav__trigger" type="button">{{ $name }}</button></li>
        @endforeach
    </ul></nav>
    <div class="content-body agenda-page"><div class="container-fluid agenda-page__container">
        <main class="agenda-board agenda-board--day" id="agenda-board" data-ui-screen="agenda">
            @include('scheduling.agenda.partials.toolbar')
            <div class="agenda-workspace">
                <aside class="agenda-operations" aria-label="Selección y preparación de cita">
                    <div class="agenda-operations__pair">
                        @include('scheduling.agenda.partials.professionals')
                        @include('scheduling.agenda.partials.mini-calendar')
                    </div>
                    @include('scheduling.agenda.partials.quick-registration')
                    @include('scheduling.agenda.partials.withdrawal')
                </aside>
                <section class="agenda-center" data-ui-zone="grid" aria-label="Agenda horaria">
                    <div class="agenda-center__head">
                        <div><h2 class="agenda-section-title">Agenda horaria · Médico de demostración</h2><a id="preview-audit-link" hidden>Mapa de clics</a><p class="agenda-range">09/10/2026</p></div>
                        <dl class="agenda-kpis"><div><dt>Libres</dt><dd>30</dd></div><div><dt>Ocupadas</dt><dd>0</dd></div><div><dt>Min. libres</dt><dd>600</dd></div><div><dt>Especiales</dt><dd>0 AD · 0 FH</dd></div></dl>
                    </div>
                    @include('scheduling.agenda.partials.context')
                    <div class="agenda-calendar-surface">
                        <section class="agenda-day-grid" id="agenda-day-grid">
                            <div class="agenda-row-head" role="row">@foreach(['Hora','Citado','Pago','H.C.','Apellidos y nombres'] as $label)<span role="columnheader">{{ $label }}</span>@endforeach</div>
                            <div class="agenda-day-grid__body" id="agenda-day-grid-body" role="rowgroup"></div>
                        </section>
                        <div id="agenda-calendar" class="agenda-calendar" hidden></div>
                    </div>
                    <div class="agenda-legend">@foreach($legend as $entry)<span class="agenda-legend__item"><span class="agenda-legend__marker" style="--key-color:{{ $entry['color'] }};--key-bg:{{ $entry['fondo'] }}"></span><strong>{{ $entry['etiqueta'] }}</strong></span>@endforeach</div>
                    <p class="agenda-center__foot">Referencia visual · sin pacientes reales</p>
                </section>
            </div>
        </main>
    </div></div>
</div>
<script src="{{ asset('assets/vendor/fullcalendar/js/main.min.js') }}"></script>
<script src="{{ asset('js/scheduling/agenda-day-grid.js') }}"></script>
<script src="{{ asset('js/scheduling/agenda-heatmap-preview.js') }}"></script>
</body>
</html>
