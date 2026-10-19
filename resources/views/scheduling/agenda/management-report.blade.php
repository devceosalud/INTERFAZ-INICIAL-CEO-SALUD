@extends('layouts.app')

@section('css_data')
<style>
    .management-report__all-sites { background: #f3f7f7 !important; color: #34465b; font-weight: 600; min-height: 50px; }
    #management-doctor { display: block; width: 100%; min-height: 50px; }
    .management-report__download-actions { display: flex; gap: 9px; flex-wrap: wrap; align-items: center; }
    .management-report__download-actions .btn { margin: 0; white-space: normal; }
    .management-report__filters .form-label { display: block; margin-bottom: 7px; font-weight: 600; }
    .management-report__filters .form-control { max-width: 100%; }
</style>
@endsection

@section('body')
@include('templates.preloader')
<div id="main-wrapper" class="menu-toggle">
    @include('templates.nav-header')
    @include('templates.chat-box')
    @include('templates.header')
    @include('templates.sidebar')

    <div class="content-body">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="mb-1">Reportes de gerencia — Agenda operativa</h2>
                            <p class="text-muted mb-0">Exporta a Excel con autofiltros integrados o conserva la descarga CSV. Selecciona un periodo y, si deseas, un profesional.</p>
                        </div>
                        <a class="btn btn-outline-secondary" href="{{ route('scheduling.mvp.agenda') }}">Volver a Agenda operativa</a>
                    </div>

                    <div class="alert alert-warning" role="note">
                        <strong>Información confidencial.</strong> Los reportes de citas y pagos contienen datos identificables y motivos de consulta.
                        Descárgalos solo para fines autorizados, guárdalos en una ubicación corporativa protegida y no los compartas por medios públicos.
                    </div>

                    <form method="GET" action="{{ route('scheduling.mvp.agenda.reports.appointments') }}">
                        <div class="row management-report__filters">
                            <div class="col-12 col-md-3 mb-3">
                                <label class="form-label" for="management-from">Desde</label>
                                <input class="form-control" type="date" id="management-from" name="from" value="{{ old('from', $from) }}" required>
                            </div>
                            <div class="col-12 col-md-3 mb-3">
                                <label class="form-label" for="management-to">Hasta</label>
                                <input class="form-control" type="date" id="management-to" name="to" value="{{ old('to', $to) }}" required>
                            </div>
                            <div class="col-12 col-md-3 mb-3">
                                <label class="form-label" for="management-site">Sede</label>
                                <div class="form-control d-flex align-items-center management-report__all-sites" id="management-site">Todas las sedes</div>
                            </div>
                            <div class="col-12 col-md-3 mb-3">
                                <label class="form-label" for="management-doctor">Profesional</label>
                                <select class="form-control" id="management-doctor" name="doctor_id">
                                    <option value="">Todos los profesionales</option>
                                    @foreach($doctors as $doctor)
                                        <option value="{{ $doctor->id }}" @selected(old('doctor_id') == $doctor->id)>{{ $doctor->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="text-muted">Puedes exportar hasta 31 días consecutivos. Se incluyen todas las sedes. Si no seleccionas un profesional, se incluyen todos.</p>
                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                        @endif
                        <div class="row">
                            <div class="col-12 col-lg-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <h3 class="h5">Citas, pacientes y economía</h3>
                                    <p>Una fila por cita: médico, sede, horario, paciente, documento, contacto, motivo, estados, descuentos, exoneración, saldo y fuente del pago.</p>
                                    <div class="management-report__download-actions">
                                        <button type="submit" class="btn btn-primary" name="format" value="xlsx" formaction="{{ route('scheduling.mvp.agenda.reports.appointments') }}">Excel con filtros (.xlsx)</button>
                                        <button type="submit" class="btn btn-outline-primary" name="format" value="csv" formaction="{{ route('scheduling.mvp.agenda.reports.appointments') }}">CSV</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <h3 class="h5">Detalle de pagos</h3>
                                    <p>Una fila por pago atribuido, con comprobante y método. Los pagos ambiguos o heredados quedan expresamente identificados para conciliación.</p>
                                    <div class="management-report__download-actions">
                                        <button type="submit" class="btn btn-primary" name="format" value="xlsx" formaction="{{ route('scheduling.mvp.agenda.reports.payments') }}">Excel con filtros (.xlsx)</button>
                                        <button type="submit" class="btn btn-outline-primary" name="format" value="csv" formaction="{{ route('scheduling.mvp.agenda.reports.payments') }}">CSV</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <h3 class="h5">Slots y disponibilidad</h3>
                                    <p>Una fila por intervalo: inicio, fin, duración, estado, sede y carga diaria. Se usa el mismo motor de disponibilidad de Agenda.</p>
                                    <div class="management-report__download-actions">
                                        <button type="submit" class="btn btn-primary" name="format" value="xlsx" formaction="{{ route('scheduling.mvp.agenda.reports.slots') }}">Excel con filtros (.xlsx)</button>
                                        <button type="submit" class="btn btn-outline-primary" name="format" value="csv" formaction="{{ route('scheduling.mvp.agenda.reports.slots') }}">CSV</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <p class="small text-muted mb-0">Los archivos .xlsx tienen filtros desplegables en todos los encabezados, primera fila fija y montos numéricos. También puedes descargar CSV UTF-8. Ningún archivo incluye macros.</p>
                </div>
            </div>
        </div>
    </div>
    @include('templates.footer')
</div>
@endsection

{{-- El preloader y la clase 'show' del shell dependen del inicializador global. --}}
@section('script_data')
    <script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/vendor/global/global.min.js') }}"></script>
    <script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
    <script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/js/custom.min.js') }}"></script>
    <script src="{{ \App\Support\Scheduling\AgendaAssets::url('assets/js/deznav-init.js') }}"></script>
    <script>
        // The legacy shell globally transforms all SELECTs with bootstrap-select.
        // Restore a native select on this reporting screen to avoid stacked controls.
        window.addEventListener('load', function () {
            var select = document.getElementById('management-doctor');
            if (!select) return;
            var $ = window.jQuery;
            if ($ && $.fn && $.fn.selectpicker) {
                var control = $(select);
                if (control.data('selectpicker')) control.selectpicker('destroy');
            }
            select.style.display = 'block';
        });
    </script>
@endsection
