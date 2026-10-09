<template>
    <el-dialog :title="'Agregar Comprador'" :visible="showDialog" @close="close" 
               :close-on-click-modal="false" append-to-body>
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.identity_document_type_id}">
                            <label class="control-label">Tipo Doc. Identidad <span class="text-danger">*</span></label>
                            <el-select v-model="form.identity_document_type_id" filterable
                                       popper-class="el-select-identity_document_type" dusk="identity_document_type_id"
                                       @change="changeIdentityDocType">
                                <el-option v-for="option in identity_document_types" :key="option.id" :value="option.id"
                                           :label="option.description"></el-option>
                            </el-select>
                            <small class="form-control-feedback" v-if="errors.identity_document_type_id"
                                   v-text="errors.identity_document_type_id[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group btn-sun-ren-container" :class="{'has-danger': errors.number}">
                            <label class="control-label">Número <span class="text-danger">*</span></label>

                            <!-- <el-input v-model="form.number" :maxlength="maxLength" dusk="number">
                            </el-input> -->

                            <div v-if="api_service_token != false">
                                <x-input-service :identity_document_type_id="form.identity_document_type_id"
                                                 v-model="form.number" @search="searchNumber"></x-input-service>
                            </div>
                            <div v-else>
                                <el-input v-model="form.number" :maxlength="maxLength" dusk="number">
                                    <template
                                        v-if="form.identity_document_type_id === '6' || form.identity_document_type_id === '1'">
                                        <el-button type="primary" slot="append" :loading="loading_search"
                                                   icon="el-icon-search" @click.prevent="searchCustomer">
                                            <template v-if="form.identity_document_type_id === '6'">
                                                SUNAT
                                            </template>
                                            <template v-if="form.identity_document_type_id === '1'">
                                                RENIEC
                                            </template>
                                        </el-button>
                                    </template>
                                </el-input>
                            </div>

                            <small class="form-control-feedback" v-if="errors.number" v-text="errors.number[0]"></small>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.name}">
                            <label class="control-label">Nombre <span class="text-danger">*</span></label>
                            <el-input v-model="form.name" dusk="name"></el-input>
                            <small class="form-control-feedback" v-if="errors.name" v-text="errors.name[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.address}">
                            <label class="control-label">Dirección </label>
                            <el-input v-model="form.address" dusk="address"></el-input>
                            <small class="form-control-feedback" v-if="errors.address" v-text="errors.address[0]"></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions text-right mt-4">
                <el-button class="second-buton" @click.prevent="close()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
        </form>
    </el-dialog>
</template>


<script>
import {serviceNumber} from '@mixins/functions'

export default {
    props: ['identity_document_types', 'showDialog'],
    mixins: [serviceNumber],
    data () {
        return {
            form: {
                name: null,
                number: null,
                identity_document_type_id: null,
                address: null,
            },
            errors: {},
            loading_submit: false,
            resource: 'dispatch_persons',
        }
    },
    computed: {
        maxLength: function () {
            if (this.form.identity_document_type_id === '6') {
                return 11
            }
            if (this.form.identity_document_type_id === '1') {
                return 8
            }
        }
    },
    methods:{
        close() {
            this.$emit('update:showDialog', false)
            this.initForm()
        },
        searchCustomer() {
            this.searchServiceNumberByType()
        },
        searchNumber(data) {
            this.form.name = (this.form.identity_document_type_id === '1') ? data.nombre_completo : data.nombre_o_razon_social;
            this.form.address = data.direccion_completa
            this.form.location_id = data.location_id[2]

        },
        submit(){
            this.$http.post(`/${this.resource}/buyer`, this.form)
                .then(({data}) => {
                    if (data.success) {
                        
                        this.$emit('addBuyer', data.data.buyer_id)
                        this.$eventHub.$emit('reloadDataBuyers')
                        this.close()
                        this.$notify({
                            title: 'Éxito',
                            message: 'Comprador agregado',
                            type: 'success'
                        })
                    }
                }).catch(({response}) => {
                if (response.status === 422) {
                    this.errors = response.data.errors
                } else {
                    this.$notify({
                        title: 'Error',
                        message: response.data.message,
                        type: 'error'
                    })
                }
            }).finally(() => {
                this.loading_submit = false
            })
        }


    }
}
</script>