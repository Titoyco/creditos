<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagaré - Crédito #{{ $credito->full_identificador }}</title>
    <script type="text/javascript">
        function imprimir() { 
            setTimeout(function(){ 
                window.print(); 
            }, 500);
        }
    </script>
    <style type="text/css">
        @media print {
            @page {
                size: 210mm 297mm;
                margin: 15px;
            }
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 10px;
        }
        .contenedor {
            width: 780px;
            margin: 0 auto;
        }
        .tabla-border {
            width: 100%;
            border-collapse: collapse;
        }
        .tabla-border td, .tabla-border th {
            border: 1px solid #ccc;
            padding: 4px;
        }
        .bg-gris {
            background-color: #E2E2E2;
            font-size: 10px;
            text-align: center;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .texto-manuscrito {
            font-family: "Brush Script MT", cursive, serif;
            font-size: 13.5px;
            line-height: 22px;
            text-align: justify;
        }
        .box-firma {
            border: 2px solid #E2E2E2;
            text-align: center;
            vertical-align: bottom;
            height: 80px;
            font-size: 10px;
            padding: 5px;
        }
        hr.linea-punteada {
            border: none;
            border-top: 2px dashed #000;
            margin: 20px 0;
        }
    </style>
</head>
<body onload="imprimir();">
<div class="contenedor">

    <!-- Encabezado -->
    <table style="width: 100%;">
        <tr>
            <td rowspan="2" style="width: 60%;">
                <img src="{{ asset('img/logo.png') }}" alt="Logo" height="50" onerror="this.style.display='none'" />
                <strong style="font-size: 16px;">LA MARINA</strong>
            </td>
            <td class="bg-gris" style="width: 15%;">Id Venta:</td>
            <td style="border: 1px solid #ccc; padding: 4px;">&nbsp;<strong>{{ $credito->full_identificador }}</strong></td>
        </tr>
        <tr>
            <td class="bg-gris">Fecha/Hora:</td>
            <td style="border: 1px solid #ccc; padding: 4px;">&nbsp;{{ $credito->fecha_credito ? \Carbon\Carbon::parse($credito->fecha_credito)->format('d/m/Y') : '' }}</td>
        </tr>
    </table>

    <p style="font-size: 11px; margin-top: 10px;">
        Autorizo a GUILLERMO BARZINI, “LA MARINA”, y/o “TIENDAS LA MARINA SAS”, a debitar de mi cuenta bancaria el importe que surge de la siguiente compra. Si el débito no fuese posible por alguna causa ajena a la EMPRESA, me comprometo a realizar el pago en el local comercial de RIVADAVIA 249 – Trelew.
    </p>
    <p style="font-size: 10px; font-style: italic;">* El incumplimiento de los pagos pactados, generará intereses mensuales por mora y dará vía al COBRO JUDICIAL.</p>

    <!-- Datos Cliente -->
    <table class="tabla-border" style="margin-top: 10px;">
        <tr>
            <td class="bg-gris" style="width: 35%;">Nombres y Apellidos</td>
            <td class="bg-gris" style="width: 15%;">DNI</td>
            <td class="bg-gris" style="width: 20%;">Teléfono</td>
            <td class="bg-gris" style="width: 30%;">Dirección</td>
        </tr>
        <tr>
            <td><strong>{{ $credito->cliente->nombre }}</strong></td>
            <td class="text-center">{{ $credito->cliente->dni }}</td>
            <td class="text-center">{{ $credito->cliente->telefono ?? '-' }}</td>
            <td>{{ $credito->cliente->domicilio ?? '-' }}</td>
        </tr>
    </table>

    <!-- Plan y Firma Autorizado -->
    <table style="width: 100%; margin-top: 15px;">
        <tr>
            <td style="width: 60%; vertical-align: top; font-size: 11px;">
                Son <strong>{{ $credito->cantidad_cuotas }}</strong> cuotas mensuales consecutivas de <strong>$ {{ number_format($credito->valor_cuota_ref, 2, ',', '.') }}</strong><br/><br/>
                {{ $concepto }}
            </td>
            <td style="width: 40%;" class="box-firma">
                <strong>FIRMA</strong>
                @if($credito->autorizado && $credito->autorizado->cliente)
                    <p style="margin: 5px 0 0 0;">
                        AUTORIZADO: {{ $credito->autorizado->cliente->nombre }}<br>
                        DNI: {{ $credito->autorizado->cliente->dni }}
                    </p>
                @endif
            </td>
        </tr>
    </table>

    <hr style="border: none; border-top: 1px solid #ccc; margin: 15px 0;" />

    <!-- Datos del Pagaré -->
    <table style="width: 100%;">
        <tr>
            <td class="bg-gris" style="width: 20%;">SELLADO</td>
            <td class="bg-gris" style="width: 20%;">NUMERO</td>
            <td class="bg-gris" style="width: 20%;">VENCIMIENTO</td>
            <td class="text-right" style="width: 15%; font-size: 18px; font-weight: bold;">Por:</td>
            <td style="border: 2px solid #ccc; font-size: 16px; font-weight: bold; padding: 5px; width: 25%;" class="text-center">
                $ {{ number_format($totalMonto, 2, ',', '.') }}.-
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #ccc; height: 25px;"></td>
            <td style="border: 1px solid #ccc; height: 25px;"></td>
            <td style="border: 1px solid #ccc; height: 25px;"></td>
            <td colspan="2"></td>
        </tr>
    </table>

    <p class="texto-manuscrito" style="margin-top: 15px;">
        El _____________ de ______________________ de ___________________ pagaré / mes a ________________
    </p>
    <p class="texto-manuscrito">
        _________________________________________________________________________________ a su orden 
    </p>
    
    <table style="width: 100%;">
        <tr>
            <td class="texto-manuscrito" style="width: 160px;">la cantidad de pesos:</td>
            <td style="background-color: #E2E2E2; padding: 5px; font-weight: bold; font-size: 13px;">
                &nbsp;{{ $montoEnLetras }} PESOS.-
            </td>
        </tr>
    </table>

    <p class="texto-manuscrito">
        por igual valor recibido en ___________________________________________ a _____________________ entera satisfacción pagadero sin protesto (art. 50 Ley 5965/63) en Rivadavia 249 de Trelew.
    </p>

    <!-- Firmante y Cuadro de Firma -->
    <table style="width: 100%; margin-top: 10px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <table style="width: 100%; font-size: 11px;">
                    <tr>
                        <td style="width: 80px; padding: 4px 0;"><strong>Firmante:</strong></td>
                        <td style="border-bottom: 1px solid #ccc;">{{ $credito->cliente->nombre }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0;"><strong>Documento:</strong></td>
                        <td style="border-bottom: 1px solid #ccc;">{{ $credito->cliente->dni }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0;"><strong>Domicilio:</strong></td>
                        <td style="border-bottom: 1px solid #ccc;">{{ $credito->cliente->domicilio ?? '' }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 40%;" class="box-firma">
                <strong>FIRMA</strong>
                @if($credito->autorizado && $credito->autorizado->cliente)
                    <p style="margin: 5px 0 0 0;">
                        AUTORIZADO: {{ $credito->autorizado->cliente->nombre }}<br>
                        DNI: {{ $credito->autorizado->cliente->dni }}
                    </p>
                @endif
            </td>
        </tr>
    </table>

    <hr class="linea-punteada" />

    <!-- Talón Cortable / Copia Inferior -->
    <table style="width: 100%;">
        <tr>
            <td style="width: 65%; border: 1px solid #ccc; padding: 8px; font-size: 11px;">
                Son <strong>{{ $credito->cantidad_cuotas }}</strong> cuotas mensuales consecutivas de <strong>$ {{ number_format($credito->valor_cuota_ref, 2, ',', '.') }}</strong>.<br />
                {{ $concepto }}
            </td>
            <td style="width: 35%; padding-left: 10px; vertical-align: top;">
                <table style="width: 100%; font-size: 11px;">
                    <tr>
                        <td class="bg-gris" style="width: 40%;">Id venta:</td>
                        <td style="border: 1px solid #ccc; padding: 3px;">&nbsp;<strong>{{ $credito->full_identificador }}</strong></td>
                    </tr>
                    <tr>
                        <td class="bg-gris">Fecha/hora:</td>
                        <td style="border: 1px solid #ccc; padding: 3px;">&nbsp;{{ $credito->fecha_credito ? \Carbon\Carbon::parse($credito->fecha_credito)->format('d/m/Y') : '' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</div>
</body>
</html>