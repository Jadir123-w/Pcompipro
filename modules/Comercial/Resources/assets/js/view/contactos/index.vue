<template>
    <div>
        <div class="page-header pr-0">
            <h2><a href="/comercial/contactos/listado">
                <svg  xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-building-factory-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21h18" /><path d="M5 21v-12l5 4v-4l5 4h4" /><path d="M19 21v-8l-1.436 -9.574a.5 .5 0 0 0 -.495 -.426h-1.145a.5 .5 0 0 0 -.494 .418l-1.43 8.582" /><path d="M9 17h1" /><path d="M14 17h1" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>
                        Listado de Contactos
                    </span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
              <button
                class="btn btn-custom btn-sm  mt-2 mr-2"
                type="button"
                @click.prevent="clickCreate()"
              >
                <i class="fa fa-plus-circle"></i> Nuevo Contacto
              </button>
            </div>

        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="data-table-visible-columns">
              <el-dropdown v-if="hasSelectedItems">
                      <span class="el-dropdown-link">
                          <el-button aria-expanded="false"
                                     class="btn btn-custom btn-sm dropdown-toggle"
                                     type="button">
                              Acciones masivas
                          </el-button>
                      </span>
                <el-dropdown-menu slot="dropdown">
                  <el-dropdown-item @click.native="clickDeleteSelected">Eliminar</el-dropdown-item>
                  <el-dropdown-item @click.native="clickDisableSelected">Inactivar</el-dropdown-item>
                  <el-dropdown-item @click.native="clickEnableSelected">Activar</el-dropdown-item>
                </el-dropdown-menu>
              </el-dropdown>
            </div>
            <div class="card-body">
                <data-table :resource="resource" ref="datatable">
                    <tr slot="heading">
                      <th>
                        <el-dropdown>
                                <span class="el-dropdown-link">
                                    <el-button class="btn-default">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </el-button>
                                </span>
                          <el-dropdown-menu slot="dropdown">
                            <el-dropdown-item
                              @click.native="onChecktAll"
                            >Seleccionar todo</el-dropdown-item
                            >
                            <el-dropdown-item
                              @click.native="onUnCheckAll"
                            >Deseleccionar todo</el-dropdown-item
                            >
                          </el-dropdown-menu>
                        </el-dropdown>
                      </th>
                      <th>Empresa</th>
                      <th>Nombres</th>
                      <th>Apellidos</th>
                      <th>Cargo</th>
                      <th>Celular</th>
                      <th>Telefono</th>
                      <th>Email</th>
                      <th class="text-left"><el-tooltip content="Responsable comercial" placement="top"><span>Responsable</span></el-tooltip></th>
                      <th class="text-left"><el-tooltip content="Usuario que hizo la última modificación" placement="top"><span>Modificado por</span></el-tooltip></th>
                      <th><el-tooltip content="Fecha de la última modificación" placement="top"><span>Últ. modificación</span></el-tooltip></th>
                      <th>Estado</th>

                      <th class="col-acciones-fija">Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }" :key="index">
                        <td>
                          <el-checkbox
                            v-model="row.selected"
                            @change="handleSelectionChange(row)"
                          ></el-checkbox>
                        </td>
                        <td><el-tooltip :disabled="!esLargo(row.empresa_nombre)" :content="row.empresa_nombre" placement="top"><span>{{ recortar(row.empresa_nombre) }}</span></el-tooltip></td>
                        <td>{{ row.nombres }}</td>
                        <td>{{ row.apellidos }}</td>
                        <td><el-tooltip :disabled="!esLargo(row.cargo)" :content="row.cargo" placement="top"><span>{{ recortar(row.cargo) }}</span></el-tooltip></td>
                        <td>{{ mostrarTelefono(row.celular) }}</td>
                        <td>{{ mostrarTelefono(row.telefono) }}</td>
                        <td><el-tooltip :disabled="!esLargo(row.email)" :content="row.email" placement="top"><span>{{ recortar(row.email) }}</span></el-tooltip></td>
                        <td class="text-left">
                          <el-tooltip v-if="row.responsable_comercial" :disabled="!esLargo(row.responsable_comercial)" :content="row.responsable_comercial" placement="top"><span>{{ recortar(row.responsable_comercial) }}</span></el-tooltip>
                          <span v-else style="color: #999; font-size: 12px;">Sin asignar</span>
                        </td>
                        <td class="text-left">
                          <div v-if="row.ultimo_usuario" style="font-size: 12px;">
                            <i class="fa fa-user" style="color: #666; margin-right: 4px;"></i>
                            <strong>{{ row.ultimo_usuario }}</strong>
                          </div>
                          <div v-else style="color: #999; font-size: 12px;">
                            <i class="fa fa-question-circle"></i> Sin información
                          </div>
                        </td>
                        <td>{{ formatFechaLegible(row.updated_at) }}</td>
                        <td>{{ row.estado_name }}</td>

                        <td class="col-acciones-fija">
                          <el-tooltip content="Editar" placement="top"><button type="button" class="btn waves-effect waves-light btn-xs btn-info" @click.prevent="clickCreate(row.id)"><i class="fas fa-edit"></i></button></el-tooltip>
                          <el-tooltip v-if="row.estado === 1" content="Inactivar" placement="top"><button type="button" class="btn waves-effect waves-light btn-xs btn-warning" @click.prevent="clickInactivar(row)"><i class="fas fa-user-slash"></i></button></el-tooltip>
                          <el-tooltip v-else content="Activar" placement="top"><button type="button" class="btn waves-effect waves-light btn-xs btn-success" @click.prevent="clickActivar(row)"><i class="fas fa-user-check"></i></button></el-tooltip>
                          <el-tooltip content="Eliminar" placement="top"><button type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickDelete(row)"><i class="fas fa-trash-alt"></i></button></el-tooltip>
                        </td>
                    </tr>
                </data-table>
            </div>

          <Contactos-form
            :recordId.sync="recordId"
            :showDialog.sync="showDialog"
          ></Contactos-form>

        </div>
    </div>

</template>
<style>
@media only screen and (max-width: 485px){
    .filter-container{
      margin-top: 0;
      & .btn-filter-content, .btn-container-mobile{
        display: flex;
        align-items: center;
        justify-content: start;
      }
    }
  }
</style>
<script>

import ContactosForm from "../../components/Contactosform.vue";
import DataTable from '../../components/Datatable.vue'
import { formatearTelefono, separarAnexo } from '../../helpers/telefono'
import { esLargo, recortar } from '../../helpers/texto'
import { eliminarContacto, ejecutarAccionContacto } from '../../helpers/contacto'

export default {
    components: {ContactosForm, DataTable },
    data() {
        return {
            selectedItems: [],
            selected: [],
            title: null,
            showDialog: false,
            resource: 'comercial/contactos',
            recordId: null,
        }
    },
    created() {
      this.title = 'Contactos'
    },
    mounted() {
      // "Ver contacto" desde un dato repetido: abre ese contacto y limpia la URL.
      const ver = Number(new URLSearchParams(window.location.search).get('ver'))
      if (ver) {
        window.history.replaceState({}, '', window.location.pathname)
        this.clickCreate(ver)
      }
    },
    methods: {
      esLargo,
      recortar,
      clickCreate(recordId = null) {
        this.recordId = recordId;
        this.showDialog = true;
      },
      mostrarTelefono(valor) {
        if (!valor) return ''
        const partes = separarAnexo(valor)
        return formatearTelefono(partes.numero) + (partes.anexo ? ' anexo ' + partes.anexo : '')
      },
      clickDelete(row) {
        eliminarContacto(this, row.id, () => this.$eventHub.$emit('reloadData'))
      },
      clickInactivar(row) {
        this.accionContacto(
          'post', `/${this.resource}/record/${row.id}/inactivar`, 'Inactivar',
          'Úsalo cuando el contacto dejó la empresa o cambió de trabajo: conserva su historial y libera sus teléfonos y correos para otro contacto. Podrás activarlo de nuevo. ¿Inactivar?'
        )
      },
      clickActivar(row) {
        this.accionContacto(
          'post', `/${this.resource}/record/${row.id}/activar`, 'Activar',
          'Se recuperan sus teléfonos y correos, salvo los que ya use otro contacto activo. ¿Activar?'
        )
      },
      accionContacto(metodo, url, titulo, texto) {
        this.$confirm(texto, titulo, {
          confirmButtonText: titulo,
          cancelButtonText: 'Cancelar',
          type: 'warning'
        }).then(() => {
          ejecutarAccionContacto(this, metodo, url, titulo.toLowerCase(), () => this.$eventHub.$emit('reloadData'))
        }).catch(() => {})
      },
      async handleSelectionChange(id) {
        this.$forceUpdate();
        this.selectedItems = this.$refs.datatable.records.filter(item => item.selected);
      },
      clickDeleteSelected() {
        return new Promise((resolve) => {
          this.$confirm('¿Desea eliminar los registro seleccionado?', 'Eliminar', {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning'
          }).then(() => {
            this.$http.post(`/${this.resource}/destroyMassive`, {
              selected: this.selectedItems
            })
              .then(res => {
                if(res.data.success) {
                  // Si algunos no se eliminaron por tener historial, el aviso dura más.
                  const conHistorial = res.data.con_historial > 0
                  this.$message({ type: conHistorial ? 'warning' : 'success', message: res.data.message, duration: conHistorial ? 10000 : 3000, showClose: true })
                  this.selectedItems = []
                  this.$eventHub.$emit("reloadData")
                  resolve()
                }else{
                  this.$message.error(res.data.message)
                  resolve()
                }
              })
              .catch(error => {
                if (error.response.status === 500) {
                  this.$message.error('Error al intentar eliminar');
                } else {
                  console.log(error.response.data.message)
                }
              })
          }).catch(error => {
            console.log(error)
          });
        })
      },
      clickDisableSelected() {
        return new Promise((resolve) => {
          this.$confirm('¿Desea inactivar los registros seleccionados?', 'Inactivar', {
            confirmButtonText: 'Inactivar',
            cancelButtonText: 'Cancelar',
            type: 'warning'
          }).then(() => {
            this.$http.post(`/${this.resource}/disableMassive`, {
              selected: this.selectedItems
            })
              .then(res => {
                if(res.data.success) {
                  this.$message.success(res.data.message)
                  this.selectedItems = []
                  this.$eventHub.$emit("reloadData")
                  resolve()
                }else{
                  this.$message.error(res.data.message)
                  resolve()
                }
              })
              .catch(error => {
                if (error.response.status === 500) {
                  this.$message.error('Error al intentar inactivar');
                } else {
                  console.log(error.response.data.message)
                }
              })
          }).catch(error => {
            console.log(error)
          });
        })
      },
      clickEnableSelected() {
        return new Promise((resolve) => {
          this.$confirm('¿Desea activar los registros seleccionados?', 'Activar', {
            confirmButtonText: 'Activar',
            cancelButtonText: 'Cancelar',
            type: 'warning'
          }).then(() => {
            this.$http.post(`/${this.resource}/enableMassive`, {
              selected: this.selectedItems
            })
              .then(res => {
                if(res.data.success) {
                  this.$message.success(res.data.message)
                  this.selectedItems = []
                  this.$eventHub.$emit("reloadData")
                  resolve()
                }else{
                  this.$message.error(res.data.message)
                  resolve()
                }
              })
              .catch(error => {
                if (error.response.status === 500) {
                  this.$message.error('Error al intentar acivar');
                } else {
                  console.log(error.response.data.message)
                }
              })
          }).catch(error => {
            console.log(error)
          });
        })
      },
      onChecktAll() {
        this.$refs.datatable.records = this.$refs.datatable.records.map(r => {
          r.selected = true;
          return r;
        });
        this.selectedItems = this.$refs.datatable.records.filter(r => r.selected);
      },
      onUnCheckAll() {
        this.$refs.datatable.records = this.$refs.datatable.records.map(r => {
          r.selected = false;
          return r;
        });
        this.selectedItems = [];
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

    },
  computed: {
    hasSelectedItems() {
      return this.selectedItems.length > 0;
    }
  }

}
</script>

<style scoped>
/* Acciones fija a la derecha: siempre visible aunque la tabla se desplace */
.col-acciones-fija {
    position: sticky;
    right: 0;
    z-index: 2;
    background: #fff;
    box-shadow: -6px 0 8px -6px rgba(0, 0, 0, 0.15);
    white-space: nowrap;
    text-align: center;
}
</style>
