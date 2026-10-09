<template>
    <div>
        <div class="page-header pr-0">
            <h2><a href="/comercial/leads/listado">
                <svg  xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-building-factory-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21h18" /><path d="M5 21v-12l5 4v-4l5 4h4" /><path d="M19 21v-8l-1.436 -9.574a.5 .5 0 0 0 -.495 -.426h-1.145a.5 .5 0 0 0 -.494 .418l-1.43 8.582" /><path d="M9 17h1" /><path d="M14 17h1" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>
                        Listado de Leads
                    </span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
              <button
                class="btn btn-custom btn-sm  mt-2 mr-2"
                type="button"
                @click.prevent="clickCreate()"
              >
                <i class="fa fa-plus-circle"></i> Nuevo Lead
              </button>
            </div>

        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="card-body">
                <data-table :resource="resource" ref="datatable">
                    <tr slot="heading">
                      <th>Empresa</th>
                      <th>Origen</th>
                      <th>Etapa</th>
                      <th>Descripcion</th>
                      <th>Contacto</th>
                      <th>Email</th>
                      <th>Prioridad</th>
                      <th>Monto Estimado</th>
                      <th>Fecha Estimada</th>
                      <th>Revision</th>
                      <th>Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }" :key="index">
                        <td>{{ row.razon_social }}</td>
                        <td>{{ row.origen }}</td>
                        <td>{{ row.etapa }}</td>
                        <td>{{ row.descripcion }}</td>
                        <td>{{ row.contacto }}</td>
                        <td>{{ row.email }}</td>
                        <td>{{ row.prioridad }}</td>
                        <td>{{ row.monto_estimado }}</td>
                        <td>{{ row.fecha_estimada }}</td>
                        <td>
                          <span
                            :class="'badge ' + (classNameEstadosRevision[row.estado_revision] || '')"
                          >{{ row.estado_revision }}
                          </span>
                        </td>

                        <td class="text-right">
                          <button type="button" class="btn waves-effect waves-light btn-xs btn-primary" @click.prevent="reviewLead(row.id)">Revisar Lead</button>
                          <button type="button" class="btn waves-effect waves-light btn-xs btn-info" @click.prevent="clickCreate(row.id)">Editar</button>
                          <button type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickDelete(row.id)">Eliminar</button>
                        </td>
                    </tr>
                </data-table>
            </div>

            <Leads-form
              :recordId.sync="recordId"
              :showDialog.sync="showDialog"
            ></Leads-form>

            <Leads-reviews-form
              :recordId.sync="recordId"
              :showReviewDialog.sync="showReviewDialog"
            ></Leads-reviews-form>

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

import DataTable from '../../components/Datatable.vue'
import {deletable} from '../../../../../../../resources/js/mixins/deletable'
import LeadsForm from "../../components/Leadsform.vue";
import LeadsReviewsForm from "../../components/LeadsReviewsform.vue";

export default {
    mixins: [deletable],
    components: {LeadsForm, DataTable, LeadsReviewsForm },
    data() {
        return {
            selectedItems: [],
            selected: [],
            showDialog: false,
            showReviewDialog: false,
            resource: 'comercial/leads',
            recordId: null,
            classNameEstadosRevision: {
              'Pendiente' : 'badge-warning',
              'En Revision' : 'badge-info',
              'Convertido': '',
              'Coincidencia Total' : 'badge-success',
              'Conflicto' : 'badge-danger'
            }
        }
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
      reviewLead(id) {
        this.recordId = id;
        this.showReviewDialog = true;
      },
    },


}
</script>
