<template>
    <div class="bitacora-container" v-if="show">
        <div class="bitacora-header">
            <i class="fa fa-history"></i> Bitácora de cambios
        </div>
        <div class="bitacora-content" v-loading="loading">
            <div v-if="logs.length === 0 && !loading" class="bitacora-empty">
                <i class="el-icon-document"></i>
                <p>Sin registros de cambios</p>
            </div>
            <div v-else class="bitacora-list">
                <div
                    v-for="(log, index) in logs"
                    :key="index"
                    class="bitacora-item"
                    @click="showDetails(log)">
                    <div class="bitacora-event">
                        <span :class="['event-badge', getEventClass(log.event)]">
                            {{ getEventLabel(log.event) }}
                        </span>
                        <span v-if="log.origen" class="origen-badge">
                            {{ log.origen }}
                        </span>
                    </div>
                    <div class="bitacora-user">
                        <i class="fa fa-user"></i>
                        Usuario: {{ log.user ? (log.user.name || log.user.email || 'N/A') : 'Sistema' }}
                    </div>
                    <div class="bitacora-date">
                        {{ formatDateReadable(log.created_at) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de detalles -->
        <el-dialog
            title="Detalle del cambio"
            :visible.sync="showDetailsDialog"
            width="600px"
            append-to-body>
            <div v-if="selectedLog">
                <div class="detail-header">
                    <span :class="['event-badge', getEventClass(selectedLog.event)]">
                        {{ getEventLabel(selectedLog.event) }}
                    </span>
                    <span class="detail-date">{{ formatDateReadable(selectedLog.created_at) }}</span>
                </div>
                <p><strong>Usuario:</strong> {{ selectedLog.user ? (selectedLog.user.name || selectedLog.user.email) : 'Sistema' }}</p>
                <p v-if="selectedLog.ip_address"><strong>IP:</strong> {{ selectedLog.ip_address }}</p>

                <div v-if="selectedLog.changes && selectedLog.changes.length > 0" class="changes-table">
                    <h5>Campos modificados:</h5>
                    <el-table :data="selectedLog.changes" border size="small">
                        <el-table-column prop="field" label="Campo" width="170">
                            <template slot-scope="scope">
                                {{ formatFieldName(scope.row.field) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="Anterior">
                            <template slot-scope="scope">
                                <span class="value-old">{{ formatValue(scope.row.old) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Nuevo">
                            <template slot-scope="scope">
                                <span class="value-new">{{ formatValue(scope.row.new) }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script>
/**
 * Bitácora lateral de proveedores.
 * Consume: GET /persons/suppliers/{id}/audits
 */
export default {
    name: 'SupplierBitacoraLogs',
    props: {
        entityId: {
            type: [Number, String],
            default: null
        },
        show: {
            type: Boolean,
            default: true
        }
    },
    data() {
        return {
            logs: [],
            loading: false,
            showDetailsDialog: false,
            selectedLog: null
        }
    },
    watch: {
        entityId: {
            immediate: true,
            handler(newVal) {
                if (newVal) this.loadLogs();
                else this.logs = [];
            }
        }
    },
    methods: {
        async loadLogs() {
            if (!this.entityId) {
                this.logs = [];
                return;
            }
            this.loading = true;
            try {
                const response = await this.$http.get(`/persons/suppliers/${this.entityId}/audits`);
                this.logs = response.data.data || [];
            } catch (e) {
                console.error('Error cargando bitácora:', e);
            } finally {
                this.loading = false;
            }
        },
        showDetails(log) {
            this.selectedLog = log;
            this.showDetailsDialog = true;
        },
        getEventClass(event) {
            const classes = {
                created: 'event-created',
                updated: 'event-updated',
                deleted: 'event-deleted',
                restored: 'event-restored'
            };
            return classes[event] || 'event-default';
        },
        getEventLabel(event) {
            const labels = {
                created: 'Creación',
                updated: 'Actualización',
                deleted: 'Eliminación',
                restored: 'Restaurado'
            };
            return labels[event] || event;
        },
        formatDateReadable(date) {
            if (!date) return '';
            const d = new Date(date);
            return d.toLocaleString('es-PE', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        },
        formatFieldName(field) {
            const map = {
                name: 'Razón social',
                trade_name: 'Razón comercial',
                number: 'RUC',
                rubro_sector: 'Rubro / sector',
                tipo_provee: 'Tipo de proveedor',
                calificacion: 'Calificación',
                supplier_category_id: 'Categoría',
                supplier_subcategory_id: 'Subcategoría',
                condicion_pago: 'Condición de pago',
                descuentos: 'Descuentos',
                numero_cuenta: 'N° de cuenta',
                aniversario: 'Aniversario',
                origen: 'Origen',
                referencia: 'Referencia',
                perfil_adicional: 'Perfil adicional',
                redes_sociales: 'Redes sociales',
                catalogos: 'Catálogos',
                state: 'Estado contribuyente',
                condition: 'Condición contribuyente',
                enabled: 'Habilitado',
                credit_days: 'Días de crédito',
                is_agent_retention: 'Agente retención',
                perception_agent: 'Agente percepción',
                percentage_perception: '% percepción',
                address: 'Dirección',
                nombres: 'Nombres',
                apellidos: 'Apellidos',
                cargo: 'Cargo',
                cumpleanios: 'Cumpleaños',
                estado: 'Estado'
            };
            return map[field] || field;
        },
        formatValue(value) {
            if (value === null || value === undefined || value === '') return '(vacío)';
            if (typeof value === 'object') return JSON.stringify(value);
            if (typeof value === 'boolean') return value ? 'Sí' : 'No';
            return String(value);
        }
    }
}
</script>

<style scoped>
.bitacora-container {
    background: #f8f9fa;
    border-left: 1px solid #e0e0e0;
    padding: 16px;
    height: 100%;
    overflow-y: auto;
}
.bitacora-header {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e0e0e0;
}
.bitacora-header i { color: #e6a23c; margin-right: 6px; }
.bitacora-empty {
    text-align: center;
    color: #909399;
    padding: 30px 10px;
}
.bitacora-empty i { font-size: 32px; display: block; margin-bottom: 8px; }
.bitacora-list { display: flex; flex-direction: column; gap: 8px; }
.bitacora-item {
    background: #fff;
    border: 1px solid #e6e6e6;
    border-radius: 6px;
    padding: 10px 12px;
    cursor: pointer;
    transition: box-shadow 0.2s;
}
.bitacora-item:hover { box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
.bitacora-event { display: flex; gap: 6px; margin-bottom: 6px; }
.event-badge {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 500;
}
.event-created { background: #f0f9eb; color: #67c23a; }
.event-updated { background: #fdf6ec; color: #e6a23c; }
.event-deleted { background: #fef0f0; color: #f56c6c; }
.event-restored { background: #ecf5ff; color: #409eff; }
.origen-badge {
    font-size: 10px;
    padding: 2px 6px;
    background: #eef;
    color: #606266;
    border-radius: 3px;
}
.bitacora-user, .bitacora-date {
    font-size: 12px;
    color: #606266;
    line-height: 1.5;
}
.bitacora-user i { margin-right: 4px; }
.detail-header { display: flex; gap: 10px; align-items: center; margin-bottom: 12px; }
.detail-date { color: #909399; font-size: 12px; }
.value-old { color: #f56c6c; }
.value-new { color: #67c23a; font-weight: 500; }
.changes-table h5 { margin: 15px 0 8px; font-size: 13px; }
</style>
