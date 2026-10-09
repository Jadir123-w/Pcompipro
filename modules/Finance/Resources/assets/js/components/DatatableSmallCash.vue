<template>
    <div v-loading="loading_submit">
        <div class="row ">
            <div class="col-md-12 col-lg-12 col-xl-12 filter-container">
                <!-- Nuevos filtros adicionales -->
                <div class="row mb-3">
                  <div class="col-md-3">
                    <label class="control-label">Estado</label>
                    <el-select
                      v-model="additionalFilters.status"
                      clearable
                      placeholder="Todos"
                      @change="getRecords"
                      style="width: 100%;"
                    >
                      <el-option label="Abiertas" value="open"></el-option>
                      <el-option label="Cerradas" value="closed"></el-option>
                    </el-select>
                  </div>
                  <div class="col-md-3" v-if="typeUser === 'admin'">
                    <label class="control-label">Usuario</label>
                    <el-select
                      v-model="additionalFilters.user_id"
                      clearable
                      placeholder="Todos"
                      filterable
                      @change="getRecords"
                      style="width: 100%;"
                    >
                      <el-option
                        v-for="user in users"
                        :key="user.id"
                        :label="user.name"
                        :value="user.id">
                      </el-option>
                    </el-select>
                  </div>
                </div>

                <div class="btn-filter-content">
                    <el-button
                        type="secondary"
                        class="btn-show-filter mb-3"
                        :class="{ shift: isVisible }"
                        @click="toggleInformation"
                    >
                        {{ isVisible ? "Ocultar filtros" : "Mostrar filtros" }}
                    </el-button>
                </div>
                <div class="row" v-if="applyFilter && isVisible">
                    <div class="col-lg-4 col-md-4 col-sm-12 pb-2">
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
                    <div class="col-lg-3 col-md-4 col-sm-12 pb-2">
                        <template
                            v-if="
                                search.column === 'date_opening'
                            "
                        >
                            <el-date-picker
                                v-model="search.value"
                                type="date"
                                style="width: 100%;"
                                placeholder="Seleccione una fecha"
                                value-format="yyyy-MM-dd"
                                format="dd-MM-yyyy"
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
                </div>
            </div>

            <div class="col-md-12">
                <div class="scroll-shadow shadow-left" v-show="showLeftShadow"></div>
                <div class="scroll-shadow shadow-right" v-show="showRightShadow"></div>
                <div class="table-responsive" ref="scrollContainer">
                    <table class="table">
                        <thead>
                            <slot name="heading"></slot>
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
        typeUser: {
          type: String,
          required: false,
          default: ''
        },
        users: {
          type: Array,
          required: false,
          default: () => []
        }
    },
    data() {
        return {
            search: {
                column: null,
                value: null
            },
            additionalFilters: {
              status: null,
              user_id: null
            },
            columns: [],
            records: [],
            pagination: {},
            isVisible: false,
            loading_submit: false,
            showLeftShadow: false,
            showRightShadow: false,
        };
    },
    created() {
        this.$eventHub.$on("reloadData", () => {
            this.getRecords();
        });
        this.$root.$refs.DataTable = this;
    },
    async mounted() {
        await this.$http
            .get(`/${this.resource}/columns`)
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
          const params = {
            page: this.pagination.current_page,
            limit: this.limit,
            ...this.search
          };

          // Agregar filtros adicionales solo si tienen valor
          if (this.additionalFilters.status) {
            params.status = this.additionalFilters.status;
          }
          if (this.additionalFilters.user_id) {
            params.user_id = this.additionalFilters.user_id;
          }

          return queryString.stringify(params);
        },
        changeClearInput() {
            this.search.value = "";
            this.getRecords();
        },
        getSearch() {
            return this.search;
        }
    }
};
</script>
