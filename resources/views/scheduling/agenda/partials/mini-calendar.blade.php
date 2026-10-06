<section data-ui-zone="mini" class="agenda-operations__section agenda-mini" aria-labelledby="agenda-mini-title">
    <div class="agenda-pane-head agenda-mini__head">
        <button type="button" class="agenda-mini__nav" id="agenda-mini-prev" aria-label="Mes anterior">Anterior</button>
        <h2 class="agenda-section-title" id="agenda-mini-title">Calendario <abbr class="agenda-mini-help" tabindex="0" title="Capacidad regular asegurada con pago/crédito real de al menos 50%. Para datos antiguos sin vouchers se conserva fallback controlado." aria-label="Capacidad regular asegurada con pago o crédito real de al menos 50%">ⓘ</abbr></h2>
        <button type="button" class="agenda-mini__nav" id="agenda-mini-next" aria-label="Mes siguiente">Siguiente</button>
    </div>

    <p class="agenda-mini__month" id="agenda-mini-month"></p>
    <div class="agenda-mini__weekdays" aria-hidden="true">
        <span>L</span><span>M</span><span>X</span><span>J</span><span>V</span><span>S</span><span>D</span>
    </div>
    <div class="agenda-mini__grid" id="agenda-mini-grid" role="grid" aria-label="Seleccionar fecha"></div>
    <div class="agenda-mini-legend" aria-label="Ocupación segura del médico">
        <span title="0–49% de capacidad regular"><i class="agenda-mini-legend__dot" data-capacity="verde" aria-hidden="true"></i>Disponible</span>
        <span title="50–79% de capacidad regular"><i class="agenda-mini-legend__dot" data-capacity="amarillo" aria-hidden="true"></i>Medio</span>
        <span title="80% o más de capacidad regular"><i class="agenda-mini-legend__dot" data-capacity="rojo" aria-hidden="true"></i>Lleno</span>
        <span title="Médico sin horario activo"><i class="agenda-mini-legend__dot" data-capacity="plomo" aria-hidden="true"></i>Sin horario</span>
    </div>
</section>
