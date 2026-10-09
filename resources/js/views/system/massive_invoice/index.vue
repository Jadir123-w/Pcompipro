<template>
    <div>
        <header class="page-header bg-primary">
            <h2 class="text-white"><a href="/dashboard"><i class="fa fa-list-alt"></i></a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span class="text-white">Facturas Masivas</span></li>
            </ol>
        </header>
        
        <div class="card">
            <div class="card-header bg-teal">
                <div>
                    <button @click="showUploadModal = true" class="btn btn-primary mr-2 float-right">
                        <i class="fas fa-upload"></i> Subir Facturas
                    </button>
                    <button @click="showFilterModal = true" class="btn btn-info mr-2 float-right">
                        <i class="fas fa-filter"></i> Filtros
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive" v-loading="loading">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Emisión</th>
                                <th>Emisor</th>
                                <th>Cliente</th>
                                <th>Número</th> 
                                <th>Estado</th>
                                <th>T.Gravado</th>
                                <th>T.IGV</th>
                                <th>Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="record in records" :key="record.id">
                                <td>{{ formatDate(record.fecha_emision) }}</td>
                                <td>
                                    <div class="nombre-ruc">
                                        <span>{{ extractName(record.ruc_emisor) }}</span>
                                        <small>{{ extractRuc(record.ruc_emisor) }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="nombre-ruc">
                                        <span>{{ extractName(record.ruc) }}</span>
                                        <small>{{ extractRuc(record.ruc) }}</small>
                                    </div>
                                </td>
                                <td>
                                    <p class="mb-0">{{ record.serie_comprobante }}</p>
                                    <small>{{ getTipoDoc(record.tipo_comprobante) }}</small>
                                </td>
                                <td>
                                    <span :class="getStatusClass(record.estado_sunat || record.status)">
                                        {{ record.estado_sunat || record.status }}
                                    </span>
                                </td>
                                <td>S/ {{ record.total_gravado }}</td>
                                <td>S/ {{ record.total_igv }}</td>
                                <td>S/ {{ record.total_venta }}</td>
                                <td>
                                    <button @click="downloadFile(record.id, 'pdf')" 
                                        class="btn btn-sm btn-info mr-1" 
                                        title="Descargar PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <button @click="downloadFile(record.id, 'xml')" 
                                        class="btn btn-sm btn-secondary" 
                                        title="Descargar XML">
                                        <i class="fas fa-file-code"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Paginación -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <el-select v-model="pagination.perPage" @change="onPerPageChange" size="small" class="pagination-select">
                                    <el-option :value="10" label="10"></el-option>
                                    <el-option :value="25" label="25"></el-option>
                                    <el-option :value="50" label="50"></el-option>
                                    <el-option :value="100" label="100"></el-option>
                                </el-select>
                                <span class="ml-3">
                                    Mostrando {{ paginationInfo.from }} - {{ paginationInfo.to }} de {{ paginationInfo.total }} registros
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <el-pagination
                                @current-change="onPageChange"
                                :current-page.sync="pagination.currentPage"
                                :page-size="pagination.perPage"
                                :total="paginationInfo.total"
                                layout="prev, pager, next">
                            </el-pagination>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Filtros -->
        <el-dialog
            title="Filtros de Búsqueda"
            :visible.sync="showFilterModal"
            width="500px">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label>Mes:</label>
                    <el-date-picker
                        v-model="filters.month"
                        type="month"
                        format="MM/yyyy"
                        value-format="yyyy-MM"
                        placeholder="Seleccione mes"
                        class="w-100">
                    </el-date-picker>
                </div>
                <div class="col-md-12 mb-3">
                    <label>Fecha Específica:</label>
                    <el-date-picker
                        v-model="filters.date"
                        type="date"
                        format="dd/MM/yyyy"
                        value-format="yyyy-MM-dd"
                        placeholder="Seleccione fecha"
                        class="w-100">
                    </el-date-picker>
                </div>
                <div class="col-md-12 mb-3">
                    <label>Emisor:</label>
                    <el-input
                        v-model="filters.emisor"
                        placeholder="Ingrese RUC o nombre del emisor"
                        prefix-icon="el-icon-office-building">
                    </el-input>
                </div>
                <div class="col-md-12 mb-3">
                    <label>Número de Factura:</label>
                    <el-input
                        v-model="filters.serie_numero"
                        placeholder="Ingrese número de factura"
                        prefix-icon="el-icon-document">
                    </el-input>
                </div>
                <div class="col-md-12">
                    <label>RUC/DNI Receptor:</label>
                    <el-input
                        v-model="filters.receptor"
                        placeholder="Ingrese RUC o DNI"
                        prefix-icon="el-icon-user">
                    </el-input>
                </div>
            </div>
            <span slot="footer" class="dialog-footer">
                <el-button @click="clearFilters">Limpiar</el-button>
                <el-button type="primary" @click="applyFilters">Aplicar Filtros</el-button>
            </span>
        </el-dialog>

        <!-- Modal de carga masiva -->
        <el-dialog
            title="Subir Facturas Masivas"
            :visible.sync="showUploadModal"
            width="500px"
            :close-on-click-modal="false"
            :before-close="closeModal">
            
            <div class="text-center">
                <button @click="downloadFormat" class="btn btn-info mb-4 w-100">
                    <i class="fas fa-download"></i> Descargar Formato Excel
                </button>
                
                <div class="upload-area p-4 border rounded" v-if="!fileSelected">
                    <input
                        type="file"
                        ref="fileInput"
                        @change="handleFileUpload"
                        accept=".xlsx,.xls"
                        class="d-none">
                    <button @click="$refs.fileInput.click()" class="btn btn-outline-primary w-100">
                        <i class="fas fa-file-excel"></i> Seleccionar Archivo Excel
                    </button>
                </div>

                <div v-if="fileSelected" class="mt-3">
                    <p class="mb-2">
                        <i class="fas fa-file-excel text-success"></i> 
                        {{ selectedFileName }}
                    </p>
                    <button @click="processFile" class="btn btn-success" :disabled="processing">
                        <i class="fas fa-cog fa-spin" v-if="processing"></i>
                        {{ processing ? 'Procesando facturas...' : 'Procesar Facturas' }}
                    </button>
                </div>

                <el-progress 
                    v-if="processing" 
                    :percentage="progressPercentage"
                    :format="format"
                    class="mt-3">
                </el-progress>
            </div>
        </el-dialog>
    </div>
</template>

<script>
export default {
    data() {
        return {
            resource: 'massive-invoice',
            loading: false,
            showUploadModal: false,
            showFilterModal: false,
            fileSelected: false,
            selectedFileName: '',
            processing: false,
            selectedFile: null,
            records: [],
            progressPercentage: 0,
            filters: {
                month: '',
                date: '',
                serie_numero: '',
                receptor: '',
                emisor: ''
            },
            pagination: {
                currentPage: 1,
                perPage: 10
            },
            paginationInfo: {
                total: 0,
                from: 0,
                to: 0
            }
        }
    },
    created() {
        this.getRecords()
    },
    methods: {
        async getRecords() {
            this.loading = true
            try {
                const params = {
                    page: this.pagination.currentPage,
                    per_page: this.pagination.perPage,
                    month: this.filters.month,
                    date: this.filters.date,
                    serie_numero: this.filters.serie_numero,
                    receptor: this.filters.receptor,
                    emisor: this.filters.emisor
                }

                const response = await this.$http.get(`/${this.resource}/records`, { params })
                
                if (response.data.success) {
                    this.records = response.data.data.data
                    this.paginationInfo = {
                        total: response.data.data.total,
                        from: response.data.data.from,
                        to: response.data.data.to
                    }
                }
            } catch (error) {
                console.error(error)
                this.$message.error('Error al cargar registros')
            } finally {
                this.loading = false
            }
        },
        formatDate(date) {
            if (!date) return '';
            const d = new Date(date);
            return d.toLocaleDateString('es-PE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        },
        getTipoDoc(tipo) {
            const tipos = {
                '01': 'FACTURA ELECTRÓNICA',
                '03': 'BOLETA DE VENTA ELECTRÓNICA'
            };
            return tipos[tipo] || tipo;
        },
        getStatusClass(status) {
            const statusLower = status.toLowerCase();
            return {
                'badge': true,
                'badge-success': ['aceptado', 'procesado'].includes(statusLower),
                'badge-dark': ['rechazado', 'error'].includes(statusLower),
                'badge-secondary': ['registrado', 'pendiente'].includes(statusLower),
                'badge-infos': ['enviado'].includes(statusLower)
            }
        },
        downloadFormat() {
            window.open(`/${this.resource}/download-format`, '_blank')
        },
        handleFileUpload(event) {
            const file = event.target.files[0]
            if (file) {
                this.selectedFile = file
                this.selectedFileName = file.name
                this.fileSelected = true
            }
        },
        format(percentage) {
            return percentage === 100 ? 'Completado' : `${percentage}%`
        },
        async processFile() {
            if (!this.selectedFile) return
            
            this.processing = true
            const formData = new FormData()
            formData.append('file', this.selectedFile)

            try {
                // Primer paso: Validar y procesar Excel
                const uploadResponse = await this.$http.post(`/${this.resource}/upload`, formData)
                
                if (!uploadResponse.data.success) {
                    throw new Error(uploadResponse.data.message)
                }

                // Segundo paso: Procesar documentos
                this.progressPercentage = 50
                const processResponse = await this.$http.post(`/${this.resource}/process`, {
                    documents: uploadResponse.data.data
                })

                if (processResponse.data.success) {
                    this.progressPercentage = 100
                    await this.$message({
                        message: 'Facturas procesadas correctamente',
                        type: 'success'
                    })
                    this.closeModal()
                    this.getRecords()
                } else {
                    throw new Error(processResponse.data.message)
                }
            } catch (error) {
                console.error('Error:', error)
                const errorMessage = error.response && error.response.data ? 
                    error.response.data.message : (error.message || 'Error al procesar el archivo')
                this.$message.error(errorMessage)
            } finally {
                this.processing = false
                this.progressPercentage = 0
            }
        },
        async downloadFile(id, type) {
            try {
                window.open(`/${this.resource}/download/${id}/${type}`, '_blank')
            } catch (error) {
                console.error(error)
                this.$message.error('Error al descargar archivo')
            }
        },
        closeModal() {
            this.showUploadModal = false
            this.showFilterModal = false
            this.fileSelected = false
            this.selectedFileName = ''
            this.selectedFile = null
            this.processing = false
            this.progressPercentage = 0
        },
        onFilterChange() {
            this.pagination.currentPage = 1
            this.getRecords()
        },
        onPageChange(page) {
            this.pagination.currentPage = page
            this.getRecords()
        },
        onPerPageChange() {
            this.pagination.currentPage = 1
            this.getRecords()
        },
        clearFilters() {
            this.filters = {
                month: '',
                date: '',
                serie_numero: '',
                receptor: '',
                emisor: ''
            }
            this.onFilterChange()
        },
        applyFilters() {
            this.showFilterModal = false
            this.onFilterChange()
        },
        extractName(text) {
            if (!text) return '';
            const parts = text.split(' - ');
            return parts.length > 1 ? parts[1] : text;
        },
        extractRuc(text) {
            if (!text) return '';
            const parts = text.split(' - ');
            return parts[0] || '';
        },
    }
}
</script>

<style>
.pagination-select {
    width: 70px !important;
}
.pagination-select :deep(.el-input__inner) {
    padding-right: 25px !important;
}

/* Status badge styles */
.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.badge-success {
    background-color: #67C23A;
    color: white;
}

.badge-dark {
    background-color: #303133;
    color: white;
}

.badge-secondary {
    background-color: #909399;
    color: white;
}

.badge-warning {
    background-color: #E6A23C;
    color: white;
}

.badge-infos {
    background-color: #7bdcfa;
    color: white;
}

.nombre-ruc {
    display: flex;
    flex-direction: column;
}

.nombre-ruc span {
    font-weight: 500;
    margin-bottom: 2px;
}

.nombre-ruc small {
    color: #666;
}
</style>