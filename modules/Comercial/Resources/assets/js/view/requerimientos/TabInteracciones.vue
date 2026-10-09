<!--
============================================================================
ARCHIVO: TabInteracciones.vue (VERSIÓN 3)
============================================================================
CAMBIOS EN V3:
- Funcionalidad de edición en interacciones existentes
- Orden por updated_at DESC (la editada sube al tope)
============================================================================
-->
<template>
  <div class="tab-interacciones">
    <el-alert
      v-if="!requerimientoId"
      title="Guarda el requerimiento antes de agregar interacciones"
      type="warning"
      :closable="false"
      style="margin-bottom: 20px;"
    />

    <div v-else class="interacciones-container">
      <!-- Formulario -->
      <el-card shadow="never" class="form-card">
        <div slot="header" class="card-header">
          <span>{{ editandoId ? 'Editar interacción' : 'Registrar nueva interacción' }}</span>
        </div>

        <el-form
          ref="formInteraccion"
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
              <el-form-item label="Canal" prop="canal" class="compact-item">
                <el-select v-model="formData.canal" placeholder="Seleccione canal" style="width: 100%">
                  <el-option value="Teléfono" label="Teléfono" />
                  <el-option value="WhatsApp" label="WhatsApp" />
                  <el-option value="Email" label="Email" />
                  <el-option value="Visita" label="Visita" />
                  <el-option value="Reunión" label="Reunión" />
                  <el-option value="Otro" label="Otro" />
                </el-select>
              </el-form-item>
            </el-col>

            <el-col :span="12">
              <el-form-item label="Próx. seguimiento" class="compact-item">
                <el-date-picker
                  v-model="formData.proximo_seguimiento"
                  type="date"
                  placeholder="YYYY-MM-DD"
                  format="yyyy-MM-dd"
                  value-format="yyyy-MM-dd"
                  style="width: 100%"
                />
              </el-form-item>
            </el-col>
          </el-row>

          <el-form-item label="Resumen" prop="resumen" class="compact-item">
            <el-input
              v-model="formData.resumen"
              type="textarea"
              :autosize="{minRows: 4, maxRows: 8}"
              placeholder="Describa lo conversado con el cliente..."
              maxlength="1000"
              show-word-limit
            />
          </el-form-item>

          <el-form-item class="compact-item">
            <el-checkbox v-model="formData.requiere_seguimiento">
              Requiere seguimiento
            </el-checkbox>
          </el-form-item>

          <el-form-item v-if="!editandoId" label="Archivo adjunto" class="compact-item">
            <input
              type="file"
              ref="fileInput"
              @change="onFileChange"
              accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
            />
            <div v-if="formData.archivo_adjunto" class="file-selected">
              <i class="el-icon-document"></i>
              {{ formData.archivo_adjunto.name }}
              <el-button type="text" icon="el-icon-close" @click="removeFile" size="mini" />
            </div>
          </el-form-item>

          <div class="form-actions">
            <el-button v-if="editandoId" @click="cancelarEdicion">
              Cancelar
            </el-button>
            <el-button type="primary" icon="el-icon-check" :loading="submitting" @click="handleSubmit">
              {{ editandoId ? 'Actualizar interacción' : 'Agregar interacción' }}
            </el-button>
          </div>
        </el-form>
      </el-card>

      <!-- Historial -->
      <div class="historial-section" v-loading="loading">
        <h3 class="historial-title">
          Historial de interacciones
          <span class="historial-count">({{ interacciones.length }})</span>
        </h3>

        <div v-if="interacciones.length === 0" class="empty-state">
          <i class="el-icon-chat-dot-square empty-icon"></i>
          <p>No hay interacciones registradas</p>
        </div>

        <div v-else class="interacciones-list">
          <div
            v-for="item in interacciones"
            :key="item.id"
            class="interaccion-card"
            :class="{ 'editando': editandoId === item.id }"
          >
            <div class="interaccion-header">
              <div class="interaccion-canal">
                <el-tag :type="getCanalTagType(item.canal)" size="small" effect="plain">
                  {{ item.canal || 'Sin canal' }}
                </el-tag>
              </div>
              <div class="interaccion-fecha">{{ item.created_at }}</div>
            </div>

            <div class="interaccion-resumen">
              <p>{{ item.resumen }}</p>
            </div>

            <div v-if="item.proximo_seguimiento" class="interaccion-seguimiento">
              <i class="el-icon-alarm-clock"></i>
              Próximo seguimiento: {{ item.proximo_seguimiento }}
            </div>

            <div class="interaccion-footer">
              <div class="interaccion-usuario">
                <i class="el-icon-user"></i>
                {{ item.usuario || item.usuario_nombre || 'Usuario' }}
              </div>

              <div class="interaccion-actions">
                <el-button v-if="item.archivo_adjunto" type="text" size="mini" icon="el-icon-document" @click="verArchivo(item.archivo_adjunto)">
                  Ver archivo
                </el-button>
                <el-button type="text" size="mini" icon="el-icon-edit" @click="iniciarEdicion(item)">
                  Editar
                </el-button>
              </div>
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
  name: 'TabInteracciones',

  props: {
    requerimientoId: {
      type: Number,
      default: null
    }
  },

  data: function() {
    return {
      editandoId: null,

      formData: {
        canal: '',
        resumen: '',
        proximo_seguimiento: null,
        requiere_seguimiento: false,
        archivo_adjunto: null
      },

      rules: {
        canal: [
          { required: true, message: 'Seleccione un canal', trigger: 'change' }
        ],
        resumen: [
          { required: true, message: 'Ingrese un resumen', trigger: 'blur' },
          { min: 10, message: 'Mínimo 10 caracteres', trigger: 'blur' }
        ]
      },

      interacciones: [],
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
          this.fetchInteracciones();
        }
      },
      immediate: true
    }
  },

  methods: {
    fetchInteracciones: function() {
      var self = this;
      if (!self.requerimientoId) return;

      self.loading = true;
      axios.get('/comercial/requerimientos/' + self.requerimientoId + '/interacciones', {
        headers: { 'Accept': 'application/json' }
      })
      .then(function(response) {
        var data = response.data;
        self.interacciones = (data && data.data) ? data.data : [];
      })
      .catch(function(error) {
        console.error('Error al cargar interacciones:', error);
        self.$message.error('Error al cargar interacciones');
      })
      .finally(function() {
        self.loading = false;
      });
    },

    iniciarEdicion: function(item) {
      this.editandoId = item.id;
      this.formData = {
        canal: item.canal || '',
        resumen: item.resumen || '',
        proximo_seguimiento: item.proximo_seguimiento || null,
        requiere_seguimiento: !!item.requiere_seguimiento,
        archivo_adjunto: null
      };
      if (this.$refs.formInteraccion) {
        this.$refs.formInteraccion.clearValidate();
      }
      this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    cancelarEdicion: function() {
      this.editandoId = null;
      this.resetForm();
    },

    onFileChange: function(event) {
      var file = event.target.files[0];
      if (file) {
        if (file.size > 10 * 1024 * 1024) {
          this.$message.error('El archivo no debe superar 10MB');
          this.$refs.fileInput.value = '';
          return;
        }
        this.formData.archivo_adjunto = file;
      }
    },

    removeFile: function() {
      this.formData.archivo_adjunto = null;
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = '';
      }
    },

    handleSubmit: function() {
      var self = this;
      self.$refs.formInteraccion.validate(function(valid) {
        if (valid) {
          if (self.editandoId) {
            self.submitUpdate();
          } else {
            self.submitForm();
          }
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
      formData.append('canal', self.formData.canal);
      formData.append('resumen', self.formData.resumen);
      formData.append('requiere_seguimiento', self.formData.requiere_seguimiento ? '1' : '0');

      if (self.formData.proximo_seguimiento) {
        formData.append('proximo_seguimiento', self.formData.proximo_seguimiento);
      }

      if (self.formData.archivo_adjunto) {
        formData.append('archivo_adjunto', self.formData.archivo_adjunto);
      }

      axios.post('/comercial/requerimientos/' + self.requerimientoId + '/interaccion', formData, {
        headers: {
          'X-CSRF-TOKEN': self.csrfToken,
          'Content-Type': 'multipart/form-data',
          'Accept': 'application/json'
        }
      })
      .then(function(response) {
        var data = response.data;
        if (data && data.ok) {
          self.$message.success('Interacción registrada');
          self.resetForm();
          self.fetchInteracciones();
          self.$emit('interaccion-added');
        } else {
          self.$message.error(data.msg || 'Error al guardar');
        }
      })
      .catch(function(error) {
        console.error('Error al guardar interacción:', error);
        var message = 'Error al guardar interacción';
        if (error.response && error.response.data) {
          if (error.response.data.msg) {
            message = error.response.data.msg;
          } else if (error.response.data.message) {
            message = error.response.data.message;
          }
        }
        self.$message.error(message);
      })
      .finally(function() {
        self.submitting = false;
      });
    },

    submitUpdate: function() {
      var self = this;
      self.submitting = true;

      axios.put(
        '/comercial/requerimientos/' + self.requerimientoId + '/interaccion/' + self.editandoId,
        {
          canal:                self.formData.canal,
          resumen:              self.formData.resumen,
          requiere_seguimiento: self.formData.requiere_seguimiento ? '1' : '0',
          proximo_seguimiento:  self.formData.proximo_seguimiento || null,
        },
        {
          headers: {
            'X-CSRF-TOKEN': self.csrfToken,
            'Accept': 'application/json'
          }
        }
      )
      .then(function(response) {
        var data = response.data;
        if (data && data.ok) {
          self.$message.success('Interacción actualizada');
          self.editandoId = null;
          self.resetForm();
          self.fetchInteracciones();
        } else {
          self.$message.error(data.msg || 'Error al actualizar');
        }
      })
      .catch(function(error) {
        console.error('Error al actualizar interacción:', error);
        self.$message.error('Error al actualizar la interacción');
      })
      .finally(function() {
        self.submitting = false;
      });
    },

    resetForm: function() {
      this.formData = {
        canal: '',
        resumen: '',
        proximo_seguimiento: null,
        requiere_seguimiento: false,
        archivo_adjunto: null
      };
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = '';
      }
      if (this.$refs.formInteraccion) {
        this.$refs.formInteraccion.clearValidate();
      }
    },

    getCanalTagType: function(canal) {
      var types = {
        'Teléfono': 'success',
        'WhatsApp': 'success',
        'Email': 'primary',
        'Visita': 'warning',
        'Reunión': 'info',
        'Otro': ''
      };
      return types[canal] || '';
    },

    verArchivo: function(url) {
      window.open('/storage/' + url, '_blank');
    }
  }
};
</script>

<style scoped>
.tab-interacciones {
  padding: 20px;
}

.interacciones-container {
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
  gap: 8px;
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

.interacciones-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

@media (max-width: 900px) {
  .interacciones-list {
    grid-template-columns: 1fr;
  }
}

.interaccion-card {
  border: 1px solid #a8afb6;
  border-radius: 10px;
  padding: 16px;
  background-color: #FFFFFF;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18), 0 1px 2px rgba(0, 0, 0, 0.06);
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.interaccion-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12), 0 2px 4px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.interaccion-card.editando {
  border-color: #409EFF;
  box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.2);
}

.interaccion-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.interaccion-fecha {
  font-size: 13px;
  color: #909399;
}

.interaccion-resumen p {
  margin: 0;
  color: #606266;
  font-size: 14px;
  line-height: 1.6;
}

.interaccion-seguimiento {
  background-color: #FFF7E6;
  border-left: 3px solid #E6A23C;
  padding: 8px 12px;
  margin: 12px 0;
  font-size: 13px;
  color: #606266;
}

.interaccion-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 12px;
  border-top: 1px solid #F2F6FC;
}

.interaccion-usuario {
  font-size: 13px;
  color: #909399;
}

.interaccion-actions {
  display: flex;
  gap: 4px;
}
</style>
