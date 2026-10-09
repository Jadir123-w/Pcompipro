<template>
    <div class="persons">
        <div class="page-header pr-0">
            <h2>
                <a :href="personUrl">
                    <svg
                        v-if="type === 'suppliers'"
                        xmlns="http://www.w3.org/2000/svg"
                        style="margin-top: -5px;"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-bag"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path
                            d="M6.331 8h11.339a2 2 0 0 1 1.977 2.304l-1.255 8.152a3 3 0 0 1 -2.966 2.544h-6.852a3 3 0 0 1 -2.965 -2.544l-1.255 -8.152a2 2 0 0 1 1.977 -2.304z"
                        />
                        <path d="M9 11v-5a3 3 0 0 1 6 0v5" />
                    </svg>

                    <svg
                        v-else-if="type === 'customers'"
                        xmlns="http://www.w3.org/2000/svg"
                        style="margin-top: -5px;"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-users-group"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M10 13a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                        <path d="M8 21v-1a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v1" />
                        <path d="M15 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                        <path d="M17 10h2a2 2 0 0 1 2 2v1" />
                        <path d="M5 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                        <path d="M3 13v-1a2 2 0 0 1 2 -2h2" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>{{ title }}</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <button
                    class="btn btn-custom btn-sm  mt-2 mr-2"
                    type="button"
                    @click.prevent="clickExport()"
                >
                    <i class="fa fa-download"></i> Exportar
                </button>
                <button
                    class="btn btn-custom btn-sm  mt-2 mr-2"
                    type="button"
                    @click.prevent="clickImport()"
                >
                    <i class="fa fa-upload"></i> Importar
                </button>
                <button
                    class="btn btn-custom btn-sm  mt-2 mr-2"
                    type="button"
                    @click.prevent="clickCreate()"
                >
                    <i class="fa fa-plus-circle"></i> Nuevo
                </button>
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">Listado de {{ title }}</h3>
            </div> -->
            <div class="data-table-visible-columns">
                <el-dropdown :hide-on-click="false">
                    <el-button type="secondary">
                        Mostrar/Ocultar columnas<i
                            class="el-icon-arrow-down el-icon--right"
                        ></i>
                    </el-button>
                    <el-dropdown-menu slot="dropdown">
                        <el-dropdown-item
                            v-for="(column, index) in columns"
                            :key="index"
                            v-if="column.title !== 'Zona'"
                        >
                            <el-checkbox
                                @change="getColumnsToShow(1)"
                                v-model="column.visible"
                            >
                                {{ column.title }}
                            </el-checkbox>
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </el-dropdown>
            </div>
            <div class="card-body">
                <data-table :resource="resource + `/${this.type}`">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th>ID</th>
                        <th>Nombre</th>
                        <th class="text-right">Cód interno</th>
                        <th class="text-left">Tipo de documento</th>
                        <th class="text-right">Número</th>
                        <th
                            v-if="columns.person_type.visible === true"
                            class="text-center"
                        >
                            T. Cliente
                        </th>
                        <th
                            v-if="columns.observation.visible === true"
                            class="text-left"
                        >
                            Observaciones
                        </th>
                        <th
                            v-if="columns.zone.visible === true"
                            class="text-left"
                        >
                            Zona
                        </th>
                        <th
                            v-if="columns.website.visible === true"
                            class="text-left"
                        >
                            WebSite
                        </th>
                        <th
                            v-if="columns.credit_days.visible === true"
                            class="text-right"
                        >
                            Días de crédito
                        </th>
                        <th
                            v-if="columns.seller.visible === true"
                            class="text-left"
                        >
                            Vendedor asignado
                        </th>
                        <th
                            v-if="columns.email.visible === true"
                            class="text-left"
                        >
                            Correo
                        </th>
                        <th
                            v-if="columns.telephone.visible === true"
                            class="text-right"
                        >
                            Telefono
                        </th>
                        <th
                            v-if="columns.department.visible === true"
                            class="text-left"
                        >
                            Departamento
                        </th>
                        <th
                            v-if="columns.province.visible === true"
                            class="text-left"
                        >
                            Provincia
                        </th>
                        <th
                            v-if="columns.district.visible === true"
                            class="text-left"
                        >
                            Distrito
                        </th>

                        <th class="text-center" v-if="showAccumulatedPoints">
                            Puntos acumulados
                        </th>

                        <th class="text-right">Acciones</th>
                    </tr>

                    <tr></tr>
                    <tr
                        slot-scope="{ index, row }"
                        :class="{ disable_color: !row.enabled }"
                    >
                        <!-- <td>{{ index }}</td> -->
                        <td>{{ row.id }}</td>
                        <td>{{ row.name }}</td>
                        <td class="text-right">{{ row.internal_code }}</td>
                        <td class="text-left">{{ row.document_type }}</td>
                        <td class="text-right">{{ row.number }}</td>
                        <td
                            v-if="columns.person_type.visible === true"
                            class="text-left"
                        >
                            {{ row.person_type }}
                        </td>
                        <td
                            v-if="columns.observation.visible === true"
                            class="text-left"
                        >
                            {{ row.observation }}
                        </td>
                        <td
                            v-if="columns.zone.visible === true"
                            class="text-left"
                        >
                            {{ row.zone ? row.zone.name : "" }}
                        </td>
                        <td
                            v-if="columns.website.visible === true"
                            class="text-left"
                        >
                            {{ row.website }}
                        </td>
                        <td
                            v-if="columns.credit_days.visible === true"
                            class="text-right"
                        >
                            {{ row.credit_days }}
                        </td>
                        <td
                            v-if="columns.seller.visible === true"
                            class="text-left"
                        >
                            {{
                                row.seller && row.seller.name
                                    ? row.seller.name
                                    : ""
                            }}
                        </td>
                        <td
                            v-if="columns.email.visible === true"
                            class="text-left"
                        >
                            {{ row.email }}
                        </td>
                        <td
                            v-if="columns.telephone.visible === true"
                            class="text-right"
                        >
                            {{ row.telephone ? row.telephone : "" }}
                        </td>
                        <td
                            v-if="columns.department.visible === true"
                            class="text-left"
                        >
                            {{
                                row.department ? row.department.description : ""
                            }}
                        </td>
                        <td
                            v-if="columns.province.visible === true"
                            class="text-left"
                        >
                            {{ row.province ? row.province.description : "" }}
                        </td>
                        <td
                            v-if="columns.district.visible === true"
                            class="text-left"
                        >
                            {{ row.district ? row.district.description : "" }}
                        </td>

                        <td v-if="showAccumulatedPoints" class="text-center">
                            {{ row.accumulated_points }}
                        </td>

                        <td class="text-right">
                          <el-dropdown trigger="click" placement="bottom-end">
                            <button
                              class="btn btn-default btn-sm btn-dropdown-toggle"
                              type="button"
                              id="dropdownMenuButton"
                              aria-haspopup="true"
                              aria-expanded="false"
                            >
                              <i class="fas fa-ellipsis-v"></i>
                              <i class="fas fa-ellipsis-h" style="display: none;"></i>
                            </button>
                        
                            <el-dropdown-menu slot="dropdown">
                              <el-dropdown-item
                                v-if="row.enabled"
                                @click.native.prevent="clickCreate(row.id)"
                              >
                                Editar
                              </el-dropdown-item>
                          
                              <el-dropdown-item
                                v-if="typeUser === 'admin'"
                                @click.native.prevent="clickDelete(row.id)"
                              >
                                Eliminar
                              </el-dropdown-item>
                          
                              <template v-if="typeUser === 'admin'">
                                <el-dropdown-item
                                  v-if="row.enabled"
                                  @click.native.prevent="clickDisable(row.id)"
                                >
                                  Inhabilitar
                                </el-dropdown-item>
                                <el-dropdown-item
                                  v-else
                                  @click.native.prevent="clickEnable(row.id)"
                                >
                                  Habilitar
                                </el-dropdown-item>
                              </template>
                          
                              <el-dropdown-item
                                @click.native.prevent="clickBarcode(row)"
                              >
                                Cod. Barras
                              </el-dropdown-item>
                          
                              <el-dropdown-item
                                @click.native.prevent="clickPrintBarcode(row)"
                              >
                                Etiquetas
                              </el-dropdown-item>
                            </el-dropdown-menu>
                          </el-dropdown>
                        </td>
                    </tr>
                </data-table>
            </div>

            <persons-form
                :api_service_token="api_service_token"
                :recordId="recordId"
                :showDialog.sync="showDialog"
                :type="type"
            ></persons-form>

            <persons-import
                :showDialog.sync="showImportDialog"
                :type="type"
            ></persons-import>

            <persons-export
                :showDialog.sync="showExportDialog"
                :type="type"
            ></persons-export>
        </div>
    </div>
</template>
<style>
.dropdown-menu.show{
    max-height: 125px;
}
</style>
<script>
import PersonsForm from "./form.vue";
import PersonsImport from "./import.vue";
import PersonsExport from "./partials/export.vue";
import DataTable from "../../../components/DataTable.vue";
import { deletable } from "../../../mixins/deletable";

export default {
    mixins: [deletable],
    props: ["type", "typeUser", "api_service_token", "configuration"],
    components: { PersonsForm, PersonsImport, PersonsExport, DataTable },
    data() {
        return {
            isClient: true,
            title: null,
            showDialog: false,
            showImportDialog: false,
            showExportDialog: false,
            resource: "persons",
            recordId: null,
            columns: {
                observation: {
                    title: "Observacion",
                    visible: false
                },
                zone: {
                    title: "Zona",
                    visible: false
                },
                website: {
                    title: "Sitio Web",
                    visible: false
                },
                person_type: {
                    title: "Tipo de cliente",
                    visible: false
                },
                credit_days: {
                    title: "Días de crédito",
                    visible: false
                },
                seller: {
                    title: "Vendedor asignado",
                    visible: false
                },
                email: {
                    title: "Correo electrónico",
                    visible: false
                },
                telephone: {
                    title: "Teléfono",
                    visible: false
                },
                department: {
                    title: "Departamento",
                    visible: false
                },
                province: {
                    title: "Provincia",
                    visible: false
                },
                district: {
                    title: "Distrito",
                    visible: false
                }
            }
        };
    },
    created() {
        const storedColumns = JSON.parse(
            localStorage.getItem("client_columns")
        );

        if (storedColumns && storedColumns.zone) {
            storedColumns.zone.visible = false;
            localStorage.setItem(
                "client_columns",
                JSON.stringify(storedColumns)
            );
        }

        this.title = this.type === "customers" ? "Clientes" : "Proveedores";
        this.getColumnsToShow();
    },
    computed: {
        personUrl() {
            return this.type === "customers"
                ? "/persons/customers"
                : "/persons/suppliers";
        },
        showAccumulatedPoints() {
            if (this.configuration) {
                return (
                    this.configuration.enabled_point_system &&
                    this.type === "customers"
                );
            }

            return false;
        }
    },
    methods: {
        getColumnsToShow(updated) {
            this.$http
                .post("/validate_columns", {
                    columns: this.columns,
                    report: "client_index",
                    updated: updated !== undefined
                })
                .then(response => {
                    if (updated === undefined) {
                        let currentCols = response.data.columns;
                        if (currentCols !== undefined) {
                            this.columns = currentCols;

                            if (this.columns.zone) {
                                this.columns.zone.visible = false;
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error(error);
                });
        },

        clickCreate(recordId = null) {
            this.recordId = recordId;
            this.showDialog = true;
        },
        clickImport() {
            this.showImportDialog = true;
        },
        clickExport() {
            this.showExportDialog = true;
        },
        clickDelete(id) {
            this.destroy(`/${this.resource}/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        clickDisable(id) {
            this.disable(`/${this.resource}/enabled/${0}/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        clickEnable(id) {
            this.enable(`/${this.resource}/enabled/${1}/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        clickBarcode(row) {
            if (!row.barcode) {
                return this.$message.error(
                    "Para generar el código de barras debe registrar el código de barras."
                );
            }

            window.open(`/${this.resource}/barcode/${row.id}`);
        },
        clickPrintBarcode(row) {
            if (!row.barcode) {
                return this.$message.error(
                    "Para generar el código de barras debe registrar el código de barras."
                );
            }

            window.open(`/${this.resource}/export/barcode/print?id=${row.id}`);
        }
    }
};
</script>
