<template>
    <el-dialog :close-on-click-modal="false"
        :title="titleDialog"
        :visible="showDialog"
        :append-to-body="true"
        @close="close"
        @open="create"
        >
        <form autocomplete="off"
            @submit.prevent="submit">
            <div class="form-body">
                <el-tabs v-model="activeName">
                    <el-tab-pane class name="first">
                        <span slot="label">{{ titleTabDialog }}</span>
                        <div class="row">
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.identity_document_type_id}"
                                    class="form-group">
                                    <label class="control-label">Tipo Doc. Identidad <span class="text-danger">*</span></label>
                                    <el-select v-model="form.identity_document_type_id"
                                        dusk="identity_document_type_id"
                                        filterable
                                        popper-class="el-select-identity_document_type"
                                        @change="changeIdentityDocType">
                                        <el-option v-for="option in identity_document_types"
                                            :key="option.id"
                                            :label="option.description"
                                            :value="option.id"></el-option>
                                    </el-select>
                                    <small v-if="errors.identity_document_type_id"
                                        class="invalid-feedback"
                                        v-text="errors.identity_document_type_id[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.number}"
                                     class="form-group">
                                    <label class="control-label">Número <span class="text-danger">*</span></label>
                                    <div v-if="api_service_token != false">
                                        <x-input-service v-model="form.number"
                                            :identity_document_type_id="form.identity_document_type_id"
                                            @search="searchNumber"></x-input-service>
                                    </div>
                                    <div v-else>
                                        <el-input v-model="form.number"
                                            :maxlength="maxLength"
                                            dusk="number">
                                            <template
                                                v-if="form.identity_document_type_id === '6' || form.identity_document_type_id === '1'">
                                                <el-button slot="append"
                                                    :loading="loading_search"
                                                    icon="el-icon-search"
                                                    type="primary"
                                                    @click.prevent="searchCustomer">
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
                                    <small v-if="errors.number"
                                        class="invalid-feedback"
                                        v-text="errors.number[0]"></small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.name}"
                                     class="form-group">
                                    <label class="control-label">Nombre <span class="text-danger">*</span></label>
                                    <el-input v-model="form.name"
                                              dusk="name"></el-input>
                                    <small v-if="errors.name"
                                           class="invalid-feedback"
                                           v-text="errors.name[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.telephone}"
                                     class="form-group">
                                    <label class="control-label">Telefono </label>
                                    <el-input v-model="form.telephone"
                                              dusk="telephone"></el-input>
                                    <small v-if="errors.telephone"
                                           class="invalid-feedback"
                                           v-text="errors.telephone[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.country_id}"
                                     class="form-group">
                                    <label class="control-label">País</label>
                                    <el-select v-model="form.country_id"
                                               filterable>
                                        <el-option v-for="option in countries"
                                                   :key="option.id"
                                                   :label="option.description"
                                                   :value="option.id"></el-option>
                                    </el-select>
                                    <small v-if="errors.country_id"
                                           class="invalid-feedback"
                                           v-text="errors.country_id[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.location_id}"
                                     class="form-group">
                                    <label class="control-label">Ubigeo</label>
                                    <el-cascader v-model="form.location_id"
                                                 :clearable="true"
                                                 :options="locations"
                                                 filterable
                                                 class="w-100"></el-cascader>
                                    <small v-if="errors.location_id"
                                           class="invalid-feedback"
                                           v-text="errors.location_id[0]"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div :class="{'has-danger': errors.address}"
                                     class="form-group">
                                    <label class="control-label">Dirección</label>
                                    <el-input v-model="form.address"></el-input>
                                    <small v-if="errors.address"
                                           class="invalid-feedback"
                                           v-text="errors.address[0]"></small>
                                </div>
                            </div>
                        </div>

                    </el-tab-pane>
                </el-tabs>
            </div>
            <div class="form-actions text-end mt-4">
                <el-button @click.prevent="close()">Cancelar</el-button>
                <el-button :loading="loading_submit"
                           native-type="submit"
                           type="primary">Guardar
                </el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>
import {mapActions, mapState} from "vuex/dist/vuex.mjs";

import {serviceNumber} from '../../../../mixins/functions'

export default {
    mixins: [serviceNumber],
    props: [
        'showDialog',
        'personId'
    ],
    data() {
        return {
            parent: null,
            loading_submit: false,
            titleDialog: 'Nuevo Consignado',
            titleTabDialog: null,
            typeDialog: null,
            resource: 'consigneds',
            errors: {},
            api_service_token: true,
            form: {
            },
            person_types: [],
            identity_document_types: [],
            activeName: 'first',
            countries: [],
            locations: [],
        }
    },
    async created() {
        this.loadConfiguration()
        await this.initTables()
        await this.initForm()
    },
    computed: {
        ...mapState([
            'config',
            'person',
            'parentPerson',
        ]),
        maxLength: function () {
            if (this.form.identity_document_type_id === '6') {
                return 11
            }
            if (this.form.identity_document_type_id === '1') {
                return 8
            }
        },
    },
    methods: {
        customFilterMethod(node, keyword) {
            return node.label.toLowerCase().includes(keyword.toLowerCase());
        },
        ...mapActions([
            'loadConfiguration',
        ]),
        async initTables() {
            await this.$http.get(`/${this.resource}/tables`)
                .then(response => {
                    this.identity_document_types = response.data.identity_document_types;
                    this.countries = response.data.countries;
                    this.locations = response.data.locations;
                })
                .finally(() => {

                })
        },
        initForm() {
            this.errors = {}
            this.form = {
                id: null,
                identity_document_type_id: '1',
                number: '',
                name: null,
                country_id: 'PE',
                location_id: [],
                address: null,
                person_id: null,
                establishment_code: '0000'
            }
        },
        async opened() {


        },
        create() {

            this.titleDialog = (this.recordId) ? 'Editar Consignado' : 'Nuevo Consignado'
            this.titleTabDialog = 'Datos de Consignado';

            if (this.recordId) {
                this.$http.get(`/${this.resource}/record/${this.recordId}`)
                    .then(response => {
                        this.form = response.data.data
                    }).then(() => {

                    })
            }
        },
        validateDigits() {

            const pattern_number = new RegExp('^[0-9]+$', 'i');

            if (this.form.identity_document_type_id === '6') {

                if (this.form.number.length !== 11) {
                    return {
                        success: false,
                        message: `El campo número debe tener 11 dígitos.`
                    }
                }

                if (!pattern_number.test(this.form.number)) {
                    return {
                        success: false,
                        message: `El campo número debe contener solo números`
                    }
                }

            }


            if (this.form.identity_document_type_id === '1') {

                if (this.form.number.length !== 8) {
                    return {
                        success: false,
                        message: `El campo número debe tener 8 dígitos.`
                    }
                }

                if (!pattern_number.test(this.form.number)) {
                    return {
                        success: false,
                        message: `El campo número debe contener solo números`
                    }
                }
            }


            if (['4', '7', '0'].includes(this.form.identity_document_type_id)) {

                const pattern = new RegExp('^[A-Z0-9\-]+$', 'i');

                if (!pattern.test(this.form.number)) {
                    return {
                        success: false,
                        message: `El campo número no cumple con el formato establecido`
                    }
                }

            }


            return {
                success: true
            }
        },
        async submit() {

            let val_digits = await this.validateDigits()
            if (!val_digits.success) {
                return this.$message.error(val_digits.message)
            }

            if (this.form.location_id.length === 0 || !this.form.address ) {
                return this.$message.error('Falta ingresar el ubigeo o la dirección');
            }
            
            this.form.person_id = (this.personId) ? this.personId: null;
            this.loading_submit = true

            await this.$http.post(`/${this.resource}/store-document`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        this.$eventHub.$emit('reloadDataConsigned')
                        this.close()
                    } else {
                        this.$message.error(response.data.message)
                    }

                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data
                    } else {
                        console.log(error)
                    }
                })
                .finally(() => {
                    this.loading_submit = false
                })
        },
        changeIdentityDocType() {
            (this.recordId == null) ? this.setDataDefaultCustomer() : null
        },
        setDataDefaultCustomer() {

            if (this.form.identity_document_type_id == '0') {
                this.form.number = '99999999'
                this.form.name = "Clientes - Varios"
            } else {
                this.form.number = ''
                this.form.name = null
            }

        },
        close() {
            this.$eventHub.$emit('initInputPerson')
            this.$emit('update:showDialog', false)
            this.initForm()
        },
        searchCustomer() {
            this.searchServiceNumberByType()
        },
        async searchNumber(data) {
            this.form.name = data.name;
        },

    }
}
</script>
