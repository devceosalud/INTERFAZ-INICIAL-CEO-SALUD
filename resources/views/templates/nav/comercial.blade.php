  <li>
      <a href="javascript:void(0);" class="ai-icon has-arrow" aria-expanded="false">
          <i class="flaticon-381-user-7"></i>
          <span class="nav-text">Pacientes</span>
      </a>
      <ul aria-expanded="false">
          <li><a href="{{ route('admissionit.patient.index') }}">Pacientes</a></li>
      </ul>
  </li>

  <li>
      <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
          <i class="flaticon-381-user"></i>
          <span class="nav-text">Responsables</span>
      </a>
      <ul aria-expanded="false">
          <li><a href="{{ route('admissionit.responsible.index') }}">Responsables</a></li>
      </ul>
  </li>

  <li>
      <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
          <i class="flaticon-381-calendar"></i>
          <span class="nav-text">Citas</span>
      </a>
      <ul aria-expanded="false">
          <li><a href="{{ route('admissionit.available.schedule.index') }}">Disponibles</a></li>
          <li><a href="{{ route('admissionit.appointment.index') }}">Registrar Cita</a></li>
      </ul>
  </li>

  <li>
      <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
          <i class="flaticon-381-clock"></i>
          <span class="nav-text">Horarios</span>
      </a>
      <ul aria-expanded="false">
          <li><a href="{{ route('admissionit.doctor.schedule.index') }}">Horarios médicos</a></li>
      </ul>
  </li>
