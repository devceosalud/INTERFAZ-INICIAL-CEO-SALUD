@if(config('scheduling.enabled') && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS) && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::VIEW))
<section class="card clarity-mask" data-clarity-mask="true" aria-label="Reevaluaciones"
    data-operational-reevaluations="{{ route('scheduling.mvp.admission.reevaluations') }}"
    data-detail-base="{{ route('scheduling.mvp.admission') }}">
    <div class="card-header"><h2>Reevaluaciones</h2></div>
    <div class="card-body">
        <p>Regreso al médico dentro de la misma cita · jornada actual de Lima · sin nuevo cobro.</p>
        <p role="status" aria-live="polite">Consultando reevaluaciones…</p>
        <div data-review-rows></div>
    </div>
</section>
@once
<script defer src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/operational-reevaluations.js') }}"></script>
@endonce
@endif
