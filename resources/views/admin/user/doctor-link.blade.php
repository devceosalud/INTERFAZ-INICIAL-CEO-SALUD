@extends('layouts.app')
@section('body')
<div id="main-wrapper">
@include('templates.nav-header')
@include('templates.header')
@include('templates.sidebar')
<div class="content-body"><main class="container-fluid">
    <h1>Vinculación médica</h1>
    <p><a href="{{ route('admin.user.index') }}">Volver a Usuarios / buscar o crear una cuenta</a></p>
    <p>Reutiliza la cuenta existente tras comprobar personalmente su identidad. Si no existe, utiliza Agregar Usuario y vuelve a vincularla. No se crean cuentas ni se asignan roles por vincular.</p>
    <div class="card"><div class="card-body">
        <h2>{{ $target->name }} · Cuenta #{{ $target->id }}</h2>
        <p>{{ $target->email }} · DNI: {{ $target->dni ?? 'sin DNI heredado' }} · {{ $target->is_active?'Activa':'Desactivada' }}</p>
        <p>Vínculo actual: <strong>{{ $target->doctor?->nombre ?? 'Sin vinculación' }}</strong></p>
        <p>El rol MEDICO y sus permisos se asignan por separado desde los editores existentes. La modificación invalida solo las sesiones de esta cuenta.</p>
        @if($target->id===auth()->id())
            <p role="alert">Otro administrador debe gestionar tu vinculación. No puedes modificarla tú mismo.</p>
        @else
        <form id="user-doctor-link" data-url="{{ route('admin.user.doctor-link.update',$target->id) }}"
            data-version="{{ $target->auth_version }}" data-current="{{ $target->doctor_id }}" data-current-label="{{ $target->doctor?->nombre ?? 'Sin vinculación' }}">
            <label class="d-block">Médico del catálogo
                <select data-native-select name="doctor_id" class="form-control" aria-label="Médico del catálogo">
                    <option value="">Sin vinculación / retirar vínculo</option>
                    @foreach($doctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected($target->doctor_id===$doctor->id)
                            @disabled($doctor->estado!=='ACTIVO' || ($doctor->user && $doctor->user->id!==$target->id))>
                            #{{ $doctor->id }} · {{ $doctor->nombre }} · CMP {{ $doctor->cmp ?? 'no registrado' }}
                            {{ $doctor->estado!=='ACTIVO'?'(inactivo)':'' }}
                            {{ $doctor->user && $doctor->user->id!==$target->id?'(cuenta ya vinculada #'.$doctor->user->id.')':'' }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="d-block mt-3">Motivo<textarea name="reason" class="form-control" required minlength="3" maxlength="500"></textarea></label>
            <label class="d-block mt-3"><input name="identity_confirmed" type="checkbox" required> He verificado la identidad personal y su correspondencia con el médico seleccionado.</label>
            <button class="btn btn-primary mt-3" type="submit">Revisar vinculación</button>
            <p id="user-doctor-link-status" role="status" aria-live="polite"></p>
        </form>
        @endif
    </div></div>
    <section class="card"><div class="card-body"><h2>Historial de vinculaciones</h2>
        @forelse($events as $event)
            <article class="border-bottom py-2">
                <p>{{ $event->created_at->format('d/m/Y H:i:s') }} · {{ $people[$event->actor_user_id] ?? 'Usuario no disponible' }}</p>
                <p>Médico anterior: #{{ $event->metadata['before']['doctor_id'] ?? 'sin vínculo' }} → Médico nuevo: #{{ $event->metadata['after']['doctor_id'] ?? 'sin vínculo' }}</p>
                <p>Motivo: {{ $event->reason }}</p><small>Operación {{ $event->request_key }} · Identidad confirmada · Roles conservados</small>
            </article>
        @empty<p>Sin cambios registrados.</p>@endforelse
        {{ $events->links() }}
    </div></section>
</main></div>
@include('templates.footer')
</div>
@endsection
@section('script_data')
<script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
<script src="{{ asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
<script src="{{ asset('assets/js/custom.min.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/scheduling/agenda-shell.js') }}"></script>
<script src="{{ asset('assets/js/deznav-init.js') }}"></script>
<script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/admin/master/user/doctor-link.js') }}"></script>
@endsection
