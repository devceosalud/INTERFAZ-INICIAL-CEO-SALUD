$(document).on("click", ".update-appointment", async function (e) {
    e.preventDefault();

    $("#appointmentModalState").modal("show");
    let appointmentId = $(this).data("id");
    let estado_cita = $('appointmentModalState #estado_cita').val();


    try {
        const res = await fetch(`${window.location.origin}/admissionist/appointment/update`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                id: appointmentId,
                estado_cita: estado_cita
            }),
        }, );

        const data = await res.json();
        console.log("DATOS PARA EDITAR:", data);

        if (data.code === 1) {
            Swal.fire({
                title: "¡Inactivado!",
                text: data.msg,
                icon: "success",
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                title: "Error",
                text: data.msg,
                icon: "error"
            });
        }
    } catch (error) {
        console.error(error);
    }
});
