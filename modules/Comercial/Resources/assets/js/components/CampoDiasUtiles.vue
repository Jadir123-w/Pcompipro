<template>
    <!-- Casilla de plazo: se escribe "8" o "10 a 12" y "días útiles" va fijo al costado. -->
    <span class="campo-dias-utiles" :class="{ 'is-invalido': invalido, 'is-disabled': disabled }"
          :title="invalido ? 'Escribe un número (8) o un rango (10 a 12)' : ''">
        <input type="text" :value="entrada" :placeholder="placeholder" :disabled="disabled"
               maxlength="12" @input="onInput($event.target.value)" @blur="onBlur">
        <span class="campo-dias-utiles__unidad">{{ unidad }}</span>
    </span>
</template>

<script>
import { valorDiasUtiles, normalizarDiasUtiles } from '../helpers/diasUtiles'

export default {
    props: {
        // Texto completo guardado ("10 a 12 días útiles").
        value: { type: String, default: '' },
        placeholder: { type: String, default: '10 a 12' },
        disabled: { type: Boolean, default: false },
    },
    data() {
        return { entrada: valorDiasUtiles(this.value) }
    },
    computed: {
        invalido() {
            return !normalizarDiasUtiles(this.entrada).ok
        },
        unidad() {
            return normalizarDiasUtiles(this.entrada).texto === '1 día útil' ? 'día útil' : 'días útiles'
        },
    },
    watch: {
        value(nuevo) {
            // Solo si cambió desde fuera (no mientras se escribe).
            if (normalizarDiasUtiles(this.entrada).texto !== nuevo) {
                this.entrada = valorDiasUtiles(nuevo)
            }
        },
    },
    methods: {
        onInput(valor) {
            this.entrada = valor
            // Válido → texto completo; inválido → se emite tal cual para que la pantalla lo bloquee al guardar.
            this.$emit('input', normalizarDiasUtiles(valor).texto)
        },
        onBlur() {
            const r = normalizarDiasUtiles(this.entrada)
            if (r.ok) this.entrada = valorDiasUtiles(r.texto)
        },
    },
}
</script>

<style scoped>
.campo-dias-utiles {
    display: inline-flex;
    align-items: stretch;
    height: 28px;
    max-width: 100%;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    overflow: hidden;
    background: #fff;
    vertical-align: middle;
}
.campo-dias-utiles:focus-within {
    border-color: #409eff;
}
.campo-dias-utiles.is-invalido {
    border-color: #f56c6c;
}
.campo-dias-utiles input {
    width: 58px;
    min-width: 0;
    padding: 0 6px;
    border: 0;
    outline: none;
    font-size: 12px;
    text-align: center;
    background: transparent;
}
.campo-dias-utiles__unidad {
    display: inline-flex;
    align-items: center;
    padding: 0 8px;
    font-size: 11px;
    color: #606266;
    background: #f5f7fa;
    border-left: 1px solid #dcdfe6;
    white-space: nowrap;
}
/* Variante que llena su celda (tablas): la casilla nunca se sale de la columna. */
.campo-dias-utiles--lleno {
    display: flex;
    width: 100%;
    box-sizing: border-box;
}
.campo-dias-utiles--lleno input {
    flex: 1 1 0;
    width: 0;
    min-width: 0;
}
.campo-dias-utiles.is-disabled {
    background: #f5f7fa;
    cursor: not-allowed;
}
.campo-dias-utiles.is-disabled input {
    color: #606266;
    cursor: not-allowed;
}
</style>
