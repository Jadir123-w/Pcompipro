<template>
    <el-dialog :title="titleDialog" :visible="showDialog" :close-on-click-modal="false" :close-on-press-escape="false" @close="handleCloseDialog" @open="create">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group" :class="{'has-danger': errors.name}">
                            <label class="control-label">Nombre</label>
                            <el-input v-model="form.name"></el-input>
                            <small class="form-control-feedback" v-if="errors.name" v-text="errors.name[0]"></small>
                        </div>
                    </div> 
                </div> 
            </div>
            <div class="form-actions text-right pt-2">
                <el-button class="second-buton" @click.prevent="handleCloseDialog()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
        </form>
    </el-dialog>
</template>
 
<script>
 

    export default {
        props: ['showDialog', 'recordId'],
        data() {
            return {
                loading_submit: false,
                titleDialog: null,
                resource: 'brands', 
                errors: {}, 
                form: {}, 
            }
        },
        created() {
            this.initForm() 
        },
        methods: {
            handleCloseDialog() {
              if (this.hasUnsavedChanges()) {
                this.$confirm('¿Estás seguro de cerrar el formulario? Se perderán los datos no guardados.', 'Confirmar', {
                  confirmButtonText: 'Cerrar sin guardar',
                  cancelButtonText: 'Cancelar',
                  type: 'warning'
                }).then(() => {
                  this.forceCloseDialog()
                }).catch(() => {

                });
              } else {
                this.forceCloseDialog()
              }
            },
            forceCloseDialog() {
              this.initForm()
              this.$emit('update:showDialog', false)
            },
            hasUnsavedChanges() {
                return JSON.stringify(this.form) !== this.originalForm
            },
            initForm() { 
                this.errors = {} 

                this.form = {
                    id: null,
                    name: null, 
                }
                this.originalForm = JSON.stringify(this.form)
            },
            create() {

                this.titleDialog = (this.recordId)? 'Editar marca':'Nueva marca'
                if (this.recordId) {
                    this.$http.get(`/${this.resource}/record/${this.recordId}`).then(response => {
                            this.form = response.data
                        })
                }
            },
            submit() {   
 

                this.loading_submit = true  
                this.$http.post(`${this.resource}`, this.form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.$eventHub.$emit('reloadData')
                            this.close()
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(error => {
                        if (error.response.status === 422) {
                            this.errors = error.response.data 
                        } else {
                            console.log(error.response)
                        }
                    })
                    .then(() => {
                        this.loading_submit = false
                    })
                    
            },  
            close() {
                this.$emit('update:showDialog', false)
                this.initForm()
            }
        }
    }
</script>