<template>
    <el-dialog :visible="showDialog" @open="create" width="30%" :close-on-click-modal="false" :close-on-press-escape="false" :show-close="false" append-to-body>
        <span slot="title">
            <div class="widget-summary widget-summary-xs d-flex align-items-center">
                <div class="">
                    <div class="summary-icon bg-success succes-check-container m-0">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                <div class="widget-summary-col">
                    <div>
                        <div>
                            <span class="ml-2 el-dialog__title">{{ titleDialog }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </span>        
        <div class="row">
            
            <div class="col-lg-12 col-md-12 col-sm-12 text-center font-weight-bold" >
                <el-alert    title="Documento enviado a proveedores"    type="success"    show-icon>  </el-alert>
            </div>
            
            <span class="mt-2 col">Formatos disponibles para la descarga de la cotización:</span>

            <div class="col-lg-12 col-md-12 col-sm-12 text-center font-weight-bold mt-2">
                <button type="button" class="btn btn-lg btn-info waves-effect waves-light w-100" @click="clickPrint('a4')">
                    Imprimir A4
                </button>
            </div> 
        </div>   
        <span slot="footer" class="dialog-footer">
            <template v-if="showClose">
                <el-button class="second-buton" @click="clickClose">Cerrar</el-button>
            </template>
            <template v-else>
                <el-button class="list second-buton" @click="clickFinalize">Ir al listado</el-button>
                <el-button type="primary" @click="clickNewDocument">{{button_text}}</el-button>
            </template>
        </span>
    </el-dialog>
</template>

<script>
    export default {
        props: ['showDialog', 'recordId', 'showClose','isUpdate'],
        data() {
            return {
                titleDialog: null,
                loading: false,
                resource: 'purchase-quotations',
                errors: {},
                form: {},
                company: {},
                locked_emission:{},
                button_text:'Nuevo documento'
            }
        },
        async created() {
            this.initForm() 
        },
        methods: {
            initForm() {
                this.errors = {};
                this.form = {
                    external_id: null,
                    identifier: null,
                    external_id: null,
                    date_of_issue: null,
                    id: null
                }; 
            },
            async create() {
                await this.$http.get(`/${this.resource}/record/${this.recordId}`).then(response => {
                    this.form = response.data.data;
                    this.titleDialog = 'Documento generado: '+this.form.identifier;
                });
                this.button_text = this.isUpdate ? 'Continuar':'Nuevo documento'
            },
            clickPrint(format){
                window.open(`/${this.resource}/print/${this.form.external_id}/${format}`, '_blank');
            },
            clickDownloadImage() {
                window.open(`${this.form.image_detraction}`, '_blank');
            },
            clickDownload(format) {
                window.open(`${this.form.download_pdf}/${format}`, '_blank');
            },
            clickSendEmail() {
                this.loading = true
                this.$http.post(`/${this.resource}/email`, {
                    customer_email: this.form.customer_email,
                    id: this.form.id
                })
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success('El correo fue enviado satisfactoriamente')
                        } else {
                            this.$message.error('Error al enviar el correo')
                        }
                    })
                    .catch(error => {
                        if (error.response.status === 422) {
                            this.errors = error.response.data.errors
                        } else {
                            this.$message.error(error.response.data.message)
                        }
                    })
                    .then(() => {
                        this.loading = false
                    })
            }, 
            clickFinalize() {
                location.href = `/${this.resource}`
            },
            clickNewDocument() {
                this.clickClose()
            },
            clickClose() {
                this.$emit('update:showDialog', false)
                this.initForm()
            },
        }
    }
</script>
