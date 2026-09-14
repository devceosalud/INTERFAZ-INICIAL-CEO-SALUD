<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $voucher->tipo_comprobante }} {{ $voucher->serie }}-{{ $voucher->correlativo }}</title>
    <style>
        /* 80mm es el ancho estándar de rollo térmico. Todo el
           diseño se calcula en base a esto, no en píxeles de
           pantalla normal. */
        @page {
            size: 80mm auto;
            margin: 0;
        }

        body {
            width: 76mm;
            /* 2mm de margen a cada lado dentro de los 80mm */
            margin: 0 auto;
            font-family: 'Courier New', monospace;
            /* monoespaciada, como en tus PDFs */
            font-size: 11px;
            color: #000;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .logo {
            width: 50px;
            margin: 0 auto;
            display: block;
        }

        .titulo-empresa {
            font-size: 12px;
            font-weight: bold;
            margin-top: 4px;
        }

        .separador {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            text-align: left;
            font-size: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 2px;
        }

        .items-table td {
            font-size: 10px;
            padding: 2px 0;
            vertical-align: top;
        }

        .col-cant {
            width: 12%;
        }

        .col-item {
            width: 56%;
        }

        .col-pu {
            width: 16%;
            text-align: right;
        }

        .col-total {
            width: 16%;
            text-align: right;
        }

        .medico {
            font-style: italic;
            font-size: 9px;
        }

        .totales-row td {
            padding: 1px 0;
            font-size: 11px;
        }

        .totales-row .label {
            text-align: left;
        }

        .totales-row .valor {
            text-align: right;
        }

        .qr {
            width: 90px;
            display: block;
            margin: 8px auto;
        }

        .footer-legal {
            font-size: 9px;
            text-align: center;
            margin-top: 6px;
        }

        @media print {

            /* evita que el navegador agregue su propio margen al imprimir */
            body {
                margin: 0;
            }
        }
    </style>
</head>

<body onload="window.print()">

    {{-- LOGO Y DATOS DE LA EMPRESA — reemplaza la ruta por tu logo real --}}
    <div class="center">
        {{-- <img src="{{ asset('images/logo-ceo-salud.png') }}" class="logo"> --}}
        <div class="titulo-empresa">CEO SALUD</div>
    </div>

    <div class="center" style="margin-top:4px;">
        ORTHO FISIO TRAUMA<br>
        ORTHO FISIO TRAUMA S.A.C.<br>
        Av Sucre 1136 Magdalena del Mar<br>
        RUC: 20604981141<br>
        Cel.: 913557016
    </div>

    <div class="separador"></div>

    {{--
        TÍTULO DEL COMPROBANTE
        match() de PHP 8 elige el texto según el tipo — más limpio
        que una cadena de if/elseif para esto.
    --}}
    <div class="center bold">
        {{ match ($voucher->tipo_comprobante) {
            'BOLETA' => 'BOLETA ELECTRÓNICA',
            'FACTURA' => 'FACTURA ELECTRÓNICA',
            'TICKET' => 'TICKET',
            'NOTA_CREDITO' => 'NOTA DE CRÉDITO ELECTRÓNICA',
            default => $voucher->tipo_comprobante,
        } }}
    </div>
    <div class="center bold">{{ $voucher->serie }}-{{ str_pad($voucher->correlativo, 5, '0', STR_PAD_LEFT) }}</div>

    <div style="margin-top:4px;">
        Fecha: {{ $voucher->created_at->format('Y-m-d') }} &nbsp; Hora: {{ $voucher->created_at->format('H:i:s') }}
    </div>

    {{--
        CLIENTE / QUIÉN PAGA
        Si pagaPaciente existe (persona distinta al paciente) se
        muestra ese; si no, cae al mismo paciente. Para FACTURA se
        muestra la razón social/RUC guardados directo en el voucher.
    --}}
    <div style="margin-top:4px;">
        <span class="bold">Cliente:</span>
        @if ($voucher->tipo_comprobante === 'FACTURA')
            {{ $voucher->razon_social_cliente }}<br>
            RUC: {{ $voucher->numero_doc_cliente }}
        @else
            {{ $voucher->pagaPaciente->nombre_completo ?? $voucher->paciente->nombre_completo }}<br>
            DNI: {{ $voucher->pagaPaciente->numero_identidad ?? $voucher->paciente->numero_identidad }}
        @endif
        <br>
        Dirección: {{ $voucher->direccion_cliente ?? '' }}
    </div>

    {{--
        PACIENTE — se muestra SIEMPRE, aunque quien pague sea otra
        persona/empresa, porque el paciente es quien recibió el
        servicio médico (dato clínico, no solo de facturación).
    --}}
    <div style="margin-top:4px;">
        <span class="bold">Paciente:</span> {{ $voucher->paciente->nombre_completo }}<br>
        Nro. Historia: {{ $voucher->paciente->historia_clinica ?? '-' }}
    </div>

    <div style="margin-top:2px;">Moneda: SOLES</div>

    <div class="separador"></div>

    {{-- TABLA DE ÍTEMS --}}
    <table class="items-table">
        <thead>
            <tr>
                <th class="col-cant">Cant.</th>
                <th class="col-item">Item</th>
                <th class="col-pu">P.U.</th>
                <th class="col-total">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($voucher->items as $item)
                <tr>
                    <td class="col-cant">{{ number_format($item->cantidad, 2) }}</td>
                    <td class="col-item">
                        {{ $item->descripcion }}
                        {{--
                            El tipo (SERV/PROD) se muestra igual que
                            en tu PDF de referencia. item_type='cita'
                            también se etiqueta como SERV, porque una
                            consulta médica es, en esencia, un servicio.
                        --}}
                        <br><small>{{ $item->item_type === 'item' && $item->item?->tipo === 'PRODUCTO' ? 'PROD' : 'SERV' }}</small>
                        @if ($item->doctor)
                            <br><span class="medico">Méd.: {{ $item->doctor->nombre }}</span>
                        @endif
                    </td>
                    <td class="col-pu">{{ number_format($item->precio_unitario, 2) }}</td>
                    <td class="col-total">{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="separador"></div>

    {{--
        DESGLOSE DE IGV — SOLO para BOLETA/FACTURA/NOTA_CREDITO.
        Esta es la regla de negocio original: el TICKET nunca
        muestra este bloque, sin importar el monto.
    --}}
    @if ($voucher->tipo_comprobante !== 'TICKET')
        <table>
            <tr class="totales-row">
                <td class="label">DESCUENTO GLOBAL</td>
                <td class="valor">S/ 0.00</td>
            </tr>
            @if ($voucher->total_exonerado > 0)
                <tr class="totales-row">
                    <td class="label">OP.EXONERADAS</td>
                    <td class="valor">{{ number_format($voucher->total_exonerado, 2) }}</td>
                </tr>
            @endif
            @if ($voucher->total_inafecto > 0)
                <tr class="totales-row">
                    <td class="label">OP.INAFECTAS</td>
                    <td class="valor">{{ number_format($voucher->total_inafecto, 2) }}</td>
                </tr>
            @endif
            <tr class="totales-row">
                <td class="label">OP.GRAVADAS</td>
                <td class="valor">{{ number_format($voucher->total_gravado, 2) }}</td>
            </tr>
            <tr class="totales-row">
                <td class="label">IGV (18%)</td>
                <td class="valor">{{ number_format($voucher->igv, 2) }}</td>
            </tr>
        </table>
    @endif

    <table>
        <tr class="totales-row bold">
            <td class="label">TOTAL</td>
            <td class="valor">S/ {{ number_format($voucher->total, 2) }}</td>
        </tr>
    </table>

    <div style="margin-top:4px;">
        SON: {{ $montoEnLetras }}
    </div>

    {{--
        Texto fijo, igual a tus 4 PDFs de referencia — si en el
        futuro quieres que muestre el nombre real del cajero en vez
        de un texto genérico, cambia esto por
        {{ $voucher->user->nombre }}.
    --}}
    <div>Cajero(a): ADMISION CAJA</div>

    <div class="separador"></div>

    {{--
        PAGO CON / VUELTO — replica el bloque de tus PDFs. Solo
        tiene sentido mostrarlo cuando la venta se pagó completa en
        el momento (no aplica bien a un TICKET con saldo pendiente,
        así que ahí se omite el "vuelto" y solo se muestra lo pagado).
    --}}
    @php
        $totalPagadoHoy = $voucher->payments->sum('monto');
        $vuelto = max(0, $totalPagadoHoy - $voucher->total);
    @endphp
    <table>
        <tr class="totales-row">
            <td class="label">Pago con :</td>
            <td class="valor bold">S/ {{ number_format($totalPagadoHoy, 2) }}</td>
        </tr>
        <tr class="totales-row">
            <td class="label">Vuelto :</td>
            <td class="valor bold">S/ {{ number_format($vuelto, 2) }}</td>
        </tr>
    </table>

    <div class="bold" style="margin-top:4px;">FORMAS DE PAGO</div>
    @foreach ($voucher->payments as $pago)
        <div style="display:flex; justify-content:space-between;">
            <span>{{ $pago->metodo_pago === 'TARJETA' ? 'T. ' . ($pago->entidad_destino ?? '') : $pago->metodo_pago }}</span>
            <span>{{ number_format($pago->monto, 2) }}</span>
        </div>
        @if ($pago->numero_operacion)
            <div style="font-size:9px;">
                @if ($pago->entidad_origen || $pago->entidad_destino)
                    Entidad financiera<br>
                    Origen: {{ $pago->entidad_origen }}<br>
                    Destino: {{ $pago->entidad_destino }}<br>
                @endif
                Nro. Ope.: {{ $pago->numero_operacion }}<br>
                Fecha: {{ $pago->created_at->format('d/m/Y h:i A') }}
            </div>
        @endif
    @endforeach

    {{--
        OBSERVACIÓN — el caso de tu ticket "COSTO CERO AUTORIZADO".
        Solo se muestra si el voucher tiene algo escrito ahí.
    --}}
    @if ($voucher->observacion)
        <div class="separador"></div>
        <div style="font-size:10px;">Observación: {{ $voucher->observacion }}</div>
    @endif

    <div class="separador"></div>
    <div class="center">CEO SALUD "Confianza y Bienestar"</div>

    {{--
        El texto legal y el QR SOLO van en BOLETA/FACTURA — un
        TICKET no es un comprobante fiscal, así que no debe sugerir
        que se puede verificar en SUNAT (porque no existe ahí).
    --}}
    @if ($voucher->tipo_comprobante !== 'TICKET')
        <div class="footer-legal">
            Representación impresa de {{ $voucher->tipo_comprobante === 'FACTURA' ? 'FACTURA' : 'BOLETA' }}
            ELECTRÓNICA. Puede verificarla en el portal de SUNAT usando su clave SOL
        </div>

        {{--
            El QR real lo genera Greenter cuando conectes SUNAT (va
            codificado con RUC/tipo/serie/correlativo/fecha/monto,
            según especificación de SUNAT) — por ahora, mientras no
            esté conectado, puedes ocultar este bloque o mostrar un
            QR de prueba.
        --}}
        {{-- <img src="{{ $voucher->qr_path }}" class="qr"> --}}
    @endif

</body>

</html>
