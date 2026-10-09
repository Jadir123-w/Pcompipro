<template>
    <div v-loading="loading" :element-loading-text="loadingText" element-loading-spinner="el-icon-loading"
        element-loading-background="rgba(255, 255, 255, 0.8)">
        <form :action="currentAction" method="POST" ref="form" @submit.prevent="handleSubmit">
            <slot></slot>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <el-date-picker v-model="form.fecha_inicio" type="date" placeholder="Fecha Inicio"
                            format="dd/MM/yyyy" value-format="yyyy-MM-dd" :clearable="false" class="w-100">
                        </el-date-picker>
                        <input type="hidden" name="fecha_inicio" :value="form.fecha_inicio">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin</label>
                        <el-date-picker v-model="form.fecha_fin" type="date" placeholder="Fecha Fin" format="dd/MM/yyyy"
                            value-format="yyyy-MM-dd" :clearable="false" class="w-100">
                        </el-date-picker>
                        <input type="hidden" name="fecha_fin" :value="form.fecha_fin">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="tipo_reporte">Tipo de Reporte</label>
                        <select name="tipo_reporte" id="tipo_reporte" class="form-control" v-model="form.tipo_reporte"
                            required>
                            <option value="detallado">Reporte Detallado</option>
                            <option value="resumido">Reporte Resumido</option>
                            <option value="afp">Reporte AFP</option>
                            <option value="essalud">Reporte ESSALUD</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary" @click="setGenerateAction">
                        <i class="fas fa-search"></i> Generar Reporte
                    </button>
                    <button type="button" class="btn btn-success" @click="submitExport">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                    <a :href="urlClean" class="btn btn-default ml-2" v-if="showClean">
                        Limpiar / Volver
                    </a>
                </div>
            </div>
        </form>
    </div>
</template>

<script>
export default {
    props: {
        configuration: {
            type: Object,
            required: true,
        },
        urlGenerate: {
            type: String,
            required: true,
        },
        urlExport: {
            type: String,
            required: true,
        },
        urlClean: {
            type: String,
            required: true,
        },
        showClean: {
            type: Boolean,
            default: false,
        }
    },
    data() {
        return {
            loading: true,
            loadingText: 'Cargando...',
            form: {
                fecha_inicio: this.configuration.fecha_inicio,
                fecha_fin: this.configuration.fecha_fin,
                tipo_reporte: this.configuration.tipo_reporte,
            },
            currentAction: this.urlGenerate
        }
    },
    mounted() {
        this.$nextTick(() => {
            this.loading = false;
        });
    },
    methods: {
        setGenerateAction() {
            this.currentAction = this.urlGenerate;
        },
        handleSubmit() {
            if (!this.form.fecha_inicio || !this.form.fecha_fin || !this.form.tipo_reporte) {
                this.$message.error('Por favor complete todos los campos');
                return;
            }
            this.loadingText = 'Generando reporte...';
            this.loading = true;
            this.$refs.form.submit();
        },
        submitExport() {
            if (!this.form.fecha_inicio || !this.form.fecha_fin || !this.form.tipo_reporte) {
                this.$message.error('Por favor complete todos los campos');
                return;
            }
            this.currentAction = this.urlExport;
            this.loadingText = 'Exportando excel...';
            this.loading = true;

            this.$nextTick(() => {
                this.$refs.form.submit();
                setTimeout(() => {
                    this.loading = false;
                    this.currentAction = this.urlGenerate;
                }, 2000);
            });
        }
    }
}
</script>
