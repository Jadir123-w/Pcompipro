<template>
    <div v-loading="loading_submit">
        <div class="row ">
            <div class="col-md-12 col-lg-12 col-xl-12 filter-container">
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
                    <div class="col-12 col-md-auto pb-2">
                        <div class="d-flex">
                            <div class="d-flex align-items-center text-nowrap mr-2">
                                Filtrar por:
                            </div>
                            <el-select
                                v-model="search.column"
                                placeholder="Select"
                                :style="{ width: selectWidth }"
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
                    <div class="col-lg-2 col-md-4 col-sm-12 pb-2">
                        <template
                            v-if="
                                search.column === 'periodo'
                            "
                        >
                            <el-date-picker
                                v-model="search.value"
                                type="month"
                                style="width: 100%;"
                                placeholder="Buscar"
                                value-format="yyyy-MM"
                                @change="searchNow"
                            >
                            </el-date-picker>
                        </template>
                        <template v-else>
                            <el-input
                                placeholder="Buscar"
                                v-model="search.value"
                                style="width: 100%;"
                                prefix-icon="el-icon-search"
                                @input="searchDebounced"
                            >
                            </el-input>
                        </template>
                    </div>
                    <!-- Filtros adicionales que aporta cada vista -->
                    <slot name="filters"></slot>
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
        // Parámetros extra del listado (p. ej. rangos); al cambiar se busca desde la página 1.
        extraParams: {
            type: Object,
            default: () => ({})
        },
    },
    computed: {
        // Ancho del selector acorde a su opción más larga (sin estirarse a toda la columna).
        selectWidth() {
            const longest = Math.max(0, ...Object.values(this.columns || {}).map(l => String(l).length));
            return Math.min(260, Math.max(130, longest * 8 + 50)) + "px";
        }
    },
    watch: {
        extraParams: {
            deep: true,
            handler() {
                this.searchNow();
            }
        }
    },
    data() {
        return {
            search: {
                column: null,
                value: null
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
        // Espera 300 ms tras la última tecla antes de consultar.
        this.searchDebounced = _.debounce(this.searchNow, 300);
        this.requestSeq = 0;
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
        // Nueva búsqueda: vuelve a la página 1 y consulta de inmediato.
        searchNow() {
            this.searchDebounced.cancel();
            this.$set(this.pagination, "current_page", 1);
            return this.getRecords();
        },
        getRecords() {
            this.loading_submit = true;
            // Solo la última consulta pinta la tabla: una respuesta vieja no pisa a la nueva.
            const seq = ++this.requestSeq;
            return this.$http
                .get(`/${this.resource}/records?${this.getQueryParameters()}`)
                .then(response => {
                    if (seq !== this.requestSeq) return;
                    this.records = response.data.data;
                    this.pagination = response.data.meta;
                    this.pagination.per_page = parseInt(
                        response.data.meta.per_page
                    );
                })
                .catch(error => {
                    if (seq !== this.requestSeq) return;
                    this.$message.error("No se pudo cargar el listado. Intenta de nuevo.");
                })
                .then(() => {
                    if (seq === this.requestSeq) this.loading_submit = false;
                });
        },
        getQueryParameters() {
            return queryString.stringify({
                page: this.pagination.current_page,
                limit: this.limit,
                ...this.search,
                ...this.extraParams
            });
        },
        changeClearInput() {
            this.search.value = "";
            this.searchNow();
        },
        getSearch() {
            return this.search;
        }
    }
};
</script>
