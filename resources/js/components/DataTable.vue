<template>
    <div v-loading="loading_submit">
        <div class="row ">
            <div class="filter-container"
            :class="{
              'col-md-12 col-lg-12 col-xl-12': !fromEcommerce && !fromRestaurant,
              'col-md-6 col-lg-6 col-xl-6': fromEcommerce || fromRestaurant
            }">
                <div class="btn-filter-content">
                    <el-button
                        type="secondary"
                        class="btn-show-filter mb-2"
                        :class="{ shift: isVisible }"
                        @click="toggleInformation"
                    >
                        {{ isVisible ? "Ocultar filtros" : "Mostrar filtros" }}
                    </el-button>
                </div>
                <div class="row filter-content" v-if="applyFilter && isVisible">
                    <div class="col-sm-12 pb-2"
                    :class="{
                      'col-lg-4 col-md-4': !fromEcommerce && !fromRestaurant,
                      'col-md-6 col-lg-6': fromEcommerce || fromRestaurant
                    }">
                        <div class="d-flex">
                            <div class="d-flex align-items-center" style="width:100px">
                                Filtrar por:
                            </div>
                            <el-select
                                v-model="search.column"
                                placeholder="Select"
                                @change="changeClearInput"
                            >
                                <el-option
                                    v-for="(label, key) in columns"
                                    :key="key"
                                    :value="key"
                                    :label="label"
                                ></el-option>
                            </el-select>
                        </div>
                    </div>
                    <div class="col-sm-12 pb-2"
                    :class="{
                      'col-lg-3 col-md-3': !fromEcommerce && !fromRestaurant,
                      'col-md-5 col-lg-5': fromEcommerce || fromRestaurant
                    }">
                        <template
                            v-if="
                                search.column === 'date_of_issue' ||
                                search.column === 'date_of_due' ||
                                search.column === 'date_of_payment' ||
                                search.column === 'delivery_date'
                            "
                        >
                            <el-date-picker
                                v-model="search.value"
                                type="date"
                                style="width: 100%;"
                                placeholder="Buscar"
                                value-format="yyyy-MM-dd"
                                @change="getRecords"
                            >
                            </el-date-picker>
                        </template>
                        <template v-else>
                            <el-input
                                placeholder="Buscar"
                                v-model="search.value"
                                style="width: 100%;"
                                prefix-icon="el-icon-search"
                                @input="getRecords"
                            >
                            </el-input>
                        </template>
                    </div>
                    <div class="col-lg-5 col-md-5 col-sm-12 pb-2 d-flex" v-if="showProductFilter">
                        <div class="d-flex align-items-center col-4 justify-content-end">
                            Listar productos
                        </div>
                        <el-select
                          class="col-8"
                          v-model="showDisabledValue"
                          placeholder="Filtrar productos"
                          size="small"
                          @change="handleShowDisabledChange"
                        >
                          <el-option label="Todos" value="all"></el-option>
                          <el-option label="Habilitados" value="enabled"></el-option>
                          <el-option label="Inhabilitados" value="disabled"></el-option>
                        </el-select>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-6">
                <div class="row" v-if="fromRestaurant||fromEcommerce">
                    <div class="col-lg-12 col-md-12 col-sm-12 pb-2 d-flex">
                        <div class="d-flex col-5 pl-0" v-if="fromRestaurant||fromEcommerce">
                            <div class="my-auto w-100">
                                <el-button  @click="methodVisibleAllProduct" type="primary" size="mini" icon="el-icon-check" class="w-100 button-truncate pl-2 pr-4" title="Mostrar todos los productos">
                                    Mostrar todos los productos
                                    <el-tooltip
                                        class="item"
                                        content="Solo se mostrarán productos con código interno registrado. Esta opción aplica para el canal actual."
                                        effect="dark"
                                        placement="top-start"
                                    >
                                        <i class="fa fa-info-circle"></i>
                                    </el-tooltip>
                                </el-button>
                            </div>
                        </div>

                        <div class="d-flex col-7 px-0">
                            <div class="col-5 list-products-container py-1">
                                Listar productos
                            </div>
                            <el-select
                                class="col-7 pr-0"
                                v-model="search.list_value"
                                placeholder="Select"
                                @change="getRecords"
                            >
                                <el-option
                                    v-for="(label, key) in list_columns"
                                    :key="key"
                                    :value="key"
                                    :label="label"
                                ></el-option>
                            </el-select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12 position-relative">
                <div class="scroll-shadow shadow-left" v-show="showLeftShadow"></div>
                <div class="scroll-shadow shadow-right" v-show="showRightShadow"></div>

                <div class="table-responsive table-responsive-new" ref="scrollContainer">
                    <table class="table">
                        <thead>
                            <slot name="heading" :sort="handleSort"></slot>
                        </thead>
                        <tbody>
                            <slot
                                v-for="(row, index) in records"
                                :row="row"
                                :index="customIndex(index)"
                            ></slot>
                        </tbody>
                    </table>
                    <div>
                        <el-pagination
                            @current-change="getRecords"
                            layout="total, prev, pager, next"
                            :total="pagination.total"
                            :current-page.sync="pagination.current_page"
                            :page-size="pagination.per_page"
                        >
                        </el-pagination>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<style>
.button-truncate, .list-products-container {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  display: inline-block;
  width: 100%;
  text-align: left;
}
</style>
<script>
import queryString from "query-string";

export default {
    props: {
        productType: {
            type: String,
            required: false,
            default: ''
        },
        resource: String,
        applyFilter: {
            type: Boolean,
            default: true,
            required: false
        },
        pharmacy: Boolean,
        restaurant: Boolean,
        ecommerce: Boolean,
        sortField: {
            type: String,
            default: 'id'
        },
        sortDirection: {
            type: String,
            default: 'desc'
        },
        showProductFilter: {
            type: Boolean,
            default: false,
            required: false
        }
    },
    data() {
        return {
            search: {
                column: null,
                value: null,
                list_value: 'all',
            },
            columns: [],
            records: [],
            pagination: {},
            isVisible: false,
            loading_submit: false,
            fromPharmacy: false,
            fromRestaurant: false,
            fromEcommerce: false,
            list_columns: {
                all:'Todos',
                visible:'Visibles',
                hidden:'Ocultos'
            },
            currentSort: {
                field: this.sortField,
                direction: this.sortDirection
            },
            showLeftShadow: false,
            showRightShadow: false,
            showDisabledValue: localStorage.getItem('filterDisabled') || 'all',
        };
    },
    created() {
        if(this.pharmacy !== undefined && this.pharmacy === true){
            this.fromPharmacy = true;
        }
        if(this.ecommerce !== undefined && this.ecommerce === true){
            this.fromEcommerce = true;
        }
        if(this.restaurant !== undefined && this.restaurant === true){
            this.fromRestaurant = true;
        }
        this.$eventHub.$on("reloadData", () => {
            this.getRecords();
        });
        this.$root.$refs.DataTable = this;
    },
    async mounted() {
        let column_resource = _.split(this.resource, "/");
        await this.$http
            .get(`/${_.head(column_resource)}/columns`)
            .then(response => {
                this.columns = response.data;
                this.search.column = _.head(Object.keys(this.columns));
            });
        await this.getRecords();

        this.$nextTick(() => {
            const el = this.$refs.scrollContainer;
            if (el) {
                el.addEventListener('scroll', this.checkScrollShadows);
                this.checkScrollShadows();
            }
        });
    },
    methods: {
        handleShowDisabledChange() {
          localStorage.setItem('filterDisabled', this.showDisabledValue)
          this.getRecords();
        },
        checkScrollShadows() {
            const el = this.$refs.scrollContainer;
            if (!el) return;

            const scrollLeft = el.scrollLeft;
            const scrollRight = el.scrollWidth - el.clientWidth - scrollLeft;

            this.showLeftShadow = scrollLeft > 1;
            this.showRightShadow = scrollRight > 1;
        },
        toggleInformation() {
            this.isVisible = !this.isVisible;
        },
        customIndex(index) {
            return (
                this.pagination.per_page * (this.pagination.current_page - 1) +
                index +
                1
            );
        },
        getRecords() {
            this.loading_submit = true;
            return this.$http
                .get(`/${this.resource}/records?${this.getQueryParameters()}`)
                .then(response => {
                    this.records = response.data.data;
                    this.pagination = response.data.meta;
                    this.pagination.per_page = parseInt(
                        response.data.meta.per_page
                    );
                })
                .catch(error => {})
                .then(() => {
                    this.loading_submit = false;
                });
        },
        getQueryParameters() {
            if (this.productType == 'ZZ') {
                this.search.type = 'ZZ';
            }
            if (this.productType == 'PRODUCTS') {
                // Debe listar solo productos
                this.search.type = this.productType;
            }
            return queryString.stringify({
                page: this.pagination.current_page,
                limit: this.limit,
                isPharmacy:this.fromPharmacy,
                isRestaurant:this.fromRestaurant,
                isEcommerce:this.fromEcommerce,
                sort_field: this.currentSort.field,
                sort_direction: this.currentSort.direction,
                show_disabled: this.showDisabledValue,
                ...this.search
            });
        },
        changeClearInput() {
            this.search.value = "";
            this.getRecords();
        },
        getSearch() {
            return this.search;
        },
        async methodVisibleAllProduct() {
            let response = await this.$http.post(`/${this.resource}/visibleMassive`,{
                resource: this.fromRestaurant ? 'restaurant' : 'ecommerce',
            });
            console.log(response);

            if (response.status === 200) {
                this.$message.success(response.data.message);
                this.getRecords()
            } else {
                this.$message.error(response.data.message);

            }
        },
        handleSort(field) {
            if (this.currentSort.field === field) {
                if (this.currentSort.direction === 'asc') {
                    this.currentSort.direction = 'desc';
                } else if (this.currentSort.direction === 'desc' && field === 'description') {
                    this.currentSort.field = 'id';
                    this.currentSort.direction = 'desc';
                } else {
                    this.currentSort.direction = 'asc';
                }
            } else {
                this.currentSort.field = field;
                this.currentSort.direction = 'asc';
            }

            this.$emit('sort-change', this.currentSort);
            this.getRecords();
        }
    },
    watch: {
        showDisabled(newVal) {
            if (newVal) {
              this.getRecords();
            }
        },
        sortField(newVal) {
            this.currentSort.field = newVal;
        },
        sortDirection(newVal) {
            this.currentSort.direction = newVal;
        }
    }
};
</script>
