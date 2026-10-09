@php
    $company = \App\Models\Tenant\Company::active();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta de Pago</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #000; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        .header-box { border: 1px solid #000; background-color: #bbd4f5; padding: 5px; margin-bottom: 5px; }
        .header-box p { margin: 2px 0; font-weight: normal; font-size: 11px; }
        
        .title-row { font-weight: bold; font-size: 11px; }
        .title-right { text-align: right; font-weight: normal; }
        
        th, td { border: 1px solid #000; padding: 3px 5px; }
        .bg-gray { background-color: #bbd4f5; text-align: center; font-size: 9px; font-weight: normal; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        
        .no-border { border: none; }
        .no-border td { border: none; padding: 0; }
        
        .section-header { background-color: #bbd4f5; font-weight: normal; text-align: left; font-size: 10px; }
        .net-pay-header { background-color: #bbd4f5; font-weight: normal; text-align: left; font-size: 11px; }
        
        .footer-signatures { width: 100%; margin-top: 40px; }
        .signature-line { width: 40%; border-top: 2px solid #000; text-align: center; font-weight: bold; font-size: 12px; padding-top: 5px; }
    </style>
</head>
<body>
    <table class="no-border" style="margin-bottom: 2px;">
        <tr>
            <td class="title-row" style="font-size: 12px;">R08: Trabajador – Datos de boleta de pago</td>
            <td class="title-row title-right">{{ \Carbon\Carbon::now()->format('d/m/y') }}</td>
        </tr>
        <tr>
            <td style="font-size: 9px;">(Contiene datos mínimos de una Boleta de Pago)</td>
            <td class="title-right">{{ \Carbon\Carbon::now()->format('H:i:s') }}</td>
        </tr>
    </table>

    <div class="header-box">
        <p>RUC : {{ $company->number ?? '20000000001' }}</p>
        <p>Empleador : {{ $company->name ?? config('app.name', 'EMPRESA DEMO') }}</p>
        <p>Periodo : {{ \Carbon\Carbon::parse($detalle->planilla->periodo . '-01')->format('m/Y') }}</p>
        <p>PDT Planilla Electrónica - PLAME &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Número de Orden : </p>
    </div>

    <table>
        <tr>
            <td colspan="2" class="bg-gray">Documento de Identidad</td>
            <td rowspan="2" class="bg-gray" style="width: 40%;">Nombre y Apellidos</td>
            <td rowspan="2" class="bg-gray" style="width: 25%;">Situación</td>
        </tr>
        <tr>
            <td class="bg-gray" style="width: 15%;">Tipo</td>
            <td class="bg-gray" style="width: 20%;">Número</td>
        </tr>
        <tr>
            <td class="text-center">DNI</td>
            <td class="text-center">{{ $detalle->dni }}</td>
            <td class="text-center">{{ $detalle->nombres_completos }}</td>
            <td class="text-center">ACTIVO O SUBSIDIADO</td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="bg-gray" style="width: 25%;">Fecha de Ingreso</td>
            <td class="bg-gray" style="width: 25%;">Tipo de Trabajador</td>
            <td class="bg-gray" style="width: 25%;">Regimen Pensionario</td>
            <td class="bg-gray" style="width: 25%;">CUSPP</td>
        </tr>
        <tr>
            <td class="text-center">{{ \Carbon\Carbon::parse($detalle->empleado->user->contract_date ?? now())->format('d/m/y') }}</td>
            <td class="text-center">EMPLEADO</td>
            <td class="text-center">{{ $detalle->tipo_regimen }} {{ mb_strtoupper($detalle->afp_nombre ?? '') }}</td>
            <td class="text-center">{{ $detalle->empleado->cuspp ?? '-' }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <td rowspan="2" class="bg-gray" style="width: 15%;">Días<br>Laborados</td>
            <td rowspan="2" class="bg-gray" style="width: 15%;">Días No<br>Laborados</td>
            <td rowspan="2" class="bg-gray" style="width: 15%;">Días<br>subsidiados</td>
            <td rowspan="2" class="bg-gray" style="width: 15%;">Condición</td>
            <td colspan="2" class="bg-gray">Jornada Ordinaria</td>
            <td colspan="2" class="bg-gray">Sobretiempo</td>
        </tr>
        <tr>
            <td class="bg-gray" style="width: 10%;">Total Horas</td>
            <td class="bg-gray" style="width: 10%;">Minutos</td>
            <td class="bg-gray" style="width: 10%;">Total Horas</td>
            <td class="bg-gray" style="width: 10%;">Minutos</td>
        </tr>
        <tr>
            <td class="text-center">{{ $detalle->dias_laborados }}</td>
            <td class="text-center">0</td>
            <td class="text-center">0</td>
            <td class="text-center">Domiciliado</td>
            <td class="text-center">{{ $detalle->dias_laborados * 8 }}</td>
            <td class="text-center"></td>
            <td class="text-center"></td>
            <td class="text-center"></td>
        </tr>
    </table>

    <table>
        <tr>
            <td colspan="3" class="bg-gray">Motivo de Suspensión de Labores</td>
            <td rowspan="2" class="bg-gray" style="width: 25%;">Otros empleadores por<br>Rentas de 5ta categoría</td>
        </tr>
        <tr>
            <td class="bg-gray" style="width: 15%;">Tipo</td>
            <td class="bg-gray" style="width: 45%;">Motivo</td>
            <td class="bg-gray" style="width: 15%;">N.º Días</td>
        </tr>
        <tr>
            <td class="text-center">&nbsp;</td>
            <td class="text-center">&nbsp;</td>
            <td class="text-center">&nbsp;</td>
            <td class="text-center">No tiene</td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="bg-gray" style="width: 10%;">Código</td>
            <td class="bg-gray" style="width: 48%;">Conceptos</td>
            <td class="bg-gray" style="width: 14%;">Ingresos S/.</td>
            <td class="bg-gray" style="width: 14%;">Descuentos S/.</td>
            <td class="bg-gray" style="width: 14%;">Neto S/.</td>
        </tr>
        <!-- Ingresos -->
        <tr>
            <td colspan="5" class="section-header">Ingresos</td>
        </tr>
        <tr>
            <td class="text-center">0121</td>
            <td>REMUNERACIÓN O JORNAL BÁSICO</td>
            <td class="text-right">{{ number_format($detalle->sueldo_basico, 2) }}</td>
            <td></td>
            <td></td>
        </tr>
        @if($detalle->asignacion_familiar > 0)
        <tr>
            <td class="text-center">0201</td>
            <td>ASIGNACIÓN FAMILIAR</td>
            <td class="text-right">{{ number_format($detalle->asignacion_familiar, 2) }}</td>
            <td></td>
            <td></td>
        </tr>
        @endif
        @if($detalle->movilidad > 0)
        <tr>
            <td class="text-center">0300</td>
            <td>MOVILIDAD</td>
            <td class="text-right">{{ number_format($detalle->movilidad, 2) }}</td>
            <td></td>
            <td></td>
        </tr>
        @endif
        @if($detalle->horas_extras > 0)
        <tr>
            <td class="text-center">0105</td>
            <td>TRABAJO EN SOBRETIEMPO</td>
            <td class="text-right">{{ number_format($detalle->horas_extras, 2) }}</td>
            <td></td>
            <td></td>
        </tr>
        @endif
        @if($detalle->bonificaciones > 0)
        <tr>
            <td class="text-center">0300</td>
            <td>BONIFICACIONES</td>
            <td class="text-right">{{ number_format($detalle->bonificaciones, 2) }}</td>
            <td></td>
            <td></td>
        </tr>
        @endif
        @if($detalle->otros_ingresos > 0)
        <tr>
            <td class="text-center">0300</td>
            <td>OTROS INGRESOS</td>
            <td class="text-right">{{ number_format($detalle->otros_ingresos, 2) }}</td>
            <td></td>
            <td></td>
        </tr>
        @endif

        <!-- Descuentos -->
        <tr>
            <td colspan="5" class="section-header">Descuentos</td>
        </tr>
        <tr>
            <td colspan="5" class="font-bold border-bottom-0" style="border-bottom: none; border-top: none; font-size: 10px;">Aportes del Trabajador</td>
        </tr>
        @if($detalle->tipo_regimen === 'ONP')
            <tr>
                <td class="text-center">0607</td>
                <td>SISTEMA NACIONAL DE PENSIONES - ONP</td>
                <td></td>
                <td class="text-right">{{ number_format($detalle->aporte_afp_onp, 2) }}</td>
                <td></td>
            </tr>
        @else
            @if($detalle->comision_afp > 0)
            <tr>
                <td class="text-center">0601</td>
                <td>COMISIÓN AFP PORCENTUAL</td>
                <td></td>
                <td class="text-right">{{ number_format($detalle->comision_afp, 2) }}</td>
                <td></td>
            </tr>
            @endif
            @if($detalle->renta_quinta > 0)
            <tr>
                <td class="text-center">0605</td>
                <td>RENTA QUINTA CATEGORÍA RETENCIONES</td>
                <td></td>
                <td class="text-right">{{ number_format($detalle->renta_quinta, 2) }}</td>
                <td></td>
            </tr>
            @endif
            @if($detalle->seguro_afp > 0)
            <tr>
                <td class="text-center">0606</td>
                <td>PRIMA DE SEGURO AFP</td>
                <td></td>
                <td class="text-right">{{ number_format($detalle->seguro_afp, 2) }}</td>
                <td></td>
            </tr>
            @endif
            @if($detalle->aporte_afp_onp > 0)
            <tr>
                <td class="text-center">0608</td>
                <td>SPP - APORTACIÓN OBLIGATORIA</td>
                <td></td>
                <td class="text-right">{{ number_format($detalle->aporte_afp_onp, 2) }}</td>
                <td></td>
            </tr>
            @endif
        @endif
        
        @if($detalle->otros_descuentos > 0)
        <tr>
            <td class="text-center">0700</td>
            <td>OTROS DESCUENTOS</td>
            <td></td>
            <td class="text-right">{{ number_format($detalle->otros_descuentos, 2) }}</td>
            <td></td>
        </tr>
        @endif

        <!-- Totales -->
        <tr>
            <td colspan="4" class="net-pay-header text-left">Neto a Pagar</td>
            <td class="net-pay-header text-right">{{ number_format($detalle->neto_pagar, 2) }}</td>
        </tr>
        
    </table>

    <table>
        <tr>
            <td colspan="3" class="section-header">Aportes de Empleador</td>
        </tr>
        @if($detalle->essalud > 0)
        <tr>
            <td class="text-center" style="width: 10%;">0804</td>
            <td style="width: 76%;">ESSALUD(REGULAR CBSSP AGRAR/AC)TRAB</td>
            <td class="text-right" style="width: 14%;">{{ number_format($detalle->essalud, 2) }}</td>
        </tr>
        @endif
    </table>
    
    <br><br><br><br>

    <table class="no-border footer-signatures">
        <tr>
            <td style="width: 10%;"></td>
            <td style="width: 35%; align-items: center; justify-content: center; text-align: center;">
                <div class="signature-line" style="width: 100%;">TRABAJADOR</div>
            </td>
            <td style="width: 10%;"></td>
            <td style="width: 35%; align-items: center; justify-content: center; text-align: center;">
                <div class="signature-line" style="width: 100%;">EMPLEADOR</div>
            </td>
            <td style="width: 10%;"></td>
        </tr>
    </table>

</body>
</html>
