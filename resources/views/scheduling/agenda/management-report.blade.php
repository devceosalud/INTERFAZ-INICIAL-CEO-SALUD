@extends('layouts.app')

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
                            <p class="text-muted mb-0">Descargas CSV compatibles con Excel, con filtros independientes de la vista diaria.</p>
                        </div>
                        <a class="btn btn-outline-secondary" href="{{ route('scheduling.mvp.agenda') }}">Volver a Agenda operativa</a>
                    </div>

                    <div class="alert alert-warning" role="note">
                        <strong>Información confidencial.</strong> Los reportes de citas y pagos contienen datos identificables y motivos de consulta.
                        Descárgalos solo para fines autorizados, guárdalos en una ubicación corporativa protegida y no los compartas por medios públicos.
                    </div>

                    <form method="GET" action="{{ route('scheduling.mvp.agenda.reports.appointments') }}">
                        <div class="row">
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
                                <select class="form-control" id="management-site" name="site_id">
                                    <option value="">Todas las sedes</option>
                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}" @selected(old('site_id') == $site->id)>{{ $site->nombre }}</option>
                                    @endforeach
                                </select>
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
                        <p class="text-muted">Puedes exportar hasta 31 días consecutivos. Las fechas incluyen ambos extremos. Si dejas una sede o profesional sin seleccionar, se incluyen todos los aplicables.</p>
                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                        @endif
                        <div class="row">
                            <div class="col-12 col-lg-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <h3 class="h5">Citas, pacientes y economía</h3>
                                    <p>Una fila por cita: médico, sede, horario, paciente, documento, contacto, motivo, estados, descuentos, exoneración, saldo y fuente del pago.</p>
                                    <button type="submit" class="btn btn-primary" formaction="{{ route('scheduling.mvp.agenda.reports.appointments') }}">Descargar citas CSV</button>
                                </div>
                            </div>
                            <div class="col-12 col-lg-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <h3 class="h5">Detalle de pagos</h3>
                                    <p>Una fila por pago atribuido, con comprobante y método. Los pagos ambiguos o heredados quedan expresamente identificados para conciliación.</p>
                                    <button type="submit" class="btn btn-primary" formaction="{{ route('scheduling.mvp.agenda.reports.payments') }}">Descargar pagos CSV</button>
                                </div>
                            </div>
                            <div class="col-12 col-lg-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <h3 class="h5">Slots y disponibilidad</h3>
                                    <p>Una fila por intervalo: inicio, fin, duración, estado, sede y carga diaria. Se usa el mismo motor de disponibilidad de Agenda.</p>
                                    <button type="submit" class="btn btn-primary" formaction="{{ route('scheduling.mvp.agenda.reports.slots') }}">Descargar slots CSV</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <p class="small text-muted mb-0">Formato UTF-8 con separador punto y coma (;) y primera fila de encabezados. Sin macros ni conexiones externas. Para gráficos, filtros y estilos avanzados, importa los CSV en Excel y guarda la copia interna como .xlsx.</p>
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
@endsection
