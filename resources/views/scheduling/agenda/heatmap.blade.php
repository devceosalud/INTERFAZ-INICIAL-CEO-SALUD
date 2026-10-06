@extends('layouts.app')

@section('body')
    <main class="container py-4" id="agenda-heatmap" data-endpoint="{{ route('scheduling.mvp.agenda.heatmap.data') }}">
        <h1>Mapa de clics · Agenda Operativa</h1>
        <p>Coordenadas del viewport normalizadas. No contiene datos de pacientes ni identifica usuarios.</p>
        <p>Rango de fechas en {{ $timezone }}; almacenamiento de eventos en UTC.</p>
        <a href="{{ route('scheduling.mvp.agenda') }}">Volver a Agenda</a>
        @unless ($installed)
            <p role="alert">La tabla de telemetría todavía no está instalada. Agenda continúa operativa.</p>
        @endunless
        <form id="agenda-heatmap-filters" class="d-flex flex-wrap gap-3 my-3">
            <label>Desde <input type="date" name="from" value="{{ now($timezone)->subDays(6)->toDateString() }}" required></label>
            <label>Hasta <input type="date" name="to" value="{{ now($timezone)->toDateString() }}" required></label>
            <label>Vista <select name="view_mode"><option value="">Todas</option><option value="dia">Día</option><option value="semana">Semana</option><option value="mes">Mes</option></select></label>
            <label>Zona <select name="element"><option value="">Todas</option>@foreach ($elements as $element)<option value="{{ $element }}">{{ $element }}</option>@endforeach</select></label>
            <button type="submit" class="btn btn-primary" @disabled(!$installed)>Consultar</button>
        </form>
        <p id="agenda-heatmap-status" role="status" aria-live="polite"></p>
        <canvas id="agenda-heatmap-canvas" width="1000" height="650" style="width:100%;max-width:1000px;border:1px solid #ccc" aria-label="Densidad de clics por posición normalizada"></canvas>
        <p>Arriba/izquierda = (0,0), abajo/derecha = (1,1). La intensidad indica cantidad de clics.</p>
    </main>
@endsection
@section('script_data')
    <script src="{{ asset('js/scheduling/agenda-heatmap.js') }}"></script>
@endsection
