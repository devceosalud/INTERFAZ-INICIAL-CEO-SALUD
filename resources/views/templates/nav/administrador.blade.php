  <li>
      <a href="javascript:void(0);" class="ai-icon has-arrow" aria-expanded="false">
          <i class="flaticon-381-user-7"></i>
          <span class="nav-text">Pacientes</span>
      </a>
      <ul aria-expanded="false">
          <li><a href="{{ route('admin.patient.index') }}">Pacientes</a></li>
      </ul>
  </li>

  <li>
      <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
          <i class="flaticon-381-user"></i>
          <span class="nav-text">Responsables</span>
      </a>
      <ul aria-expanded="false">
          <li><a href="{{ route('admin.responsible.index') }}">Responsables</a></li>
      </ul>
  </li>

  <li>
      <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
          <i class="flaticon-381-calendar"></i>
          <span class="nav-text">Citas</span>
      </a>
      <ul aria-expanded="false">
          <li><a href="{{ route('admin.available.schedule.index') }}">Disponibles</a></li>
          <li><a href="{{ route('admin.appointment.index') }}">Registrar Cita</a></li>
      </ul>
  </li>

  <li>
      <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
          <i class="flaticon-381-clock"></i>
          <span class="nav-text">Horarios</span>
      </a>
      <ul aria-expanded="false">
          <li><a href="{{ route('admin.doctor.schedule.index') }}">Horarios médicos</a></li>
      </ul>
  </li>

  <li>
      <a href="javascript:void(0);" class="ai-icon has-arrow" aria-expanded="false">
          <i class="flaticon-381-controls"></i>
          <span class="nav-text">Maestros</span>
      </a>
      <ul aria-expanded="false">
          <li><a href='{{ route('master.specialty.index') }}'>Especialidades</a></li>
          <li><a href="{{ route('master.service.index') }}">Servicios</a></li>
          <li><a href='{{ route('master.doctor.index') }}'>Doctores</a></li>
          <li><a href='{{ route('master.service.doctor.index') }}'>Doctor/Servicio</a></li>
          <li><a href="{{ route('master.additionalRate.index') }}">Tarifas</a></li>
          <li><a href="{{ route('master.interactionMedia.index') }}">Medios</a></li>
          <li><a href='{{ route('master.channel.index') }}'>Canales</a></li>
      </ul>
  </li>

  <li>
      <a href="javascript:void(0);" class="ai-icon has-arrow" aria-expanded="false">
          <i class="flaticon-381-user"></i>
          <span class="nav-text">Usuarios</span>
      </a>
      <ul aria-expanded="false">
          <li><a href="{{ route('admin.user.index') }}">Lista de Usuarios</a></li>
      </ul>
  </li>

  <li>
      <a href="javascript:void(0);" class="ai-icon has-arrow" aria-expanded="false">
          <i class="flaticon-381-settings"></i>
          <span class="nav-text">Roles</span>
      </a>
      <ul aria-expanded="false">
          <li><a href="{{ route('admin.roles.index') }}">Lista de Roles</a></li>
      </ul>
  </li>
