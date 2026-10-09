<template>
    <el-dialog title="Exportar Productos Woocommerce" :visible="showDialog" @close="close" class="dialog-import">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-12">
                        <p>Se exportarán todos los productos en formato Woocommerce.</p>
                    </div>
                </div>
                <div class="form-actions text-right mt-4">
                    <el-button class="second-buton" @click.prevent="close()">Cancelar</el-button>
                    <el-button type="primary" native-type="submit" :loading="loading_submit">Exportar</el-button>
                </div>
            </div>
        </form>
    </el-dialog>
</template>

<script>
    import queryString from 'query-string'

    export default {
        props: [
            'showDialog',
            'pharmacy',
        ],
        data() {
            return {
                loading_submit: false,
                headers: headers_token,
                resource: 'items',
                errors: {},
                form: {},
                fromPharmacy: false,
            }
        },
        created() {
            if(this.pharmacy !== undefined && this.pharmacy === true){
                this.fromPharmacy = true;
            }
            this.initForm()
        },
        methods: {
            initForm() {
                this.errors = {}
                this.form = {}
            },
            close() {
                this.$emit('update:showDialog', false)
                this.initForm()
            },
            submit() {
                this.loading_submit = true

                let query = queryString.stringify({
                    isPharmacy: this.fromPharmacy
                });
                window.open(`/${this.resource}/export/wp/?${query}`, '_blank');

                this.loading_submit = false
                this.$emit('update:showDialog', false)
                this.initForm()
            }
        }
    }
</script>
