@extends('layouts.app')
@section('body_class','admission-viewport')
@section('css_data')
<link href="{{ \App\Support\Scheduling\AgendaAssets::url('css/scheduling/medical-followups.css') }}" rel="stylesheet">
@endsection
@section('body')
<div id="main-wrapper" class="menu-toggle">
    @include('templates.nav-header')
    @include('templates.header')
    @include('templates.sidebar')
    <div class="content-body"><main id="medical-followups" class="medical-followups" data-feed="{{ route('scheduling.mvp.medical-followups.feed') }}" data-base="{{ url('/scheduling-mvp/medical-followups') }}">
        <header><div><p class="eyebrow">CONTINUIDAD DE ATENCIÓN</p><h1>Seguimiento de pacientes</h1><p>Procedimientos indicados por ti · seguimiento separado de la atención original.</p></div><a class="mf-button" href="{{ route('scheduling.mvp.admission') }}">Agenda de admisión</a></header>
        <section id="medical-followup-counts" class="mf-counts" aria-label="Resumen de seguimientos"></section>
        <form id="medical-followup-filters" class="mf-filters" autocomplete="off">
            <label>Buscar paciente<input name="patient" type="search" maxlength="100" placeholder="Nombre o historia clínica"></label>
            <label>Estado<select name="status"><option value="">Todos los estados</option>@foreach(\App\Services\Scheduling\MedicalProcedureFollowupService::STATES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label>Procedimiento<select name="procedure"><option value="">Todos los procedimientos</option></select></label>
            <label>Orden<select name="order"><option value="oldest">Más antiguos primero</option><option value="newest">Más recientes primero</option></select></label>
            <button class="mf-button primary" type="submit">Aplicar</button><button id="medical-followup-reset" class="mf-button" type="button">Restablecer</button>
        </form>
        <p id="medical-followup-status" role="status" aria-live="polite"></p>
        <section class="mf-board" aria-label="Lista de seguimientos"><div class="mf-list-head"><span>Paciente / procedimiento</span><span>Solicitud / atención</span><span>Estado y revisión</span><span>Acciones</span></div><div id="medical-followup-list"></div><footer><button id="medical-followup-prev" class="mf-button" type="button">Anterior</button><span id="medical-followup-page"></span><button id="medical-followup-next" class="mf-button" type="button">Siguiente</button></footer></section>
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
