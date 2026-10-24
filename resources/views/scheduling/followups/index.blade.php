@extends('layouts.app')
@section('body_class','admission-viewport medical-followups-viewport')
@section('css_data')
<link href="{{ \App\Support\Scheduling\AgendaAssets::url('css/scheduling/medical-followups.css') }}" rel="stylesheet">
@endsection
@section('body')
<div id="main-wrapper" class="menu-toggle">
    @include('templates.nav-header')
    @include('templates.header')
    @include('templates.sidebar')
    <div class="content-body"><main id="medical-followups" class="medical-followups" data-feed="{{ route('scheduling.mvp.medical-followups.feed') }}" data-base="{{ url('/scheduling-mvp/medical-followups') }}">
        <header><div><p class="eyebrow">CONTINUIDAD CLÍNICA · VISTA PERSONAL</p><h1>Seguimiento de pacientes</h1><p>Procedimientos indicados por ti · seguimiento separado de la atención original.</p></div><a class="mf-button" href="{{ route('scheduling.mvp.admission') }}">Agenda de admisión</a></header>
        <section id="medical-followup-counts" class="mf-counts" aria-label="Resumen de seguimientos"></section>
        <form id="medical-followup-filters" class="mf-filters" autocomplete="off">
            <label>Buscar paciente<input name="patient" type="search" maxlength="100" placeholder="Nombre o historia clínica"></label>
            <label>Estado<select name="status"><option value="">Todos los estados</option>@foreach(\App\Services\Scheduling\MedicalProcedureFollowupService::STATES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label>Procedimiento<select name="procedure"><option value="">Todos los procedimientos</option></select></label>
            <label>Origen<select name="origin"><option value="">Todos los orígenes</option><option value="manual">Envíos manuales</option><option value="automatic">Automáticos</option><option value="general">Seguimiento general</option></select></label>
            <label>Orden<select name="order"><option value="oldest">Más antiguos primero</option><option value="newest">Más recientes primero</option></select></label>
            <button class="mf-button primary" type="submit">Aplicar</button><button id="medical-followup-reset" class="mf-button" type="button">Restablecer</button>
        <div class="mf-filter-secondary"><label>Desde atención<input type="date" name="date_from"></label><label>Hasta atención<input type="date" name="date_to"></label><label>Antigüedad<select name="older_than"><option value="">Cualquier antigüedad</option><option value="7">Más de7 días</option><option value="30">Más de30 días</option><option value="90">Más de90 días</option></select></label></div></form>
        <div class="mf-quick-filters" aria-label="Filtros rápidos"><button type="button" class="mf-button" data-followup-quick="procedure:surgery">Cirugía</button><button type="button" class="mf-button" data-followup-quick="procedure:mri">Resonancia magnética</button><button type="button" class="mf-button" data-followup-quick="origin:manual">Envíos manuales</button><button type="button" class="mf-button" data-followup-quick="origin:general">Seguimiento general</button></div>
        <p id="medical-followup-status" role="status" aria-live="polite"></p>
        <div class="mf-workspace"><aside class="mf-context" aria-label="Contexto personal"><div class="mf-date-anchor"><span>{{ now('America/Lima')->translatedFormat('M Y') }}</span><strong>{{ now('America/Lima')->format('d') }}</strong><small>Hoy</small></div><p>Mi cartera<br>de seguimiento</p></aside><section class="mf-board" aria-label="Lista de seguimientos"><div class="mf-list-head"><span>Paciente / procedimiento</span><span>Solicitud / atención</span><span>Estado y revisión</span><span>Acciones</span></div><div id="medical-followup-list"></div><footer><button id="medical-followup-prev" class="mf-button" type="button">Anterior</button><span id="medical-followup-page"></span><button id="medical-followup-next" class="mf-button" type="button">Siguiente</button></footer></section></div>
    </main></div>
    @include('templates.footer')
</div>
@endsection
@section('script_data')
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/vendor/global/global.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/js/custom.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/agenda-shell.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/js/deznav-init.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/medical-followups.js') }}"></script>
@endsection
