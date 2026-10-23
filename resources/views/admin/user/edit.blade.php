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
    <!-- Datatable -->
    <link href="{{ asset('assets/vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link href="{{ asset('assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">
@endsection



@section('body')
    <!--*******************Preloader start********************-->
    @include('templates.preloader')
    <!--*******************Preloader end********************-->

    <!--**********************************Main wrapper start***********************************-->
    <div id="main-wrapper">

        <!--**********************************Nav header start***********************************-->
        @include('templates.nav-header')
        <!--**********************************Nav header end***********************************-->

        <!--**********************************Chat box start***********************************-->
        @include('templates.chat-box')
        <!--**********************************Chat box End***********************************-->

        <!--**********************************Header start***********************************-->
        @include('templates.header')
        <!--**********************************Header end ti-comment-alt***********************************-->

        <!--**********************************Sidebar start***********************************-->
        @include('templates.sidebar')
        <!--**********************************Sidebar end***********************************-->


        <!--**********************************Content body start***********************************-->

        <div class="content-body">
            <div class="container-fluid">
                {{--
                <div class="page-titles">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="javascript:void(0)">Table</a>
                            </li>
                            <li class="breadcrumb-item active">
                                <a href="javascript:void(0)">Permisos</a>
                            </li>
                        </ol>

                        <a href="{{ route('admin.roles.index') }}" class="btn btn-primary btn-rounded add-appointment">
                            Ver Roles
                        </a>
                    </div>
                </div>
                --}}
                <!-- row -->
                <div class="row">

                    <div class="col-12">
                        <div class="card">
                            <div
                                class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <h4 class="card-title">Roles</h4>

                                <a href="{{ route('admin.roles.index') }}"
                                    class="btn btn-primary btn-rounded add-appointment">
                                    Ver Roles
                                </a>
                            </div>
                            <div class="card-body">

                                @if (session('exito'))
                                    <div class="alert alert-success solid alert-end-icon alert-dismissible fade show">
                                        <span><i class="mdi mdi-check"></i></span>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"
                                            aria-label="btn-close">
                                            <span><i class="fa-solid fa-xmark"></i></span>
                                        </button>

                                        {{ session('exito') }}
                                    </div>
                                @endif

                                <div class="col-lg-12 table-responsive mb-5">
                                    {!! Form::model($user, ['route' => ['admin.user.update', $user], 'method' => 'put']) !!}

                                    {!! Form::label('text', 'Usuario escogido', ['class' => 'control-label mb-2']) !!}
                                    {!! Form::text('nombre', $user->name, ['class' => 'form-control mb-4', 'placeholder' => 'Nombre del usuario']) !!}

                                    @foreach ($roles as $role)
                                        <div>
                                            <label class="d-flex">
                                                {!! Form::radio('role', $role->id, $user->hasRole($role->id), ['class' => 'mr-1']) !!}
                                                {{ $role->name }}
                                            </label>
                                            @if ($role->name === 'MEDICO' && $user->hasRole('ADMINISTRADOR'))
                                                <p id="medical-role-additive-note" class="alert alert-info text-dark mt-2" role="note">
                                                    Agregar MEDICO conserva ADMINISTRADOR y sus permisos existentes.
                                                    El acceso médico solo funcionará tras vincular y verificar su identidad en Vinculación médica.
                                                    Otro administrador debe aprobar el cambio; se invalidarán las sesiones de esta cuenta.
                                                </p>
                                            @elseif ($role->name === 'MEDICO' && $user->roles->contains(fn($current) => $current->name !== 'MEDICO'))
                                                <p id="medical-role-incompatible-warning" class="alert alert-warning text-dark mt-2" role="note">
                                                    Esta cuenta tiene roles distintos de ADMINISTRADOR o MEDICO:
                                                    <strong>{{ $user->roles->pluck('name')->implode(', ') }}</strong>.
                                                    No se quitarán esos roles automáticamente para agregar MEDICO. Verifica los accesos antes de continuar.
                                                </p>
                                            @endif
                                        </div>
                                    @endforeach

                                    @if ($user->hasRole('MEDICO') && $user->hasRole('ADMINISTRADOR'))
                                        <p class="alert alert-warning text-dark mt-2" role="note">
                                            Esta cuenta posee MEDICO y ADMINISTRADOR. Elegir un rol distinto de MEDICO
                                            sustituirá los roles actuales y puede retirar el acceso clínico.
                                        </p>
                                        <label class="d-block mt-2">
                                            <input type="checkbox" name="confirm_remove_medico" value="1">
                                            Confirmo expresamente que quiero retirar MEDICO si selecciono otro rol.
                                        </label>
                                    @endif

                                    {!! Form::submit('Asignar Rol al usuario', ['class' => 'btn btn-primary mt-4 btn-rounded']) !!}

                                    {!! Form::close() !!}
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
                            {{ $doctor->nombre }} · CMP {{ $doctor->cmp ?? 'no registrado' }}
                            {{ $doctor->estado!=='ACTIVO'?'(inactivo)':'' }}
                            {{ $doctor->user && $doctor->user->id!==$target->id?'(cuenta ya vinculada)':'' }}
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

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <!--**********************************Content body end***********************************-->


        <!--**********************************Scripts***********************************-->

    @section('script_data')
        <!-- Required vendors -->
        <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>


        <!-- Datatable -->
        <script src="{{ asset('assets/vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins-init/datatables.init.js') }}"></script>
        <script src="{{ asset('assets/js/custom.min.js') }}"></script>
        <script src="{{ asset('assets/js/deznav-init.js') }}"></script>
        <script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/admin/master/user/doctor-link.js') }}"></script>
    @endsection



    <!--**********************************Footer start***********************************-->
    @include('templates.footer')
    <!--**********************************Footer end***********************************-->

    <!--**********************************Support ticket button start***********************************-->

    <!--**********************************Support ticket button end***********************************-->


</div>
<!--**********************************Main wrapper end***********************************-->


@endsection
