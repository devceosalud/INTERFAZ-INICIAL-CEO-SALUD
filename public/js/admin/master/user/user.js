(function (root) {
    async function lookup(url, id, token, fetcher) {
        const response = await fetcher(url, { method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ id }) });
        if (response.redirected || [401, 419].includes(response.status)) {
            throw new Error('La sesión venció. Inicia sesión nuevamente antes de editar usuarios.');
        }
        if (response.status === 403) { throw new Error('No tienes permiso para editar usuarios.'); }
        if (!(response.headers.get('content-type') || '').includes('application/json')) {
            throw new Error('No se pudo cargar el usuario. Recarga la página y vuelve a intentarlo.');
        }
        let body;
        try { body = await response.json(); } catch (error) { throw new Error('No se pudo cargar el usuario. Recarga la página y vuelve a intentarlo.'); }
        if (!response.ok) { throw new Error(response.status === 404 ? 'El usuario ya no está disponible.' : 'No se pudo cargar el usuario.'); }
        return body;
    }
    async function changeState(url,payload,token,fetcher){
        const response=await fetcher(url,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify(payload)});
        if(response.redirected||[401,419].includes(response.status))throw new Error('Inicia sesión nuevamente antes de administrar accesos.');
        if(response.status===403)throw new Error('No tienes autorización para administrar accesos.');
        if(!(response.headers.get('content-type')||'').includes('application/json'))throw new Error('No se pudo confirmar el resultado. Actualiza antes de repetir.');
        let body;try{body=await response.json();}catch(error){throw new Error('No se pudo confirmar el resultado. Actualiza antes de repetir.');}
        if(!response.ok)throw new Error([409,422].includes(response.status)?Object.values(body.errors||{}).flat()[0]||body.message||'Actualiza el estado del trabajador.':'No se pudo confirmar el resultado. Actualiza antes de repetir.');
        return body;
    }
    function latestLookup(){let version=0;return {cancel(){version++;},async run(work,paint){const current=++version;try{const data=await work();if(current!==version)return false;paint(data);return true;}catch(error){if(current!==version)return false;throw error;}}};}
    root.UserEditorApi = { lookup, changeState, latestLookup };
    if (typeof module === 'object' && module.exports) { module.exports = root.UserEditorApi; }
}(typeof window !== 'undefined' ? window : globalThis));

if (typeof window !== 'undefined') window.addEventListener("DOMContentLoaded", function () {



    // GUARDAR DATOS DEL USUARIO
    $("#formCreateUser").on("submit", function (e) {
        e.preventDefault();

        let form = this;

        $.ajax({
            url: $(form).attr("action"),
            method: $(form).attr("method"),
            data: new FormData(form),
            processData: false,
            contentType: false,
            dataType: "json",

            beforeSend: function () {
                // Limpiar errores anteriores
                $(form).find("span.error-text").text("");
                // deshabilitar boton de envio
                $(form).find('input[type="submit"]').prop("disabled", true);
            },

            success: function (response) {
                if (response.code == 0) {
                    $.each(response.error, function (prefix, val) {
                        $(form).find("span." + prefix + "_error").text(val[0]);
                    });
                } else {
                    Swal.fire({
                        icon: "success",
                        title: "Correcto",
                        text: response.msg,
                        timer: 2000,
                        showConfirmButton: false,
                    }).then(() => {
                        location.reload();
                    });
                    form.reset();
                    $("#userModalCreate").modal("hide");
                }
            },

            error: function (xhr) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "No se pudo guardar el usuario. Comprueba la sesión y los datos.",
                });
            },

            complete: function () {
                $(form).find('input[type="submit"]').prop("disabled", false);
            },
        });
    });


    const editLookup=window.UserEditorApi.latestLookup();
    $("#userModalEdit").on("hidden.bs.modal",function(){editLookup.cancel();document.getElementById("formUpdateUser").reset();});

    //PARA EDITAR AL USUARIO
    $(document).on("click", ".edit-user", async function (e) {
        e.preventDefault();
        let userId = $(this).data("id");

        try {
            await editLookup.run(() => window.UserEditorApi.lookup(`${window.location.origin}/api/admin/user/search`, userId,
                document.querySelector('meta[name="csrf-token"]').content, window.fetch.bind(window)), data => {

            if (data.message === "encontrado") {
                let u = data.user;
                //PINTAR DATOS EN EL MODAL
                $("#userModalEdit #usuario_id_edit").val(u.id);
                $("#userModalEdit #nombre_usuario_edit").val(u.name);
                $("#userModalEdit #email_edit").val(u.email);
                $("#userModalEdit #dni_edit").val(u.dni || '');
                $("#userModalEdit input[name=password]").val('');

                //ABRIR MODAL
                $("#userModalEdit").modal("show");
            }
            });
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'No se pudo abrir la edición', text: error.message });
        }
    });


    //PARA ACTUALIZAR DATOS DEL USUARIO
    $("#formUpdateUser").on("submit", function (e) {
        e.preventDefault();

        let form = this;

        $.ajax({
            url: $(form).attr("action"),
            method: "POST",
            data: new FormData(form),
            processData: false,
            contentType: false,
            dataType: "json",

            beforeSend: function () {
                $(form).find("span.error-text").text("");
                $(form).find('input[type="submit"]').prop("disabled", true);
            },

            success: function (response) {
                if (response.code == 0) {
                    $.each(response.error, function (prefix, val) {
                        $(form)
                            .find("span." + prefix + "_error")
                            .text(val[0]);
                    });
                } else {
                    Swal.fire({
                        icon: "success",
                        title: "Actualizado",
                        text: response.msg,
                        timer: 2000,
                        showConfirmButton: false,
                    }).then(() => {
                        location.reload();
                    });

                    $("#userModalEdit").modal("hide");
                }
            },

            error: function (xhr) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Ocurrió un error al actualizar al usuario",
                });
            },

            complete: function () {
                $(form).find('input[type="submit"]').prop("disabled", false);
            },
        });
    });


    let changingAccess=false;
    $(document).on('click','.user-access-state',async function(){
        if(changingAccess)return;const button=this;const active=button.dataset.active!=='1';
        if(!window.Swal)return;
        const decision=await Swal.fire({title:active?'Reactivar trabajador':'Desactivar trabajador',
            text:button.dataset.worker+': '+(active?'Debe iniciar sesión de nuevo. Se conservan sus roles y permisos.':'Se impedirán nuevas solicitudes y se revocarán sus sesiones y tokens. Se conservan citas, pagos y roles.'),
            input:'textarea',inputLabel:'Motivo administrativo',inputAttributes:{maxlength:'500'},
            showCancelButton:true,confirmButtonText:active?'Reactivar':'Desactivar',cancelButtonText:'Volver',
            inputValidator:value=>!value||value.trim().length<3?'Registra un motivo de al menos 3 caracteres.':undefined});
        if(!decision.isConfirmed||changingAccess)return;changingAccess=true;button.disabled=true;
        try{
            await window.UserEditorApi.changeState(button.dataset.url,{active,expected_version:Number(button.dataset.version),
                request_key:crypto.randomUUID(),reason:decision.value.trim()},document.querySelector('meta[name="csrf-token"]').content,window.fetch.bind(window));
            await Swal.fire({icon:'success',title:'Acceso actualizado',text:'Roles e historial conservados. La reactivación exige iniciar sesión de nuevo.'});location.reload();
        }catch(error){await Swal.fire({icon:'error',title:'No se pudo confirmar el resultado',text:error.message});}
        finally{changingAccess=false;button.disabled=false;}
    });

});
