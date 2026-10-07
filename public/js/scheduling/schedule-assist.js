/*
 * Assistance while configuring operating hours — MVP-2C.
 *
 * Two small things, neither of which changes how a block is saved:
 *
 *  1. Prefill: arriving from the agenda with ?doctor_id=&fecha_cita= opens the create modal
 *     already pointing at that professional and date, so configuring a gap the user just saw
 *     takes a couple of steps instead of retyping everything.
 *
 *  2. Warning: while the hours are being typed, it reports whether the block would overlap
 *     another one of the same professional. It is advisory. Inherited data may already contain
 *     overlaps, so the save path is left exactly as it was.
 */
document.addEventListener('DOMContentLoaded', function () {
    const host = document.getElementById('schedule-assist');

    if (!host) {
        return;
    }

    const endpoint = host.dataset.endpoint;

    const scopes = {
        create: {
            doctor: '#doctorScheduleModalCreate #doctor_id',
            date: '#doctorScheduleModalCreate #fecha_cita',
            start: '#doctorScheduleModalCreate #hora_inicio',
            end: '#doctorScheduleModalCreate #hora_fin',
            id: null,
        },
        edit: {
            doctor: '#doctorScheduleModalEdit #doctor_id_edit',
            date: '#doctorScheduleModalEdit #fecha_cita_edit',
            start: '#doctorScheduleModalEdit #hora_inicio_edit',
            end: '#doctorScheduleModalEdit #hora_fin_edit',
            id: '#doctorScheduleModalEdit #doctor_schedule_id_edit',
        },
    };

    prefillFromAgenda();

    Object.keys(scopes).forEach(function (name) {
        const scope = scopes[name];
        const target = document.querySelector('.schedule-overlap-warning[data-scope="' + name + '"]');

        if (!target) {
            return;
        }

        [scope.doctor, scope.date, scope.start, scope.end].forEach(function (selector) {
            const field = document.querySelector(selector);

            if (field) {
                field.addEventListener('change', () => check(scope, target));
            }
        });
    });

    function prefillFromAgenda() {
        const params = new URLSearchParams(window.location.search);
        const doctorId = params.get('doctor_id');
        const date = params.get('fecha_cita');

        if (!doctorId && !date) {
            return;
        }

        const doctor = document.querySelector(scopes.create.doctor);
        const dateField = document.querySelector(scopes.create.date);

        if (doctorId && doctor) {
            doctor.value = doctorId;
        }

        if (date && dateField) {
            dateField.value = date;
        }

        const modal = document.getElementById('doctorScheduleModalCreate');

        if (modal && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    }

    async function check(scope, target) {
        const doctorId = value(scope.doctor);
        const date = value(scope.date);
        const start = value(scope.start);
        const end = value(scope.end);

        target.textContent = '';
        target.classList.remove('text-warning', 'text-success');

        if (!doctorId || !date || !start || !end || end <= start) {
            return;
        }

        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('doctor_id', doctorId);
        url.searchParams.set('fecha_cita', date);
        url.searchParams.set('hora_inicio', start);
        url.searchParams.set('hora_fin', end);

        const scheduleId = scope.id ? value(scope.id) : '';

        if (scheduleId) {
            url.searchParams.set('doctor_schedule_id', scheduleId);
        }

        try {
            const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            target.textContent = data.mensaje;
            target.classList.add(data.solapa ? 'text-warning' : 'text-success');
        } catch (error) {
            // A warning that cannot be produced must never get in the way of saving.
            console.error(error);
        }
    }

    function value(selector) {
        const field = selector ? document.querySelector(selector) : null;

        return field ? field.value : '';
    }
});
