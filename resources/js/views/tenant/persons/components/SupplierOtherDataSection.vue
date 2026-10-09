<template>
    <div class="supplier-other-section">
        <!-- Número de cuenta -->
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label">Número de cuenta (banco / CCI / titular)</label>
                    <el-input type="textarea" :rows="3" v-model="value.numero_cuenta"
                              placeholder="Ej: BCP Soles 191-1234567-0-00&#10;CCI: 00219100123456700000&#10;Titular: Razón Social del Proveedor" />
                </div>
            </div>
        </div>

        <!-- Perfil adicional -->
        <div class="section-subtitle">Perfil del proveedor</div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Resumen del perfil</label>
                    <el-input type="textarea" :rows="3" v-model="perfil.resumen_del_perfil"
                              @input="syncPerfil" maxlength="1000" />
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Capacidad de respuesta</label>
                    <el-input type="textarea" :rows="3" v-model="perfil.capacidad_de_respuesta"
                              @input="syncPerfil" maxlength="500" />
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Servicio</label>
                    <el-input v-model="perfil.servicio" @input="syncPerfil" maxlength="255" />
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Productos que maneja</label>
                    <el-select v-model="perfil.productos_que_maneja"
                               multiple filterable allow-create
                               placeholder="Escriba producto y Enter"
                               style="width:100%"
                               @change="syncPerfil">
                    </el-select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label">Comentarios adicionales</label>
                    <el-input type="textarea" :rows="2" v-model="perfil.comentarios_adicionales"
                              @input="syncPerfil" maxlength="500" />
                </div>
            </div>
        </div>

        <!-- Redes sociales -->
        <div class="section-subtitle">Redes sociales</div>
        <div v-for="(r, i) in redes" :key="'rs-'+i" class="dyn-row">
            <el-select v-model="r.tipo" size="small" style="width:130px" @change="syncRedes">
                <el-option value="facebook" label="Facebook" />
                <el-option value="instagram" label="Instagram" />
                <el-option value="linkedin" label="LinkedIn" />
                <el-option value="tiktok" label="TikTok" />
                <el-option value="whatsapp" label="WhatsApp" />
                <el-option value="otro" label="Otro" />
            </el-select>
            <el-input v-model="r.url" size="small" placeholder="https://..."
                      style="flex:1; margin:0 8px" @input="syncRedes" />
            <el-button type="danger" size="mini" icon="el-icon-delete"
                       @click="removeRed(i)" circle />
        </div>
        <el-button size="mini" icon="el-icon-plus" @click="addRed">Agregar red social</el-button>

        <!-- Catálogos -->
        <div class="section-subtitle" style="margin-top:14px">Catálogos</div>
        <div v-for="(c, i) in catalogosList" :key="'cat-'+i" class="dyn-row">
            <el-input v-model="c.nombre" size="small" placeholder="Nombre del catálogo"
                      style="width:250px; margin-right:8px" @input="syncCatalogos" />
            <el-input v-model="c.url" size="small" placeholder="URL (Drive, web...)"
                      style="flex:1; margin-right:8px" @input="syncCatalogos" />
            <el-button type="danger" size="mini" icon="el-icon-delete"
                       @click="removeCat(i)" circle />
        </div>
        <el-button size="mini" icon="el-icon-plus" @click="addCat">Agregar catálogo</el-button>

        <!-- Referencia (dirección) -->
        <div class="row" style="margin-top:14px">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label">Referencia (dirección)</label>
                    <el-input v-model="value.referencia" maxlength="255" placeholder="Referencia de la ubicación" />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * Sección "Otros Datos" del proveedor.
 * Usa layout Bootstrap-style (row/col-md-X) porque el form.vue contenedor usa <form> HTML nativo.
 */
export default {
    name: 'SupplierOtherDataSection',
    props: {
        value: { type: Object, required: true }
    },
    data() {
        return {
            perfil: this.emptyPerfil(),
            redes: [],
            catalogosList: []
        };
    },
    watch: {
        'value.perfil_adicional': {
            immediate: true,
            handler(val) { this.perfil = { ...this.emptyPerfil(), ...(val || {}) }; }
        },
        'value.redes_sociales': {
            immediate: true,
            handler(val) { this.redes = Array.isArray(val) ? JSON.parse(JSON.stringify(val)) : []; }
        },
        'value.catalogos': {
            immediate: true,
            handler(val) { this.catalogosList = Array.isArray(val) ? JSON.parse(JSON.stringify(val)) : []; }
        }
    },
    methods: {
        emptyPerfil() {
            return {
                resumen_del_perfil: '',
                capacidad_de_respuesta: '',
                servicio: '',
                comentarios_adicionales: '',
                productos_que_maneja: []
            };
        },
        syncPerfil() {
            this.$set(this.value, 'perfil_adicional', { ...this.perfil });
        },
        syncRedes() {
            this.$set(this.value, 'redes_sociales', JSON.parse(JSON.stringify(this.redes)));
        },
        syncCatalogos() {
            this.$set(this.value, 'catalogos', JSON.parse(JSON.stringify(this.catalogosList)));
        },
        addRed() {
            this.redes.push({ tipo: 'facebook', url: '' });
            this.syncRedes();
        },
        removeRed(i) {
            this.redes.splice(i, 1);
            this.syncRedes();
        },
        addCat() {
            this.catalogosList.push({ nombre: '', url: '' });
            this.syncCatalogos();
        },
        removeCat(i) {
            this.catalogosList.splice(i, 1);
            this.syncCatalogos();
        }
    }
}
</script>

<style scoped>
.supplier-other-section { padding: 5px 0; }
.section-subtitle {
    font-size: 13px;
    font-weight: 600;
    color: #303133;
    margin: 14px 0 8px;
    padding-bottom: 4px;
    border-bottom: 1px solid #eee;
}
.dyn-row {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
}
</style>
