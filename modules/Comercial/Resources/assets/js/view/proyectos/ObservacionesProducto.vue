<template>
  <div>
    <!-- Caso normal: el campo trae la plantilla, así que se muestra con los
         títulos en negrita de verdad y un espacio para escribir bajo cada uno. -->
    <div v-if="secciones.length > 0" class="obs-bloque">
      <textarea v-if="prefijo !== null"
                ref="prefijo"
                v-model="prefijo"
                class="obs-respuesta obs-respuesta--suelta"
                rows="1"
                placeholder="Observaciones generales"
                @input="alEscribir($event)"></textarea>

      <div v-for="(sec, i) in secciones" :key="i" class="obs-seccion">
        <div class="obs-titulo">{{ sec.titulo }}</div>
        <textarea v-model="sec.texto"
                  class="obs-respuesta"
                  rows="1"
                  placeholder="Escribe aquí…"
                  @input="alEscribir($event)"></textarea>
      </div>
    </div>

    <!-- Caso de respaldo: el producto heredó texto libre de la pre-cotización y
         no tiene títulos. Se deja tal cual estaba para no tocar esa información. -->
    <el-input v-else
              :value="value"
              type="textarea"
              :autosize="{minRows: 8, maxRows: 20}"
              placeholder="Preocupaciones del cliente, requisitos de calidad, acuerdos especiales y fechas críticas."
              @input="$emit('input', $event)"></el-input>
  </div>
</template>

<script>
/**
 * Agosto 2026 (punto 19) · Observaciones / Preocupaciones del Cliente
 * del producto del Proyecto.
 *
 * El requerimiento original pide dos cosas a la vez:
 *
 *   "Que cargue el texto de la misma forma que se visualiza en la imagen
 *    (respetando separaciones y texto negrita) cabe resaltar que el texto que
 *    escriba el usuario no tendrá las características de la plantilla, es decir
 *    no será texto negrita."
 *
 * O sea: los títulos se tienen que VER en negrita, y lo que escriba el usuario
 * no puede heredarla. La primera entrega usaba marcadores (`**título:**`), que
 * cumplían la segunda mitad pero dejaban los asteriscos a la vista.
 *
 * ── Por qué títulos fijos y campos aparte, y no un editor de texto enriquecido ──
 *
 * Con un editor visual, la negrita es un estado del cursor: al escribir justo
 * después de un título el navegador continúa en negrita, y hay que ir
 * corrigiéndolo a mano. Acá el título ni siquiera es editable —es un rótulo— y
 * lo que el usuario escribe vive en su propio campo. Que su texto salga en
 * negrita es directamente imposible, no algo que haya que evitar.
 *
 * De paso se evita el otro problema de los editores visuales: que entre HTML
 * pegado desde Word y termine guardado en la base.
 *
 * ── De dónde salen los títulos ──
 *
 * NO están escritos acá. Se deducen del propio texto guardado: cualquier línea
 * que sea enteramente `**algo**` es un título, y lo que sigue hasta el próximo
 * título es su contenido. Así, el día que el cliente pida cambiar un título o
 * agregar otro, se toca únicamente la plantilla del backend
 * (ProyectosController::PLANTILLA_OBSERVACIONES_PRODUCTO) y esta pantalla lo
 * refleja sola.
 *
 * Lo que se guarda sigue siendo el mismo texto plano con marcadores de siempre,
 * así que nada de lo que ya existe deja de funcionar.
 */

/** Una línea que es enteramente `**texto**` es un título de la plantilla. */
const RE_TITULO = /^\s*\*\*(.+?)\*\*\s*$/

export default {
  name: 'ObservacionesProducto',

  props: {
    value: { type: String, default: '' },
  },

  data() {
    return {
      // Texto que aparece ANTES del primer título. Es null cuando no hay nada
      // antes: en ese caso no se dibuja el campo suelto, para no agregar un
      // recuadro vacío arriba de todo.
      prefijo: null,
      secciones: [],
      // Último texto que emitimos. Sirve para distinguir un cambio que viene de
      // afuera —hay que volver a leerlo— de uno que produjo esta misma pantalla,
      // que rearmaría la lista mientras el usuario escribe y le movería el cursor.
      ultimoEmitido: null,
    }
  },

  watch: {
    value: {
      immediate: true,
      handler(nuevo) {
        if (nuevo === this.ultimoEmitido) return
        this.leer(nuevo)
      },
    },
  },

  mounted() {
    var self = this
    this.$nextTick(function () {
      // Una vuelta más antes de medir. En $nextTick el DOM ya está armado, pero
      // el navegador todavía no terminó de calcular el alto de los campos y la
      // medición sale enorme: se vieron ocho recuadros de 236px en vez de 22px.
      window.requestAnimationFrame(function () { self.ajustarTodos() })
    })
  },

  updated() {
    this.ajustarTodos()
  },

  methods: {
    /**
     * Parte el texto guardado en título + contenido.
     *
     * @param {string} texto
     */
    leer(texto) {
      var lineas = (texto || '').split('\n')
      var previas = []
      var secciones = []

      for (var i = 0; i < lineas.length; i++) {
        var m = lineas[i].match(RE_TITULO)
        if (m) {
          secciones.push({ titulo: m[1], lineas: [] })
        } else if (secciones.length > 0) {
          secciones[secciones.length - 1].lineas.push(lineas[i])
        } else {
          previas.push(lineas[i])
        }
      }

      this.secciones = secciones.map(function (s) {
        return { titulo: s.titulo, texto: s.lineas.join('\n').replace(/^\n+|\n+$/g, '') }
      })

      var antes = previas.join('\n').replace(/^\n+|\n+$/g, '')
      this.prefijo = (secciones.length > 0 && antes !== '') ? antes : null
    },

    /**
     * Rearma el texto plano con marcadores y lo devuelve al formulario.
     *
     * El formato es el mismo que guarda hoy el backend, así que lo que ya está
     * en la base se sigue leyendo igual.
     *
     * @return {string}
     */
    escribir() {
      var partes = []
      if (this.prefijo !== null && this.prefijo !== '') partes.push(this.prefijo)

      for (var i = 0; i < this.secciones.length; i++) {
        var s = this.secciones[i]
        partes.push('**' + s.titulo + '**')
        if (s.texto !== '') partes.push(s.texto)
      }

      return partes.join('\n') + '\n'
    },

    alEscribir(ev) {
      this.ajustar(ev.target)
      var texto = this.escribir()
      this.ultimoEmitido = texto
      this.$emit('input', texto)
    },

    /** Hace crecer el campo con el texto, para que no aparezca una barra interna. */
    ajustar(ta) {
      if (!ta) return
      ta.style.height = 'auto'
      ta.style.height = ta.scrollHeight + 'px'
    },

    ajustarTodos() {
      var campos = this.$el ? this.$el.querySelectorAll('textarea') : []
      for (var i = 0; i < campos.length; i++) this.ajustar(campos[i])
    },
  },
}
</script>

<style scoped>
/* El conjunto se ve como un solo recuadro, igual que el campo que reemplaza y
   que la imagen del documento del cliente. Los campos de adentro no llevan
   borde propio: si lo tuvieran, se leería como un formulario de ocho casillas
   en vez de como un texto continuo. */
.obs-bloque {
  border: 1px solid #DCDFE6;
  border-radius: 4px;
  background: #fff;
  padding: 10px 12px;
}
.obs-seccion + .obs-seccion {
  margin-top: 10px;
}
.obs-titulo {
  font-weight: 700;
  color: #303133;
  font-size: 13px;
  line-height: 1.5;
}
.obs-respuesta {
  display: block;
  width: 100%;
  box-sizing: border-box;
  border: 0;
  padding: 2px 0 0;
  margin: 0;
  resize: none;
  overflow: hidden;
  background: transparent;
  color: #606266;
  font-family: inherit;
  font-size: 13px;
  line-height: 1.5;
}
.obs-respuesta:focus {
  outline: 0;
  /* Una línea al pie marca dónde se está escribiendo sin encerrar el campo. */
  box-shadow: 0 1px 0 0 #409EFF;
}
.obs-respuesta::placeholder {
  color: #C0C4CC;
}
.obs-respuesta--suelta {
  margin-bottom: 10px;
}
</style>
