<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/comercial/contactos/listado">
                    <svg xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24" height="24"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"
                         class="icon icon-tabler icons-tabler-outline icon-tabler-history">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M12 8l0 4l2 2"/>
                        <path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5a9 9 0 1 1 .5 -4"/>
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li>
                    <a href="/comercial/contactos/listado">Comercial</a>
                </li>
                <li class="active">
                    <span>Auditoría General</span>
                </li>
            </ol>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <el-select
                            v-model="filters.auditable_type"
                            placeholder="Filtrar por tipo"
                            clearable
                            @change="loadLogs"
                            style="width: 180px; margin-right: 10px;">
                            <el-option label="Todos" value=""></el-option>
                            <el-option label="Empresas" value="Modules\Comercial\Models\Empresa"></el-option>
                            <el-option label="Contactos" value="Modules\Comercial\Models\Contacto"></el-option>
                            <el-option label="Leads" value="Modules\Comercial\Models\Lead"></el-option>
                            <el-option label="Requerimientos" value="Modules\Comercial\Models\Requerimiento"></el-option>
                            <el-option label="Proyectos" value="Modules\Comercial\Models\Proyecto"></el-option>
                        </el-select>
                        <el-select
                            v-model="filters.event"
                            placeholder="Filtrar por evento"
                            clearable
                            @change="loadLogs"
                            style="width: 150px; margin-right: 10px;">
                            <el-option label="Todos" value=""></el-option>
                            <el-option label="Creado" value="created"></el-option>
                            <el-option label="Actualizado" value="updated"></el-option>
                            <el-option label="Eliminado" value="deleted"></el-option>
                            <el-option label="Restaurado" value="restored"></el-option>
                        </el-select>
                        <el-select
                            v-if="filters.auditable_type === 'Modules\\Comercial\\Models\\Contacto'"
                            v-model="filters.contacto_id"
                            placeholder="Filtrar por contacto"
                            clearable
                            filterable
                            @change="loadLogs"
                            style="width: 200px; margin-right: 10px;">
                            <el-option
                                v-for="contacto in contactos"
                                :key="contacto.id"
                                :label="`${contacto.nombres} ${contacto.apellidos}`"
                                :value="contacto.id">
                            </el-option>
                        </el-select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card tab-content-default row-new mb-0">
            <div class="card-body">
                <data-table :resource="resource" ref="datatable">
                    <tr slot="heading">
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Registro</th>
                        <th>Evento</th>
                        <th>Usuario</th>
                        <th>Cambios</th>
                        <th>Fecha</th>
                        <th>IP</th>
                        <th>Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }" :key="index">
                        <td>{{ row.id }}</td>
                        <td>
                            <el-tag size="small" type="info">{{ row.tipo_label || getTipoLabel(row.auditable_type) }}</el-tag>
                        </td>
                        <td>
                            <strong>#{{ row.auditable_id }}</strong>
                        </td>
                        <td>
                            <el-tag :type="getEventType(row.event)" size="small">
                                {{ getEventLabel(row.event) }}
                            </el-tag>
                        </td>
                        <td>{{ row.user ? (row.user.name || row.user.email || 'N/A') : 'Sistema' }}</td>
                        <td>
                            <el-button
                                v-if="row.changes_count > 0"
                                type="text"
                                size="small"
                                @click="showChanges(row)">
                                Ver Cambios ({{ row.changes_count }})
                            </el-button>
                            <span v-else>-</span>
                        </td>
                        <td>{{ formatDate(row.created_at) }}</td>
                        <td>{{ row.ip_address || '-' }}</td>
                        <td class="text-right">
                            <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info"
                                @click.prevent="showDetails(row)">
                                Detalles
                            </button>
                        </td>
                    </tr>
                </data-table>
            </div>
        </div>

        <!-- Modal de Cambios -->
        <el-dialog
            title="Detalles de Cambios"
            :visible.sync="showChangesDialog"
            width="70%">
            <div v-if="selectedLog">
                <h4>Evento: <el-tag :type="getEventType(selectedLog.event)">{{ getEventLabel(selectedLog.event) }}</el-tag></h4>
                <p><strong>Tipo:</strong> {{ selectedLog.tipo_label || getTipoLabel(selectedLog.auditable_type) }}</p>
                <p><strong>Registro ID:</strong> #{{ selectedLog.auditable_id }}</p>
                <p><strong>Usuario:</strong> {{ selectedLog.user ? (selectedLog.user.name || selectedLog.user.email || 'N/A') : 'Sistema' }}</p>
                <p><strong>Fecha:</strong> {{ formatDate(selectedLog.created_at) }}</p>
                <p><strong>IP:</strong> {{ selectedLog.ip_address || '-' }}</p>
                
                <el-table v-if="selectedLog.changes && selectedLog.changes.length > 0" :data="selectedLog.changes" border style="width: 100%; margin-top: 20px;">
                    <el-table-column prop="field" label="Campo" width="200"></el-table-column>
                    <el-table-column label="Valor Anterior">
                        <template slot-scope="scope">
                            <span class="text-danger">{{ scope.row.old || '-' }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="Valor Nuevo">
                        <template slot-scope="scope">
                            <span class="text-success">{{ scope.row.new || '-' }}</span>
                        </template>
                    </el-table-column>
                </el-table>
                <p v-else class="text-muted" style="margin-top: 20px;">No hay cambios registrados para este evento.</p>
            </div>
        </el-dialog>
    </div>
</template>

<script>
import DataTable from '../../components/Datatable.vue'

export default {
    components: { DataTable },
    data() {
        return {
            resource: 'comercial/logs',
            contactos: [],
            filters: {
                auditable_type: null,
                event: null,
                contacto_id: null
            },
            showChangesDialog: false,
            selectedLog: null
        }
    },
    created() {
        this.loadContactos();
    },
    mounted() {
        // Cargar todos los logs al inicio
        this.$nextTick(() => {
            if (this.$refs.datatable) {
                this.loadLogs();
            }
        });
    },
    methods: {
        loadContactos() {
            this.$http.get('/comercial/api/logs/contactos')
                .then(response => {
                    if (response.data.ok) {
                        this.contactos = response.data.data || [];
                    }
                })
                .catch(error => {
                    console.error('Error al cargar contactos:', error);
                });
        },
        loadLogs() {
            if (!this.$refs.datatable) return;
            
            // Construir parámetros de filtro
            const params = {};
            if (this.filters.auditable_type) {
                params.auditable_type = this.filters.auditable_type;
            }
            if (this.filters.event) {
                params.event = this.filters.event;
            }
            if (this.filters.contacto_id && this.filters.auditable_type === 'Modules\\Comercial\\Models\\Contacto') {
                params.auditable_id = this.filters.contacto_id;
            }
            
            // Actualizar los parámetros de búsqueda del DataTable
            this.$refs.datatable.search = {
                column: this.$refs.datatable.search?.column || null,
                value: this.$refs.datatable.search?.value || null,
                ...params
            };
            
            // Recargar los registros
            this.$refs.datatable.getRecords();
        },
        getEventType(event) {
            const types = {
                'created': 'success',
                'updated': 'warning',
                'deleted': 'danger',
                'restored': 'info'
            };
            return types[event] || 'info';
        },
        getEventLabel(event) {
            const labels = {
                'created': 'Creado',
                'updated': 'Actualizado',
                'deleted': 'Eliminado',
                'restored': 'Restaurado'
            };
            return labels[event] || event;
        },
        getTipoLabel(auditableType) {
            const tipos = {
                'Modules\\Comercial\\Models\\Empresa': 'Empresa',
                'Modules\\Comercial\\Models\\Lead': 'Lead',
                'Modules\\Comercial\\Models\\Contacto': 'Contacto',
                'Modules\\Comercial\\Models\\Requerimiento': 'Requerimiento',
                'Modules\\Comercial\\Models\\Proyecto': 'Proyecto',
            };
            return tipos[auditableType] || auditableType;
        },
        formatDate(date) {
            if (!date) return '';
            const d = new Date(date);
            return d.toLocaleString('es-ES');
        },
        showChanges(row) {
            this.$http.get(`/comercial/api/logs/${row.id}`)
                .then(response => {
                    if (response.data.ok) {
                        const audit = response.data.data.audit;
                        audit.changes = response.data.data.changes || [];
                        audit.changes_count = audit.changes.length;
                        audit.tipo_label = this.getTipoLabel(audit.auditable_type);
                        this.selectedLog = audit;
                        this.showChangesDialog = true;
                    }
                })
                .catch(error => {
                    this.$message.error('Error al cargar los detalles');
                    console.error(error);
                });
        },
        showDetails(row) {
            this.showChanges(row);
        }
    }
}
</script>

<style scoped>
.text-danger {
    color: #f56c6c;
}
.text-success {
    color: #67c23a;
}
.text-muted {
    color: #909399;
}
</style>

