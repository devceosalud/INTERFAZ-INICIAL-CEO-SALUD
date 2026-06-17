<div class="form-group mb-3">
    {!! Form::label('name', 'Nombre', ['class' => 'mb-2']) !!}
    {!! Form::text('name', null, ['class' => 'form-control mb-3', 'placeholder' => 'Escriba el nombre del permiso']) !!}

    @error('name')
        <span class="text-danger"><strong>{{ $message }}</strong></span>
    @enderror
</div>

<div class="form-group">
    <strong class="">Permisos</strong>
    <br>
    @error('permissions')
        <span class="text-danger"><strong>{{ $message }}</strong></span>
    @enderror

    @foreach ($permissions as $permission)
        <div>
            <label>
                {!! Form::checkbox('permissions[]', $permission->id, null, ['class' => 'mr-1']) !!}
                {{ $permission->name }}
            </label>
        </div>
    @endforeach
</div>
