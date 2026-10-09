<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $proposal->numero_cotizacion }}</title>
    <style>
        * { box-sizing: border-box; }
        /* Reservamos margen superior para la cabecera fija (3 logos) e inferior
           para el pie fijo (marca + contacto), repetidos en CADA página. */
        @page {
            margin: 120px 40px 62px 40px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        /* Header FIJO: dompdf lo repite en todas las hojas. Cabecera maestra de
           3 celdas: logo Compina | sello ECOLÓGICO SOSTENIBLE | logo Compipro.
           Se posiciona dentro de la banda del margen superior. */
        .pdf-header-fixed {
            position: fixed;
            top: -92px;
            left: 0;
            right: 0;
        }
        .pdf-header-fixed table.header-logos {
            width: 100%;
            border-collapse: collapse;
        }
        .pdf-header-fixed table.header-logos td {
            vertical-align: middle;
            text-align: center;
            padding: 0 4px;
        }
        /* Logos "con slogan": un poco más altos para que el slogan sea legible. */
        .pdf-header-fixed .logo-marca { height: 52px; }
        .pdf-header-fixed .sello-eco  { height: 60px; }
        .header { margin-bottom: 14px; }
        .header-top {
            width: 100%;
            border-bottom: 2px solid #ED6A0A;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header-top td { vertical-align: middle; }
        .brand-logo {
            color: #ED6A0A;
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .brand-tagline {
            font-size: 9px;
            color: #555;
        }
        /* Fila fecha / N° de cotización (1ra página): fecha a la izquierda, N° y
           su caja a la derecha, sobre la línea naranja (ref. Header_correcto). */
        .header-top .fecha { font-size: 11px; }
        .header-top .meta-num { text-align: right; white-space: nowrap; padding-right: 8px; }
        .header-top .meta-box { white-space: nowrap; width: 1px; }
        .header-top .num {
            background: #f5f5f5;
            border: 1px solid #ED6A0A;
            padding: 3px 8px;
            font-weight: bold;
            color: #ED6A0A;
            display: inline-block;
        }
        .destinatario {
            margin: 8px 0 10px 0;
            line-height: 1.45;
        }
        .destinatario strong { color: #222; }
        .titulo-tabla {
            background: #333;
            color: #fff;
            padding: 6px 8px;
            font-weight: bold;
            font-size: 11px;
            margin-top: 6px;
        }
        .notas-prev {
            margin: 6px 0 4px 0;
            padding: 4px 0;
        }
        .notas-prev li {
            color: #ED6A0A;
            font-weight: bold;
            font-size: 9px;
            list-style-type: disc;
            margin-left: 15px;
            padding-left: 3px;
        }
        table.productos {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }
        table.productos th {
            background: #ED6A0A;
            color: #fff;
            padding: 5px 3px;
            font-size: 9px;
            border: 1px solid #444;
            text-align: center;
        }
        table.productos td {
            border: 1px solid #bbb;
            padding: 4px;
            font-size: 9px;
            /* R7 — centrar V+H todas las celdas; "Detalles" se sobre-escribe a la izquierda. */
            vertical-align: middle;
            text-align: center;
        }
        table.productos td.numero { width: 22px; }
        {{-- Anchos: OJO, están duplicados con el width inline de los <th> (más
             abajo). Si se cambia solo un lado, dompdf toma el MAYOR de los dos
             y el cambio no surte efecto. Cambiar SIEMPRE los dos. --}}
        table.productos td.codigo { width: 40px; font-family: monospace; font-size: 8px; }
        table.productos td.articulo { font-weight: bold; width: 58px; }
        table.productos td.foto { font-style: italic; color: #888; width: 124px; font-size: 8px; }
        table.productos td.cantidad { width: 34px; font-weight: bold; }
        table.productos td.tiempo { width: 60px; font-size: 8px; }
        /* Detalles: única columna alineada a la izquierda (centrada en vertical
           por la base). No tiene ancho fijo: absorbe el espacio restante. */
        table.productos td.detalles { font-size: 8px; white-space: pre-line; line-height: 1.3; text-align: left; }
        table.productos td.precio { width: 52px; }
        table.productos td.total { width: 60px; font-weight: bold; }
        .totales {
            margin-top: 4px;
            width: 100%;
        }
        .totales td {
            padding: 3px 6px;
            font-size: 10px;
        }
        .totales .label {
            text-align: right;
            font-weight: bold;
            background: #eee;
        }
        .totales .value {
            text-align: right;
            width: 80px;
            border: 1px solid #bbb;
        }
        .totales .row-total .label,
        .totales .row-total .value {
            background: #ED6A0A;
            color: #fff;
            font-size: 11px;
            font-weight: bold;
        }
        .nota-foot {
            font-size: 8px;
            font-style: italic;
            color: #ED6A0A;
            margin-top: 3px;
        }
        h3.section-title {
            background: #333;
            color: #fff;
            padding: 5px 8px;
            margin: 14px 0 6px 0;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        table.condiciones {
            width: 100%;
            border-collapse: collapse;
        }
        table.condiciones td {
            padding: 5px 7px;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
            vertical-align: top;
            line-height: 1.45;
        }
        table.condiciones .label {
            width: 180px;
            font-weight: bold;
            background: #f7f7f7;
        }
        table.condiciones ul {
            margin: 0;
            padding-left: 16px;
        }
        table.condiciones li {
            margin-bottom: 2px;
        }
        /* Párrafos del markdown-lite ("> "): dompdf necesita margin explícito,
           si no rompe el alto de la fila de la tabla. */
        table.condiciones p {
            margin: 0 0 3px 0;
            line-height: 1.45;
        }
        table.condiciones td > p:last-child,
        table.condiciones td > ul:last-child {
            margin-bottom: 0;
        }
        table.condiciones strong {
            font-weight: bold;
        }
        table.bloque-info {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        table.bloque-info td {
            padding: 4px 6px;
            font-size: 9px;
            border-bottom: 1px dotted #aaa;
        }
        table.bloque-info .label {
            width: 180px;
            font-weight: bold;
            color: #ED6A0A;
        }
        /* Bloque de sostenibilidad (plantilla maestra): sello + texto verde en
           itálica, en caja de fondo verde claro. No se parte entre páginas. */
        .sostenibilidad {
            margin: 14px 0 4px 0;
            border: 1px solid #2e7d32;
            background: #f2f9f2;
            padding: 10px 12px;
            page-break-inside: avoid;
        }
        .sostenibilidad td { vertical-align: middle; }
        .sostenibilidad .sost-sello { width: 78px; text-align: center; }
        .sostenibilidad .sost-texto {
            color: #2e7d32;
            font-style: italic;
            font-size: 9px;
            line-height: 1.5;
            text-align: justify;
            padding-left: 12px;
        }
        .sostenibilidad .sost-texto p { margin: 0 0 6px 0; }
        .sostenibilidad .sost-texto p.last { margin-bottom: 0; }
        .footer-content {
            margin-top: 16px;
            font-size: 9px;
            line-height: 1.45;
        }
        .firma-block {
            margin-top: 10px;
        }
        .firma-block .nombre {
            font-weight: bold;
            font-size: 11px;
        }
        /* R6 — apellidos en naranja de marca dentro del nombre de la firma. */
        .firma-block .firma-apellidos { color: #ED6A0A; }
        .firma-block .cargo { color: #000; }
        /* Pie FIJO: dompdf lo repite en todas las hojas. Réplica del pie de la
           plantilla maestra: "marca de la familia" + línea de contacto COMPIPRO.
           Se posiciona dentro de la banda del margen inferior. */
        .pdf-footer-fixed {
            position: fixed;
            bottom: -14px;
            left: 0;
            right: 0;
            text-align: center;
        }
        .pdf-footer-fixed .pie-marca {
            font-weight: bold;
            font-size: 9px;
            color: #666;
            letter-spacing: 0.3px;
        }
        .pdf-footer-fixed .pie-contacto {
            margin-top: 3px;
            font-size: 10px;
            color: #8a8a8a;
        }
    </style>
</head>
<body>

    {{-- ══════════ CABECERA FIJA — 3 LOGOS, EN CADA HOJA ══════════
         compina · SELLO ECOLÓGICO SOSTENIBLE (verde) · compipro.
         dompdf repite el bloque `fixed` en todas las páginas y deduplica las
         imágenes base64 (el sello se embebe una sola vez). --}}
    <div class="pdf-header-fixed">
        <table class="header-logos" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width: 38%;">
                    @if(!empty($logo_compina))
                        <img src="{{ $logo_compina }}" class="logo-marca" alt="Compina">
                    @else
                        <span class="brand-logo">COMPINA</span>
                    @endif
                </td>
                <td style="width: 24%;">
                    @if(!empty($sello_eco))
                        <img src="{{ $sello_eco }}" class="sello-eco" alt="Ecológico Sostenible">
                    @endif
                </td>
                <td style="width: 38%;">
                    @if(!empty($logo_compipro))
                        <img src="{{ $logo_compipro }}" class="logo-marca" alt="Compipro">
                    @else
                        <span class="brand-logo">COMPIPRO</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ══════════ PIE FIJO — EN CADA HOJA ══════════
         Réplica del pie de la plantilla maestra: "marca de la familia" + contacto
         COMPIPRO. dompdf repite el bloque `fixed` en todas las páginas. --}}
    <div class="pdf-footer-fixed">
        <div class="pie-marca">COMPIPRO es una marca de la familia GRUPO COMPINA</div>
        <div class="pie-contacto">{{ $pie_email }} &nbsp;|&nbsp; {{ $pie_telefono }} &nbsp;|&nbsp; {{ $pie_web }}</div>
    </div>

    {{-- ══════════ CABECERA (1ra página): fecha, N° y destinatario ══════════ --}}
    <div class="header">
        <table class="header-top" cellpadding="0" cellspacing="0">
            <tr>
                <td class="fecha">{{ $fecha_larga }}</td>
                <td class="meta-num">
                    <strong>N° COTIZACIÓN</strong>
                </td>
                <td class="meta-box">
                    <span class="num">{{ $proposal->numero_cotizacion }}</span>
                </td>
            </tr>
        </table>

        <div class="destinatario">
            <strong>Sr(a).</strong><br>
            @if($proposal->destinatario_nombre)
                <strong>{{ $proposal->destinatario_nombre }}</strong><br>
            @endif
            @if($proposal->destinatario_cargo)
                {{ $proposal->destinatario_cargo }}<br>
            @endif
            @if($proposal->destinatario_sede)
                {{ $proposal->destinatario_sede }}<br>
            @endif
            @if($proposal->destinatario_empresa)
                <strong>{{ $proposal->destinatario_empresa }}</strong><br>
            @endif
            <span style="font-style: italic;">Presente.</span>
        </div>

        <div style="margin: 8px 0; font-size: 10px;">
            Me es grato dirigirme a Ud. con el fin de saludarle y poner a su consideración:
        </div>

        <div class="titulo-tabla">{{ $proposal->titulo_descriptivo }}</div>

        {{-- ══════════ NOTAS ENCABEZADO (3 líneas en rojo/negrita) ══════════ --}}
        @if(!empty($notas))
            <ul class="notas-prev">
                @foreach($notas as $nota)
                    <li>{{ $nota }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- ══════════ TABLA DE PRODUCTOS ══════════ --}}
    <table class="productos">
        <thead>
            {{-- Cabecera de 2 filas: "Costo uni." y "Costo total" se agrupan bajo
                 una sola cabecera para ahorrar ancho y dárselo a "Detalles".
                 Ambas columnas de costo van adyacentes al final. --}}
            <tr>
                <th rowspan="2" style="width: 22px;">Nº</th>
                {{-- El <br> es necesario: sin él, el min-content de "Cód. Artíc."
                     impide que la columna baje a 40px. El ancho liberado aquí y
                     en "Artículo" (cuyo nombre ya se repite en Detalles) es el
                     que se le da a la Foto, sin quitarle a Detalles. --}}
                <th rowspan="2" style="width: 40px;">Cód.<br>Artíc.</th>
                <th rowspan="2" style="width: 58px;">Artículo</th>
                <th rowspan="2" style="width: 124px;">Foto</th>
                <th rowspan="2" style="width: 34px;">Cant.</th>
                <th rowspan="2" style="width: 60px;">Tiempo Entrega</th>
                <th rowspan="2">Detalles</th>
                {{-- Agosto 2026 (punto 14) — El rótulo y los valores siguen a
                     `precios_incluyen_igv`: con IGV muestran el total del ítem con
                     impuesto incluido; sin IGV, los valores del motor tal cual.
                     El texto viene resuelto desde ProposalPdfGenerator. --}}
                <th colspan="2">{{ $costos_label }} ({{ $moneda_signo }})</th>
            </tr>
            <tr>
                <th style="width: 52px;">Unit.</th>
                <th style="width: 60px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $it)
                <tr>
                    <td class="numero">{{ $it['orden'] }}</td>
                    <td class="codigo">{{ $it['codigo'] }}</td>
                    <td class="articulo">{{ $it['nombre'] }}</td>
                    <td class="foto">
                        @if(!empty($it['imagen_url']))
                            {{-- Agosto 2026 (punto 12) — "la imagen debe ocupar todo
                                 el ancho del campo Foto sin deformarse".

                                 Antes iba con max-width: la imagen se mostraba a su
                                 tamaño natural si era más chica que la celda, y
                                 quedaba una foto diminuta perdida en el medio.
                                 Ahora width:100% la estira siempre al ancho completo
                                 de la columna.

                                 height:auto es lo que evita la deformación: dompdf
                                 recalcula el alto manteniendo la proporción. El
                                 max-height sigue como tope para que una imagen muy
                                 vertical no empuje la fila y rompa el salto de
                                 página. --}}
                            <img src="{{ $it['imagen_url'] }}"
                                 style="width: 100%; height: auto; max-height: 115px;" alt="">
                        @else
                            imagen referencial
                        @endif
                    </td>
                    <td class="cantidad">{{ $it['cantidad'] }}</td>
                    <td class="tiempo">{{ $it['tiempo_entrega'] }}</td>
                    {{-- Agosto 2026 (punto 11) — Va sin escapar a propósito: el
                         contenido YA viene escapado y convertido por
                         TextoEnriquecido::aHtml(), que solo deja pasar <strong>,
                         <em> y un <span> de color de una paleta cerrada. Lo que
                         escribió el usuario nunca llega crudo hasta acá.
                         Los saltos de línea siguen resolviéndose por CSS
                         (white-space: pre-line), no con <br>. --}}
                    <td class="detalles">{!! $it['detalles'] !!}</td>
                    <td class="precio">{{ $moneda_signo }} {{ number_format($it['costo_unitario'], 2) }}</td>
                    <td class="total">{{ $moneda_signo }} {{ number_format($it['costo_total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center; padding: 10px; color: #999;">
                        (La precotización no tiene ítems)
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ══════════ TOTALES ══════════
         El tratamiento de IGV lo gobierna $proposal->aplica_igv:
           true  → Subtotal + IGV (18%) + TOTAL FACTURADO  (= subtotal + IGV)
           false → Subtotal + TOTAL (Sin IGV)              (= subtotal)
         Los montos vienen del prequote (subtotal/igv/total siempre completos);
         aplica_igv solo decide qué se muestra.

         Agosto 2026 (punto 13) — Todo el bloque se puede ocultar con
         `incluye_totales`, para las cotizaciones por escala donde sumar las tres
         alternativas no significa nada.

         El `=== null` no es redundante: durante un despliegue las migraciones de
         tenant corren DESPUÉS de que el código nuevo ya está en disco, así que hay
         unos segundos en los que la columna todavía no existe y Eloquent devuelve
         null. Sin esa guarda, en esa ventana los totales desaparecerían de los
         PDF. Null se trata como "mostrar", que es el default de la columna. --}}
    @if($proposal->incluye_totales === null || $proposal->incluye_totales)
    <table class="totales" cellpadding="0" cellspacing="0">
        @if($proposal->aplica_igv)
            <tr>
                <td style="width: 75%;"></td>
                <td class="label">SUB TOTAL</td>
                <td class="value">{{ $moneda_signo }} {{ number_format($subtotal, 2) }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="label">IGV (18%)</td>
                <td class="value">{{ $moneda_signo }} {{ number_format($igv, 2) }}</td>
            </tr>
            <tr class="row-total">
                <td></td>
                <td class="label">TOTAL FACTURADO</td>
                <td class="value">{{ $moneda_signo }} {{ number_format($total, 2) }}</td>
            </tr>
        @else
            <tr>
                <td style="width: 75%;"></td>
                <td class="label">SUB TOTAL</td>
                <td class="value">{{ $moneda_signo }} {{ number_format($subtotal, 2) }}</td>
            </tr>
            <tr class="row-total">
                <td></td>
                <td class="label">TOTAL (Sin IGV)</td>
                <td class="value">{{ $moneda_signo }} {{ number_format($subtotal, 2) }}</td>
            </tr>
        @endif
    </table>
    @endif

    {{-- ══════════ TÉRMINOS Y CONDICIONES GENERALES ══════════ --}}
    {{-- Las 11 secciones se pre-renderizan en ProposalPdfGenerator como
         markdown-lite → HTML seguro (viñetas, párrafos "> ", negrita **). Aquí
         solo se pintan las filas no vacías, en su orden del PDF. --}}
    @if(!empty($condiciones_rows))
        <h3 class="section-title">Términos y Condiciones Generales</h3>

        <table class="condiciones" cellpadding="0" cellspacing="0">
            @foreach($condiciones_rows as $row)
                <tr>
                    <td class="label">{{ $row['label'] }}</td>
                    <td>{!! $row['html'] !!}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- ══════════ SOSTENIBILIDAD (bloque verde + sello) ══════════
         Mensaje fijo de marca de la plantilla maestra (Shipibo-Conibo /
         10.000 árboles / UNESCO). En el PDF fuente aplica a CF/E/P; aquí se
         muestra siempre (los quick wins no incluyen el motor de segmentos).
         Editable/ocultable por config (empresa_sost_parrafo1/2). --}}
    @if($proposal->incluye_sostenibilidad && (!empty($sost_p1) || !empty($sost_p2)))
        <div class="sostenibilidad">
            <table style="width:100%;" cellpadding="0" cellspacing="0">
                <tr>
                    @if(!empty($sello_eco))
                        <td class="sost-sello">
                            <img src="{{ $sello_eco }}" style="height:66px;" alt="Ecológico Sostenible">
                        </td>
                    @endif
                    <td class="sost-texto">
                        @if(!empty($sost_p1))
                            <p>{{ $sost_p1 }}</p>
                        @endif
                        @if(!empty($sost_p2))
                            <p class="last">{{ $sost_p2 }}</p>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endif

    {{-- ══════════ BLOQUE OPCIONAL: DATOS DE LA EMPRESA ══════════ --}}
    @if($proposal->incluye_datos_empresa)
        <h3 class="section-title">DATOS DE LA EMPRESA</h3>
        <table class="bloque-info" cellpadding="0" cellspacing="0">
            <tr>
                <td class="label">Razón Social</td>
                <td>{{ $empresa['razon_social'] }}</td>
            </tr>
            <tr>
                <td class="label">RUC</td>
                <td>{{ $empresa['ruc'] }}</td>
            </tr>
            <tr>
                <td class="label">Dirección</td>
                <td>{{ $empresa['direccion'] }}</td>
            </tr>
            <tr>
                <td class="label">Teléfono</td>
                <td>{{ $empresa['telefono'] }}</td>
            </tr>
        </table>
    @endif

    {{-- ══════════ BLOQUE OPCIONAL: CUENTA BANCARIA ══════════ --}}
    @if($proposal->incluye_datos_bancarios && $banco)
        <h3 class="section-title">NÚMERO DE CUENTA — {{ $banco['entidad'] }}</h3>
        <table class="bloque-info" cellpadding="0" cellspacing="0">
            <tr>
                <td class="label">Entidad Bancaria</td>
                <td>{{ $banco['entidad'] }}</td>
            </tr>
            <tr>
                <td class="label">{{ $banco['tipo'] }}</td>
                <td><strong>{{ $banco['numero'] }}</strong></td>
            </tr>
            @if($banco['cci'])
            <tr>
                <td class="label">Código Cuenta Interbancario (CCI)</td>
                <td><strong>{{ $banco['cci'] }}</strong></td>
            </tr>
            @endif
            <tr>
                <td class="label">Titular</td>
                <td>{{ $banco['titular'] }}</td>
            </tr>
        </table>
    @endif

    {{-- ══════════ FOOTER / DESPEDIDA / FIRMA ══════════ --}}
    <div class="footer-content">
        <p>
            Esperando que esta propuesta sea de su agrado, quedo a la espera de sus apreciables comentarios.
            @if(!empty($empresa['catalogo_url']))
                Aprovecho la oportunidad a invitarle a visitar nuestro catálogo online
                <a href="{{ $empresa['catalogo_url'] }}" style="color:#ED6A0A;">{{ $empresa['catalogo_url'] }}</a>
            @endif
            @if(!empty($empresa['facebook']))
                o nuestra página en FACEBOOK como <strong>{{ $empresa['facebook'] }}</strong>.
            @endif
        </p>

        <div class="firma-block">
            <div>Saludos cordiales,</div>
            {{-- R6 — si la cotización no tiene ejecutiva ($firma == null) no se
                 imprime ninguna firma. Los apellidos van en naranja; las líneas
                 vacías (cargo/cel/email) se omiten. --}}
            @if($firma)
            <div style="margin-top: 12px;">
                <div class="nombre">{{ $firma['nombres'] }} <span class="firma-apellidos">{{ $firma['apellidos'] }}</span></div>
                @if(!empty($firma['cargo']))
                    <div class="cargo">{{ $firma['cargo'] }}</div>
                @endif
                @if(!empty($firma['tel']))
                    <div>Tel: {{ $firma['tel'] }}</div>
                @endif
                @if(!empty($firma['celular']))
                    <div>Cel: {{ $firma['celular'] }}</div>
                @endif
                @if(!empty($firma['email']))
                    <div>Email: {{ $firma['email'] }}</div>
                @endif
            </div>
            @endif
        </div>
    </div>

</body>
</html>
