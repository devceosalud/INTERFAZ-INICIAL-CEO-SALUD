<div class="form-group mb-3">
    {!! Form::label('name', 'Nombre', ['class' => 'mb-2']) !!}
    {!! Form::text('name', null, ['class' => 'form-control mb-3', 'placeholder' => 'Escriba el nombre del permiso', 'readonly' => isset($role)]) !!}

    @error('name')
        <span class="text-danger"><strong>{{ $message }}</strong></span>
    @enderror
</div>

@if (isset($role))
    <input type="hidden" name="permissions_present" value="1">
    <input type="hidden" name="permission_revision" value="{{ $permissionRevision }}">
@endif
<div class="form-group">
    <strong class="">Permisos</strong>
    <br>
    @error('permissions')
        <span class="text-danger"><strong>{{ $message }}</strong></span>
    @enderror

    <p class="text-muted small mb-3">Los nombres explican qué permite cada opción. Activar o quitar una casilla modifica los accesos del rol al guardar.</p>
    @foreach ($permissions as $permission)
        <div class="mb-3">
            <label class="d-flex align-items-start gap-2" title="{{ $permission->name }}">
                {!! Form::checkbox('permissions[]', $permission->id, isset($role) && $role->permissions->contains('id', $permission->id), ['class' => 'mt-1']) !!}
                <span>
                    <strong class="d-block">{{ \App\Support\Authorization\PermissionDisplay::label($permission->name) }}</strong>
                    <small class="text-muted d-block">{{ \App\Support\Authorization\PermissionDisplay::description($permission->name) }}</small>
                </span>
            </label>
        </div>
    @endforeach
</div>
