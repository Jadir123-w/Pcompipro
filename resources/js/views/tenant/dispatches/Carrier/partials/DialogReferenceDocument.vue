<template>
  <el-dialog
    title="Añadir documento relacionado"
    :close-on-click-modal="false"
    :visible="showDialog"
    @close="close"
    @open="create">
    <el-form 
      :model="form"
      :rules="rules"
      ref="ReferenceDocumentForm"
      class="row"
      @submit.prevent="submit">
      
      <div class="col-12">
        <el-form-item v-if="dispatch_type_id == '09'" prop="name" class="form-group">
          <label class="control-label mb-0">Nombre de Empresa<span class="text-danger"> *</span></label>
          <el-input
            v-model="form.name"
            @input="enterAmount()">
          </el-input>
        </el-form-item>
      </div>
      
      <div class="col-6">
        <el-form-item prop="document_type_id" class="form-group">
          <label class="control-label mb-0">Tipo de documento<span class="text-danger"> *</span></label>
          <el-select v-model="form.document_type_id" placeholder="Tipo de documento">
            <el-option
              v-for="(row, index) in document_types"
              :key="index"
              :label="row.description"
              :value="row.id">
            </el-option>
          </el-select>
        </el-form-item>
      </div>
      
      <div class="col-6">
        <el-form-item prop="ruc" class="form-group">
          <label class="control-label mb-0">Número RUC<span class="text-danger"> *</span></label>
          <el-input v-model="form.ruc" :maxlength="11"></el-input>
        </el-form-item>
      </div>
      
      <div class="col-6">
        <el-form-item prop="serie" class="form-group">
          <label class="control-label mb-0">Serie de documento<span class="text-danger"> *</span></label>
          <el-input v-model="form.serie" :maxlength="4"></el-input>
        </el-form-item>
      </div>
      
      <div class="col-6">
        <el-form-item prop="number" class="form-group">
          <label class="control-label mb-0">Numero de documento<span class="text-danger"> *</span></label>
          <el-input v-model="form.number"></el-input>
        </el-form-item>
      </div>
      
    </el-form>
    <span slot="footer" class="dialog-footer">
      <el-button class="second-buton" @click="close">Cancelar</el-button>
      <el-button type="primary" @click="submit">Agregar</el-button>
    </span>
  </el-dialog>
</template>
<style>
.el-form-item__content {
  margin-left: 0px !important;
}
</style>
<script>
export default {
  props: ['showDialog', 'dispatch_type_id', 'document_data', 'supplierData'],
  data() {
    return {
      loading_dialog: false,
      form: {
        document_type_id: '09',
        serie: '',
        number: '',
        ruc: '',
        name: null,
      },
      document_types: [],
      rules: {
        document_type_id: [
          { required: true, message: 'Completar este campo', trigger: 'change' },
        ],
        serie: [
          { required: true, message: 'Completar este campo', trigger: 'blur' },
          { min: 4, max: 4, message: 'La serie está compuesta por 4 dígitos', trigger: 'blur' }
        ],
        number: [
          { required: true, message: 'Completar este campo', trigger: 'blur' },
        ],
        ruc: [
          { required: true, message: 'Completar este campo', trigger: 'blur' },
          { min: 11, max: 11, message: 'El RUC debe contener 11 dígitos', trigger: 'blur' }
        ],
        name: [
          { required: this.dispatch_type_id === '09', message: 'Completar este campo', trigger: 'blur' }
        ],
      }
    }
  },
  mounted() {
    if (this.dispatch_type_id == '09') {
      this.document_types = [
        { id: '01', description: 'Factura' },
        { id: '03', description: 'Boleta' },
      ]
    } else {
      this.document_types = [
        { id: '09', description: 'Guía de remisión remitente' }
      ]
    }
    if (Object.keys(this.$props.document_data).length > 0) {
      this.setFirstDocumentReference()
    }
  },
  watch: {
    showDialog: function(data) {
      this.initSupplierDataDocument();
    },
  },
  methods: {
    close() {
      this.initForm()
      this.$emit('update:showDialog', false)
    },
    create() {
      this.initForm()
    },
    submit() {
      this.$refs['ReferenceDocumentForm'].validate((valid) => {
        if (valid) {
          // formato listo para xml
          let row = {
            document_type: this.document_types.find(e => e.id === this.form.document_type_id),
            number: this.form.serie + '-' + this.form.number,
            customer: this.form.ruc,
            name: this.form.name
          }
          this.$emit('addReferenceDocument', row)
          this.close()
        } else {
          console.log('error submit!!');
          return false;
        }
      });
    },
    initForm() {
      this.form = {
        document_type_id: '',
        serie: '',
        number: '',
        ruc: '',
        name: null,
      }
      this.initSupplierDataDocument()
    },
    setFirstDocumentReference() {
      if (this.document_data.document_type_id == '01' || this.document_data.document_type_id == '03') {
        let row = {
          document_type: this.document_types.find(e => e.id === this.document_data.document_type_id),
          number: this.document_data.serie + '-' + this.document_data.number,
          customer: this.document_data.customer,
          name: this.document_data.name ? this.document_data.name : null,
        }
        this.$emit('addReferenceDocument', row)
      }
    },
    async initSupplierDataDocument() {
      if (this.supplierData && this.supplierData.identity_document_type_id) {
        const { name, number } = this.supplierData;
        this.form.ruc = number;
        this.form.name = name
      }
    },
  }
}
</script>