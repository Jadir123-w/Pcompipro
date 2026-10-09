<template>
    <el-tabs v-model="activeName" type="border-card" class="rounded">
        <el-tab-pane class="mb-3" name="first">
                        <span slot="label">Configuración de grifos</span>
                        <div class="row switch-configuration-container">
                            <div class="col-md-6 mt-4">
                                <label class="control-label">Guardar placas respecto a un cliente</label>
                                <div :class="{ 'has-danger': errors.save_plates_client }" class="form-group">
                                    <el-switch v-model="form.save_plates_client"
                                        @change="submit"></el-switch>
                                    <small v-if="errors.save_plates_client" class="form-control-feedback"
                                        v-text="errors.save_plates_client[0]"></small>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>
    </el-tabs>
    

</template>
<script>

export default {
    data() {
        return {
            activeName: 'first',
            form: {
                save_plates_client: false,
            },
            errors: {},
            typeUser: '',
        };
    },
    created() {
        this.getRecord();
    },
    methods: {
        getRecord() {
            this.$http
                .get('/bussiness_turns/configuration/tap')
                .then((response) => {
                    this.form = response.data;
                });
        },
        submit() {
            this.errors = {};
            this.$http
                .post('/bussiness_turns/configuration/tap', this.form)
                .then((response) => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                    }
                })
                .catch((error) => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data.errors;
                    }
                });
        },
    },
}
</script>