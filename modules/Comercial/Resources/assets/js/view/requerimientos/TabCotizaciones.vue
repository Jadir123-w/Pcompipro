<!--
============================================================================
ARCHIVO: TabCotizaciones.vue (VERSIÓN 2)
============================================================================
CAMBIOS EN V2:
- CSS corregido para labels (no se cortan)
============================================================================
-->
<template>
  <div class="tab-cotizaciones">
    <el-alert
      v-if="!requerimientoId"
      title="Guarda el requerimiento antes de agregar cotizaciones"
      type="warning"
      :closable="false"
      style="margin-bottom: 20px;"
    />

    <div v-else class="cotizaciones-container">
      <!-- Formulario (oculto): las cotizaciones las arma el cotizador del sistema y el
           PDF se genera al pedirlo; subir archivos aquí ya no aplica. Se quitará. -->
      <el-card v-if="mostrarSubirCotizacion" shadow="never" class="form-card">
        <div slot="header" class="card-header">
          <span>Subir nueva cotización</span>
        </div>

        <el-form
          ref="formCotizacion"
          :model="formData"
          :rules="rules"
          label-width="140px"
          label-position="left"
          size="small"
          class="form-compact"
          @submit.native.prevent="handleSubmit"
        >
          <el-row :gutter="12">
            <el-col :span="12">
              <el-form-item prop="archivo" class="compact-item">
                <span slot="label" class="lbl-tip" title="Archivo de la cotización a subir (PDF, Word o Excel).">Archivo PDF <i class="el-icon-info"></i></span>
                <input
                  type="file"
                  ref="fileInput"
                  @change="onFileChange"
                  accept=".pdf,.doc,.docx,.xls,.xlsx"
                />
                <div v-if="formData.archivo" class="file-selected">
                  <i class="el-icon-document"></i>
                  {{ formData.archivo.name }}
                  <el-button type="text" icon="el-icon-close" @click="removeFile" size="mini" />
                </div>
              </el-form-item>
            </el-col>

            <el-col :span="12">
              <el-form-item prop="monto" class="compact-item">
                <span slot="label" class="lbl-tip" title="Monto total de la cotización, en soles.">Monto (S/) <i class="el-icon-info"></i></span>
                <el-input v-model="formData.monto" type="number" step="0.01" min="0" placeholder="0.00">
                  <template slot="prepend">S/</template>
                </el-input>
              </el-form-item>
            </el-col>
          </el-row>

          <el-row :gutter="12">
            <el-col :span="12">
              <el-form-item prop="estado" class="compact-item">
                <span slot="label" class="lbl-tip" title="Situación actual de la cotización (enviada, aprobada, caída, etc.).">Estado <i class="el-icon-info"></i></span>
                <el-select v-model="formData.estado" placeholder="Seleccione estado" style="width: 100%">
                  <el-option label="Enviada" value="Enviada" />
                  <el-option label="Recotizada" value="Recotizada" />
                  <el-option label="Aprobada" value="Aprobada" />
                  <el-option label="Caída" value="Caida" />
                  <el-option label="No cotizado" value="No cotizado" />
                </el-select>
              </el-form-item>
            </el-col>

            <el-col :span="12" v-if="formData.estado === 'Caida'">
              <el-form-item prop="motivo_caida" class="compact-item">
                <span slot="label" class="lbl-tip" title="Razón por la que la cotización no prosperó.">Motivo caída <i class="el-icon-info"></i></span>
                <el-input v-model="formData.motivo_caida" placeholder="¿Por qué cayó la cotización?" />
              </el-form-item>
            </el-col>
          </el-row>

          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Notas adicionales sobre esta cotización.">Observaciones <i class="el-icon-info"></i></span>
            <el-input
              v-model="formData.observaciones"
              type="textarea"
              :autosize="{minRows: 3, maxRows: 7}"
              placeholder="Detalles de la cotización..."
              maxlength="500"
              show-word-limit
            />
          </el-form-item>

          <div class="form-actions">
            <el-button type="primary" icon="el-icon-upload" :loading="submitting" @click="handleSubmit">
              Agregar cotización
            </el-button>
          </div>
        </el-form>
      </el-card>

      <!-- Historial -->
      <div class="historial-section" v-loading="loading">
        <h3 class="historial-title">
          Historial de cotizaciones
          <span class="historial-count">({{ cotizaciones.length }})</span>
        </h3>

        <div v-if="cotizaciones.length === 0" class="empty-state">
          <i class="el-icon-document empty-icon"></i>
          <p>No hay cotizaciones registradas</p>
        </div>

        <div v-else class="cotizaciones-list">
          <div
            v-for="cot in cotizaciones"
            :key="cot.id"
            class="cotizacion-card"
            :class="{'cotizacion-aprobada': cot.estado === 'Aprobada'}"
          >
            <div class="cotizacion-header">
              <div class="cotizacion-titulo">
                <strong>Cotización</strong>
                <span class="cotizacion-version">v{{ cot.version }}</span>
              </div>
              <div class="cotizacion-link">
                <el-button type="text" size="small" icon="el-icon-view" @click="verPDF(cot.archivo_url)">
                  Ver PDF
                </el-button>
              </div>
            </div>

            <div class="cotizacion-monto">
              <div class="monto-label">Monto</div>
              <div class="monto-value">S/ {{ formatMoney(cot.monto) }}</div>
            </div>

            <div class="cotizacion-detalles">
              <div class="detalle-item">
                <i class="el-icon-date"></i>
                {{ cot.created_at }}
              </div>
              
              <div class="detalle-item" v-if="cot.observaciones">
                <i class="el-icon-tickets"></i>
                {{ cot.observaciones }}
              </div>

              <div class="detalle-item" v-if="cot.motivo_caida">
                <i class="el-icon-warning"></i>
                <strong>Motivo caída:</strong> {{ cot.motivo_caida }}
              </div>

              <div class="detalle-item">
                <i class="el-icon-user"></i>
                {{ cot.usuario || 'Usuario' }}
              </div>
            </div>

            <div class="cotizacion-estado">
              <el-tag :type="getEstadoTagType(cot.estado)" size="medium" effect="plain">
                {{ cot.estado }}
              </el-tag>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'TabCotizaciones',

  props: {
    requerimientoId: {
      type: Number,
      default: null
    }
  },

  data: function() {
    return {
      // "Subir nueva cotización" oculto: lo reemplaza el cotizador (Pre-cotización).
      mostrarSubirCotizacion: false,
      formData: {
        archivo: null,
        monto: null,
        estado: 'Enviada',
        motivo_caida: '',
        observaciones: ''
      },

      rules: {
        monto: [
          { required: true, message: 'Ingrese el monto', trigger: 'blur' }
        ],
        estado: [
          { required: true, message: 'Seleccione un estado', trigger: 'change' }
        ]
      },

      cotizaciones: [],
      loading: false,
      submitting: false,
      csrfToken: ''
    };
  },

  mounted: function() {
    var meta = document.head.querySelector('meta[name="csrf-token"]');
    this.csrfToken = meta ? meta.content : '';
  },

  watch: {
    requerimientoId: {
      handler: function(newVal) {
        if (newVal) {
          this.fetchCotizaciones();
        }
      },
      immediate: true
    },
    'formData.estado': function(newVal) {
      if (newVal !== 'Caida') {
        this.formData.motivo_caida = '';
      }
    }
  },

  methods: {
    fetchCotizaciones: function() {
      var self = this;
      if (!self.requerimientoId) return;

      self.loading = true;
      axios.get('/comercial/requerimientos/' + self.requerimientoId + '/cotizaciones', {
        headers: { 'Accept': 'application/json' }
      })
      .then(function(response) {
        var data = response.data;
        self.cotizaciones = (data && data.data) ? data.data : [];
      })
      .catch(function(error) {
        console.error('Error al cargar cotizaciones:', error);
        self.$message.error('Error al cargar cotizaciones');
      })
      .finally(function() {
        self.loading = false;
      });
    },

    onFileChange: function(event) {
      var file = event.target.files[0];
      if (file) {
        if (file.size > 20 * 1024 * 1024) {
          this.$message.error('El archivo no debe superar 20MB');
          this.$refs.fileInput.value = '';
          return;
        }
        this.formData.archivo = file;
      }
    },

    removeFile: function() {
      this.formData.archivo = null;
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = '';
      }
    },

    handleSubmit: function() {
      var self = this;
      
      if (!self.formData.archivo) {
        self.$message.warning('Seleccione un archivo');
        return;
      }

      if (self.formData.estado === 'Caida' && !self.formData.motivo_caida) {
        self.$message.warning('Indique el motivo de caída');
        return;
      }

      self.$refs.formCotizacion.validate(function(valid) {
        if (valid) {
          self.submitForm();
        } else {
          self.$message.warning('Complete los campos obligatorios');
          return false;
        }
      });
    },

    submitForm: function() {
      var self = this;
      self.submitting = true;

      var formData = new FormData();
      formData.append('archivo', self.formData.archivo);
      formData.append('monto', self.formData.monto);
      formData.append('estado', self.formData.estado);
      
      if (self.formData.observaciones) {
        formData.append('observaciones', self.formData.observaciones);
      }
      
      if (self.formData.estado === 'Caida' && self.formData.motivo_caida) {
        formData.append('motivo_caida', self.formData.motivo_caida);
      }

      axios.post('/comercial/requerimientos/' + self.requerimientoId + '/cotizacion', formData, {
        headers: {
          'X-CSRF-TOKEN': self.csrfToken,
          'Content-Type': 'multipart/form-data',
          'Accept': 'application/json'
        }
      })
      .then(function(response) {
        var data = response.data;
        if (data && data.ok) {
          self.$message.success('Cotización subida exitosamente');
          self.resetForm();
          self.fetchCotizaciones();
          self.$emit('cotizacion-added');
        } else {
          self.$message.error(data.msg || 'Error al guardar');
        }
      })
      .catch(function(error) {
        console.error('Error al subir cotización:', error);
        var message = 'Error al guardar';
        if (error.response && error.response.data && error.response.data.msg) {
          message = error.response.data.msg;
        }
        self.$message.error(message);
      })
      .finally(function() {
        self.submitting = false;
      });
    },

    resetForm: function() {
      this.formData = {
        archivo: null,
        monto: null,
        estado: 'Enviada',
        motivo_caida: '',
        observaciones: ''
      };
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = '';
      }
      if (this.$refs.formCotizacion) {
        this.$refs.formCotizacion.clearValidate();
      }
    },

    getEstadoTagType: function(estado) {
      var types = {
        'Enviada': 'info',
        'Recotizada': 'warning',
        'Aprobada': 'success',
        'Caida': 'danger',
        'No cotizado': ''
      };
      return types[estado] || '';
    },

    verPDF: function(url) {
      window.open('/storage/' + url, '_blank');
    },

    formatMoney: function(amount) {
      if (!amount) return '0.00';
      return parseFloat(amount).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }
  }
};
</script>

<style scoped>
.tab-cotizaciones {
  padding: 20px;
}

.cotizaciones-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.form-card {
  border-radius: 8px;
}

.card-header {
  font-weight: 600;
  font-size: 16px;
}

/* ✅ CSS para labels que no se corten */
.form-compact >>> .el-form-item {
  margin-bottom: 12px;
}

.form-compact >>> .el-form-item__label {
  font-size: 13px;
  color: #606266;
  line-height: 32px;
  white-space: nowrap;
  overflow: visible;
}

.compact-item {
  margin-bottom: 12px !important;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.file-selected {
  margin-top: 8px;
  padding: 8px 12px;
  background-color: #F5F7FA;
  border-radius: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.historial-section .historial-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 16px;
  color: #303133;
}

.historial-count {
  color: #909399;
  font-weight: 400;
  font-size: 14px;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #909399;
}

.empty-icon {
  font-size: 64px;
  color: #DCDFE6;
  margin-bottom: 16px;
}

.cotizaciones-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

@media (max-width: 900px) {
  .cotizaciones-list {
    grid-template-columns: 1fr;
  }
}

.cotizacion-card {
  border: 1px solid #a8afb6;
  border-radius: 10px;
  padding: 20px;
  background-color: #FFFFFF;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18), 0 1px 2px rgba(0, 0, 0, 0.06);
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.cotizacion-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12), 0 2px 4px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.cotizacion-card.cotizacion-aprobada {
  border-color: #67C23A;
  border-width: 2px;
  background-color: #f6ffed;
}

.cotizacion-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.cotizacion-titulo {
  display: flex;
  align-items: center;
  gap: 8px;
}

.cotizacion-version {
  font-size: 13px;
  color: #909399;
  background-color: #F2F6FC;
  padding: 2px 8px;
  border-radius: 4px;
}

.cotizacion-monto {
  text-align: center;
  margin: 20px 0;
  padding: 16px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 8px;
}

.monto-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.8);
  margin-bottom: 4px;
}

.monto-value {
  font-size: 28px;
  font-weight: 700;
  color: #FFFFFF;
}

.cotizacion-detalles {
  margin-bottom: 16px;
}

.detalle-item {
  padding: 8px 0;
  font-size: 14px;
  color: #606266;
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.detalle-item i {
  color: #909399;
  margin-top: 2px;
}

.cotizacion-estado {
  display: flex;
  justify-content: center;
  padding-top: 12px;
  border-top: 1px solid #F2F6FC;
}

/* Tooltips de ayuda en labels del formulario.
   z-index/position evita que el contenido del form-item (overflow visible)
   capture el mouse y tape el label, igual que en la pestaña Datos. */
.lbl-tip { position: relative; z-index: 2; cursor: help; }
.lbl-tip i { color: #909399; font-size: 13px; margin-left: 3px; vertical-align: middle; }
</style>
