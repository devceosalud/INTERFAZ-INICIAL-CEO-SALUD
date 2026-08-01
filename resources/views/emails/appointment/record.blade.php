{{--
<x-mail::message>
    # Datos de Tu Cita

    Hola {{ $appointment->patient->nombre }}. Tu cita fue registrada correctamente

    **N° de Cita:** {{ $appointment->numero_cita }}
    **Fecha:** {{ $appointment->fecha_cita }}
    **Hora:** {{ $appointment->hora_cita }}

    # Especialidad

    **Especialidad:** {{ $appointment->doctor->specialty->nombre }}
    **Servicio:** {{ $appointment->service->nombre }}
    <br>

    # Especialista
    **Doctor:** {{ $appointment->doctor->nombre }}

    Gracias por elegirnos, lo esperamos pronto,<br>
    {{ config('app.name') }}
</x-mail::message>
--}}

@php
    $whatsappMessage = urlencode(
        "Hola CEO Salud, tengo una cita agendada para el día {$appointment->fecha_cita} a las {$appointment->hora_cita} horas (Nro. de cita: {$appointment->numero_cita})."
    );
@endphp

<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Confirmación de cita - CEO Salud</title>
    <!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
    <style>
        /* Reset básico */
        body,
        table,
        td,
        a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table,
        td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            height: 100% !important;
        }

        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }

        /* Colores de marca CEO Salud (light mode por defecto) */
        body {
            background-color: #eef2f3;
        }

        .email-bg {
            background-color: #eef2f3;
        }

        .card-bg {
            background-color: #ffffff;
        }

        .header-bg {
            background-color: #21798c;
        }

        /* tono intermedio entre #315f73 y #3db0b2 */
        .logo-box {
            background-color: #ffffff;
        }

        .accent-bg {
            background-color: #e7f5f5;
        }

        /* tinte del teal #3db0b2 */
        .text-primary {
            color: #1c2b2a;
        }

        .text-secondary {
            color: #55666a;
        }

        .text-muted {
            color: #8a9598;
        }

        .divider {
            border-color: #dcecec;
        }

        .btn-bg {
            background-color: #315f73;
        }

        .btn-text {
            color: #ffffff !important;
        }

        .footer-text {
            color: #9aa4a6;
        }

        .detail-label {
            color: #218a8c;
        }

        /* variante oscurecida de #3db0b2 para contraste AA */
        .whatsapp-bg {
            background-color: #25D366;
        }

        .whatsapp-box-bg {
            background-color: #f4f9f6;
        }

        .whatsapp-box-border {
            border-color: #d9ece1;
        }

        /* ============ MODO OSCURO ============ */
        @media (prefers-color-scheme: dark) {

            body,
            .email-bg {
                background-color: #121212 !important;
            }

            .card-bg {
                background-color: #1e1e1e !important;
            }

            .header-bg {
                background-color: #1f3e4d !important;
            }

            .logo-box {
                background-color: #ffffff !important;
            }

            .accent-bg {
                background-color: #17302f !important;
            }

            .text-primary {
                color: #f2f2f2 !important;
            }

            .text-secondary {
                color: #cfd8d7 !important;
            }

            .text-muted {
                color: #9aa4a6 !important;
            }

            .divider {
                border-color: #33403f !important;
            }

            .btn-bg {
                background-color: #3db0b2 !important;
            }

            .footer-text {
                color: #7c8688 !important;
            }

            .detail-label {
                color: #4fd1c5 !important;
            }

            .whatsapp-box-bg {
                background-color: #17302f !important;
            }

            .whatsapp-box-border {
                border-color: #33403f !important;
            }
        }

        /* Clientes que usan clase u-dark en vez de media query (Outlook.com, algunos Gmail) */
        [data-ogsc] body,
        [data-ogsc] .email-bg {
            background-color: #121212 !important;
        }

        [data-ogsc] .card-bg {
            background-color: #1e1e1e !important;
        }

        [data-ogsc] .text-primary {
            color: #f2f2f2 !important;
        }

        [data-ogsc] .text-secondary {
            color: #cfd8d7 !important;
        }

        [data-ogsc] .detail-label {
            color: #4fd1c5 !important;
        }

        @media only screen and (max-width: 620px) {
            .email-container {
                width: 100% !important;
            }

            .stack {
                display: block !important;
                width: 100% !important;
            }

            .px-24 {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
        }
    </style>
</head>

<body class="email-bg" style="margin:0; padding:0; background-color:#eef2f3;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
        Tu cita en CEO Salud ha sido confirmada. Revisa la fecha, hora y especialista aquí.
        &#847; &zwnj; &nbsp; &#8199; &#65279; &#847; &zwnj; &nbsp; &#8199; &#65279;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="email-bg"
        style="background-color:#eef2f3;">
        <tr>
            <td align="center" style="padding: 32px 16px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                    class="email-container card-bg"
                    style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden;">

                    <!-- HEADER / LOGO -->
                    <tr>
                        <td class="header-bg" align="center"
                            style="background-color:#21798c; background-image:linear-gradient(135deg, #315f73 0%, #3db0b2 100%); padding: 22px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="logo-box" align="center"
                                        style="background-color:#ffffff; border-radius:12px; padding:10px 18px;">
                                        <img src="https://ceosalud.pe/wp-content/uploads/2026/01/ceosalud.pe_pequeno-01.png"
                                            width="150" alt="CEO Salud"
                                            style="display:block; width:150px; max-width:150px; height:auto; border:0;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- TITULO -->
                    <tr>
                        <td class="px-24" style="padding: 36px 40px 8px 40px;" align="center">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center"
                                        style="width:56px; height:56px; border-radius:50%; background-color:#e7f5f5;"
                                        class="accent-bg">
                                        <!-- check icon -->
                                        <div
                                            style="width:56px; height:56px; line-height:56px; text-align:center; font-size:26px; color:#218a8c;">
                                            &#10003;</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-24 text-primary" align="center"
                            style="padding: 12px 40px 4px 40px; font-family: Arial, Helvetica, sans-serif; font-size: 22px; font-weight: bold; color:#1c2b2a;">
                            ¡Tu cita ha sido confirmada!
                        </td>
                    </tr>
                    <tr>
                        <td class="px-24 text-secondary" align="center"
                            style="padding: 4px 40px 24px 40px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 22px; color:#55666a;">
                            Hola <strong>{{ $appointment->patient->nombre }}</strong>, tu cita fue agendada correctamente. Aquí tienes
                            los detalles:
                        </td>
                    </tr>

                    <!-- DETALLE DE LA CITA -->
                    <tr>
                        <td class="px-24" style="padding: 0 40px 8px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                class="accent-bg" style="background-color:#e7f5f5; border-radius:10px;">
                                <tr>
                                    <td style="padding: 24px 24px 8px 24px;">

                                        <!-- N° de cita -->
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                            border="0">
                                            <tr>
                                                <td class="detail-label"
                                                    style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color:#218a8c; padding-bottom:4px;">
                                                    N° de cita</td>
                                            </tr>
                                            <tr>
                                                <td class="text-primary"
                                                    style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight:bold; color:#1c2b2a; padding-bottom:16px;">
                                                    {{ $appointment->numero_cita }}</td>
                                            </tr>
                                        </table>

                                        <!-- Fecha / Hora -->
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                            border="0">
                                            <tr>
                                                <td class="stack" width="50%" valign="top"
                                                    style="padding-bottom:16px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0"
                                                        border="0">
                                                        <tr>
                                                            <td class="detail-label"
                                                                style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color:#218a8c; padding-bottom:4px;">
                                                                Fecha</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-primary"
                                                                style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight:bold; color:#1c2b2a;">
                                                                {{ $appointment->fecha_cita }}</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td class="stack" width="50%" valign="top"
                                                    style="padding-bottom:16px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0"
                                                        border="0">
                                                        <tr>
                                                            <td class="detail-label"
                                                                style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color:#218a8c; padding-bottom:4px;">
                                                                Hora</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-primary"
                                                                style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight:bold; color:#1c2b2a;">
                                                                {{ $appointment->hora_cita }}</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Especialidad -->
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                            border="0">
                                            <tr>
                                                <td class="detail-label"
                                                    style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color:#218a8c; padding-bottom:4px;">
                                                    Especialidad</td>
                                            </tr>
                                            <tr>
                                                <td class="text-primary"
                                                    style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight:bold; color:#1c2b2a; padding-bottom:16px;">
                                                    {{ $appointment->doctor->specialty->nombre }}</td>
                                            </tr>
                                        </table>

                                        <!-- Servicio -->
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                            border="0">
                                            <tr>
                                                <td class="detail-label"
                                                    style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color:#218a8c; padding-bottom:4px;">
                                                    Servicio</td>
                                            </tr>
                                            <tr>
                                                <td class="text-primary"
                                                    style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight:bold; color:#1c2b2a; padding-bottom:16px;">
                                                    {{ $appointment->service->nombre }}</td>
                                            </tr>
                                        </table>

                                        <!-- Especialista -->
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                            border="0">
                                            <tr>
                                                <td class="detail-label"
                                                    style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color:#218a8c; padding-bottom:4px;">
                                                    Especialista</td>
                                            </tr>
                                            <tr>
                                                <td class="text-primary"
                                                    style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight:bold; color:#1c2b2a; padding-bottom:16px;">
                                                    Dr(a). {{ $appointment->doctor->nombre }}</td>
                                            </tr>
                                        </table>

                                        <!-- Dirección -->
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                            border="0">
                                            <tr>
                                                <td class="divider"
                                                    style="border-top:1px solid #dcecec; padding-top:16px; padding-bottom:8px;">
                                                    <table role="presentation" width="100%" cellpadding="0"
                                                        cellspacing="0" border="0">
                                                        <tr>
                                                            <td class="detail-label"
                                                                style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color:#218a8c; padding-bottom:4px;">
                                                                Dirección</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-primary"
                                                                style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight:bold; color:#1c2b2a; line-height:20px; padding-bottom:6px;">
                                                                Av. Antonio de Sucre 1136, Magdalena del Mar</td>
                                                        </tr>
                                                        <tr>
                                                            <td><a href="https://maps.app.goo.gl/m64vmc4ixkHzKqng7"
                                                                    target="_blank"
                                                                    style="font-family: Arial, Helvetica, sans-serif; font-size: 13px; font-weight:bold; color:#218a8c; text-decoration:underline;">Ver
                                                                    cómo llegar en Google Maps →</a></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- BOTÓN WHATSAPP -->
                    <tr>
                        <td class="px-24" align="center" style="padding: 28px 40px 12px 40px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" class="whatsapp-bg"
                                        style="background-color:#25D366; border-radius: 8px;">
                                        <a href="https://wa.me/51961209601?text={{ $whatsappMessage }}"
                                            target="_blank" class="btn-text"
                                            style="display:inline-block; padding: 14px 32px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight:bold; color:#ffffff !important; text-decoration:none; border-radius:8px;">
                                            Escríbenos por WhatsApp
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- NOTA WHATSAPP / ADMISIÓN -->
                    <tr>
                        <td class="px-24" style="padding: 4px 40px 24px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                border="0" class="whatsapp-box-bg"
                                style="background-color:#f4f9f6; border:1px solid #d9ece1; border-radius:8px;">
                                <tr>
                                    <td class="text-secondary"
                                        style="padding:14px 16px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 18px; color:#55666a;">
                                        Este es el número del <strong>área de Admisión</strong> de CEO Salud
                                        (<strong>961 209 601</strong>) para reprogramaciones, cancelaciones o cualquier
                                        consulta sobre tu cita. Ten en cuenta que <strong>no es el mismo número</strong>
                                        desde el cual agendaste inicialmente.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- DIVIDER -->
                    <tr>
                        <td class="px-24" style="padding: 0 40px;">
                            <div class="divider" style="border-top:1px solid #e3e8e8;"></div>
                        </td>
                    </tr>

                    <!-- POLÍTICAS Y RECOMENDACIONES -->
                    <tr>
                        <td class="px-24"
                            style="padding: 24px 40px 32px 40px; font-family: Arial, Helvetica, sans-serif;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                border="0">
                                <tr>
                                    <td class="text-primary"
                                        style="font-size: 13px; font-weight:bold; color:#1c2b2a; padding-bottom:10px;">
                                        Políticas y recomendaciones antes de tu consulta:
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-secondary"
                                        style="font-size: 13px; line-height: 21px; color:#55666a; padding-bottom:14px;">
                                        • Llega 15 minutos antes de tu hora programada. En caso haya oportunidad se te
                                        atenderá antes.<br>
                                        • Tienes hasta 15 minutos de tolerancia para ser considerado en el rango de hora
                                        agendado, sin embargo, si llegas 15 minutos después de tu hora programada,
                                        pasarás al final de la lista de espera hasta ese momento.<br>
                                        • Presentar tu documento de identidad es obligatorio. Según indica NTS N°
                                        139-MINSA/2018 (DNI, C.E. o alguna documentación oficial).<br>
                                        • Si tienes exámenes o informes previos, no olvides traerlos o compartirlos
                                        digitalmente de forma oportuna.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="detail-label"
                                        style="font-size: 12px; font-weight:bold; color:#218a8c; padding-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">
                                        Pagos y reprogramaciones
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-secondary"
                                        style="font-size: 13px; line-height: 21px; color:#55666a;">
                                        • Todo pago por transferencia debe ser autorizado.<br>
                                        • Una vez realizado el pago, envía el comprobante de la transacción con el
                                        número de operación visible.<br>
                                        • <strong>Reprogramación anticipada</strong> (más de 24 horas antes): sin
                                        penalidad (01 sola vez); el pago se mantiene como crédito para la nueva
                                        cita.<br>
                                        • <strong>Reprogramación tardía</strong> (menos de 12 horas): cargo
                                        administrativo del 20% del valor de la consulta; el 80% restante queda como
                                        crédito (01 sola vez).<br>
                                        • <strong>Cancelación</strong> con menos de 12 horas de anticipación: cargo
                                        administrativo del 20% del valor de la consulta.<br>
                                        • En caso de no presentarte, no se admiten devoluciones.<br>
                                        • Para reprogramar o cancelar, escríbenos al área de Admisión al <strong>961 209
                                            601</strong> con la mayor anticipación posible.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

                <!-- FOOTER -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                    class="email-container" style="width:600px; max-width:600px;">
                    <tr>
                        <td align="center" style="padding: 24px 20px;">
                            <p class="footer-text"
                                style="margin:0 0 6px 0; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height:18px; color:#9aa4a6;">
                                CEO Salud · Confianza y Bienestar
                            </p>
                            <p class="footer-text"
                                style="margin:0 0 6px 0; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height:18px; color:#9aa4a6;">
                                Av. Antonio de Sucre 1136, Magdalena del Mar · Lima, Perú
                            </p>
                            <p class="footer-text"
                                style="margin:0 0 18px 0; font-family: Arial, Helvetica, sans-serif; font-size: 11px; color:#9aa4a6;">
                                Este correo fue enviado porque agendaste una cita con nosotros.
                            </p>

                            <!-- REDES SOCIALES -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0"
                                style="margin:0 auto;">
                                <tr>
                                    <td style="padding:0 5px;">
                                        <a href="https://www.facebook.com/ceosalud.peru" target="_blank"
                                            style="display:inline-block; width:32px; height:32px; line-height:32px; text-align:center; background-color:#315f73; border-radius:50%; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:13px; font-weight:bold; text-decoration:none;">f</a>
                                    </td>
                                    <td style="padding:0 5px;">
                                        <a href="https://www.instagram.com/ceosalud.pe/" target="_blank"
                                            style="display:inline-block; width:32px; height:32px; line-height:32px; text-align:center; background-color:#315f73; border-radius:50%; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:11px; font-weight:bold; text-decoration:none;">IG</a>
                                    </td>
                                    <td style="padding:0 5px;">
                                        <a href="https://www.tiktok.com/@ceosalud.pe" target="_blank"
                                            style="display:inline-block; width:32px; height:32px; line-height:32px; text-align:center; background-color:#315f73; border-radius:50%; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:11px; font-weight:bold; text-decoration:none;">TT</a>
                                    </td>
                                    <td style="padding:0 5px;">
                                        <a href="https://www.youtube.com/@CEOSALUD" target="_blank"
                                            style="display:inline-block; width:32px; height:32px; line-height:32px; text-align:center; background-color:#315f73; border-radius:50%; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:11px; font-weight:bold; text-decoration:none;">YT</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:14px 0 0 0; font-family: Arial, Helvetica, sans-serif; font-size: 13px;">
                                <a href="https://www.ceosalud.pe" target="_blank"
                                    style="color:#218a8c; font-weight:bold; text-decoration:none;">www.ceosalud.pe</a>
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>

</html>
