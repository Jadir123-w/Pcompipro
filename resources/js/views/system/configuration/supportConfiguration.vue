<template>
    <div class="card">
        <div class="card-header bg-info bg-info-customer-admin">
            <h3 class="my-0">Configuración de Soporte</h3>
        </div>
        <div class="card-body">
            <form autocomplete="off" @submit.prevent="submit">
                <div class="form-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-danger': errors.phone}">
                                <label class="control-label">Teléfono</label>
                                <el-input v-model="form.phone"></el-input>
                                <small class="form-control-feedback text-muted">Agregar código de país, ejemplo; 51955955955</small>
                                <small class="form-control-feedback" v-if="errors.phone" v-text="errors.phone[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-danger': errors.whatsapp_number}">
                                <label class="control-label">Número de WhatsApp</label>
                                <el-input v-model="form.whatsapp_number"></el-input>
                                <small class="form-control-feedback text-muted">Agregar código de país, ejemplo; 51955955955</small>
                                <small class="form-control-feedback" v-if="errors.whatsapp_number" v-text="errors.whatsapp_number[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-danger': errors.address_contact}">
                                <label class="control-label">Correo Electrónico</label>
                                <el-input v-model="form.address_contact" type="email"></el-input>
                                <small class="form-control-feedback text-muted">Ejemplo: soporte@miempresa.com</small>
                                <small class="form-control-feedback" v-if="errors.address_contact" v-text="errors.address_contact[0]"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label>Ver icono de soporte</label>
                            <div :class="{'has-danger': errors.enable_whatsapp}">
                                <el-switch
                                    v-model="form.enable_whatsapp"
                                    active-text="Si"
                                    inactive-text="No">
                                </el-switch>
                                <small class="form-control-feedback" v-if="errors.enable_whatsapp" v-text="errors.enable_whatsapp[0]"></small>
                                <br>
                                <small class="form-control-feedback">Se mostrará un icono de Whatsapp en cada cliente.</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group" :class="{'has-danger': errors.introduction}">
                                <label class="control-label d-block">Introducción</label>
                                <ckeditor
                                    :editors="editors"
                                    type="classic"
                                    v-model="form.introduction"
                                    :config="editorConfig"
                                />
                                <small class="form-control-feedback" v-if="errors.introduction" v-text="errors.introduction[0]"></small>                                
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-actions text-right pt-2">
                    <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import CKEditor from 'vue-ckeditor5'
import ClassicEditor from '@ckeditor/ckeditor5-build-classic'

export default {
    components: {
        // registra el componente como <ckeditor />
        ckeditor: CKEditor.component
    },
    data() {
        return {
            loading_submit: false,
            resource: 'users',
            errors: {},
            editors: {
                classic: ClassicEditor
            },
            editorConfig: {
                toolbar: [
                    'heading',
                    '|',
                    'bold', 'italic', 'link',
                    'bulletedList', 'numberedList',
                    '|',
                    'blockQuote',
                    'undo', 'redo'
                ]
            },
            form: {
                phone: null,
                whatsapp_number: null,
                address_contact: null,
                enable_whatsapp: true,
                introduction: ''
            }
        }
    },
    created() {
        this.initForm();
        this.$http.get(`/${this.resource}/record`)
            .then(response => {
                if (response.data !== '') {
                    this.form = Object.assign({
                        phone: null,
                        whatsapp_number: null,
                        address_contact: null,
                        enable_whatsapp: true,
                        introduction: ''
                    }, response.data.data || {})
                }
            });
    },
    methods: {
        initForm() {
            this.errors = {};
            this.form = {
                phone: null,
                whatsapp_number: null,
                address_contact: null,
                enable_whatsapp: true,
                introduction: ''
            };
        },
        submit() {
            this.loading_submit = true;
            this.$http.post(`/${this.resource}`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data;
                    } else {
                        console.log(error);
                    }
                })
                .finally(() => {
                    this.loading_submit = false;
                });
        }
    }
}
</script>