<template>
    <div>
        <div class="page-header pr-0">
            <h2><a href="/payroll/employees/list">
                <svg  xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-building-factory-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21h18" /><path d="M5 21v-12l5 4v-4l5 4h4" /><path d="M19 21v-8l-1.436 -9.574a.5 .5 0 0 0 -.495 -.426h-1.145a.5 .5 0 0 0 -.494 .418l-1.43 8.582" /><path d="M9 17h1" /><path d="M14 17h1" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>
                        Listado de Empleados
                    </span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <button
                class="btn btn-custom btn-sm  mt-2 mr-2"
                type="button"
                @click.prevent="clickCreate()"
                >
                <i class="fa fa-plus-circle"></i> Nueva Empleado
                </button>
            </div>

        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <th>Código</th>
                        <th>DNI</th>
                        <th>Nombres Completos</th>
                        <th>Email</th>
                        <th>Cargo</th>
                        <th>Régimen</th>
                        <th>AFP/ONP</th>
                        <th>Sueldo Básico</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }">
                        <td>{{ row.codigo_empleado }}</td>
                        <td>{{ row.user.number }}</td>
                        <td>{{ row.user.name }}</td>
                        <td>
                            {{ row.user.email }}
                        </td>
                        <td>{{ row.user.position }}</td>
                        <td>{{ row.tipo_regimen }}</td>
                        <td>{{ row.afp ? row.afp.nombre : '-' }}</td>
                        <td>{{ row.sueldo_basico }}</td>
                        <td>{{ row.activo == true ? 'Activo' : 'Inactivo' }}</td>
                        <td class="text-right">

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
                    </tr>
                </data-table>
            </div>

            <Employee-form
                :recordId.sync="recordId"
                :showDialog.sync="showDialog"
            ></Employee-form>
      
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

import EmployeeForm from "../../components/EmployeeForm.vue";
import DataTable from '../../components/Datatable.vue'
import queryString from 'query-string'
import { deletable } from '../../../../../../../resources/js/mixins/deletable'


export default {
    mixins: [deletable],
    components: {
        DataTable, EmployeeForm
    },
    data() {
        return {
            showDialog: false,
            resource: 'payroll/employees',
            recordId: null,
        }
    },
    created() {
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

    }
}
</script>
