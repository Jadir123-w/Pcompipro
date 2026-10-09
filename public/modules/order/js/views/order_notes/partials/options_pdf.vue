<template>
    <div>
        <el-dialog :title="titleDialog" :visible="showDialog" @open="create" @close="clickClose" :close-on-click-modal="false" width="30%"
                >
            <span>Formatos disponibles para la descarga del documento:</span>
            <div class="row print-buttons-container mt-3">
                <div class="col text-center font-weight-bold">                
                    <button type="button" class="btn btn-lg btn-info waves-effect waves-light w-100" title="Imprimir A4" @click="clickToPrint('a4')">
                        A4
                    </button>
                </div>
                <div class="col text-center font-weight-bold">
                    <button type="button" class="btn btn-lg btn-info waves-effect waves-light w-100" title="Imprimir Ticket" @click="clickToPrint('ticket')">
                        Ticket
                    </button>
                </div>
                <div class="col text-center font-weight-bold" v-if="configuration.ticket_58">
                    <button type="button" class="btn btn-lg btn-info waves-effect waves-light w-100" title="Imprimir Ticket 58MM" @click="clickToPrint('ticket_58')">
                      Ticket 58MM
                    </button>
                </div>
                <div class="col text-center font-weight-bold">
                    <button type="button" class="btn btn-lg btn-info waves-effect waves-light w-100" title="Imprimir A5" @click="clickToPrint('a5')">
                        A5
                    </button>
                </div>
            </div>
            <br>
            <div class="row print-buttons-container">
                <div class="col text-center font-weight-bold">
                    <button type="button" class="btn btn-lg btn-info waves-effect waves-light w-100" @click="clickDownload('a4')">
                        Descargar A4
                    </button>
                </div>
                <div class="col text-center font-weight-bold">
                    <button type="button" class="btn btn-lg btn-info waves-effect waves-light w-100" @click="clickDownload('ticket')">
                        Descargar Ticket
                    </button>
                </div>
            </div>


            <!-- <span slot="footer" class="dialog-footer row"> -->
            <span slot="footer" class="dialog-footer">
                <!-- <div class="col-md-6">
                    <el-input v-model="form.customer_email">
                        <el-button slot="append" icon="el-icon-message"   @click="clickSendEmail" :loading="loading">Enviar</el-button>
                    </el-input>
                </div> -->
                <!-- <div class="col-md-6">  -->
                    <el-button @click="clickClose">Cerrar</el-button>
                <!-- </div> -->

            </span>
        </el-dialog>


    </div>
</template>

<script>


    export default {

        props: ['showDialog', 'recordId', 'showClose','configuration'],
        data() {
            return {
                titleDialog: null,
                resource: 'order-notes',
                form: {},
                loading: false,

            }
        },
        created() {
            this.initForm()
        },
        methods: {
            initForm() {
                this.form = {
                    id: null,
                    external_id: null,
                }
            },
            create() {
                this.$http.get(`/${this.resource}/record/${this.recordId}`)
                    .then(response => {
                        this.form = response.data.data
                        this.titleDialog = `Pedido registrado: ${this.form.identifier}`
                    })
            },
            clickClose() {
                this.$emit('update:showDialog', false)
                this.initForm()
            },
            clickToPrint(format){
                window.open(`/${this.resource}/print/${this.form.external_id}/${format}`, '_blank');
            } ,
            clickDownload(format){
                window.open(`/${this.resource}/download/${this.form.external_id}/${format}`, '_blank');
            } ,

            clickSendEmail()
            {
                this.loading = true
                this.$http.post(`/${this.resource}/email`, {
                    customer_email: this.customer_email,
                    id: this.form.id,
                    customer_id: this.form.order_note.customer_id
                })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success('El correo fue enviado satisfactoriamente')
                    } else {
                        this.$message.error('Error al enviar el correo')
                    }
                })
                .catch(error => {
                    this.$message.error('Error al enviar el correo')
                })
                .then(() => {
                    this.loading = false
                })
            }
        }
    }
</script>
