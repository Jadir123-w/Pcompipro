<template>
  <div>

    <el-dialog
      :title="title"
      :visible="visible"
      @close="closeDialog"
      @open="create"
      width="60%"
    >
      <form autocomplete="off" @submit.prevent="onSubmit">
        <div class="form-body">

          <div class="row">
            <div
                class="col-6 col-md-4 form-group"
                :class="{ 'has-danger': errors.duration }"
            >
                <label class="control-label" for="duration">Cant. noches</label>
                <el-input-number
                    v-model="form.duration"
                    controls-position="right"
                    @change="updateDuration"
                    :min="1"
                ></el-input-number>
                <small
                    class="form-control-feedback"
                    v-if="errors.duration"
                    v-text="errors.duration[0]"
                ></small>
            </div>
            <div
                class="col-6 col-md-4 form-group"
                :class="{ 'has-danger': errors.output_date }"
            >
                <label class="control-label">Fecha de salida</label>
                <el-date-picker
                    v-model="form.output_date"
                    type="date"
                    placeholder="Seleccione una fecha"
                    value-format="dd-MM-yyyy"
                    format="dd-MM-yyyy"
                    readonly
                ></el-date-picker>
                <small
                    class="form-control-feedback"
                    v-if="errors.output_date"
                    v-text="errors.output_date[0]"
                ></small>
            </div>
            <div
                class="col-6 col-md-4 form-group"
                :class="{ 'has-danger': errors.output_time }"
            >
                <label class="control-label">Hora de salida</label>
                <el-input v-model="form.output_time" placeholder="HH:MM">
                </el-input>
                <small
                    class="form-control-feedback"
                    v-if="errors.output_time"
                    v-text="errors.output_time[0]"
                ></small>
            </div>
          </div>

          <div class="d-flex justify-content-end text-center ml-auto">
            <div class="mx-1">
              <el-button class="btn-block second-buton btn" @click="closeDialog" style="min-width: 78px;">Cancelar</el-button>
            </div>
            <div class="mx-1">
              <el-button
                native-type="submit"
                type="primary"
                class="btn-block btn"
                :disabled="loading"
                style="min-width: 78px;"
                >Guardar</el-button
              >
            </div>            
          </div>
        </div>
      </form>
    </el-dialog>
  </div>
</template>

<script>
export default {
  props: ['room','visible'],
  data() {
    return {
      form: {
        output_date: moment().format("YYYY-MM-DD"),
        output_time: moment().format("HH:mm:ss"),
        duration: 1
      },
      showDialog: false,
      title: 'Editar Estadía',
      errors: {},
      loading: false,
      item: {},
      item_debt: {},
    }
  },
  methods: {
    initForm(){
      this.form = {
        output_date: moment().format("YYYY-MM-DD"),
        output_time: moment().format("HH:mm:ss"),
        duration: 1,
        item: {}
      }
      this.item = {}
    },
    closeDialog() {
      this.initForm()
      this.$emit("onRefresh")
      this.$emit("update:visible", false)
    },
    async create() {

      // console.log(this.room)
      this.form = {
        output_date: this.room.rent.output_date,
        output_time: this.room.rent.output_time,
        duration: this.room.rent.duration
      }
    },
    updateDuration() {
      const newDate = moment().add(this.form.duration, "days")
      this.form.output_date = newDate.format("YYYY-MM-DD")

      this.getItem()
    },
    getItem() {
      this.$http
          .get(`/hotels/reception/${this.room.rent.id}/rent/get-item`)
          .then(response => {
            this.item = response.data.data.item
            this.item_debt = response.data.data.item_debt
            if(this.item_debt && this.item_debt.payment_status=='DEBT'){
              this.changeJsonItem()
            }else{
              this.addJsonItem()
            }
            
          })
    },
    changeJsonItem() {

      let quantity = this.form.duration

      if(this.item && this.item.payment_status=='PAID'){
        quantity = quantity - this.item.item.quantity;
        console.log(this.item.item.quantity)
      }

      console.log(quantity)
      
      let percentage_igv = this.item_debt.item.percentage_igv
      let unit_price = this.item_debt.item.unit_price
      let total = quantity * unit_price
      let total_base_igv = total / (1 + (percentage_igv / 100))
      let total_igv = total - total_base_igv

      this.item_debt.item.quantity = quantity
      this.item_debt.item.unit_value = _.round(unit_price, 2)
      this.item_debt.item.input_unit_price_value = _.round(unit_price, 2)
      this.item_debt.item.total = _.round(total, 2)
      this.item_debt.item.total_base_igv = _.round(total_base_igv, 2)
      this.item_debt.item.total_value = _.round(total_base_igv, 2)
      this.item_debt.item.total_taxes = _.round(total_igv, 2)
      this.item_debt.item.total_igv = _.round(total_igv, 2)

      this.item_debt.item.total_value_without_rounding = total_base_igv
      this.item_debt.item.total_base_igv_without_rounding = total_base_igv
      this.item_debt.item.total_igv_without_rounding = total_igv
      this.item_debt.item.total_taxes_without_rounding = total_igv
      this.item_debt.item.total_without_rounding = total

      this.form.item = this.item_debt
    },
    addJsonItem() {

      let quantity = this.form.duration

      if(this.item && this.item.payment_status=='PAID'){
        quantity = quantity - this.item.item.quantity;
        console.log(this.item.item.quantity)
      }

      let percentage_igv = this.item.item.percentage_igv
      let unit_price = this.item.item.unit_price
      let total = quantity * unit_price
      let total_base_igv = total / (1 + (percentage_igv / 100))
      let total_igv = total - total_base_igv

      this.item.item.quantity = quantity
      this.item.item.unit_value = _.round(unit_price, 2)
      this.item.item.input_unit_price_value = _.round(unit_price, 2)
      this.item.item.total = _.round(total, 2)
      this.item.item.total_base_igv = _.round(total_base_igv, 2)
      this.item.item.total_value = _.round(total_base_igv, 2)
      this.item.item.total_taxes = _.round(total_igv, 2)
      this.item.item.total_igv = _.round(total_igv, 2)

      this.item.item.total_value_without_rounding = total_base_igv
      this.item.item.total_base_igv_without_rounding = total_base_igv
      this.item.item.total_igv_without_rounding = total_igv
      this.item.item.total_taxes_without_rounding = total_igv
      this.item.item.total_without_rounding = total
      this.form.item = this.item
    },
    onSubmit() {
      this.loading = true
      this.$http
        .post(`/hotels/reception/${this.room.rent.id}/rent/extend-time`, this.form)
        .then((response) => {
          this.$message.success(response.data.message);
          this.closeDialog()
        })
        .catch((error) => this.axiosError(error))
        .finally(() => (this.loading = false));
    }
  },


}
</script>