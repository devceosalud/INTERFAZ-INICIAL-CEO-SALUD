<div class="deznav">
    <div class="deznav-scroll">
        <ul class="metismenu" id="menu">
            <li><a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
                    <i class="flaticon-381-networking"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
                <ul aria-expanded="false">
                    <li><a href="{{ route('admin.dashboard.index') }}">Inicio</a></li>
                </ul>
            </li>

            @if (auth()->user()->getRoleNames()->contains('ADMISION'))
                <li><a href="javascript:void(0);" class="ai-icon has-arrow" aria-expanded="false">
                        <i class="flaticon-381-id-card-4"></i>
                        <span class="nav-text">Pacientes</span>
                    </a>
                    <ul aria-expanded="false">
                        <li><a href="{{ route('admissionit.patient.index') }}">Lista de Pacientes</a></li>
                    </ul>
                </li>


                <li><a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
                        <i class="flaticon-381-menu"></i>
                        <span class="nav-text">Responsables</span>
                    </a>
                    <ul aria-expanded="false">
                        <li><a href="{{ route('admissionit.responsible.index') }}">Lista de Responsables</a></li>
                    </ul>
                </li>


                <li><a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
                        <i class="flaticon-381-menu"></i>
                        <span class="nav-text">Citas</span>
                    </a>
                    <ul aria-expanded="false">
                        <li><a href="{{ route('admissionit.appointment.index') }}">Registrar Cita</a></li>
                    </ul>
                </li>
            @endif



            @if (auth()->user()->getRoleNames()->contains('ADMINISTRADOR'))
                <li><a href="javascript:void(0);" class="ai-icon has-arrow" aria-expanded="false">
                        <i class="flaticon-381-id-card-4"></i>
                        <span class="nav-text">Maestros</span>
                    </a>
                    <ul aria-expanded="false">
                        <li><a href='{{ route('master.specialty.index') }}'>Especialidades</a></li>
                    </ul>
                </li>
                <li><a href="javascript:void(0);" class="ai-icon has-arrow" aria-expanded="false">
                        <i class="flaticon-381-id-card-4"></i>
                        <span class="nav-text">Usuarios</span>
                    </a>
                    <ul aria-expanded="false">
                        <li><a href='{{ route('admin.user.index') }}'>Lista de Usuarios</a></li>
                    </ul>
                </li>
                <li><a href="javascript:void(0);" class="ai-icon has-arrow" aria-expanded="false">
                        <i class="flaticon-381-id-card-4"></i>
                        <span class="nav-text">Roles</span>
                    </a>
                    <ul aria-expanded="false">
                        <li><a href='{{ route('admin.roles.index') }}'>Lista de Roles</a></li>
                    </ul>
                </li>
            @endif
        </ul>

        <a href='#'>
            <div class="plus-box">
                <p class="fs-16 font-w500 mb-1">Celendario y Horarios</p>
                <p class="text-white fs-26"> <i class="las la-long-arrow-alt-right"></i></p>
            </div>
        </a>

        <div class="copyright">
            <p class="fs-14 font-w200"><strong class="font-w400">ERP CEO SALUD </strong> © 2026
                Todos los Derechos Reservados</p>
            <p class="fs-12">Diseño 1 <span class="heart"></span> Maquetación</p>
        </div>
    </div>
</div>
