<!--
============================================================================
ARCHIVO: TabDatos.vue (VERSIÓN 5)
============================================================================
CAMBIOS EN V5:
- Campo "Usuario apoyo" ahora es un el-select filterable con búsqueda remota
- Usa endpoint /comercial/requerimientos/usuarios-activos
- Muestra nombre del usuario, guarda ID
============================================================================
-->
<template>
  <div class="tab-datos">
    <!-- Mensaje para nuevo requerimiento -->
    <el-alert
      v-if="isNewRecord"
      title="Nuevo Requerimiento"
      type="info"
      description="Completa los campos y guarda."
      :closable="false"
      show-icon
      style="margin-bottom: 15px;"
    />

    <el-form
      ref="formDatos"
      :disabled="soloLectura"
      :model="formData"
      :rules="rules"
      label-width="130px"
      label-position="left"
      size="small"
      @submit.native.prevent="handleSubmit"
      class="form-compact"
    >
      <!-- Fila 1: Empresa y Contacto -->
      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item prop="empresa_id" class="compact-item">
            <span slot="label" class="lbl-tip" title="Empresa cliente para la que se cotiza. Escribe para buscar por razón social o nombre comercial.">Empresa <i class="el-icon-info"></i></span>
            <div class="input-with-button">
              <el-select
                v-model="formData.empresa_id"
                placeholder="Busca y selecciona empresa"
                filterable
                remote
                :disabled="!isNewRecord"
                :remote-method="searchEmpresas"
                :loading="loadingEmpresas"
                @change="onEmpresaChange"
                class="flex-input"
              >
                <el-option
                  v-for="empresa in empresasOptions"
                  :key="empresa.id"
                  :label="empresa.nombre_comercial || empresa.razon_social"
                  :value="empresa.id"
                />
              </el-select>
              <el-button type="warning" size="small"
                         :disabled="!isNewRecord"
                         @click="showNuevaEmpresaDialog">+ Nuevo</el-button>
            </div>
            <div class="input-help">Escribe para filtrar empresas.</div>
          </el-form-item>
        </el-col>

        <el-col :span="12">
          <el-form-item prop="contacto_id" class="compact-item">
            <span slot="label" class="lbl-tip" title="Persona de contacto en la empresa; solo aparecen los contactos de la empresa seleccionada.">Contacto <i class="el-icon-info"></i></span>
            <div class="input-with-button">
              <el-select
                v-model="formData.contacto_id"
                placeholder="Seleccione contacto"
                filterable
                clearable
                :loading="loadingContactos"
                :disabled="!formData.empresa_id"
                class="flex-input"
              >
                <el-option
                  v-for="contacto in contactosOptions"
                  :key="contacto.id"
                  :label="contacto.nombre_completo"
                  :value="contacto.id"
                />
              </el-select>
              <el-button type="warning" size="small"
                         :disabled="!isNewRecord || !formData.empresa_id"
                         @click="showNuevoContactoDialog">+ Nuevo</el-button>
            </div>
            <div class="input-help">Solo contactos de la empresa seleccionada.</div>
          </el-form-item>
        </el-col>
      </el-row>

      <!-- Fila 2: Título -->
      <el-row>
        <el-col :span="24">
          <el-form-item prop="titulo" class="compact-item">
            <span slot="label" class="lbl-tip" title="Nombre corto que identifica el requerimiento; será el nombre del proyecto si se aprueba.">Nombre del Requerimiento <i class="el-icon-info"></i></span>
            <el-input
              v-model="formData.titulo"
              placeholder="Ej: Cotizar 100 uniformes corporativos"
              maxlength="191"
              show-word-limit
            />
          </el-form-item>
        </el-col>
      </el-row>

      <!-- Fila 3: Descripción -->
      <el-row>
        <el-col :span="24">
          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Detalle de lo que pide el cliente: productos, cantidades, especificaciones.">Descripción <i class="el-icon-info"></i></span>
            <el-input
              v-model="formData.descripcion"
              type="textarea"
              :autosize="{minRows: 3, maxRows: 7}"
              placeholder="Detalle del requerimiento, productos, cantidades..."
              maxlength="1000"
              show-word-limit
            />
          </el-form-item>
        </el-col>
      </el-row>

      <!-- Fila 4: Origen, Tipo condición, Nivel tentativo, Fecha requerida -->
      <el-row :gutter="15">
        <el-col :span="6">
          <el-form-item prop="origen" class="compact-item">
            <span slot="label" class="lbl-tip" title="Canal por el que llegó el requerimiento (fidelizado, referido, campaña de marketing, etc.).">Origen <i class="el-icon-info"></i></span>
            <el-select v-model="formData.origen" placeholder="Seleccione" style="width: 100%;">
              <el-option label="FIDELIZADO" value="FIDELIZADO" />
              <el-option label="REFERIDO" value="REFERIDO" />
              <el-option label="OP. COMERCIAL" value="OP. COMERCIAL" />
              <el-option label="MKTG - RRSS" value="MKTG - RRSS" />
              <el-option label="MKTG - MAIL" value="MKTG - MAIL" />
              <el-option label="MKTG - C.PAGO" value="MKTG - C.PAGO" />
              <el-option label="MKTG - WSP PAGADO" value="MKTG - WSP PAGADO" />
              <el-option label="MKTG - WSP MANUAL" value="MKTG - WSP MANUAL" />
              <el-option label="OTROS" value="OTROS" />
            </el-select>
          </el-form-item>
        </el-col>

        <el-col :span="6">
          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Urgencia de atención del requerimiento (Estándar, Importante, Urgente, Muy urgente).">Tipo condición <i class="el-icon-info"></i></span>
            <el-select v-model="formData.tipo_condicion_proyecto" placeholder="Seleccione" clearable style="width: 100%;">
              <el-option label="Estándar" value="Estándar" />
              <el-option label="Importante" value="Importante" />
              <el-option label="Urgente" value="Urgente" />
              <el-option label="Muy urgente" value="Muy urgente" />
            </el-select>
          </el-form-item>
        </el-col>

        <el-col :span="6">
          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Probabilidad estimada de que el requerimiento se concrete en venta.">Nivel tentativa <i class="el-icon-info"></i></span>
            <el-select v-model="formData.nivel_tentativo" placeholder="Seleccione" clearable style="width: 100%;">
              <el-option label="Alto" value="Alto" />
              <el-option label="Medio" value="Medio" />
              <el-option label="Bajo" value="Bajo" />
            </el-select>
          </el-form-item>
        </el-col>

        <el-col :span="6">
          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Fecha en que el cliente necesita recibir el pedido.">Fecha Entrega <i class="el-icon-info"></i></span>
            <el-date-picker
              v-model="formData.fecha_requerida"
              type="date"
              placeholder="dd/mm/aaaa"
              format="dd/MM/yyyy"
              value-format="yyyy-MM-dd"
              style="width: 100%;"
            />
          </el-form-item>
        </el-col>
      </el-row>

      <!-- Fila 5: Motivo compra, Forma pago, Categoría, Monto estimado -->
      <el-row :gutter="15">
        <el-col :span="6">
          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Razón de la compra del cliente (campaña, evento, reposición, etc.).">Motivo compra <i class="el-icon-info"></i></span>
            <el-input v-model="formData.motivo_compra" placeholder="" />
          </el-form-item>
        </el-col>

        <el-col :span="6">
          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Condición de pago acordada o propuesta con el cliente.">Forma de Pago <i class="el-icon-info"></i></span>
            <el-select v-model="formData.forma_pago" placeholder="Seleccione" clearable filterable style="width: 100%;">
              <el-option v-for="opt in formasPagoOptions" :key="opt" :label="opt" :value="opt" />
            </el-select>
          </el-form-item>
        </el-col>

        <el-col :span="6">
          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Tipo de producto o servicio solicitado, para clasificar el requerimiento.">Categoría <i class="el-icon-info"></i></span>
            <el-select v-model="formData.categoria_productos" placeholder="Seleccione" clearable style="width: 100%;">
              <el-option label="KITS CORPORATIVOS" value="KITS CORPORATIVOS" />
              <el-option label="M. IMPORTADOS" value="M. IMPORTADOS" />
              <el-option label="M. NACIONAL" value="M. NACIONAL" />
              <el-option label="TEXTIL" value="TEXTIL" />
              <el-option label="IMPRENTA" value="IMPRENTA" />
              <el-option label="WEB/MAIL" value="WEB/MAIL" />
              <el-option label="PUB. EXTERIOR" value="PUB. EXTERIOR" />
              <el-option label="SERV DISEÑO" value="SERV DISEÑO" />
              <el-option label="UPCYCLING" value="UPCYCLING" />
              <el-option label="OTROS" value="OTROS" />
            </el-select>
          </el-form-item>
        </el-col>

        <el-col :span="6">
          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Monto referencial estimado del requerimiento (se calcula desde la pre-cotización).">Monto Estimado (S/) <i class="el-icon-info"></i></span>
            <el-input v-model="formData.monto_estimado" type="number" step="0.01" min="0" placeholder="0.00" disabled />
          </el-form-item>
        </el-col>
      </el-row>

      <!-- Fila 6: Prioridad, Etapa, Usuario apoyo -->
      <el-row :gutter="15">
        <el-col :span="8">
          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Prioridad de atención asignada internamente al requerimiento.">Prioridad <i class="el-icon-info"></i></span>
            <el-select v-model="formData.nivel_prioridad" placeholder="Seleccione" style="width: 100%;">
              <el-option label="Muy alto" value="Muy alto" />
              <el-option label="Alto" value="Alto" />
              <el-option label="Media" value="Medio" />
              <el-option label="Bajo" value="Bajo" />
            </el-select>
            <el-button
              type="text"
              size="mini"
              icon="el-icon-refresh"
              @click="calcularPrioridadAutomatica"
              :loading="calculandoPrioridad"
              :disabled="isNewRecord"
              style="margin-top: 4px; padding: 0;"
            >
              Calcular automáticamente
            </el-button>
          </el-form-item>
        </el-col>

        <el-col :span="8">
          <el-form-item prop="etapa" class="compact-item">
            <span slot="label" class="lbl-tip" title="Estado del requerimiento en el flujo comercial (pendiente, en cotización, aprobada, etc.).">Etapa <i class="el-icon-info"></i></span>
            <el-select v-model="formData.etapa" placeholder="Seleccione" style="width: 100%;">
              <el-option label="Pendiente Atender" value="Pendiente atender" />
              <el-option label="En cotización" value="En cotización" />
              <!-- Las etapas de cotización solo se ofrecen al EDITAR (isNewRecord=false).
                   Al crear no se puede "nacer" en una etapa avanzada; se llega por el
                   flujo (cotizar, aprobar) o moviendo la tarjeta en el tablero. -->
              <el-option v-if="!isNewRecord" label="Cotización enviada" value="Cotización enviada" />
              <el-option v-if="!isNewRecord" label="En Re-cotización" value="En Re-cotización" />
              <el-option v-if="!isNewRecord" label="Cotización aprobada" value="Cotización aprobada" />
              <el-option v-if="!isNewRecord" label="Cotización caída" value="Cotización caída" />
              <el-option v-if="!isNewRecord" label="No cotizado" value="No cotizado" />
              <!-- Etapa automática (convertido a proyecto): se muestra para consulta,
                   pero no se ofrece para selección manual. -->
              <el-option v-if="soloLectura || formData.etapa === 'Convertido a proyecto'"
                         label="Convertido a proyecto" value="Convertido a proyecto" />
            </el-select>
            <div v-if="isNewRecord" style="font-size:11px;color:#909399;line-height:1.35;margin-top:3px;">
              Las etapas de cotización se alcanzan al avanzar en el flujo (cotizar, aprobar) o desde el tablero.
            </div>
          </el-form-item>
        </el-col>

        <el-col :span="8">
          <el-form-item class="compact-item">
            <span slot="label" class="lbl-tip" title="Usuario adicional que apoya en la gestión o cotización del requerimiento.">Usuario apoyo <i class="el-icon-info"></i></span>
            <el-select
              v-model="formData.usuario_apoyo_cotizacion"
              placeholder="Buscar usuario..."
              filterable
              remote
              clearable
              :remote-method="searchUsuarios"
              :loading="loadingUsuarios"
              style="width: 100%;"
            >
              <el-option
                v-for="usuario in usuariosOptions"
                :key="usuario.id"
                :label="usuario.name"
                :value="usuario.id"
              />
            </el-select>
            <div class="input-help">Usuario que apoyará en la cotización</div>
          </el-form-item>
        </el-col>
      </el-row>

      <!-- Botón guardar (oculto en modo solo lectura / convertido a proyecto) -->
      <el-row v-if="!soloLectura">
        <el-col :span="24" style="text-align: right; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ebeef5;">
          <el-tag v-if="isFormDirty" type="warning" size="mini" effect="plain" style="margin-right: 8px;">
            Tienes cambios sin guardar
          </el-tag>
          <el-button type="success" :loading="submitting" @click="handleSubmit">
            <i class="el-icon-check"></i> Guardar
          </el-button>
        </el-col>
      </el-row>
    </el-form>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'TabDatos',

  props: {
    requerimiento: {
      type: Object,
      default: function() { return null; }
    },
    isNewRecord: {
      type: Boolean,
      default: false
    },
    // Modo consulta: cuando el requerimiento ya fue convertido a proyecto,
    // se deshabilita todo el formulario y se oculta el botón Guardar.
    soloLectura: {
      type: Boolean,
      default: false
    }
  },

  data: function() {
    return {
      formData: {
        empresa_id: null,
        contacto_id: null,
        titulo: '',
        descripcion: '',
        origen: '',
        tipo_condicion_proyecto: null,
        nivel_tentativo: null,
        fecha_requerida: null,
        motivo_compra: '',
        forma_pago: '',
        categoria_productos: '',
        monto_estimado: null,
        nivel_prioridad: 'Medio',
        etapa: 'Pendiente atender',
        usuario_apoyo_cotizacion: null
      },

      rules: {
        empresa_id: [
          { required: true, message: 'Seleccione una empresa', trigger: 'change' }
        ],
        titulo: [
          { required: true, message: 'Ingrese un título', trigger: 'blur' }
        ],
        origen: [
          { required: true, message: 'Seleccione un origen', trigger: 'change' }
        ],
        etapa: [
          { required: true, message: 'Seleccione una etapa', trigger: 'change' }
        ]
      },

      empresasOptions: [],
      contactosOptions: [],
      usuariosOptions: [],

      // Opciones de Forma de Pago según Requerimientos_27_05_26.pdf página 6.
      // La misma lista se sirve desde ProyectosController::tables para el módulo
      // Proyecto. Si se modifica una, actualizar la otra.
      formasPagoOptions: [
        '50% adelanto / 50% contra entrega',
        '50% adelanto / 50% crédito',
        '100% contra entrega',
        'Al Contado',
        'Crédito 5 días',
        'Crédito 7 días',
        'Crédito 15 días',
        'Crédito 20 días',
        'Crédito 30 días',
        'Crédito 45 días',
        'Crédito 60 días',
        'Crédito 90 días',
        'Crédito 120 días',
        'Factura negociable',
        'Factoring',
        'Cheque',
        'Otros',
      ],

      loadingEmpresas: false,
      loadingContactos: false,
      loadingUsuarios: false,
      submitting: false,
      calculandoPrioridad: false,

      csrfToken: '',
      _pendingContactoId: null,
      // Foto de los últimos datos guardados/cargados para detectar cambios
      // sin guardar (la usa el padre al cambiar de pestaña o cerrar el modal).
      formSnapshot: null
    };
  },

  mounted: function() {
    var meta = document.head.querySelector('meta[name="csrf-token"]');
    this.csrfToken = meta ? meta.content : '';

    this.initializeForm();
    this.fetchUsuariosIniciales();
  },

  watch: {
    requerimiento: {
      handler: function(newVal) {
        if (newVal) {
          this.loadFormData(newVal);
        }
      },
      immediate: true
    }
  },

  computed: {
    // true si el formulario difiere de lo último guardado/cargado.
    // Hace que el tag "Sin guardar" reaccione a cada tecla.
    isFormDirty: function() {
      if (this.soloLectura || !this.formSnapshot) return false;
      try {
        return JSON.stringify(this.formData) !== this.formSnapshot;
      } catch (e) {
        return false;
      }
    }
  },

  methods: {
    initializeForm: function() {
      if (this.requerimiento && !this.isNewRecord) {
        this.loadFormData(this.requerimiento);
      } else {
        this.captureSnapshot();
      }
    },

    // Guarda la foto actual del formulario para comparar después.
    captureSnapshot: function() {
      try {
        this.formSnapshot = JSON.stringify(this.formData);
      } catch (e) {
        this.formSnapshot = null;
      }
    },

    // La usa el padre (modal) antes de cambiar de pestaña o cerrar.
    hasUnsavedChanges: function() {
      return !!this.isFormDirty;
    },

    // Revierte el formulario a lo último guardado/cargado.
    discardChanges: function() {
      if (!this.formSnapshot) return;
      try {
        this.formData = JSON.parse(this.formSnapshot);
      } catch (e) {
        // Sin foto válida no hay nada que revertir.
      }
    },

    loadFormData: function(data) {
      this.formData = {
        empresa_id: data.empresa_id || null,
        contacto_id: data.empresa_id ? null : (data.contacto_id || null), // se asigna tras cargar contactos
        titulo: data.titulo || '',
        descripcion: data.descripcion || '',
        origen: data.origen || '',
        tipo_condicion_proyecto: data.tipo_condicion_proyecto || null,
        nivel_tentativo: data.nivel_tentativo || null,
        fecha_requerida: data.fecha_requerida || null,
        motivo_compra: data.motivo_compra || '',
        forma_pago: data.forma_pago || '',
        categoria_productos: data.categoria_productos || '',
        monto_estimado: data.monto_estimado || null,
        nivel_prioridad: data.nivel_prioridad || 'Medio',
        etapa: data.etapa || 'Pendiente atender',
        usuario_apoyo_cotizacion: data.usuario_apoyo_cotizacion || null
      };

      if (data.empresa_id) {
        this.empresasOptions = [{
          id: data.empresa_id,
          nombre_comercial: data.empresa_nombre_comercial || data.empresa_razon_social,
          razon_social: data.empresa_razon_social
        }];
        // Guardar el contacto_id pendiente y asignarlo después de cargar los contactos
        this._pendingContactoId = data.contacto_id || null;
        this.fetchContactosByEmpresa(data.empresa_id);
      }

      // Cargar usuario de apoyo en el select si existe
      if (data.usuario_apoyo_cotizacion && data.usuario_apoyo_nombre) {
        var usuarioExiste = this.usuariosOptions.some(function(u) {
          return u.id === data.usuario_apoyo_cotizacion;
        });
        if (!usuarioExiste) {
          this.usuariosOptions.push({
            id: data.usuario_apoyo_cotizacion,
            name: data.usuario_apoyo_nombre
          });
        }
      }

      this.captureSnapshot();
    },

    searchEmpresas: function(query) {
        var self = this;
        if (!query || query.length < 2) return;

        self.loadingEmpresas = true;
        axios.get('/comercial/empresas/search', {
            params: { search: query },
            headers: { 'Accept': 'application/json' }
        })
        .then(function(response) {
            var result = response.data;
            // El endpoint devuelve paginate(), los datos están en .data
            var data = Array.isArray(result) ? result : (result.data || []);
            self.empresasOptions = data.filter(function(e) { return e && e.id; });
        })
        .catch(function(error) {
            console.error('Error al buscar empresas:', error);
        })
        .finally(function() {
            self.loadingEmpresas = false;
        });
    },
    onEmpresaChange: function(empresaId) {
      this.formData.contacto_id = null;
      this.contactosOptions = [];
      
      if (empresaId) {
        this.fetchContactosByEmpresa(empresaId);
      }
    },

    fetchContactosByEmpresa: function(empresaId) {
      var self = this;
      self.loadingContactos = true;

      axios.get('/comercial/contactos/empresa/' + empresaId, {
        headers: { 'Accept': 'application/json' }
      })
      .then(function(response) {
        var contactos = [];
        var data = response.data;

        if (Array.isArray(data)) {
          contactos = data;
        } else if (data && data.data) {
          contactos = data.data;
        }

        self.contactosOptions = contactos.map(function(c) {
          return {
            id: c.id,
            nombre_completo: ((c.nombres || '') + ' ' + (c.apellidos || '')).trim() || 'Sin nombre',
            nombres: c.nombres,
            apellidos: c.apellidos
          };
        });

        // Asignar contacto_id pendiente ahora que las opciones ya están cargadas
        if (self._pendingContactoId) {
          self.formData.contacto_id = self._pendingContactoId;
          self._pendingContactoId = null;
          self.captureSnapshot();
        }

        console.log('Contactos cargados:', self.contactosOptions);
      })
      .catch(function(error) {
        console.error('Error al obtener contactos:', error);
        self.contactosOptions = [];
      })
      .finally(function() {
        self.loadingContactos = false;
      });
    },

    searchUsuarios: function(query) {
      var self = this;
      if (!query || query.length < 2) {
        // Cargar usuarios iniciales si no hay query
        if (!query && self.usuariosOptions.length === 0) {
          self.fetchUsuariosIniciales();
        }
        return;
      }

      self.loadingUsuarios = true;
      axios.get('/comercial/requerimientos/usuarios-activos', {
        params: { q: query },
        headers: { 'Accept': 'application/json' }
      })
      .then(function(response) {
        if (response.data && response.data.ok) {
          self.usuariosOptions = response.data.data || [];
        }
      })
      .catch(function(error) {
        console.error('Error al buscar usuarios:', error);
      })
      .finally(function() {
        self.loadingUsuarios = false;
      });
    },

    fetchUsuariosIniciales: function() {
      var self = this;
      self.loadingUsuarios = true;
      axios.get('/comercial/requerimientos/usuarios-activos', {
        headers: { 'Accept': 'application/json' }
      })
      .then(function(response) {
        if (response.data && response.data.ok) {
          self.usuariosOptions = response.data.data || [];
        }
      })
      .catch(function(error) {
        console.error('Error al cargar usuarios:', error);
      })
      .finally(function() {
        self.loadingUsuarios = false;
      });
    },

    calcularPrioridadAutomatica: function() {
      var self = this;
      if (!self.requerimiento || self.isNewRecord) {
        self.$message.warning('Guarde el requerimiento primero');
        return;
      }

      self.calculandoPrioridad = true;
      axios.post('/comercial/requerimientos/' + self.requerimiento.id + '/calcular-prioridad', {}, {
        headers: {
          'X-CSRF-TOKEN': self.csrfToken,
          'Accept': 'application/json'
        }
      })
      .then(function(response) {
        var data = response.data;
        if (data && data.ok && data.data) {
          self.formData.nivel_prioridad = data.data.nivel;
          self.$message.success('Prioridad calculada: ' + data.data.nivel + ' (' + data.data.puntaje + '/10 puntos)');
        } else {
          self.$message.error(data.msg || 'Error al calcular');
        }
      })
      .catch(function(error) {
        console.error('Error al calcular prioridad:', error);
        self.$message.error('Error al calcular prioridad');
      })
      .finally(function() {
        self.calculandoPrioridad = false;
      });
    },

    handleSubmit: function() {
      var self = this;
      self.$refs.formDatos.validate(function(valid) {
        if (valid) {
          self.submitForm();
        } else {
          self.$message.warning('Por favor complete los campos obligatorios');
          return false;
        }
      });
    },

    submitForm: function() {
      var self = this;
      self.submitting = true;
      
      var url, method;
      if (self.isNewRecord) {
        url = '/comercial/requerimientos';
        method = 'post';
      } else {
        url = '/comercial/requerimientos/' + self.requerimiento.id;
        method = 'put';
      }

      var dataToSend = {};
      for (var key in self.formData) {
        if (self.formData[key] !== null && self.formData[key] !== '') {
          dataToSend[key] = self.formData[key];
        }
      }

      axios({
        method: method,
        url: url,
        data: dataToSend,
        headers: {
          'X-CSRF-TOKEN': self.csrfToken,
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      })
      .then(function(response) {
        var data = response.data;
        if (data && data.ok) {
          self.captureSnapshot();
          if (self.isNewRecord) {
            self.$message.success('Requerimiento creado exitosamente');
            self.$emit('created', data.data);
          } else {
            self.$message.success('Requerimiento actualizado');
            self.$emit('updated', data.data);
          }
        } else {
          self.$message.error(data.msg || 'Error al guardar');
        }
      })
      .catch(function(error) {
        console.error('Error al guardar:', error);
        var message = 'Error al guardar';
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

    showNuevaEmpresaDialog: function() {
      this.$emit('show-nueva-empresa-dialog');
    },

    showNuevoContactoDialog: function() {
      if (!this.formData.empresa_id) {
        this.$message.warning('Seleccione una empresa primero');
        return;
      }
      this.$emit('show-nuevo-contacto-dialog', this.formData.empresa_id);
    },

    // Llamado desde el padre (requerimientos/index.vue) cuando se crea una empresa nueva
    setEmpresaId: function(empresaId, empresaData) {
      var self = this;
      if (!empresaId) return;

      var applyEmpresa = function(data) {
        var nombre = (data && (data.nombre_comercial || data.razon_social)) || 'Empresa nueva';
        var razon = (data && data.razon_social) || 'Empresa nueva';

        var idx = self.empresasOptions.findIndex(function(e) { return e.id === empresaId; });
        if (idx >= 0) {
          self.empresasOptions.splice(idx, 1, {
            id: empresaId,
            nombre_comercial: nombre,
            razon_social: razon
          });
        } else {
          self.empresasOptions.push({
            id: empresaId,
            nombre_comercial: nombre,
            razon_social: razon
          });
        }

        self.formData.empresa_id = empresaId;
        self.formData.contacto_id = null;
        self.contactosOptions = [];
        self.fetchContactosByEmpresa(empresaId);
      };

      if (empresaData && (empresaData.razon_social || empresaData.nombre_comercial)) {
        applyEmpresa(empresaData);
        return;
      }

      // Si no recibimos data, la obtenemos del backend usando el RUC/razón social del registro
      axios.get('/comercial/empresas/record/' + empresaId, {
        headers: { 'Accept': 'application/json' }
      })
      .then(function(response) {
        // El endpoint devuelve { data: { id, ruc, razon_social, ... } }
        var payload = response.data || {};
        var data = payload.data || payload;
        applyEmpresa({
          razon_social: data.razon_social,
          nombre_comercial: data.nombre_comercial
        });
      })
      .catch(function() {
        applyEmpresa(null);
      });
    },

    // Llamado desde el padre cuando se crea un contacto nuevo
    setContactoId: function(contactoId, empresaId) {
      var self = this;
      if (!contactoId) return;

      // Si la empresa cambió, actualizarla
      if (empresaId && self.formData.empresa_id !== empresaId) {
        self.formData.empresa_id = empresaId;
      }

      // Recargar contactos y asignar el nuevo
      self._pendingContactoId = contactoId;
      if (self.formData.empresa_id) {
        self.fetchContactosByEmpresa(self.formData.empresa_id);
      }
    }
  }
};
</script>

<style scoped>
.tab-datos {
  padding: 15px 20px;
}

/* Ícono de ayuda (tooltip nativo del navegador vía atributo title).
   position + z-index: el .el-form-item__content se solapa sobre el label (por su
   overflow visible) y captaba el mouse, impidiendo el hover sobre el label.
   Elevando el span con z-index, el label vuelve a recibir el mouse y el title se activa. */
.lbl-tip {
  position: relative;
  z-index: 2;
  cursor: help;
}
.lbl-tip i {
  color: #909399;
  font-size: 13px;
  margin-left: 3px;
  vertical-align: middle;
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

.input-with-button {
  display: flex;
  gap: 8px;
  align-items: flex-start;
}

.flex-input {
  flex: 1;
}

.input-help {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.2;
}

@media (max-width: 768px) {
  .form-compact >>> .el-form-item__label {
    width: 100% !important;
    text-align: left;
  }
  
  .input-with-button {
    flex-direction: column;
  }
  
  .flex-input {
    width: 100%;
  }
}
</style>
