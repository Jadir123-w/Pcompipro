<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create" class="dialog-import">
        <el-tabs v-model="activeName">
            <el-tab-pane class
                name="first">
                <span slot="label">General</span>
                <form autocomplete="off" @submit.prevent="submit">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-12 form-group" :class="{'has-danger': errors.file}">
                                <el-upload
                                        ref="upload"
                                        :headers="headers"
                                        action="/items/import/items-update-prices"
                                        :show-file-list="true"
                                        :auto-upload="false"
                                        :multiple="false"
                                        :on-error="errorUpload"
                                        :before-upload="onBeforeUpload"
                                        :limit="1"
                                        :data="form"
                                        :on-success="successUpload">
                                    <el-button slot="trigger" type="primary">Seleccione un archivo (xlsx)</el-button>
                                </el-upload>
                                <small class="form-control-feedback" v-if="errors.file" v-text="errors.file[0]"></small>
                            </div>
                            <div class="col-12 mt-4 mb-2">
                                <a class="text-dark mr-auto" href="/formats/items_update_prices.xlsx" target="_new">
                                    <span class="mr-2">Descargar formato de ejemplo para importar</span>
                                    <i class="fa fa-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions text-right mt-5">
                        <el-button class="second-buton" @click.prevent="close()">Cancelar</el-button>
                        <el-button type="primary" native-type="submit" :loading="loading_submit">Procesar</el-button>
                    </div>
                </form>
            </el-tab-pane>
            <el-tab-pane class
                name="second">
                <span slot="label">Almacén</span>
                <form autocomplete="off">
                    <div class="form-body">
                        <div class="row">
                            <div class="col-12 form-group" :class="{'has-danger': errors.file}">
                                <el-upload
                                        ref="upload_establishment"
                                        :headers="headers"
                                        action="/items/import/items-update-prices-establishment"
                                        :show-file-list="true"
                                        :auto-upload="false"
                                        :multiple="false"
                                        :on-error="errorUpload"
                                        :before-upload="onBeforeUpload"
                                        :limit="1"
                                        :data="formEstablishment"
                                        :on-success="successUploadEstablishment">
                                    <el-button slot="trigger" type="primary">Seleccione un archivo (xlsx)</el-button>
                                </el-upload>
                                <small class="form-control-feedback" v-if="errors.file" v-text="errors.file[0]"></small>
                            </div>
                            <div class="col-12 mt-4 mb-2">
                                <a class="text-dark mr-auto" @click="downloadFormat" target="_new"
                                    style="cursor: pointer; text-decoration: none;"
                                    @mouseover="hovering = true" 
                                    @mouseleave="hovering = false">
                                        <span :style="{ textDecoration: hovering ? 'underline' : 'none' }" class="mr-2">
                                            Descargar formato de ejemplo para importar
                                        </span>
                                        <i class="fa fa-download"></i>
                                </a>
                            </div>
                            
                        </div>
                    </div>
                    <div class="form-actions text-right mt-5">
                        <el-button class="second-buton" @click.prevent="close()">Cancelar</el-button>
                        <el-button type="primary" @click.prevent="submitPriceEstablishment()" :loading="loading_submit_price">Procesar</el-button>
                    </div>
                </form>
            </el-tab-pane>

        </el-tabs>
        
    </el-dialog>
</template>

<script>

    export default {
        props: ['showDialog'],
        data() {
            return {
                loading_submit: false,
                loading_submit_price: false,
                headers: headers_token,
                titleDialog: null,
                resource: 'items',
                activeName: 'first',
                errors: {},
                form: {},
                warehouses: [],
                formEstablishment: {},
                hovering: false,
            }
        },
        async created() {
            this.initForm();
        },
        methods: {
            onBeforeUpload(file) {

            },
            initForm() {
                this.errors = {}
                this.form = {
                    warehouse_id: null
                }
            },
            create() {
                this.titleDialog = 'Actualizar precio de los productos',
                this.activeName = 'first'
            },
            async submit() {
                this.loading_submit = true
                await this.$refs.upload.submit()
                this.loading_submit = false
            },
            close() {
                this.$emit('update:showDialog', false)
                this.initForm()
            },
            successUpload(response, file, fileList) {

                if (response.success) 
                {
                    this.$message.success(response.message)
                    this.$eventHub.$emit('reloadData')
                    this.$refs.upload.clearFiles()
                    this.close()
                } 
                else 
                {
                    this.$message({message:response.message, type: 'error'})
                }
            },
            errorUpload(error) {
                console.log(error)
            },
            async submitPriceEstablishment () {
                this.loading_submit_price = true
                await this.$refs.upload_establishment.submit()
                this.loading_submit_price = false
            },
            successUploadEstablishment(response, file, fileList) {
                if (response.success) 
                {
                    this.$message.success(response.message)
                    this.$eventHub.$emit('reloadData')
                    this.$refs.upload_establishment.clearFiles()
                    this.close()
                } 
                else 
                {
                    this.$message({message:response.message, type: 'error'})
                }
            },
            downloadFormat() {
                window.open(`/${this.resource}/prices-establishment-format/export`, '_blank');
            }
        }
    }
</script>
