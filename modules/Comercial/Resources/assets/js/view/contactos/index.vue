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
                      <th>Estado</th>
                      <th>Fecha última actualización</th>

                      <th>Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }" :key="index">
                        <td>
                          <el-checkbox
                            v-model="row.selected"
                            @change="handleSelectionChange(row)"
                          ></el-checkbox>
                        </td>
                        <td>{{ row.razon_social }}</td>
                        <td>{{ row.nombres }}</td>
                        <td>{{ row.apellidos }}</td>
                        <td>{{ row.cargo }}</td>
                        <td>{{ row.celular }}</td>
                        <td>{{ row.telefono }}</td>
                        <td>{{ row.email }}</td>
                        <td>{{ row.estado_name }}</td>
                        <td>{{ formatFechaLegible(row.updated_at) }}</td>

                        <td class="text-right">
                          <button type="button" class="btn waves-effect waves-light btn-xs btn-info" @click.prevent="clickCreate(row.id)">Editar</button>
                          <button type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickDelete(row.id)">Eliminar</button>
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
import {deletable} from '../../../../../../../resources/js/mixins/deletable'

export default {
    mixins: [deletable],
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
    methods: {
      clickCreate(recordId = null) {
        this.recordId = recordId;
        this.showDialog = true;
      },
      clickDelete(id) {
        this.destroy(`/${this.resource}/record/${id}`).then(() =>
          this.$eventHub.$emit('reloadData')
        )
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
