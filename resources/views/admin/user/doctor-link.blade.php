@extends('layouts.app')
@section('css_data')
<style>
/* Scope styles to this form; do not disturb other ERP selectors. */
#user-doctor-link { max-width: 800px; }
#user-doctor-link .medical-link-field { margin: 0 0 20px; }
#user-doctor-link .medical-link-field > label {
    display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600;
}
#user-doctor-link select[data-native-select],
#user-doctor-link textarea[name="reason"] {
    box-sizing: border-box; display: block; width: 100%; max-width: 100%;
    min-width: 0; border: 1px solid #cad4d9; border-radius: 6px;
    background: #fff; color: #24343d; font-size: 14px; line-height: 1.4;
}
#user-doctor-link select[data-native-select] {
    height: 46px; padding: 8px 12px; appearance: auto;
}
#user-doctor-link textarea[name="reason"] {
    min-height: 100px; padding: 11px 12px; resize: vertical;
}
#user-doctor-link .medical-link-confirm {
    display: flex; align-items: flex-start; gap: 9px;
    max-width: 800px; line-height: 1.5;
}
#user-doctor-link .medical-link-confirm input {
    flex: 0 0 auto; margin-top: 5px;
}
#user-doctor-link .medical-link-field :is(select,textarea):focus-visible,
#user-doctor-link .medical-link-confirm input:focus-visible {
    outline: 2px solid #2d8a8c; outline-offset: 2px;
}
</style>
@endsection
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
            <div class="medical-link-field">
                <label for="medical-link-doctor">Médico del catálogo</label>
                <select id="medical-link-doctor" data-native-select name="doctor_id" aria-label="Médico del catálogo">
                    <option value="" @selected($target->doctor_id === null)>Sin vinculación / retirar vínculo</option>
                    @foreach($doctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected($target->doctor_id===$doctor->id)
                            @disabled($doctor->estado!=='ACTIVO' || ($doctor->user && $doctor->user->id!==$target->id))>
                            #{{ $doctor->id }} · {{ $doctor->nombre }} · CMP {{ $doctor->cmp ?? 'no registrado' }}
                            {{ $doctor->estado!=='ACTIVO'?'(inactivo)':'' }}
                            {{ $doctor->user && $doctor->user->id!==$target->id?'(cuenta ya vinculada #'.$doctor->user->id.')':'' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="medical-link-field">
                <label for="medical-link-reason">Motivo de la vinculación</label>
                <textarea id="medical-link-reason" name="reason" required minlength="3" maxlength="500" placeholder="Explica por qué se vincula esta cuenta con el médico seleccionado"></textarea>
            </div>
            <label class="medical-link-confirm"><input name="identity_confirmed" type="checkbox" required> <span>He verificado la identidad personal y su correspondencia con el médico seleccionado.</span></label>
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
