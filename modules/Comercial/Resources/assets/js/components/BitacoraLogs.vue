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
                        <span v-if="log._origen" class="origen-badge">
                            {{ log._origen }}
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
                        <el-table-column prop="field" label="Campo" width="150">
                            <template slot-scope="scope">
                                {{ formatFieldName(scope.row.field) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="Anterior">
                            <template slot-scope="scope">
                                <span class="value-old">{{ scope.row.old || '(vacío)' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Nuevo">
                            <template slot-scope="scope">
                                <span class="value-new">{{ scope.row.new || '(vacío)' }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
                <p v-else class="no-changes">Este registro fue creado o no tiene cambios de campos.</p>
            </div>
        </el-dialog>
    </div>
</template>

<script>
export default {
    name: 'BitacoraLogs',
    props: {
        // Tipo de entidad: 'empresa', 'contacto', 'requerimiento', 'proyecto', 'lead'
        entityType: {
            type: String,
            required: true
        },
        // ID del registro
        entityId: {
            type: [Number, String],
            default: null
        },
        // Mostrar/ocultar el componente
        show: {
            type: Boolean,
            default: true
        },
        // Entidades relacionadas cuyos logs también deben mostrarse
        // Formato: [{ type: 'contacto_interaccion', ids: [1, 2, 3] }]
        relatedEntities: {
            type: Array,
            default: function() { return [] }
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
    computed: {
        auditableType() {
            const types = {
                'empresa': 'Modules\\Comercial\\Models\\Empresa',
                'contacto': 'Modules\\Comercial\\Models\\Contacto',
                'contacto_interaccion': 'Modules\\Comercial\\Models\\ContactoInteraccion',
                'lead': 'Modules\\Comercial\\Models\\Lead',
                'requerimiento': 'Modules\\Comercial\\Models\\Requerimiento',
                'proyecto': 'Modules\\Comercial\\Models\\Proyecto'
            }
            return types[this.entityType] || this.entityType
        },
        auditableTypeMap() {
            return {
                'empresa': 'Modules\\Comercial\\Models\\Empresa',
                'contacto': 'Modules\\Comercial\\Models\\Contacto',
                'contacto_interaccion': 'Modules\\Comercial\\Models\\ContactoInteraccion',
                'lead': 'Modules\\Comercial\\Models\\Lead',
                'requerimiento': 'Modules\\Comercial\\Models\\Requerimiento',
                'proyecto': 'Modules\\Comercial\\Models\\Proyecto'
            }
        }
    },
    watch: {
        entityId: {
            immediate: true,
            handler(newVal) {
                if (newVal) {
                    this.loadLogs()
                } else {
                    this.logs = []
                }
            }
        },
        // Recargar cuando llegan los IDs de entidades relacionadas (ej: interacciones)
        relatedEntities: {
            deep: true,
            handler(newVal) {
                if (this.entityId && newVal && newVal.length > 0) {
                    const hasIds = newVal.some(rel => rel.ids && rel.ids.length > 0)
                    if (hasIds) {
                        this.loadLogs()
                    }
                }
            }
        }
    },
    methods: {
        async loadLogs() {
            if (!this.entityId) {
                this.logs = []
                return
            }

            this.loading = true
            try {
                // 1) Logs de la entidad principal
                const mainResponse = await this.$http.get('/comercial/logs/records', {
                    params: {
                        auditable_type: this.auditableType,
                        auditable_id: this.entityId,
                        per_page: 50
                    }
                })
                const mainLogs = (mainResponse.data.data || []).map(log => ({
                    ...log,
                    _origen: null  // sin etiqueta especial para la entidad principal
                }))

                // 2) Logs de entidades relacionadas (ej: interacciones)
                const relatedLogs = []
                for (const rel of this.relatedEntities) {
                    const fqn = this.auditableTypeMap[rel.type] || rel.type
                    const ids = rel.ids || []
                    if (!ids.length) continue

                    for (const relId of ids) {
                        try {
                            const relResponse = await this.$http.get('/comercial/logs/records', {
                                params: {
                                    auditable_type: fqn,
                                    auditable_id: relId,
                                    per_page: 50
                                }
                            })
                            const label = rel.label || rel.type
                            const items = (relResponse.data.data || []).map(log => ({
                                ...log,
                                _origen: label,
                                _origen_id: relId
                            }))
                            relatedLogs.push(...items)
                        } catch (e) {
                            // Si falla un ID relacionado, continuar con los demás
                            console.warn('Error cargando logs relacionados id=' + relId, e)
                        }
                    }
                }

                // 3) Unificar y ordenar por fecha descendente
                const allLogs = [...mainLogs, ...relatedLogs]
                allLogs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
                this.logs = allLogs

            } catch (error) {
                console.error('Error al cargar bitácora:', error)
                this.logs = []
            } finally {
                this.loading = false
            }
        },

        async showDetails(log) {
            try {
                // Usa la ruta existente: /comercial/logs/{id}
                const response = await this.$http.get(`/comercial/logs/${log.id}`)
                if (response.data.ok) {
                    this.selectedLog = {
                        ...response.data.data.audit,
                        changes: response.data.data.changes || [],
                        user: response.data.data.user
                    }
                    this.showDetailsDialog = true
                }
            } catch (error) {
                console.error('Error al cargar detalles:', error)
                this.$message.error('Error al cargar los detalles del cambio')
            }
        },

        getEventLabel(event) {
            const labels = {
                'created': 'Creación',
                'updated': 'Actualización',
                'deleted': 'Eliminación',
                'restored': 'Restauración'
            }
            return labels[event] || event
        },

        getEventClass(event) {
            const classes = {
                'created': 'event-created',
                'updated': 'event-updated',
                'deleted': 'event-deleted',
                'restored': 'event-restored'
            }
            return classes[event] || 'event-default'
        },

        formatDateReadable(dateString) {
            if (!dateString) return ''
            
            const date = new Date(dateString)
            const months = [
                'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
            ]
            
            const day = date.getDate()
            const month = months[date.getMonth()]
            const year = date.getFullYear()
            const hours = date.getHours().toString().padStart(2, '0')
            const minutes = date.getMinutes().toString().padStart(2, '0')
            const seconds = date.getSeconds().toString().padStart(2, '0')
            
            return `${year}-${(date.getMonth() + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')} ${hours}:${minutes}:${seconds}`
        },

        formatFieldName(field) {
            // Convertir snake_case a texto legible
            return field
                .replace(/_/g, ' ')
                .replace(/\b\w/g, l => l.toUpperCase())
        }
    }
}
</script>

<style scoped>
.bitacora-container {
    width: 220px;
    min-width: 220px;
    border-left: 1px solid #e4e7ed;
    background: #fafafa;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-height: 500px;
}

.bitacora-header {
    padding: 12px 15px;
    background: #f5f7fa;
    border-bottom: 1px solid #e4e7ed;
    font-weight: 600;
    font-size: 13px;
    color: #303133;
}

.bitacora-header i {
    margin-right: 6px;
    color: #e6a23c;
}

.bitacora-content {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.bitacora-empty {
    text-align: center;
    padding: 30px 15px;
    color: #909399;
}

.bitacora-empty i {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
}

.bitacora-empty p {
    margin: 0;
    font-size: 12px;
}

.bitacora-list {
    padding: 0;
}

.bitacora-item {
    padding: 10px 15px;
    border-bottom: 1px solid #ebeef5;
    cursor: pointer;
    transition: background-color 0.2s;
}

.bitacora-item:hover {
    background-color: #f5f7fa;
}

.bitacora-item:last-child {
    border-bottom: none;
}

.bitacora-event {
    margin-bottom: 4px;
}

.event-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
}

.event-created {
    background-color: #e1f3d8;
    color: #67c23a;
}

.event-updated {
    background-color: #fdf6ec;
    color: #e6a23c;
}

.event-deleted {
    background-color: #fef0f0;
    color: #f56c6c;
}

.event-restored {
    background-color: #ecf5ff;
    color: #409eff;
}

.event-default {
    background-color: #f4f4f5;
    color: #909399;
}

.origen-badge {
    display: inline-block;
    margin-left: 4px;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 500;
    background-color: #ecf5ff;
    color: #409eff;
    border: 1px solid #d9ecff;
    vertical-align: middle;
}

.bitacora-user {
    font-size: 11px;
    color: #606266;
    margin-bottom: 2px;
}

.bitacora-user i {
    margin-right: 4px;
    color: #909399;
}

.bitacora-date {
    font-size: 11px;
    color: #909399;
}

/* Estilos para el modal de detalles */
.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ebeef5;
}

.detail-date {
    color: #909399;
    font-size: 13px;
}

.changes-table {
    margin-top: 15px;
}

.changes-table h5 {
    margin-bottom: 10px;
    color: #303133;
}

.value-old {
    color: #f56c6c;
    text-decoration: line-through;
}

.value-new {
    color: #67c23a;
    font-weight: 500;
}

.no-changes {
    color: #909399;
    font-style: italic;
    margin-top: 15px;
}
</style>
