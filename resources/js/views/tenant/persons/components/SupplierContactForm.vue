<template>
    <el-dialog
        :title="isEdit ? 'Editar Contacto' : 'Nuevo Contacto'"
        :visible.sync="dialogVisible"
        :close-on-click-modal="false"
        width="780px"
        append-to-body
        @close="handleClose">
        <el-form :model="form" label-position="top" size="small" ref="formRef">
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item label="Nombres *">
                        <el-input v-model="form.nombres" maxlength="191" />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item label="Apellidos">
                        <el-input v-model="form.apellidos" maxlength="191" />
                    </el-form-item>
                </el-col>
            </el-row>
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item label="Cargo">
                        <el-input v-model="form.cargo" maxlength="120" />
                    </el-form-item>
                </el-col>
                <el-col :span="6">
                    <el-form-item label="Cumpleaños">
                        <el-date-picker
                            v-model="form.cumpleanios"
                            type="date"
                            format="dd/MM/yyyy"
                            value-format="yyyy-MM-dd"
                            placeholder="dd/mm/aaaa"
                            style="width:100%" />
                    </el-form-item>
                </el-col>
                <el-col :span="6">
                    <el-form-item label="Estado">
                        <el-select v-model="form.estado" style="width:100%">
                            <el-option :value="1" label="Activo" />
                            <el-option :value="0" label="Inactivo" />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>

            <!-- Teléfonos -->
            <div class="section-title">Teléfonos</div>
            <div v-for="(tel, i) in form.telefonos" :key="'tel-'+i" class="dyn-row">
                <el-select v-model="tel.tipo" size="small" style="width:110px">
                    <el-option value="trabajo" label="Trabajo" />
                    <el-option value="personal" label="Personal" />
                </el-select>
                <el-input v-model="tel.telefono" size="small" placeholder="Teléfono" style="flex:1; margin:0 8px" />
                <el-checkbox v-model="tel.es_principal" @change="setPrincipalTel(i)">Principal</el-checkbox>
                <el-button type="danger" size="mini" icon="el-icon-delete" @click="removeTel(i)" circle />
            </div>
            <el-button size="mini" @click="addTel" icon="el-icon-plus">Agregar teléfono</el-button>

            <!-- Emails -->
            <div class="section-title" style="margin-top:14px">Emails</div>
            <div v-for="(em, i) in form.emails" :key="'em-'+i" class="dyn-row">
                <el-select v-model="em.tipo" size="small" style="width:110px">
                    <el-option value="trabajo" label="Trabajo" />
                    <el-option value="personal" label="Personal" />
                </el-select>
                <el-input v-model="em.email" size="small" placeholder="correo@dominio.com" style="flex:1; margin:0 8px" />
                <el-checkbox v-model="em.es_principal" @change="setPrincipalEm(i)">Principal</el-checkbox>
                <el-button type="danger" size="mini" icon="el-icon-delete" @click="removeEm(i)" circle />
            </div>
            <el-button size="mini" @click="addEm" icon="el-icon-plus">Agregar email</el-button>
        </el-form>

        <span slot="footer">
            <el-button size="small" @click="dialogVisible = false">Cancelar</el-button>
            <el-button type="primary" size="small" :loading="submitting" @click="submit">Guardar</el-button>
        </span>
    </el-dialog>
</template>

<script>
/**
 * Modal CRUD para contactos de proveedor.
 * Props:
 *  - visible: v-sync para mostrar/ocultar
 *  - supplierId: ID del proveedor (persons.id con type='suppliers')
 *  - contactId: ID del contacto a editar (null si crear)
 * Eventos:
 *  - 'saved': emitido con el contacto creado/actualizado
 */
export default {
    name: 'SupplierContactForm',
    props: {
        visible: { type: Boolean, default: false },
        supplierId: { type: [Number, String], default: null },
        contactId: { type: [Number, String], default: null }
    },
    data() {
        return {
            submitting: false,
            form: this.emptyForm()
        };
    },
    computed: {
        dialogVisible: {
            get() { return this.visible; },
            set(v) { this.$emit('update:visible', v); }
        },
        isEdit() { return !!this.contactId; }
    },
    watch: {
        visible(v) {
            if (v) this.initialize();
        }
    },
    methods: {
        emptyForm() {
            return {
                nombres: '',
                apellidos: '',
                cargo: '',
                cumpleanios: null,
                estado: 1,
                telefonos: [],
                emails: []
            };
        },
        async initialize() {
            this.form = this.emptyForm();
            if (this.isEdit && this.supplierId) {
                try {
                    const r = await this.$http.get(`/persons/suppliers/${this.supplierId}/contacts/${this.contactId}`);
                    const c = r.data.data;
                    this.form = {
                        nombres: c.nombres || '',
                        apellidos: c.apellidos || '',
                        cargo: c.cargo || '',
                        cumpleanios: c.cumpleanios || null,
                        estado: c.estado ?? 1,
                        telefonos: (c.phones || []).map(p => ({
                            id: p.id,
                            telefono: p.telefono,
                            tipo: p.tipo || 'trabajo',
                            es_principal: !!p.es_principal
                        })),
                        emails: (c.emails || []).map(e => ({
                            id: e.id,
                            email: e.email,
                            tipo: e.tipo || 'trabajo',
                            es_principal: !!e.es_principal
                        }))
                    };
                } catch (e) {
                    this.$message.error('No se pudo cargar el contacto');
                }
            }
        },
        addTel() { this.form.telefonos.push({ telefono: '', tipo: 'trabajo', es_principal: this.form.telefonos.length === 0 }); },
        removeTel(i) { this.form.telefonos.splice(i, 1); },
        setPrincipalTel(idx) {
            if (!this.form.telefonos[idx].es_principal) return;
            this.form.telefonos.forEach((t, i) => { if (i !== idx) t.es_principal = false; });
        },
        addEm() { this.form.emails.push({ email: '', tipo: 'trabajo', es_principal: this.form.emails.length === 0 }); },
        removeEm(i) { this.form.emails.splice(i, 1); },
        setPrincipalEm(idx) {
            if (!this.form.emails[idx].es_principal) return;
            this.form.emails.forEach((e, i) => { if (i !== idx) e.es_principal = false; });
        },
        async submit() {
            if (!this.form.nombres) {
                this.$message.warning('Los nombres son obligatorios');
                return;
            }
            const tels = this.form.telefonos.filter(t => t.telefono);
            const ems = this.form.emails.filter(e => e.email);
            if (tels.length === 0 && ems.length === 0) {
                this.$message.warning('Debe agregar al menos un teléfono o un email');
                return;
            }

            this.submitting = true;
            try {
                const payload = {
                    nombres: this.form.nombres,
                    apellidos: this.form.apellidos,
                    cargo: this.form.cargo,
                    cumpleanios: this.form.cumpleanios,
                    estado: this.form.estado,
                    telefonos: tels,
                    emails: ems
                };
                const url = `/persons/suppliers/${this.supplierId}/contacts${this.isEdit ? '/' + this.contactId : ''}`;
                const method = this.isEdit ? 'put' : 'post';
                const response = await this.$http[method](url, payload);

                if (response.data.success) {
                    this.$message.success(response.data.message);
                    this.$emit('saved', response.data.data);
                    this.dialogVisible = false;
                } else {
                    this.$message.error(response.data.message || 'Error al guardar');
                }
            } catch (e) {
                const msg = e.response && e.response.data && e.response.data.message
                    ? e.response.data.message : 'Error al guardar el contacto';
                this.$message.error(msg);
            } finally {
                this.submitting = false;
            }
        },
        handleClose() {
            this.form = this.emptyForm();
        }
    }
}
</script>

<style scoped>
.section-title {
    font-size: 13px;
    font-weight: 600;
    color: #303133;
    margin: 8px 0;
    padding-bottom: 4px;
    border-bottom: 1px solid #eee;
}
.dyn-row {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
}
</style>
