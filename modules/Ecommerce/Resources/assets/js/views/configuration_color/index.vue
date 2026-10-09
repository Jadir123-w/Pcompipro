<template>
  <div class="col-lg-6 col-md-12 0">
    <div class="card">
      <div class="card-header bg-info">
        <h3 class="my-0">Color Tienda virtual</h3>
      </div>
      <div class="card-body">
        <form autocomplete="off" @submit.prevent="submit">
          <div class="form-body">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group form-modern" :class="{'has-danger': errors.script_paypal}">
                  <label class="control-label">
                    Color de Tienda Virtual
                  </label>
                  <br />
                  <el-color-picker size="medium"  v-model="form.color_ecommerce"></el-color-picker>
                  <el-input v-model="form.color_ecommerce" placeholder="Please input" :disabled="true">
                    <template slot="prepend">#</template>
                  </el-input>
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
  </div>
</template>
<style>
</style>
<script>
export default {
  data() {
    return {
      loading_submit: false,
      // headers: headers_token,
      resource: "ecommerce",
      errors: {},
      form: {},
      soap_sends: [],
      soap_types: []
    };
  },
  async created() {
    await this.initForm();

    await this.$http.get(`/${this.resource}/record`).then(response => {
      if (response.data !== "") {
        let data = response.data.data;
        this.form.id = data.id;
        this.form.color_ecommerce = data.color_ecommerce;
      }
    });
  },
  methods: {
    initForm() {
      this.errors = {};
      this.form = {
        id: null,
        color_ecommerce: null
      };
    },
    submit() {
      this.loading_submit = true;
      this.$http
        .post(`/${this.resource}/configuration_color`, this.form)
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
        .then(() => {
          this.loading_submit = false;
        });
    }
  }
};
</script>



