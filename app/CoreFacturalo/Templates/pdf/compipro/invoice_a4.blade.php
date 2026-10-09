@php
    use Modules\Template\Helpers\TemplatePdf;
    use App\CoreFacturalo\Helpers\Template\TemplateHelper;

    $establishment = $document->establishment;
    $customer      = $document->customer;
    $invoice       = $document->invoice;

    $document_number = $document->series . '-' . str_pad($document->number, 8, '0', STR_PAD_LEFT);
    $simbolo         = optional($document->currency_type)->symbol ?: 'S/';

    // La ubicación puede venir incompleta; se arma solo con lo que exista para
    // que un dato faltante no corte la impresión del comprobante.
    $ubicacion = implode(' - ', array_filter([
        optional($establishment->district)->description,
        optional($establishment->province)->description,
        optional($establishment->department)->description,
    ]));

    $accounts           = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);
    $configurationInPdf = TemplateHelper::getConfigurationInPdf();

    // Logo: prevalece el del establecimiento; si no, el de la empresa.
    // Se incrusta en el documento (base64) porque el generador de PDF no
    // resuelve rutas relativas. Si el archivo no existe o no se puede leer, se
    // omite: nunca debe impedir que se imprima el comprobante.
    $logoData = null;
    $rutaLogo = null;

    if ($establishment->logo) {
        $rutaLogo = public_path($establishment->logo);
    } elseif ($company->logo) {
        $rutaLogo = public_path("storage/uploads/logos/{$company->logo}");
    }

    if ($rutaLogo && is_file($rutaLogo) && is_readable($rutaLogo)) {
        try {
            $logoData = 'data:' . mime_content_type($rutaLogo) . ';base64,' . base64_encode(file_get_contents($rutaLogo));
        } catch (\Exception $e) {
            $logoData = null;
        }
    }

    // Retención y detracción vienen calculadas por el sistema; acá solo se muestran.
    $retencion  = $document->retention;
    $detraccion = $document->detraction;

    // Importe realmente a pagar: si hay retención o detracción, se descuenta.
    $montoDescuento = 0;
    if ($retencion)  { $montoDescuento = $retencion->amount; }
    if ($detraccion) { $montoDescuento = $detraccion->amount; }
    $totalAPagar = $document->total - $montoDescuento;

    // Cantidad mínima de renglones del detalle, para conservar el alto de la hoja.
    $filasMinimas = 12;
    $filasVacias  = max(0, $filasMinimas - $document->items->count());
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body>

{{-- ═══════════════ Cabecera ═══════════════ --}}
<table class="cabecera">
    <tr>
        <td class="col-logo">
            @if($logoData)
                <img src="{{ $logoData }}" class="logo" alt="{{ $company->name }}">
            @else
                <span class="empresa-nombre">{{ $company->name }}</span>
            @endif
        </td>
        <td class="col-empresa">
            <span class="empresa-nombre">{{ $company->name }}</span><br>
            {{ $establishment->address }}<br>
            {{ $ubicacion }}<br>
            <span class="etiqueta">Email:</span> {{ $establishment->email }}<br>
            <span class="etiqueta">Telf.:</span> {{ $establishment->telephone }}
        </td>
        <td class="col-doc">
            <table class="caja-doc">
                <tr><td class="doc-ruc">R.U.C {{ $company->number }}</td></tr>
                <tr><td class="doc-tipo">{{ $document->document_type->description }}</td></tr>
                <tr><td class="doc-numero">{{ $document_number }}</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- ═══════════════ Cliente ═══════════════ --}}
<table class="caja">
    <tr>
        <td class="et" width="90">RUC</td>
        <td width="10">:</td>
        <td>{{ $customer->number }}</td>
    </tr>
    <tr>
        <td class="et">CLIENTE</td>
        <td>:</td>
        <td>{{ $customer->name }}</td>
    </tr>
    <tr>
        <td class="et">DIRECCIÓN</td>
        <td>:</td>
        <td>{{ $customer->address }}</td>
    </tr>
</table>

{{-- ═══════════════ Datos del comprobante ═══════════════ --}}
<table class="caja">
    <tr>
        <td class="et" width="13%">F. DE EMISION</td>
        <td class="et" width="15%">F. DE VENCIMIENTO</td>
        <td class="et" width="18%">GUIA DE REMISION</td>
        <td class="et" width="18%">FORMA DE PAGO</td>
        <td class="et" width="18%">ORDEN DE COMPRA</td>
        <td class="et" width="18%">VENDEDOR</td>
    </tr>
    <tr>
        <td>{{ $document->date_of_issue->format('Y-m-d') }}</td>
        <td>{{ $document->date_of_due ? $document->date_of_due->format('Y-m-d') : '' }}</td>
        <td>
            @php
                // Las guías se guardan como JSON; según cómo se hayan grabado
                // pueden llegar como objetos, como arreglos o como texto suelto.
                $numerosGuia = [];
                foreach ((array) $document->guides as $guia) {
                    if (is_object($guia) && isset($guia->number)) {
                        $numerosGuia[] = $guia->number;
                    } elseif (is_array($guia) && isset($guia['number'])) {
                        $numerosGuia[] = $guia['number'];
                    } elseif (is_string($guia) && $guia !== '') {
                        $numerosGuia[] = $guia;
                    }
                }
            @endphp
            {{ implode(', ', $numerosGuia) }}
        </td>
        <td>{{ $document->payment_condition ? $document->payment_condition->description : '' }}</td>
        <td>{{ $document->purchase_order }}</td>
        <td>{{ $document->seller ? $document->seller->name : '' }}</td>
    </tr>
    @if($document->additional_information)
        <tr>
            <td class="et">OBSERVACIÓN</td>
            <td colspan="5">
                @foreach((array) $document->additional_information as $info)
                    {{ $info }}
                @endforeach
            </td>
        </tr>
    @endif
</table>

{{-- ═══════════════ Detalle ═══════════════ --}}
<table class="detalle">
    <thead>
    <tr>
        <th width="7%">COD.</th>
        <th width="7%">CANT.</th>
        <th width="8%">UNIDAD</th>
        <th width="48%" class="izq">DESCRIPCION</th>
        <th width="10%">P. UNIT</th>
        <th width="8%">DTO.</th>
        <th width="12%">TOTAL</th>
    </tr>
    </thead>
    <tbody>
    @foreach($document->items as $row)
        @php
            $descuentoLinea = 0;
            if ($row->discounts) {
                foreach ($row->discounts as $desc) { $descuentoLinea += $desc->amount; }
            }
        @endphp
        <tr>
            <td class="cen">{{ optional($row->item)->internal_id }}</td>
            <td class="cen">
                @if((int) $row->quantity != $row->quantity)
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 0) }}
                @endif
            </td>
            <td class="cen">{{ optional($row->item)->unit_type_id }}</td>
            <td class="izq">
                @if($row->name_product_pdf)
                    {!! $row->name_product_pdf !!}
                @else
                    {!! optional($row->item)->description !!}
                @endif
            </td>
            <td class="der">{{ number_format($row->unit_price, 2) }}</td>
            <td class="der">{{ number_format($descuentoLinea, 2) }}</td>
            <td class="der">{{ number_format($row->total, 2) }}</td>
        </tr>
    @endforeach

    @for($i = 0; $i < $filasVacias; $i++)
        <tr class="vacia">
            <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td>
        </tr>
    @endfor
    </tbody>
</table>

{{-- ═══════════════ Totales ═══════════════ --}}
<table class="totales">
    <tr>
        <td class="letras">
            <div class="letras-tit">IMPORTE EN LETRAS</div>
            <div class="letras-val">
                @foreach(array_reverse((array) $document->legends) as $legend)
                    @if($legend->code == 1000){{ $legend->value }}@endif
                @endforeach
            </div>
        </td>
        <td class="montos">
            <table class="tabla-montos">
                <tr>
                    <td class="mt-et">OP. GRAVADAS</td>
                    <td class="mt-sim">{{ $simbolo }}</td>
                    <td class="mt-val">{{ number_format($document->total_taxed, 2) }}</td>
                </tr>
                @if($document->total_exonerated > 0)
                    <tr>
                        <td class="mt-et">OP. EXONERADAS</td>
                        <td class="mt-sim">{{ $simbolo }}</td>
                        <td class="mt-val">{{ number_format($document->total_exonerated, 2) }}</td>
                    </tr>
                @endif
                @if($document->total_unaffected > 0)
                    <tr>
                        <td class="mt-et">OP. INAFECTAS</td>
                        <td class="mt-sim">{{ $simbolo }}</td>
                        <td class="mt-val">{{ number_format($document->total_unaffected, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="mt-et">IGV (18%)</td>
                    <td class="mt-sim">{{ $simbolo }}</td>
                    <td class="mt-val">{{ number_format($document->total_igv, 2) }}</td>
                </tr>

                {{-- La línea aparece solo cuando el comprobante tiene retención o detracción --}}
                @if($retencion)
                    <tr>
                        <td class="mt-et">RETENCIÓN</td>
                        <td class="mt-sim">{{ $simbolo }}</td>
                        <td class="mt-val">{{ number_format($retencion->amount, 2) }}</td>
                    </tr>
                @endif
                @if($detraccion)
                    <tr>
                        <td class="mt-et">DETRACCIÓN</td>
                        <td class="mt-sim">{{ $simbolo }}</td>
                        <td class="mt-val">{{ number_format($detraccion->amount, 2) }}</td>
                    </tr>
                @endif

                <tr>
                    <td class="mt-et total">TOTAL, A PAGAR</td>
                    <td class="mt-sim total">{{ $simbolo }}</td>
                    <td class="mt-val total">{{ number_format($totalAPagar, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ═══════════════ Información de retención ═══════════════ --}}
@if($retencion)
    <table class="caja info">
        <tr><td colspan="6" class="info-tit">INFORMACIÓN DE RETENCIÓN</td></tr>
        <tr>
            <td class="et" width="24%">Base importe de la Retención</td>
            <td width="2%">:</td>
            <td width="16%" class="negrita">{{ $simbolo }} {{ number_format($retencion->base, 2) }}</td>
            <td width="22%" class="et">Porcentaje de Retención :</td>
            <td width="10%">{{ number_format($retencion->percentage * 100, 2) }}%</td>
            <td>
                <span class="et">Monto Retención :</span>
                {{ $simbolo }} {{ number_format($retencion->amount, 2) }}
            </td>
        </tr>
    </table>
@endif

{{-- ═══════════════ Información de detracción ═══════════════ --}}
@if($detraccion)
    @inject('detractionType', 'App\Services\DetractionTypeService')
    <table class="caja info">
        <tr><td colspan="6" class="info-tit">INFORMACIÓN DE DETRACCIÓN</td></tr>
        <tr>
            <td class="et" width="18%">Leyenda</td>
            <td width="2%">:</td>
            <td colspan="4">Operación sujeta al Sistema de Pago de Obligaciones Tributarias con el Gobierno Central</td>
        </tr>
        <tr>
            <td class="et">Bien o Servicio</td>
            <td>:</td>
            <td colspan="4">
                @php
                    // Si el código no está en el catálogo, se muestra solo el código
                    // en lugar del texto de error que devuelve el servicio.
                    $descDetraccion = $detractionType->getDetractionTypeDescription($detraccion->detraction_type_id);
                    if (stripos($descDetraccion, 'no encontrado') !== false) { $descDetraccion = ''; }
                @endphp
                {{ $detraccion->detraction_type_id }} {{ $descDetraccion }}
            </td>
        </tr>
        <tr>
            <td class="et">Medio Pago</td>
            <td>:</td>
            <td colspan="4">
                {{ $detraccion->payment_method_id }}
                {{ $detractionType->getPaymentMethodTypeDescription($detraccion->payment_method_id) }}
            </td>
        </tr>
        <tr>
            <td class="et">N° Cta Detracción</td>
            <td>:</td>
            <td width="18%">{{ $detraccion->bank_account }}</td>
            <td class="et" width="22%">Porcentaje de Detracción :</td>
            <td width="10%">{{ number_format($detraccion->percentage, 2) }}%</td>
            <td>
                <span class="et">Monto Detracción :</span>
                {{ $simbolo }} {{ number_format($detraccion->amount, 2) }}
            </td>
        </tr>
    </table>
@endif

{{-- ═══════════════ Pie ═══════════════ --}}
<table class="pie">
    <tr>
        <td class="pie-col" width="45%">
            <table class="pie-datos">
                <tr><td colspan="2" class="negrita">{{ $company->name }}</td></tr>
                <tr>
                    <td class="et" width="35%">RUC</td>
                    <td>{{ $company->number }}</td>
                </tr>
                <tr>
                    <td class="et">Dirección</td>
                    <td>{{ $establishment->address }}</td>
                </tr>
                @if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf)
                    @foreach($accounts as $account)
                        <tr>
                            <td class="et">{{ optional($account->bank)->description }} {{ optional($account->currency_type)->description }}</td>
                            <td>{{ $account->number }}</td>
                        </tr>
                        @if($account->cci)
                            <tr>
                                <td class="et">CCI {{ optional($account->currency_type)->description }}</td>
                                <td>{{ $account->cci }}</td>
                            </tr>
                        @endif
                    @endforeach
                @endif
                @if($company->detraction_account)
                    <tr>
                        <td class="et">Detracción BN (S/)</td>
                        <td>{{ $company->detraction_account }}</td>
                    </tr>
                @endif
            </table>
        </td>
        <td class="pie-col" width="34%">
            Representación impresa de la {{ $document->document_type->description }},<br>
            Visita: {!! url('/buscar') !!}<br>
            Resumen: {{ $document->hash }}
        </td>
        <td class="pie-col cen" width="21%">
            @if($document->qr)
                <img src="data:image/png;base64,{{ $document->qr }}" class="qr">
            @endif
        </td>
    </tr>
</table>

</body>
</html>
