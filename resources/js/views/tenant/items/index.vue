<template>
    <div class="items">
        <div class="page-header pr-0">
            <h2>
                <a :href="itemUrl">
                    <svg
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
                        class="icon icon-tabler icons-tabler-outline icon-tabler-category-2"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M14 4h6v6h-6z" />
                        <path d="M4 14h6v6h-6z" />
                        <path d="M17 17m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                        <path d="M7 7m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>{{ titleTopBar }}</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <template v-if="typeUser === 'admin'">
                    <div class="btn-group flex-wrap">
                        <button
                            aria-expanded="false"
                            class="btn btn-custom btn-sm mt-2 mr-2 dropdown-toggle"
                            data-toggle="dropdown"
                            type="button"
                        >
                            <i class="fa fa-download"></i> Exportar
                            <span class="caret"></span>
                        </button>
                        <div
                            class="dropdown-menu"
                            role="menu"
                            style="
                                position: absolute;
                                will-change: transform;
                                top: 0px;
                                left: 0px;
                                transform: translate3d(0px, 42px, 0px);
                            "
                            x-placement="bottom-start"
                        >
                            <a
                                class="dropdown-item text-1"
                                href="#"
                                @click.prevent="clickExport()"
                                >Productos</a
                            >
                            <a
                                class="dropdown-item text-1"
                                href="#"
                                @click.prevent="clickExportWp()"
                                >Woocommerce</a
                            >
                            <a
                                class="dropdown-item text-1"
                                href="#"
                                @click.prevent="clickExportBarcode()"
                                >Etiquetas</a
                            >
                            <template v-if="config.show_extra_info_to_item">
                                <a
                                    class="dropdown-item text-1"
                                    href="#"
                                    @click.prevent="clickExportExtra()"
                                >
                                    Atributos Extra
                                </a>
                            </template>
                            <a
                                class="dropdown-item text-1"
                                href="#"
                                @click.prevent="clickExportBartender()"
                                >Bartender</a
                            >
                        </div>
                    </div>
                    <div class="btn-group flex-wrap">
                        <button
                            aria-expanded="false"
                            class="btn btn-custom btn-sm mt-2 mr-2 dropdown-toggle"
                            data-toggle="dropdown"
                            type="button"
                        >
                            <i class="fa fa-upload"></i> Importar
                            <span class="caret"></span>
                        </button>
                        <div
                            class="dropdown-menu"
                            role="menu"
                            style="
                                position: absolute;
                                will-change: transform;
                                top: 0px;
                                left: 0px;
                                transform: translate3d(0px, 42px, 0px);
                            "
                            x-placement="bottom-start"
                        >
                            <a
                                class="dropdown-item text-1"
                                href="#"
                                @click.prevent="clickImport()"
                                >Productos</a
                            >
                            <a
                                class="dropdown-item text-1"
                                href="#"
                                @click.prevent="clickImportListPrice()"
                                >L. Precios</a
                            >
                            <template v-if="config.show_extra_info_to_item">
                                <a
                                    class="dropdown-item text-1"
                                    href="#"
                                    @click.prevent="
                                        clickImportExtraWithExtraInfo()
                                    "
                                    >L. Atributos</a
                                >
                            </template>

                            <a
                                class="dropdown-item text-1"
                                href="#"
                                @click.prevent="clickImportUpdatePrice()"
                                >Actualizar precios</a
                            >
                        </div>
                    </div>
                </template>
                <button
                    v-if="can_add_new_product"
                    class="btn btn-custom btn-sm mt-2 mr-2"
                    type="button"
                    @click.prevent="clickCreate()"
                >
                    <i class="fa fa-plus-circle"></i> Nuevo
                </button>
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">{{ title }}</h3>
            </div> -->
            <div class="data-table-visible-columns">                
                <el-dropdown v-if="selected.length > 0">
                    <span class="el-dropdown-link">
                        <el-button aria-expanded="false"
                            class="btn btn-custom btn-sm dropdown-toggle"
                            data-toggle="dropdown"
                            type="button">
                            Acciones masivas
                        </el-button>
                    </span>
                    <el-dropdown-menu slot="dropdown">
                        <el-dropdown-item @click.native="clickDeleteSelected">Eliminar</el-dropdown-item>                        
                        <el-dropdown-item @click.native="duplicateSelected">Duplicar</el-dropdown-item>
                        <el-dropdown-item @click.native="clickDisableSelected">Inhabilitar</el-dropdown-item>
                        <el-dropdown-item @click.native="clickEnableSelected">Habilitar</el-dropdown-item>
                    </el-dropdown-menu>                  
                </el-dropdown>
                <el-dropdown :hide-on-click="false">
                    <el-button type="secondary">
                        Mostrar/Ocultar columnas<i
                            class="el-icon-arrow-down el-icon--right"
                        ></i>
                    </el-button>
                    <el-dropdown-menu slot="dropdown">
                        <el-dropdown-item
                            v-for="(column, index) in columnsComputed"
                            :key="index"
                        >
                            <el-checkbox
                                v-if="
                                    column.title !== undefined &&
                                        column.visible !== undefined
                                "
                                v-model="column.visible"
                                @change="saveColumnVisibility"
                                >{{ column.title }}
                            </el-checkbox>
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </el-dropdown>
            </div>
            <div class="card-body">
                <data-table ref="DataTable" :productType="type" :resource="resource" :sort-field="sortField" :sort-direction="sortDirection" :showProductFilter="type !== 'ZZ'" @sort-change="handleSortChange">
                    <tr slot="heading" width="100%" slot-scope="{ sort }">
                        <th></th>
                        <th class="text-right" style="max-width: 83px;">ID</th>
                        <th class="text-right">Cód. Interno</th>
                        <th>Unidad</th>
                        <th>Imagen</th>
                        <th>
                            <a href="#" @click.prevent="sort('description')" style="color: inherit; text-decoration: none;">
                                Nombre 
                                <i class="fas" :class="{
                                    'fa-sort-up': sortField === 'description' && sortDirection === 'asc',
                                    'fa-sort-down': sortField === 'description' && sortDirection === 'desc',
                                    'fa-sort': sortField !== 'description' || 
                                              (sortField === 'description' && sortDirection === 'default')
                                }"></i>
                            </a>
                        </th>
                        <th v-if="columns.description.visible">Descripción</th>
                        <th v-if="columns.model.visible">Modelo</th>
                        <th v-if="columns.brand.visible">Marca</th>
                        <th class="text-right" v-if="columns.item_code.visible">
                            Cód. SUNAT
                        </th>
                        <th
                            class="text-right"
                            v-if="
                                columns.sanitary !== undefined &&
                                    columns.sanitary.visible === true
                            "
                        >
                            R.S.
                        </th>
                        <th
                            v-if="
                                columns.cod_digemid !== undefined &&
                                    columns.cod_digemid.visible === true
                            "
                        >
                            DIGEMID
                        </th>
                        <template v-if="typeUser == 'admin'">
                            <th class="text-center">Historial</th>
                        </template>
                        <th class="text-left">Stock</th>
                        <th
                            v-if="
                                columns.extra_data !== undefined &&
                                    columns.extra_data.visible === true
                            "
                            class="text-center"
                        >
                            Stock por datos extra
                        </th>
                        <th class="text-right">P.Unitario (Venta)</th>
                        <th
                            v-if="
                                typeUser != 'seller' &&
                                    columns.purchase_unit_price.visible
                            "
                            class="text-right"
                        >
                            P.Unitario (Compra)
                        </th>
                        <th
                            v-if="columns.real_unit_price.visible"
                            class="text-right"
                        >
                            P. venta
                        </th>
                        <th class="text-left">Tiene Igv (Venta)</th>
                        <th
                            v-if="columns.purchase_has_igv_description.visible"
                            class="text-left"
                        >
                            Tiene Igv (Compra)
                        </th>
                        <th class="text-right"></th>
                    </tr>

                    <tr></tr>
                    <tr
                        slot-scope="{ index, row }"
                        :class="{ disable_color: !row.active }"
                    >
                        <td>
                          <el-checkbox
                            :value="selected.includes(row.id)"
                            @change="handleSelectionChange(row.id)"
                          ></el-checkbox>
                        </td>
                        <td class="text-right">{{ row.id }}</td>
                        <td class="text-right">{{ row.internal_id }}</td>
                        <td>{{ row.unit_type_id }}</td>
                        <td>
                            <img
                                :src="row.image_url_small"
                                style="object-fit: contain;"
                                alt
                                width="32px"
                                height="32px"
                            />
                        </td>
                        <td>{{ row.description }}</td>
                        <td v-if="columns.description.visible">
                            {{ row.name }}
                        </td>
                        <td v-if="columns.model.visible">{{ row.model }}</td>
                        <td v-if="columns.brand.visible">{{ row.brand }}</td>
                        <td class="text-right" v-if="columns.item_code.visible">
                            {{ row.item_code }}
                        </td>
                        <td
                            v-if="
                                columns.sanitary !== undefined &&
                                    columns.sanitary.visible === true
                            "
                        >
                            {{ row.sanitary }}
                        </td>
                        <td
                            class="text-right"
                            v-if="
                                columns.cod_digemid !== undefined &&
                                    columns.cod_digemid.visible === true
                            "
                        >
                            {{ row.cod_digemid }}
                        </td>

                        <template v-if="typeUser == 'admin'">
                            <td class="text-center">
                                <button
                                    class="btn waves-effect waves-light btn-xs btn-primary"
                                    type="button"
                                    @click.prevent="clickHistory(row.id)"
                                >
                                    <i class="fa fa-history"></i>
                                </button>
                            </td>
                        </template>

                        <td>
                            <div
                                v-if="config.product_only_location == true"
                                :class="{
                                    'text-danger': row.stock < row.stock_min
                                }"
                            >
                                {{ row.stock }}
                            </div>
                            <div v-else>
                                <template
                                    v-if="
                                        typeUser == 'seller' &&
                                            row.unit_type_id != 'ZZ'
                                    "
                                    ><span
                                        :class="{
                                            'text-danger':
                                                row.stock < row.stock_min
                                        }"
                                        >{{ row.stock }}</span
                                    >
                                </template>
                                <template
                                    v-else-if="
                                        typeUser != 'seller' &&
                                            row.unit_type_id != 'ZZ'
                                    "
                                >
                                    <button
                                        class="btn waves-effect waves-light btn-xs btn-info"
                                        type="button"
                                        @click.prevent="
                                            clickWarehouseDetail(
                                                row.warehouses,
                                                row.item_unit_types
                                            )
                                        "
                                    >
                                        <i class="fa fa-search"></i>
                                    </button>
                                </template>
                            </div>
                            <!-- <template v-for="item in row.warehouses">
                                <template>{{item.stock}} - {{item.warehouse_description}}</template><br>
                            </template> -->

                            <!-- <br/>Mín:{{ row.stock_min }} -->
                        </td>
                        <td
                            v-if="
                                columns.extra_data !== undefined &&
                                    columns.extra_data.visible === true
                            "
                            class="text-center"
                        >
                            <template
                                v-if="
                                    config.show_extra_info_to_item &&
                                        (row.stock_by_extra.total !== null ||
                                            row.stock_by_extra.colors !==
                                                null ||
                                            row.stock_by_extra.CatItemSize !==
                                                null ||
                                            row.stock_by_extra.CatItemStatus !==
                                                null ||
                                            row.stock_by_extra
                                                .CatItemUnitBusiness !== null ||
                                            row.stock_by_extra
                                                .CatItemMoldCavity !== null ||
                                            row.stock_by_extra
                                                .CatItemPackageMeasurement !==
                                                null ||
                                            row.stock_by_extra
                                                .CatItemUnitsPerPackage !==
                                                null ||
                                            row.stock_by_extra
                                                .CatItemMoldProperty !== null ||
                                            row.stock_by_extra
                                                .CatItemProductFamily !== null)
                                "
                            >
                                <button
                                    class="btn waves-effect waves-light btn-xs btn-primary"
                                    type="button"
                                    @click.prevent="clickStockItems(row)"
                                >
                                    <i class="fa fa-database"></i>
                                </button>
                            </template>
                        </td>
                        <td class="text-right">{{ row.sale_unit_price }}</td>
                        <td
                            v-if="
                                typeUser != 'seller' &&
                                    columns.purchase_unit_price.visible
                            "
                            class="text-right"
                        >
                            {{ row.purchase_unit_price }}
                        </td>
                        <td
                            v-if="columns.real_unit_price.visible"
                            class="text-right"
                        >
                            {{ row.sale_unit_price_with_igv }}
                        </td>
                        <td class="text-left">
                            {{ row.has_igv_description }}
                        </td>
                        <td
                            v-if="columns.purchase_has_igv_description.visible"
                            class="text-left"
                        >
                            {{ row.purchase_has_igv_description }}
                        </td>
                        <td class="text-right">
                          <el-dropdown trigger="click" placement="bottom-end">
                            <button
                              id="dropdownMenuButton"
                              aria-expanded="false"
                              aria-haspopup="true"
                              class="btn btn-default btn-sm btn-dropdown-toggle"
                              type="button"
                            >
                              <i class="fas fa-ellipsis-v"></i>
                              <i class="fas fa-ellipsis-h" style="display: none;"></i>
                            </button>
                        
                            <el-dropdown-menu slot="dropdown">
                              <template v-if="typeUser === 'admin'">
                                <el-dropdown-item @click.native.prevent="clickCreate(row.id)">
                                  Editar
                                </el-dropdown-item>
                            
                                <el-dropdown-item @click.native.prevent="clickBarcode(row)">
                                  Cod. Barras
                                </el-dropdown-item>
                            
                                <el-dropdown-item @click.native.prevent="clickPrintBarcode(row)">
                                  Etiquetas
                                </el-dropdown-item>
                            
                                <el-dropdown-item
                                  divided
                                  @click.native.prevent="clickPrintBarcodeX(row, 1)"
                                >
                                  Etiquetas 1x1
                                </el-dropdown-item>
                            
                                <el-dropdown-item @click.native.prevent="clickPrintBarcodeX(row, 2)">
                                  Etiquetas 1x2
                                </el-dropdown-item>
                            
                                <el-dropdown-item @click.native.prevent="clickPrintBarcodeX(row, 3)">
                                  Etiquetas 1x3
                                </el-dropdown-item>
                              </template>
                            </el-dropdown-menu>
                          </el-dropdown>
                        </td>
                    </tr>
                </data-table>
            </div>

            <items-form
                :recordId="recordId"
                :showDialog.sync="showDialog"
                :type="type"
            ></items-form>

            <items-import :showDialog.sync="showImportDialog"></items-import>
            <items-export :showDialog.sync="showExportDialog"></items-export>
            <items-export-wp
                :showDialog.sync="showExportWpDialog"
            ></items-export-wp>

            <items-export-barcode
                :showDialog.sync="showExportBarcodeDialog"
            ></items-export-barcode>

            <items-export-extra
                :showDialog.sync="showExportExtraDialog"
            ></items-export-extra>
            <warehouses-detail
                :item_unit_types="item_unit_types"
                :showDialog.sync="showWarehousesDetail"
                :warehouses="warehousesDetail"
            >
            </warehouses-detail>

            <items-import-list-price
                :showDialog.sync="showImportListPriceDialog"
            ></items-import-list-price>

            <items-import-extra-info
                :showDialog.sync="showImportExtraWithExtraInfo"
            ></items-import-extra-info>

            <items-import-update-price
                :showDialog.sync="showImporUpdatePrice"
            ></items-import-update-price>

            <!--
            : false,
            show_extra_info_to_item
            -->
            <tenant-item-aditional-info-modal
                :item="recordItem"
                :showDialog.sync="showDialogItemStock"
            ></tenant-item-aditional-info-modal>
            <items-history
                :recordId="recordId"
                :showDialog.sync="showDialogHistory"
            >
            </items-history>
            <items-export-bartender
                :configuration="configuration"
                :showDialog.sync="showExportBartenderDialog"
            ></items-export-bartender>
        </div>
    </div>
</template>
<style>
.dropdown-menu.show {
    max-height: 130px;
}
</style>
<script>
import ItemsForm from "./form.vue";
import WarehousesDetail from "./partials/warehouses.vue";
import ItemsImport from "./import.vue";
import ItemsImportListPrice from "./partials/import_list_price.vue";
import ItemsImportExtraInfo from "./partials/import_list_extra_info.vue";
// resources/js/views/tenant/items/partials/import_list_extra_info.vue
import ItemsExport from "./partials/export.vue";
import ItemsExportWp from "./partials/export_wp.vue";
import ItemsExportBarcode from "./partials/export_barcode.vue";
import ItemsExportExtra from "./partials/export_extra.vue";
import DataTable from "../../../components/DataTable.vue";
import { deletable } from "../../../mixins/deletable";
import ItemsHistory from "@viewsModuleItem/items/history.vue";
import { mapActions, mapState } from "vuex";
import ItemsImportUpdatePrice from "./partials/update_prices.vue";
import ItemsExportBartender from "./partials/export_bartender.vue";

export default {
    props: ["configuration", "typeUser", "type"],
    mixins: [deletable],
    components: {
        ItemsForm,
        ItemsImport,
        ItemsExport,
        ItemsExportWp,
        ItemsExportBarcode,
        ItemsExportExtra,
        DataTable,
        WarehousesDetail,
        ItemsImportListPrice,
        ItemsImportExtraInfo,
        ItemsHistory,
        ItemsImportUpdatePrice,
        ItemsExportBartender
    },
    data() {
        return {
            selected: [],
            can_add_new_product: false,
            showDialog: false,
            showImportDialog: false,
            showExportDialog: false,
            showExportWpDialog: false,
            showExportBarcodeDialog: false,
            showExportBartenderDialog: false,
            showExportExtraDialog: false,
            showImportListPriceDialog: false,
            showImportExtraWithExtraInfo: false,
            showImporUpdatePrice: false,
            showWarehousesDetail: false,
            resource: "items",
            recordId: null,
            recordItem: {},
            warehousesDetail: [],
            columns: {
                description: {
                    title: "Descripción",
                    visible: false
                },
                item_code: {
                    title: "Cód. SUNAT",
                    visible: false
                },
                purchase_unit_price: {
                    title: "P.Unitario (Compra)",
                    visible: false
                },
                purchase_has_igv_description: {
                    title: "Tiene Igv (Compra)",
                    visible: false
                },
                model: {
                    title: "Modelo",
                    visible: false
                },
                brand: {
                    title: "Marca",
                    visible: false
                },
                sanitary: {
                    title: "N° Sanitario",
                    visible: false
                },
                cod_digemid: {
                    title: "DIGEMID",
                    visible: false
                },
                real_unit_price: {
                    title:
                        "Mostrar el precio de venta total (con el cálculo IGV)",
                    visible: false
                },
                extra_data: {
                    title: "Stock Por datos extra",
                    visible: false
                }
            },
            item_unit_types: [],
            titleTopBar: "",
            title: "",
            showDialogHistory: false,
            showDialogItemStock: false,
            sortField: localStorage.getItem('itemSortField') || 'id',
            sortDirection: localStorage.getItem('itemSortDirection') || 'desc',
        };
    },
    created() {
        this.loadColumnVisibility();
        this.$store.commit("setConfiguration", this.configuration);
        this.loadConfiguration();

        if (this.config.is_pharmacy !== true) {
            delete this.columns.sanitary;
            delete this.columns.cod_digemid;
        }
        if (this.config.show_extra_info_to_item !== true) {
            delete this.columns.extra_data;
        }
        if (this.type === "ZZ") {
            this.titleTopBar = "Servicios";
            this.title = "Listado de servicios";
        } else {
            this.titleTopBar = "Productos";
            this.title = "Listado de productos";
        }
        this.$http.get(`/configurations/record`).then(response => {
            this.$store.commit("setConfiguration", response.data.data);
            //this.config = response.data.data;
        });
        this.canCreateProduct();
        this.getItems();

        this.filterDisabled = localStorage.getItem('filterDisabled') || 'all'
    },
    computed: {
        ...mapState([
            "config",
            "colors",
            "CatItemSize",
            "CatItemMoldCavity",
            "CatItemMoldProperty",
            "CatItemUnitBusiness",
            "CatItemStatus",
            "CatItemPackageMeasurement",
            "CatItemProductFamily",
            "CatItemUnitsPerPackage"
        ]),
        columnsComputed: function() {
            return this.columns;
        },
        itemUrl() {
            return this.type === "ZZ" ? "/services" : "/items";
        }
    },
    methods: {
        reloadTable() {
          localStorage.setItem('filterDisabled', this.filterDisabled)
          this.$refs.DataTable.showDisabled = this.filterDisabled;
          this.$refs.DataTable.getRecords();
        },
        clickDeleteSelected() {
            return new Promise((resolve) => {
                this.$confirm('¿Desea eliminar los registro seleccionado?', 'Eliminar', {
                    confirmButtonText: 'Eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.$http.post(`${this.resource}/destroyMassive`, {
                        selected: this.selected
                    })
                        .then(res => {
                            if(res.data.success) {
                                this.$message.success(res.data.message)
                                this.selected = []
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
                this.$confirm('¿Desea inhabilitar los registros seleccionados?', 'Inhabilitar', {
                    confirmButtonText: 'Inhabilitar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.$http.post(`${this.resource}/disableMassive`, {
                        selected: this.selected
                    })
                        .then(res => {
                            if(res.data.success) {
                                this.$message.success(res.data.message)
                                this.selected = []
                                this.$eventHub.$emit("reloadData")
                                resolve()
                            }else{
                                this.$message.error(res.data.message)
                                resolve()
                            }
                        })
                        .catch(error => {
                            if (error.response.status === 500) {
                                this.$message.error('Error al intentar inhabilitar');
                            } else {
                                console.log(error.response.data.message)
                            }
                        })
                }).catch(error => {
                    console.log(error)
                });
            })
        },
        duplicateSelected() {
            this.selected.forEach(id => this.duplicate(id));
            this.selected = []
        },
        clickEnableSelected() {
            return new Promise((resolve) => {
                this.$confirm('¿Desea habilitar los registros seleccionados?', 'Habilitar', {
                    confirmButtonText: 'Habilitar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.$http.post(`${this.resource}/enableMassive`, {
                        selected: this.selected
                    })
                        .then(res => {
                            if(res.data.success) {
                                this.$message.success(res.data.message)
                                this.selected = []
                                this.$eventHub.$emit("reloadData")
                                resolve()
                            }else{
                                this.$message.error(res.data.message)
                                resolve()
                            }
                        })
                        .catch(error => {
                            if (error.response.status === 500) {
                                this.$message.error('Error al intentar habilitar');
                            } else {
                                console.log(error.response.data.message)
                            }
                        })
                }).catch(error => {
                    console.log(error)
                });
            })
        },
        handleSortChange(sort) {
            if (this.sortField === sort.field && this.sortDirection === 'desc' && sort.field === 'description') {
                this.sortField = 'id';
                this.sortDirection = 'desc';
            } else {
                this.sortField = sort.field;
                this.sortDirection = sort.direction;
            }

            localStorage.setItem('itemSortField', this.sortField);
            localStorage.setItem('itemSortDirection', this.sortDirection);
        },
        saveColumnVisibility() {
            localStorage.setItem(
                "columnVisibilityItems",
                JSON.stringify(this.columns)
            );
        },
        loadColumnVisibility() {
            const savedColumns = localStorage.getItem("columnVisibilityItems");
            if (savedColumns) {
                this.columns = JSON.parse(savedColumns);
            }
        },
        ...mapActions(["loadConfiguration"]),
        clickHistory(recordId) {
            this.recordId = recordId;
            this.showDialogHistory = true;
        },
        clickStockItems(row) {
            this.recordItem = row;
            this.showDialogItemStock = true;
        },
        canCreateProduct() {
            if (this.typeUser === "admin") {
                this.can_add_new_product = true;
            } else if (this.typeUser === "seller") {
                if (
                    this.config !== undefined &&
                    this.config.seller_can_create_product !== undefined
                ) {
                    this.can_add_new_product = this.config.seller_can_create_product;
                }
            }
            return this.can_add_new_product;
        },
        duplicate(id) {
            this.$http
                .post(`${this.resource}/duplicate`, { id })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(
                            "Se guardaron los cambios correctamente."
                        );
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.error("No se guardaron los cambios");
                    }
                })
                .catch(error => {});
            this.$eventHub.$emit("reloadData");
        },
        clickWarehouseDetail(warehouses, item_unit_types) {
            this.warehousesDetail = warehouses;
            this.item_unit_types = item_unit_types;
            this.showWarehousesDetail = true;
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
        clickExportWp() {
            this.showExportWpDialog = true;
        },
        clickExportBarcode() {
            this.showExportBarcodeDialog = true;
        },
        clickExportBartender() {
            this.showExportBartenderDialog = true;
        },
        clickExportExtra() {
            this.showExportExtraDialog = true;
        },
        clickImportListPrice() {
            this.showImportListPriceDialog = true;
        },
        clickImportExtraWithExtraInfo() {
            this.showImportExtraWithExtraInfo = true;
        },
        clickImportUpdatePrice() {
            this.showImporUpdatePrice = true;
        },
        clickDelete(id) {
            this.destroy(`/${this.resource}/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        clickDisable(id) {
            this.disable(`/${this.resource}/disable/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        clickEnable(id) {
            this.enable(`/${this.resource}/enable/${id}`).then(() =>
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
        },
        clickPrintBarcodeX(row, x) {
            if (!row.barcode) {
                return this.$message.error(
                    "Para generar el código de barras debe registrar el código de barras."
                );
            }

            window.open(
                `/${this.resource}/export/barcode/print_x?format=${x}&id=${
                    row.id
                }`
            );
        },
        getItems() {
            this.$http.get(`/${this.resource}/item/tables`).then(response => {
                let data = response.data;
                if (this.config.show_extra_info_to_item) {
                    this.$store.commit("setColors", data.colors);
                    this.$store.commit("setCatItemSize", data.CatItemSize);
                    this.$store.commit(
                        "setCatItemMoldCavity",
                        data.CatItemMoldCavity
                    );
                    this.$store.commit(
                        "setCatItemMoldProperty",
                        data.CatItemMoldProperty
                    );
                    this.$store.commit(
                        "setCatItemUnitBusiness",
                        data.CatItemUnitBusiness
                    );
                    this.$store.commit("setCatItemStatus", data.CatItemStatus);
                    this.$store.commit(
                        "setCatItemPackageMeasurement",
                        data.CatItemPackageMeasurement
                    );
                    this.$store.commit(
                        "setCatItemProductFamily",
                        data.CatItemProductFamily
                    );
                    this.$store.commit(
                        "setCatItemUnitsPerPackage",
                        data.CatItemUnitsPerPackage
                    );
                }
            });
        },
        handleSelectionChange(id) {
          const index = this.selected.indexOf(id);
          if (index > -1) {
            this.selected.splice(index, 1);
          } else {
            this.selected.push(id);
          }
          console.log(this.selected);
          
        }
    }
};
</script>
