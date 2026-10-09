<template>
    <el-dialog :visible="showDialog" @open="create" width="30%"
               :close-on-click-modal="false"
               :close-on-press-escape="false"
               :show-close="false">
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
        <span>Formatos disponibles para la descarga de la compra:</span>
        <div class="row">

            <div class="col-lg-12 col-md-12 col-sm-12 text-center font-weight-bold mt-2">
                <button type="button" class="btn btn-lg btn-info waves-effect waves-light w-100" @click="clickPrint('a4')">
                    Imprimir A4
                </button>
            </div>

        </div>
        <span slot="footer" class="dialog-footer">
            <template v-if="showClose">
                <el-button @click="clickClose">Cerrar</el-button>
            </template>
            <template v-else>
                <el-button @click="clickFinalize">Ir al listado</el-button>
                <el-button type="primary" @click="clickNewDocument">Nueva compra</el-button>
            </template>
        </span>
    </el-dialog>
</template>

<script>

    export default {
        props: ['showDialog', 'recordId', 'showClose', 'type'],
        data() {
            return {
                titleDialog: null,
                loading: false,
                resource: 'purchases',
                errors: {},
                form: {},
            }
        },
        created() {
            this.initForm()
        },
        methods: {
            clickPrint(format){
                window.open(`/${this.resource}/print/${this.form.external_id}/${format}`, '_blank');
            },
            initForm() {
                this.errors = {}
                this.form = {
                    id: null,
                    external_id: null,
                    number: null,
                    customer_email: null,
                    download_pdf: null
                }
            },
            create() {
                this.$http.get(`/${this.resource}/record/${this.recordId}`)
                    .then(response => {
                        this.form = response.data.data
                        let typei = this.type == 'edit' ? 'editada' : 'registrada'
                        this.titleDialog = `Compra ${typei}: ` +this.form.number
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