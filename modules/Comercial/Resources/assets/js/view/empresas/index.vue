<template>
  <div>
    <div class="page-header pr-0">
      <h2>
        <a href="/comercial/empresas/listado">
          <svg xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24" height="24"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"
               class="icon icon-tabler icons-tabler-outline icon-tabler-building-factory-2">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <path d="M3 21h18"/>
            <path d="M5 21v-12l5 4v-4l5 4h4"/>
            <path
              d="M19 21v-8l-1.436 -9.574a.5 .5 0 0 0 -.495 -.426h-1.145a.5 .5 0 0 0 -.494 .418l-1.43 8.582"/>
            <path d="M9 17h1"/>
            <path d="M14 17h1"/>
          </svg>
        </a>
      </h2>
      <ol class="breadcrumbs">
        <li class="active">
          <span>Listado de Empresas</span>
        </li>
      </ol>
      <div class="right-wrapper pull-right">
        <button
          class="btn btn-custom btn-sm  mt-2 mr-2"
          type="button"
          @click.prevent="clickCreate()"
        >
          <i class="fa fa-plus-circle"></i> Nueva Empresa
        </button>
      </div>
    </div>

    <div class="card tab-content-default row-new mb-0">
      <div class="data-table-visible-columns">
        <el-dropdown :hide-on-click="false">
          <el-button type="secondary">
            Mostrar/Ocultar columnas
            <i class="el-icon-arrow-down el-icon--right"></i>
          </el-button>
          <el-dropdown-menu slot="dropdown">
            <el-dropdown-item
              v-for="(column, index) in columns"
              :key="index"
            >
              <el-checkbox v-model="column.visible">
                {{ column.title }}
              </el-checkbox>
            </el-dropdown-item>
          </el-dropdown-menu>
        </el-dropdown>
      </div>

      <div class="card-body">
        <data-table-expandable
          :resource="resource"
          ref="dataTable"
          :colspan-count="getColspanCount()"
        >
          <template v-slot:heading>
            <tr>
              <th>RUC</th>
              <th>Razon Social</th>
              <th>Razon Comercial</th>
              <th>Nro. Trabajadores</th>
              <th
                v-if="columns.rubro.visible === true"
                class="text-center"
              >
                Rubro
              </th>
              <th
                v-if="columns.telefono.visible === true"
                class="text-left"
              >
                Telefono
              </th>
              <th
                v-if="columns.celular.visible === true"
                class="text-left"
              >
                Celular
              </th>
              <th
                v-if="columns.email.visible === true"
                class="text-left"
              >
                Email
              </th>
              <th
                v-if="columns.aniversario.visible === true"
                class="text-right"
              >
                Aniversario
              </th>
              <th
                v-if="columns.direccion.visible === true"
                class="text-left"
              >
                Direccion
              </th>
              <th
                v-if="columns.referencia.visible === true"
                class="text-left"
              >
                Referencia
              </th>
              <th
                v-if="columns.website.visible === true"
                class="text-right"
              >
                WebSite
              </th>
              <th
                v-if="columns.tipo_empresa.visible === true"
                class="text-left"
              >
                Tipo Empresa
              </th>
              <th
                v-if="columns.provincia.visible === true"
                class="text-left"
              >
                Provincia
              </th>

              <th
                v-if="columns.distrito.visible === true"
                class="text-left"
              >
                Distrito
              </th>
              <!-- 👇 NUEVA COLUMNA AGREGADA -->
              <th class="text-left">Usuario (última actualización)</th>
              <th>Acciones</th>

            </tr>
          </template>

          <template v-slot:row="{ row, index }">
            <td>{{ row.ruc }}</td>
            <td>{{ row.razon_social }}</td>
            <td>{{ row.nombre_comercial }}</td>
            <td>{{ row.nro_trabajadores }}</td>
            <td
              v-if="columns.rubro.visible === true"
              class="text-left"
            >
              {{ row.rubro }}
            </td>
            <td
              v-if="columns.telefono.visible === true"
              class="text-left"
            >
              {{ row.telefono }}
            </td>
            <td
              v-if="columns.celular.visible === true"
              class="text-left"
            >
              {{ row.celular }}
            </td>
            <td
              v-if="columns.email.visible === true"
              class="text-left"
            >
              {{ row.email }}
            </td>
            <td
              v-if="columns.aniversario.visible === true"
              class="text-right"
            >
              {{ row.aniversario }}
            </td>
            <td
              v-if="columns.direccion.visible === true"
              class="text-left"
            >
              {{ row.direccion }}
            </td>
            <td
              v-if="columns.referencia.visible === true"
              class="text-left"
            >
              {{ row.referencia }}
            </td>
            <td
              v-if="columns.website.visible === true"
              class="text-right"
            >
              {{ row.website }}
            </td>
            <td
              v-if="columns.tipo_empresa.visible === true"
              class="text-left"
            >
              {{ row.tipo_empresa }}
            </td>
            <td
              v-if="columns.provincia.visible === true"
              class="text-left"
            >
              {{ row.provincia }}
            </td>

            <td
              v-if="columns.distrito.visible === true"
              class="text-left"
            >
              {{ row.distrito }}
            </td>

            <!-- 👇 NUEVA CELDA AGREGADA -->
            <td class="text-left">
              <div v-if="getUserName(row)" style="font-size: 12px;">
                <div style="margin-bottom: 2px;">
                  <i class="fa fa-user" style="color: #666; margin-right: 4px;"></i>
                  <strong>{{ getUserName(row) }}</strong>
                </div>
                <div style="color: #999; font-size: 11px;">
                  <i class="fa fa-clock-o" style="margin-right: 4px;"></i>
                  {{ getUserDate(row) }}
                </div>
              </div>
              <div v-else style="color: #999; font-size: 12px;">
                <i class="fa fa-question-circle"></i> Sin información
              </div>
            </td>

            <td class="text-right">
              <button
                type="button"
                class="btn waves-effect waves-light btn-xs btn-info"
                @click.prevent="clickViewContacts(row.id)"
                :disabled="loadingContacts[row.id]"
              >
                <span v-if="loadingContacts[row.id]">
                  <i class="fa fa-spinner fa-spin"></i> Cargando...
                </span>
                <span v-else>
                  {{ isRowExpanded(row.id) ? 'Ocultar' : 'Ver' }} Contactos
                </span>
              </button>
              <button
                type="button"
                class="btn waves-effect waves-light btn-xs btn-info"
                @click.prevent="clickCreate(row.id)"
              >
                Editar
              </button>
              <button
                type="button"
                class="btn waves-effect waves-light btn-xs btn-danger"
                @click.prevent="clickDelete(row.id)"
              >
                Eliminar
              </button>
            </td>
          </template>

          <template v-slot:expandable-content="{ row, close }">
            <div class="contactos-container">
              <div class="d-flex justify-content-between align-items-center">
                <div class="contactos-header">
                  <h5 class="mb-0">
                    <i class="fa fa-users mr-2"></i>
                    Contactos de: {{ row.razon_social }}
                  </h5>
                </div>

                <button
                  type="button"
                  class="btn btn-sm btn-primary"
                  @click.prevent="clickCreateContact(row)"
                >
                  <i class="fa fa-plus"></i> Nuevo Contacto
                </button>

              </div>


              <div v-if="loadingContacts[row.id]" class="text-center p-4">
                <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                <p class="mt-2">Cargando contactos...</p>
              </div>

              <div
                v-else-if="contactsList[row.id] && contactsList[row.id].length === 0"
                class="text-center p-4 text-muted"
              >
                <i class="fa fa-users fa-2x mb-2"></i>
                <p>No hay contactos registrados para esta empresa.</p>
              </div>

              <div v-else-if="contactsList[row.id]" class="table-responsive">
                <table class="table table-sm table-hover table-striped mb-0">
                  <thead class="thead-light">
                    <tr>
                      <th width="50"></th>
                      <th>Nombres</th>
                      <th>Apellidos</th>
                      <th>Cargo</th>
                      <th>Teléfono</th>
                      <th>Email</th>
                      <th>Estado</th>
                      <th width="120" class="text-center">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  <tr v-for="contact in contactsList[row.id]" :key="contact.id">
                    <td class="text-center">
                      <i class="fa fa-user text-muted"></i>
                    </td>
                    <td>{{ contact.nombres || '-' }}</td>
                    <td>{{ contact.apellidos || '-' }}</td>
                    <td>{{ contact.cargo || '-' }}</td>
                    <td>
                                  <span v-if="contact.telefono" class="badge badge-light">
                                    <i class="fa fa-phone mr-1"></i>{{ contact.telefono }}
                                  </span>
                      <span v-else class="text-muted">-</span>
                    </td>
                    <td>
                                  <span v-if="contact.email" class="badge badge-light">
                                    <i class="fa fa-envelope mr-1"></i>{{ contact.email }}
                                  </span>
                      <span v-else class="text-muted">-</span>
                    </td>
                    <td>
                                  <span
                                    class="badge"
                                    :class="contact.estado === 1 ? 'badge-success' : 'badge-secondary'"
                                  >
                                    {{ contact.estado === 1 ? 'Activo' : 'Inactivo' }}
                                  </span>
                    </td>
                    <td class="text-center">
                      <button
                        type="button"
                        class="btn btn-xs btn-warning mr-1"
                        @click="clickEditContact(contact.id, row.id)"
                        title="Editar contacto"
                      >
                        <i class="fa fa-edit"></i>
                      </button>
                      <button
                        type="button"
                        class="btn btn-xs btn-danger"
                        @click="clickDeleteContact(contact.id, row.id)"
                        title="Eliminar contacto"
                      >
                        <i class="fa fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>
        </data-table-expandable>
      </div>

      <Empresas-form
        :api_service_token="api_service_token"
        :recordId.sync="recordId"
        :showDialog.sync="showDialog"
        :type="type"
      ></Empresas-form>

      <!-- Contactos: ahora pasamos también el objeto contact -->
      <Contactos-form
        :key="showContactDialog ? (selectedContactoId || 'new') : 'hidden'"
        v-if="showContactDialog"
        :recordId.sync="selectedContactoId"
        :showDialog.sync="showContactDialog"
        :context="editContext"
        :empresaId="currentEmpresaId"
        :empresaEntity="selectedEmpresa"
        @contactSaved="handleContactSaved"
      ></Contactos-form>

    </div>
  </div>
</template>

<style>
.contactos-header {
  margin: 10px 20px 20px 20px;
}

/* Estilos para la columna de usuario */
.usuario-info {
  font-size: 12px;
  line-height: 1.5;
}

.usuario-info .usuario-nombre {
  font-weight: 600;
  color: #333;
  margin-bottom: 3px;
}

.usuario-info .usuario-fecha {
  color: #999;
  font-size: 11px;
}

.usuario-info i {
  margin-right: 4px;
}

</style>

<script>
import EmpresasForm from "../../components/Empresasform.vue";
import ContactosForm from "../../components/Contactosform.vue";
import DataTableExpandable from "../../components/DataTableExpandable.vue";
import queryString from 'query-string'
import { deletable } from '../../../../../../../resources/js/mixins/deletable'

export default {
  mixins: [deletable],
  props: ["type", "typeUser", "api_service_token", "configuration"],
  components: {
    EmpresasForm,
    ContactosForm,
    DataTableExpandable
  },
  data() {
    return {
      title: null,
      showDialog: false,
      showDialogVoided: false,
      resource: 'comercial/empresas',
      recordId: null,
      showDialogOptions: false,
      contactsList: {},
      loadingContacts: {},
      showContactDialog: false,
      selectedContactoId: null,
      currentEmpresaId: null,
      selectedEmpresa: null,
      editContext: 'empresas',
      columns: {
        rubro: {
          title: "Rubro",
          visible: false
        },
        telefono: {
          title: "Telefono",
          visible: false
        },
        celular: {
          title: "Celular",
          visible: false
        },
        email: {
          title: "Email",
          visible: false
        },
        aniversario: {
          title: "Aniversario",
          visible: false
        },
        direccion: {
          title: "Direccion",
          visible: false
        },
        referencia: {
          title: "Referencia",
          visible: false
        },
        website: {
          title: "WebSite",
          visible: false
        },
        tipo_empresa: {
          title: "Tipo Empresa",
          visible: false
        },
        provincia: {
          title: "Provincia",
          visible: false
        },
        distrito: {
          title: "Distrito",
          visible: false
        }
      }
    }
  },
  methods: {
    isRowExpanded(rowId) {
      return this.$refs.dataTable.expandedRows.includes(rowId);
    },
    async clickViewContacts(empresaId, forceReload = false) {
      // Si está expandida y NO es una recarga forzada, cerrar
      if (this.isRowExpanded(empresaId) && !forceReload) {
        this.$refs.dataTable.closeExpand(empresaId);
        return;
      }

      // Si no está expandida, expandir
      if (!this.isRowExpanded(empresaId)) {
        this.$refs.dataTable.toggleExpand(empresaId);
      }

      // Si ya tiene contactos en cache y NO es recarga forzada, no recargar
      if (this.contactsList[empresaId] && !forceReload) {
        return;
      }

      this.$set(this.loadingContacts, empresaId, true);

      try {
        const response = await this.$http.get(`/comercial/contactos/empresa/${empresaId}`);
        this.$set(this.contactsList, empresaId, response.data.data || response.data);
      } catch (error) {
        this.$set(this.contactsList, empresaId, []);
        this.$message.error('Error al cargar los contactos');
      } finally {
        this.$set(this.loadingContacts, empresaId, false);
      }
    },
    clickEditContact(contactId, empresaId) {
      // Primero limpiamos todo
      this.selectedContactoId = null;
      this.showContactDialog = false;
      this.editContext = 'empresas';
      this.currentEmpresaId = empresaId;
      this.selectedEmpresa = null;

      // Esperamos a que Vue procese la limpieza
      this.$nextTick(() => {
        // Asignamos el ID del contacto
        this.selectedContactoId = contactId;

        // Esperamos otro tick y abrimos el diálogo
        this.$nextTick(() => {
          this.showContactDialog = true;
        });
      });
    },
    clickCreateContact(row) {
      // Limpiar estado previo
      this.selectedContactoId = null;
      this.showContactDialog = false;

      // Preparar datos de la empresa
      const empresaData = {
        id: row.id,
        razon_social: row.razon_social || row.nombre_comercial
      };

      // Asignar datos
      this.currentEmpresaId = row.id;
      this.selectedEmpresa = empresaData;
      this.editContext = 'empresas';

      // Esperar a que Vue procese los cambios
      this.$nextTick(() => {
        this.showContactDialog = true;

        this.$nextTick(() => {
        });
      });
    },
    async clickDeleteContact(contactoId, empresaId) {
      if (!confirm('¿Está seguro de eliminar este contacto?')) return;

      try {
        await this.destroy(`/comercial/contactos/record/${contactoId}`);
        this.$message.success('Contacto eliminado correctamente');

        // Limpiar cache y recargar solo los contactos de esa empresa
        this.$set(this.contactsList, empresaId, null);

        // Recargar forzadamente los contactos si la fila está expandida
        if (this.isRowExpanded(empresaId)) {
          await this.clickViewContacts(empresaId, true); // forceReload = true
        }

      } catch (error) {
        this.$message.error('Error al eliminar el contacto');
      }
    },
    handleContactSaved(empresaId) {
      // Limpiar cache
      this.$set(this.contactsList, empresaId, null);

      // Recargar contactos si la fila está expandida (forzando la recarga)
      if (this.isRowExpanded(empresaId)) {
        this.clickViewContacts(empresaId, true); // forceReload = true
      }
    },
    getColspanCount() {
      let count = 4;
      Object.keys(this.columns).forEach(key => {
        if (this.columns[key].visible) count++;
      });
      count++;
      return count;
    },
    clickCreate(recordId = null) {
      this.recordId = recordId;
      this.showDialog = true;
    },
    clickDelete(id) {
      this.destroy(`/${this.resource}/record/${id}`).then(() =>
        this.$eventHub.$emit('reloadData')
      )
    },
    clickVoided(recordId) {
      this.recordId = recordId;
      this.showDialogVoided = true;
    },
    clickDownload(download) {
      let data = this.$refs.dataTable.getSearch();
      let query = queryString.stringify({
        'column': data.column,
        'value': data.value
      });
      window.open(`/${this.resource}/report/excel/?${query}`, '_blank');
    },
    clickOptions(recordId = null) {
      this.recordId = recordId
      this.showDialogOptions = true
    },

        /**
     * Formatear fecha a texto legible
     * Entrada: "2025-12-30 14:30:00" o "2025-12-30"
     * Salida: "30 diciembre 2025"
     */
    formatFechaLegible(fecha) {
      if (!fecha) return '';
      
      const meses = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
      ];
      
      // Convertir string a Date
      const date = new Date(fecha);
      
      // Verificar si es fecha válida
      if (isNaN(date.getTime())) return fecha;
      
      const dia = date.getDate();
      const mes = meses[date.getMonth()];
      const anio = date.getFullYear();
      
      return `${dia} ${mes} ${anio}`;
    },

    /**
     * Formatear fecha con hora (opcional, por si lo necesitas)
     * Salida: "30 diciembre 2025, 14:30"
     */
    formatFechaLegibleConHora(fecha) {
      if (!fecha) return '';
      
      const meses = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
      ];
      
      const date = new Date(fecha);
      if (isNaN(date.getTime())) return fecha;
      
      const dia = date.getDate();
      const mes = meses[date.getMonth()];
      const anio = date.getFullYear();
      const hora = date.getHours().toString().padStart(2, '0');
      const minutos = date.getMinutes().toString().padStart(2, '0');
      
      return `${dia} ${mes} ${anio}, ${hora}:${minutos}`;
    },

    /**
     * Obtener el nombre del usuario (maneja tanto string como objeto)
     */
    getUserName(row) {
      if (!row.usuario_nombre) return null;
      
      // Si es un string, retornarlo directamente
      if (typeof row.usuario_nombre === 'string') {
        return row.usuario_nombre;
      }
      
      // Si es un objeto (error del backend), no mostrar nada
      // El nombre real debería estar en row.updated_by
      if (row.updated_by && typeof row.updated_by === 'string') {
        return row.updated_by;
      }
      
      return null;
    },

    /**
     * Obtener la fecha formateada (maneja tanto string como objeto)
     */
    getUserDate(row) {
      // Primero intentar con updated_at
      if (row.updated_at) {
        // Si updated_at es un objeto con date (error del backend)
        if (typeof row.updated_at === 'object' && row.updated_at.date) {
          return this.formatFechaLegible(row.updated_at.date);
        }
        // Si es un string normal
        if (typeof row.updated_at === 'string') {
          return this.formatFechaLegible(row.updated_at);
        }
      }
      
      // Si usuario_nombre es un objeto con date (otro error del backend)
      if (row.usuario_nombre && typeof row.usuario_nombre === 'object' && row.usuario_nombre.date) {
        return this.formatFechaLegible(row.usuario_nombre.date);
      }
      
      return '';
    }


  }
}
</script>
