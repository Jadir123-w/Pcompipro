<template>
    <div class="supplier-contacts-list">
        <div class="list-header">
            <span class="title">Contactos del proveedor</span>
            <el-button type="primary" size="small" icon="el-icon-plus"
                       :disabled="!supplierId"
                       @click="openNew">
                Nuevo Contacto
            </el-button>
        </div>
        <div v-if="!supplierId" class="hint-info">
            Guarda primero el proveedor para poder registrar contactos.
        </div>

        <el-table v-else :data="contacts" size="small" border v-loading="loading" empty-text="Sin contactos">
            <el-table-column label="Nombres" min-width="160">
                <template slot-scope="scope">
                    {{ scope.row.nombre_completo || (scope.row.nombres + ' ' + (scope.row.apellidos || '')) }}
                </template>
            </el-table-column>
            <el-table-column prop="cargo" label="Cargo" min-width="130" />
            <el-table-column label="Teléfono principal" min-width="130">
                <template slot-scope="scope">
                    <span v-if="principalPhone(scope.row)">
                        {{ principalPhone(scope.row).telefono }}
                        <el-tag size="mini" type="info" style="margin-left:4px">
                            {{ principalPhone(scope.row).tipo }}
                        </el-tag>
                    </span>
                    <span v-else style="color:#909399">-</span>
                </template>
            </el-table-column>
            <el-table-column label="Email principal" min-width="180">
                <template slot-scope="scope">
                    <span v-if="principalEmail(scope.row)">
                        {{ principalEmail(scope.row).email }}
                    </span>
                    <span v-else style="color:#909399">-</span>
                </template>
            </el-table-column>
            <el-table-column label="Estado" width="90">
                <template slot-scope="scope">
                    <el-tag size="mini" :type="scope.row.estado ? 'success' : 'danger'">
                        {{ scope.row.estado ? 'Activo' : 'Inactivo' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column label="Acciones" width="140" align="center">
                <template slot-scope="scope">
                    <el-button size="mini" @click="openEdit(scope.row)" icon="el-icon-edit">Editar</el-button>
                    <el-button size="mini" type="danger" @click="confirmDelete(scope.row)" icon="el-icon-delete" />
                </template>
            </el-table-column>
        </el-table>

        <supplier-contact-form
            :visible.sync="formVisible"
            :supplier-id="supplierId"
            :contact-id="editingId"
            @saved="onSaved" />
    </div>
</template>

<script>
import SupplierContactForm from './SupplierContactForm.vue';

/**
 * Listado de contactos de un proveedor, con CRUD.
 * Se usa dentro de form.vue (tab "Contactos") cuando type='suppliers'.
 */
export default {
    name: 'SupplierContactsList',
    components: { SupplierContactForm },
    props: {
        supplierId: { type: [Number, String], default: null }
    },
    data() {
        return {
            contacts: [],
            loading: false,
            formVisible: false,
            editingId: null
        };
    },
    watch: {
        supplierId: {
            immediate: true,
            handler(v) {
                if (v) this.load();
                else this.contacts = [];
            }
        }
    },
    methods: {
        async load() {
            if (!this.supplierId) return;
            this.loading = true;
            try {
                const r = await this.$http.get(`/persons/suppliers/${this.supplierId}/contacts`);
                this.contacts = r.data.data || [];
            } catch (e) {
                this.$message.error('Error cargando contactos');
            } finally {
                this.loading = false;
            }
        },
        openNew() {
            this.editingId = null;
            this.formVisible = true;
        },
        openEdit(row) {
            this.editingId = row.id;
            this.formVisible = true;
        },
        async confirmDelete(row) {
            try {
                await this.$confirm(`¿Eliminar el contacto "${row.nombres}"?`, 'Confirmar', {
                    type: 'warning',
                    confirmButtonText: 'Eliminar',
                    cancelButtonText: 'Cancelar'
                });
                await this.$http.delete(`/persons/suppliers/${this.supplierId}/contacts/${row.id}`);
                this.$message.success('Contacto eliminado');
                this.load();
            } catch (e) {
                if (e !== 'cancel') this.$message.error('No se pudo eliminar');
            }
        },
        onSaved() {
            this.load();
        },
        principalPhone(c) {
            if (!c.telefonos || !c.telefonos.length) return null;
            return c.telefonos.find(t => t.es_principal) || c.telefonos[0];
        },
        principalEmail(c) {
            if (!c.emails || !c.emails.length) return null;
            return c.emails.find(e => e.es_principal) || c.emails[0];
        }
    }
}
</script>

<style scoped>
.supplier-contacts-list { padding: 5px 0; }
.list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.list-header .title {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
}
.hint-info {
    padding: 12px;
    background: #fdf6ec;
    color: #e6a23c;
    border-radius: 4px;
    font-size: 13px;
}
</style>
