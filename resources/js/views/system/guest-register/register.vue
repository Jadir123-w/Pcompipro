<template>

    <form autocomplete="off" @submit.prevent="submit">

        <div class="row email-verificate" v-if="isRegistered" v-loading="loading_submit">

            <div class="col-md-12 text-center">
                <h1 class="auth__title">Verificación de correo</h1>
            </div>
            <div class="col-md-12">
                <p class="text-justify">
                  <span class="step-number">1</span>
                  <span class="step-text">
                    Revise su bandeja de entrada en el correo: <b>{{ form.email }}</b> y haga clic en el enlace "Verificar Email" para activar su cuenta.
                  </span>
                </p>

                <p class="text-justify">
                  <span class="step-number">2</span>
                  <span class="step-text">
                    Si registró mal su correo, escríbanos al whatsapp para que el equipo de soporte lo corrija por usted.
                  </span>
                </p>

                <p class="text-justify">
                  <span class="step-number">3</span>
                  <span class="step-text">
                    Si el correo indicado es correcto y no recibió el correo de verificación en su bandeja de entrada, verifique su bandeja de spam o
                    <a href="#" @click="clickResendEmail"><b>Haga clic aquí para volver a enviar el correo de verificación.</b></a>
                  </span>
                </p>
                <p v-if="errors.key || errors.user_id || errors.email">
                    <span class="step-number error-step">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M7.938 2.016a.13.13 0 0 1 .125 0l6.857 11.856c.03.052.03.116 0 .168a.13.13 0 0 1-.125.06H1.205a.13.13 0 0 1-.125-.06.145.145 0 0 1 0-.168L7.938 2.016zM8 5c-.535 0-.954.462-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 5zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        </svg>
                    </span>
                    <span class="step-text">
                        <small v-if="errors.key" class="invalid-feedback" v-text="errors.key[0]"></small>
                        <small v-if="errors.user_id" class="invalid-feedback" v-text="errors.user_id[0]"></small>
                        <small v-if="errors.email" class="invalid-feedback" v-text="errors.email[0]"></small>
                    </span>
                </p>
            </div>
        </div>

        <div class="row" v-else>

            <div class="col-md-12 text-center">
                <h1 class="auth__title" style="font-weight: 700;">Regístrate gratis</h1>
            </div>

            <div class="col-md-12">
                <div :class="{'has-danger': errors.number}" class="form-group">
                    <label class="control-label">RUC</label>
                    <x-input-service-guest v-model="form.number"
                                             class="form-control form-top"
                                             :identity_document_type_id="form.identity_document_type_id"
                                             @search="searchNumber"></x-input-service-guest>
                    <small v-if="errors.number" class="invalid-feedback" v-text="errors.number[0]"></small>
                </div>
            </div>
            <div class="col-md-12" v-if="rucVerified">
                <div :class="{'has-danger': errors.name}" class="form-group">
                    <label class="control-label">Nombre de la empresa</label>
                    <el-input v-model="form.name" class="form-control" :disabled="true" >
                    </el-input>
                    <small v-if="errors.name" class="invalid-feedback" v-text="errors.name[0]"></small>
                </div>
            </div>
            <div class="col-md-12">

                <div :class="{'has-danger': (errors.subdomain || errors.uuid)}" class="form-group">
                    <label class="control-label">
                        Nombre de subdominio
                    </label>
                    <el-input v-model="form.subdomain" class="form-control form-top">
                        <template slot="append">{{ baseUrl }}</template>
                    </el-input>
                    <small v-if="errors.subdomain" class="invalid-feedback" v-text="errors.subdomain[0]"></small>
                    <small v-if="errors.uuid" class="invalid-feedback" v-text="errors.uuid[0]"></small>
                </div>
            </div>

            <div class="col-md-12">
                <div :class="{'has-danger': errors.email}" class="form-group">                    
                    <label class="control-label">
                    Correo de acceso
                      <el-tooltip class="item"
                                  content="Ingresa un correo válido: allí recibirás el aviso y el enlace de activación."
                                  effect="dark"
                                  placement="top-start">
                          <i class="fa fa-info-circle"></i>
                      </el-tooltip>
                    </label>
                    <el-input v-model="form.email" class="form-control" :disabled="form.is_update">
                    </el-input>
                    <small v-if="errors.email" class="invalid-feedback" v-text="errors.email[0]"></small>
                </div>
            </div>

            <div class="col-md-12">
                <div :class="{'has-danger': (errors.password)}" class="form-group">
                    <label class="control-label">
                        Contraseña
                    </label>
                    <el-input v-model="form.password" type="password" class="form-control" show-password>
                    </el-input>
                    <small v-if="errors.password" class="invalid-feedback" v-text="errors.password[0]"></small>
                </div>
            </div>

            <div class="col-md-12  text-end pt-2">      
                <button
                  class="btn-signin btn-block mt-0"
                  :disabled="loading_submit"
                  type="submit"
                >
                  <template v-if="loading_submit">
                    {{ button_text }}
                  </template>
                  <template v-else>
                        CREAR CUENTA
                  </template>
                </button>
            </div>

            <div class="col-md-12  text-center mt-3">
                <span style="font-size: 12px;">
                  <strong>✉ Se requiere confirmar el correo para acceder a la plataforma.</strong>
                </span>
            </div>

        </div>
    </form>
</template>

<script>
    import {serviceNumber} from '../../../mixins/functions'

    export default {
        props: {
            baseUrl: {
                required: true
            }
        },
        mixins: [serviceNumber],
        data() {
            return {
                resource: 'guest-register',
                form: {},
                errors: {},
                loading_submit: false,
                button_text: null,
                isRegistered: false,
                rucVerified: false,
            }
        },
        created() 
        {
            this.initForm()
        },
        methods: 
        {
            async clickResendEmail()
            {
                this.loading_submit = true

                const form = {
                    user_id: this.form.guest_register.user_id, 
                    email: this.form.email,
                    key: this.form.guest_register.key
                }

                await this.$http.post(`${this.resource}/resend-email`, form)
                            .then(response => {
                                if (response.data.success) 
                                {
                                    this.$message.success(response.data.message)
                                }
                                else 
                                {
                                    this.$message.error(response.data.message)
                                }
                            })
                            .catch(error => {
                                if (error.response.status === 422) 
                                {
                                    this.errors = error.response.data
                                }
                                else 
                                {
                                    console.log(error.response)
                                    this.$message.error(error.response.data.message)
                                }
                            })
                            .finally(() => {
                                this.loading_submit = false
                            })
            },
            async submit()
            {
                this.loading_submit = true
                this.button_text = 'CREANDO CUENTA...'

                await this.$http.post(`${this.resource}/register`, this.form)
                            .then(response => {
                                if (response.data.success) 
                                {
                                    this.$message.success(response.data.message)
                                    this.form.guest_register = response.data.guest_register
                                    this.isRegistered = true
                                }
                                else 
                                {
                                    this.$message.error(response.data.message)
                                }
                            })
                            .catch(error => {
                                if (error.response.status === 422) 
                                {
                                    this.errors = error.response.data
                                }
                                else 
                                {
                                    console.log(error.response)
                                    this.$message.error(error.response.data.message)
                                }
                            })
                            .finally(() => {
                                this.loading_submit = false
                            })

            },
            searchNumber(data) 
            {
                this.form.name = data.name
            },
            initForm() 
            {

                this.form = {
                    name: null,
                    email: null,
                    identity_document_type_id: '6',
                    number: '',
                    password: null,
                    subdomain: null,
                    guest_register: {},
                }

                this.errors = {}
            },
            searchNumber(data) {
                if (data && data.name) {
                    this.form.name = data.name
                    this.rucVerified = true
                } else {
                    this.form.name = null
                    this.rucVerified = false
                }
            }
        }
    }
</script>
