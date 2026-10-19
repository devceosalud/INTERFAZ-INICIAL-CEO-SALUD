<div class="col-md-6">
    <label class="form-label">Tipo de servicio</label>
    <select class="form-control" name="service_type"><option value="">Heredado: pendiente de clasificación</option>
        @foreach($catalogTypes as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
    </select>
</div>
<div class="col-md-6">
    <label class="form-label">Política de precio</label>
    <select class="form-control" name="pricing_policy"><option value="">Heredada: precio por profesional</option><option value="PROFESSIONAL">Por profesional (consultas y reevaluaciones)</option><option value="INSTITUTIONAL">Institucional (otras prestaciones)</option></select>
</div>
<div class="col-md-6"><label class="form-label">Precio institucional S/</label><input class="form-control" type="number" step="0.01" min="0.01" name="institutional_price"><small>En consultas es un valor por defecto opcional. La reevaluación toma su precio del profesional.</small></div>
<div class="col-md-6"><label class="form-label">Consulta canónica (solo reevaluaciones)</label><select class="form-control" name="canonical_service_id"><option value="">No corresponde</option>@foreach($canonicalServices as $canonical)<option value="{{ $canonical->id }}">#{{ $canonical->id }} · {{ $canonical->nombre }}</option>@endforeach</select><small>Conserva el servicio clínico de origen; requiere indicación acreditada.</small></div>
<div class="col-md-4"><label class="form-label">Vigente desde</label><input class="form-control" type="date" name="price_valid_from"></div>
<div class="col-md-4"><label class="form-label">Vigente hasta</label><input class="form-control" type="date" name="price_valid_until"></div>
<div class="col-md-4"><label class="form-label">Estado</label><select class="form-control" name="estado"><option value="ACTIVO">Activo</option><option value="INACTIVO">Inactivo / pendiente de precio</option></select></div>
<div class="col-12"><label class="form-label">Descripción breve del servicio (opcional)</label><textarea class="form-control" name="pricing_notes" maxlength="500" rows="2" placeholder="Ej.: Infiltración PRP; preguntar si requiere guía ecográfica."></textarea><small>Visible en Agenda operativa al elegir o consultar el servicio. Solo informativa: no modifica el precio ni genera recargos automáticamente.</small></div>
<input type="hidden" name="catalog_version" value="0">

<div class="col-12"><input type="hidden" name="shared_specialties_present" value="1"><label class="form-label">Especialidades adicionales autorizadas para este servicio compartido</label><select class="form-control" name="shared_specialty_ids[]" multiple size="5">@foreach($specialties as $specialty)<option value="{{ $specialty->id }}">{{ $specialty->nombre }}</option>@endforeach</select><small>La especialidad principal se conserva. Compartir un servicio no asigna profesionales automáticamente. Ctrl/Cmd permite seleccionar varias.</small></div>
<div class="col-12"><label class="form-label">Motivo del cambio de especialidades compartidas</label><input class="form-control" name="specialty_authorization_reason" maxlength="500"><small>Obligatorio al conceder o retirar especialidades. Queda auditado con el administrador.</small></div>
