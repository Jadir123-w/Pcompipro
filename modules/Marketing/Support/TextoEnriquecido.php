<?php

namespace Modules\Marketing\Support;

/**
 * Texto enriquecido de la propuesta: marcadores → HTML seguro.
 *
 * Punto 11 de agosto 2026. El cliente lo acotó así en el documento de
 * priorización:
 *
 *   "Ideal un editor completo. Por ahora se solicitan opciones rápidas para
 *    aplicar negrita, cursiva y cambiar el color del texto —especialmente
 *    rojo—, pudiendo combinarse para resaltar información relevante."
 *
 * O sea: NO es un editor completo. Son tres opciones que se pueden combinar.
 *
 * ── Por qué marcadores y no HTML ──
 *
 * El texto se guarda en la base como TEXTO PLANO con marcadores, igual que las
 * Condiciones Generales (`QuotationCondiciones`), que Compipro ya usa. Se
 * descartó guardar HTML directamente por dos motivos:
 *
 *   · Seguridad: lo que se guarda termina impreso en el PDF. Con HTML en la
 *     base, cualquier etiqueta pegada desde Word entra al documento y puede
 *     romper el render de dompdf o alterar la maquetación.
 *   · Reversibilidad: un texto con marcadores sigue siendo legible y editable
 *     aunque mañana se cambie la forma de renderizarlo.
 *
 * ── Marcadores ──
 *
 *     **texto**            negrita
 *     _texto_              cursiva
 *     [rojo]texto[/rojo]   color (rojo | azul | verde)
 *
 * Se combinan: `**[rojo]urgente[/rojo]**` sale en negrita y rojo.
 *
 * ── Por qué una paleta cerrada y no un color libre ──
 *
 * Los colores se resuelven contra una lista fija. Si se aceptara un color
 * arbitrario, el valor entraría dentro de un atributo `style` y bastaría un
 * texto malicioso para inyectar CSS en el PDF. Con nombres, lo que no está en
 * la lista simplemente no se convierte y queda como texto literal.
 *
 * ── El orden importa ──
 *
 * Primero se ESCAPA todo el texto y recién después se aplican los marcadores.
 * Al revés, un `<script>` escrito por el usuario sobreviviría dentro de las
 * etiquetas que agregamos nosotros.
 */
class TextoEnriquecido
{
    /**
     * Colores admitidos. La clave es lo que escribe el usuario; el valor, el
     * color real. Tonos oscuros a propósito: el PDF se imprime y los claros no
     * se leen sobre blanco.
     */
    const COLORES = [
        'rojo'  => '#C00000',
        'azul'  => '#1F4E79',
        'verde' => '#2E7D32',
    ];

    /**
     * Negrita: exactamente dos asteriscos a cada lado, ni uno más.
     *
     * Los textos que COMPIPRO ya tenía cargados usan tiradas de asteriscos como
     * adorno, del estilo:
     *
     *     ***********Arte y diseño facilitado por el cliente***********
     *     *** Color: beige y verde ***
     *
     * Con un patrón suelto (`\*\*(.+?)\*\*`) esos adornos se leen como negritas
     * y el PDF sale roto: el primero daba
     * `<strong>*</strong><strong>*</strong>*Arte…` y el segundo ponía la línea
     * entera en negrita dejando un asterisco suelto al final. Exigiendo que el
     * marcador no esté pegado a más asteriscos, un adorno se imprime tal cual.
     *
     * SALVEDAD conocida y aceptada: una negrita pegada al adorno —sin ningún
     * carácter en medio, como `*****__**texto**__*****`— tampoco se convierte,
     * porque no hay forma de distinguirla del adorno mismo. Basta un espacio
     * entre el adorno y el texto en negrita para que funcione.
     *
     * El mismo criterio está aplicado en la barra de formato de la pantalla
     * (TabPreviewEditable.vue, `envolturaDe`), para que el botón no confunda los
     * dos últimos asteriscos de un adorno con una negrita ya puesta.
     */
    const RE_NEGRITA = '/(?<!\*)\*\*(?!\*)(.+?)(?<!\*)\*\*(?!\*)/s';

    /**
     * Convierte el texto con marcadores en HTML seguro para el PDF.
     *
     * Los saltos de línea se dejan tal cual: la celda del PDF los respeta por
     * CSS (`white-space: pre-line`), así que no hace falta convertirlos a <br>
     * y se evita ensuciar el HTML.
     *
     * @param  string|null $texto
     * @return string  '' si no hay nada que mostrar
     */
    public static function aHtml($texto)
    {
        $texto = (string) $texto;
        if (trim($texto) === '') {
            return '';
        }

        // 1. Escapar TODO primero. Desde acá, nada de lo que escribió el
        //    usuario puede convertirse en una etiqueta.
        $html = e($texto);

        // 2. Color. Va antes que negrita y cursiva para que el contenido de
        //    dentro del bloque pueda llevar además esos formatos.
        //    El nombre del color se valida contra la lista: si no está, el
        //    marcador queda como texto literal y no se genera ningún style.
        $nombres = implode('|', array_keys(self::COLORES));
        $html = preg_replace_callback(
            '/\[(' . $nombres . ')\](.+?)\[\/\1\]/s',
            function ($m) {
                $color = self::COLORES[$m[1]];
                return '<span style="color: ' . $color . ';">' . $m[2] . '</span>';
            },
            $html
        );

        // 3. Negrita. El patrón es perezoso (.+?) para que dos pares de ** en
        //    la misma línea no se coman el texto del medio.
        $html = preg_replace(self::RE_NEGRITA, '<strong>$1</strong>', $html);

        // 4. Cursiva. El guion bajo exige un límite de palabra a cada lado para
        //    no romper nombres como `costo_base` o `tiempo_estimado_dias`, que
        //    aparecen a menudo en las observaciones técnicas.
        $html = preg_replace('/(?<![\w])_([^_\n]+)_(?![\w])/u', '<em>$1</em>', $html);

        return $html;
    }

    /**
     * Quita los marcadores y devuelve el texto pelado.
     *
     * Sirve donde el formato no aplica —un asunto de correo, un Excel, un
     * campo de búsqueda— para que no se filtren los asteriscos.
     *
     * @param  string|null $texto
     * @return string
     */
    public static function aTextoPlano($texto)
    {
        $texto = (string) $texto;
        if (trim($texto) === '') {
            return '';
        }

        $nombres = implode('|', array_keys(self::COLORES));
        $plano = preg_replace('/\[(' . $nombres . ')\](.+?)\[\/\1\]/s', '$2', $texto);
        $plano = preg_replace(self::RE_NEGRITA, '$1', $plano);
        $plano = preg_replace('/(?<![\w])_([^_\n]+)_(?![\w])/u', '$1', $plano);

        return $plano;
    }
}
