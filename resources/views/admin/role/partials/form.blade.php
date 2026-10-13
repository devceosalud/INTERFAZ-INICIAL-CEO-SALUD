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

    @foreach ($permissions as $permission)
        <div>
            <label>
                {!! Form::checkbox('permissions[]', $permission->id, isset($role) && $role->permissions->contains('id', $permission->id), ['class' => 'mr-1']) !!}
                {{ $permission->name }}
            </label>
        </div>
    @endforeach
</div>
