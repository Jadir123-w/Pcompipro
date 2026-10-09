<template>
    <div class="supplier-fields-section">
        <!-- Fila 1: Rubro / Tipo / Calificación -->
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Rubro / sector</label>
                    <el-input v-model="value.rubro_sector" maxlength="225" />
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label class="control-label">Tipo de proveedor</label>
                    <el-select v-model="value.tipo_provee" clearable placeholder="Seleccione" style="width:100%">
                        <el-option value="nuevo" label="Nuevo" />
                        <el-option value="activo" label="Activo" />
                        <el-option value="frecuente" label="Frecuente" />
                        <el-option value="bloqueado" label="Bloqueado" />
                    </el-select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label class="control-label">Calificación</label>
                    <el-select v-model="value.calificacion" clearable placeholder="Seleccione" style="width:100%">
                        <el-option value="nacional" label="Nacional" />
                        <el-option value="internacional" label="Internacional" />
                    </el-select>
                </div>
            </div>
        </div>

        <!-- Fila 2: Categoría / Subcategoría -->
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Categoría</label>
                    <el-select v-model="value.supplier_category_id"
                               filterable clearable
                               placeholder="Seleccione o escriba para buscar"
                               style="width:100%"
                               @change="onCategoryChange">
                        <el-option
                            v-for="c in categoriesActive"
                            :key="c.id"
                            :label="c.nombre"
                            :value="c.id" />
                    </el-select>
                    <small>
                        <a href="javascript:void(0)" @click="promptNewCategory">+ Agregar categoría nueva</a>
                    </small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">Subcategoría</label>
                    <el-select v-model="value.supplier_subcategory_id"
                               filterable clearable
                               :disabled="!value.supplier_category_id"
                               placeholder="Seleccione subcategoría"
                               style="width:100%">
                        <el-option
                            v-for="s in subcategoriesFiltered"
                            :key="s.id"
                            :label="s.nombre"
                            :value="s.id" />
                    </el-select>
                    <small v-if="value.supplier_category_id">
                        <a href="javascript:void(0)" @click="promptNewSubcategory">+ Agregar subcategoría nueva</a>
                    </small>
                </div>
            </div>
        </div>

        <!-- Fila 3: Condición de pago / Descuentos / Aniversario / Origen -->
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label class="control-label">Condición de pago</label>
                    <el-select v-model="value.condicion_pago" filterable allow-create
                               placeholder="Seleccione o escriba"
                               style="width:100%">
                        <el-option value="Contra entrega" label="Contra entrega" />
                        <el-option value="50% adelanto - 50% entrega" label="50% adelanto - 50% entrega" />
                        <el-option value="70% adelanto - 30% entrega" label="70% adelanto - 30% entrega" />
                        <el-option value="100% adelantado" label="100% adelantado" />
                        <el-option value="Crédito 15 días" label="Crédito 15 días" />
                        <el-option value="Crédito 30 días" label="Crédito 30 días" />
                        <el-option value="Otro" label="Otro" />
                    </el-select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label class="control-label">Descuentos</label>
                    <el-input v-model="value.descuentos" maxlength="255" placeholder="Ej: 5% por volumen" />
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label class="control-label">Aniversario</label>
                    <el-date-picker v-model="value.aniversario"
                        type="date" format="dd/MM/yyyy" value-format="yyyy-MM-dd"
                        placeholder="dd/mm/aaaa" style="width:100%" />
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label class="control-label">Origen</label>
                    <el-select v-model="value.origen" clearable placeholder="Seleccione" style="width:100%">
                        <el-option value="FIDELIZADO" label="FIDELIZADO" />
                        <el-option value="REFERIDO" label="REFERIDO" />
                        <el-option value="OP. COMERCIAL" label="OP. COMERCIAL" />
                        <el-option value="MKTG - RRSS" label="MKTG - RRSS" />
                        <el-option value="MKTG - MAIL" label="MKTG - MAIL" />
                        <el-option value="OTROS" label="OTROS" />
                    </el-select>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * Sección de campos del módulo Proveedores.
 * Se monta en la pestaña "Datos del proveedor" del form.vue actual.
 * Usa v-model (prop `value`) para sincronizar con el form padre.
 *
 * NOTA: Usa layout Bootstrap-style (row/col-md-X + form-group) y NO <el-form-item>
 * porque el form.vue contenedor usa <form> HTML nativo (no <el-form>).
 */
export default {
    name: 'SupplierFieldsSection',
    props: {
        value: { type: Object, required: true }
    },
    data() {
        return {
            categories: []
        };
    },
    computed: {
        categoriesActive() {
            return this.categories || [];
        },
        subcategoriesFiltered() {
            if (!this.value.supplier_category_id) return [];
            const cat = this.categories.find(c => c.id === this.value.supplier_category_id);
            return cat ? (cat.subcategorias || []) : [];
        }
    },
    mounted() {
        this.loadCategories();
    },
    methods: {
        async loadCategories() {
            try {
                const r = await this.$http.get('/persons/suppliers/categories');
                this.categories = r.data.data || [];
            } catch (e) {
                console.error('Error cargando categorías:', e);
            }
        },
        onCategoryChange() {
            this.value.supplier_subcategory_id = null;
        },
        async promptNewCategory() {
            try {
                const { value: nombre } = await this.$prompt('Nombre de la nueva categoría:', 'Nueva categoría', {
                    confirmButtonText: 'Crear',
                    cancelButtonText: 'Cancelar',
                    inputPattern: /.{2,}/,
                    inputErrorMessage: 'Mínimo 2 caracteres'
                });
                const r = await this.$http.post('/persons/suppliers/categories', { nombre });
                if (r.data.success) {
                    this.$message.success(r.data.message);
                    await this.loadCategories();
                    this.value.supplier_category_id = r.data.id;
                    this.value.supplier_subcategory_id = null;
                }
            } catch (e) {
                if (e !== 'cancel') this.$message.error('No se pudo crear la categoría');
            }
        },
        async promptNewSubcategory() {
            if (!this.value.supplier_category_id) return;
            try {
                const { value: nombre } = await this.$prompt('Nombre de la nueva subcategoría:', 'Nueva subcategoría', {
                    confirmButtonText: 'Crear',
                    cancelButtonText: 'Cancelar',
                    inputPattern: /.{2,}/,
                    inputErrorMessage: 'Mínimo 2 caracteres'
                });
                const r = await this.$http.post('/persons/suppliers/subcategories', {
                    supplier_category_id: this.value.supplier_category_id,
                    nombre
                });
                if (r.data.success) {
                    this.$message.success(r.data.message);
                    await this.loadCategories();
                    this.value.supplier_subcategory_id = r.data.id;
                }
            } catch (e) {
                if (e !== 'cancel') this.$message.error('No se pudo crear la subcategoría');
            }
        }
    }
}
</script>

<style scoped>
.supplier-fields-section { margin-top: 8px; }
.supplier-fields-section small a { color: #409eff; font-size: 11px; }
.supplier-fields-section small a:hover { text-decoration: underline; }
</style>
