<template>
    <div>
        <div class="row">

            <div class="col-md-12 col-lg-12 col-xl-12 ">

                <div class="row mt-2">

                    <div class="col-md-3 form-modern">
                        <label class="control-label">Periodo</label>
                        <el-select v-model="form.period" @change="changePeriod">
                            <el-option key="month" value="month" label="Por mes"></el-option>
                            <el-option key="between_months" value="between_months" label="Entre meses"></el-option>
                            <el-option key="date" value="date" label="Por fecha"></el-option>
                            <el-option key="between_dates" value="between_dates" label="Entre fechas"></el-option>
                        </el-select>
                    </div>
                    <template v-if="form.period === 'month' || form.period === 'between_months'">
                        <div class="col-md-3 form-modern">
                            <label class="control-label">Mes de</label>
                            <el-date-picker v-model="form.month_start" type="month"
                                            @change="changeDisabledMonths"
                                            value-format="yyyy-MM" format="MM/yyyy" :clearable="false"></el-date-picker>
                        </div>
                    </template>
                    <template v-if="form.period === 'between_months'">
                        <div class="col-md-3">
                            <label class="control-label">Mes al</label>
                            <el-date-picker v-model="form.month_end" type="month"
                                            :picker-options="pickerOptionsMonths"
                                            value-format="yyyy-MM" format="MM/yyyy" :clearable="false"></el-date-picker>
                        </div>
                    </template>
                    <template v-if="form.period === 'date' || form.period === 'between_dates'">
                        <div class="col-md-3">
                            <label class="control-label">Fecha del</label>
                            <el-date-picker v-model="form.date_start" type="date"
                                            @change="changeDisabledDates"
                                            value-format="yyyy-MM-dd" format="dd/MM/yyyy"
                                            :clearable="false"></el-date-picker>
                        </div>
                    </template>
                    <template v-if="form.period === 'between_dates'">
                        <div class="col-md-3">
                            <label class="control-label">Fecha al</label>
                            <el-date-picker v-model="form.date_end" type="date"
                                            :picker-options="pickerOptionsDates"
                                            value-format="yyyy-MM-dd" format="dd/MM/yyyy"
                                            :clearable="false"></el-date-picker>
                        </div>
                    </template>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">Sucursal</label>
                            <el-select v-model="form.establishment_id" 
                                       clearable 
                                       filterable
                                       @change="loadProductsByEstablishment">
                                <el-option v-for="option in establishments" :key="option.id" :value="option.id"
                                           :label="option.name"></el-option>
                            </el-select>
                        </div>
                    </div>

                    <div class="col-lg-7 col-md-7 col-md-7 col-sm-12 form-modern" style="margin-top:29px">
                    
                        <label class="control-label">Buscar Producto</label>
                        <el-select 
                            v-model="form.item_id" 
                            placeholder="Ingrese nombre o código de producto" 
                            filterable
                            clearable
                            :loading="loading_items"
                            @change="handleProductSelect">
                            <el-option 
                                v-for="option in items" 
                                :key="option.id" 
                                :value="option.id"
                                :label="option.full_description">
                            </el-option>
                        </el-select>
                    
                        <!-- <el-input 
                            v-model="form.search_query" 
                            placeholder="Ingrese nombre o código de producto" 
                            clearable
                            @input="getRecordsByFilter">
                        </el-input> -->
                    
                        <el-button class="submit mt-2" type="primary" @click.prevent="getRecordsByFilter"
                                   :loading="loading_submit" icon="el-icon-search">Buscar
                        </el-button>

                        <template v-if="records.length>0">

                            <el-button class="submit" type="success" @click.prevent="clickDownload('excel')"><i
                                class="fa fa-file-excel"></i> Exportar Excel
                            </el-button>

                        </template>

                        <!-- <el-tooltip class="item"
                                    content="Formato SUNAT 13.1"
                                    effect="dark"
                                    placement="top">

                            <el-button class="submit" type="success" @click.prevent="clickDownload('excel-format-sunat')"><i
                                class="fa fa-file-excel"></i> Exportar Format Sunat
                            </el-button>
                        </el-tooltip> -->

                    </div>

                </div>
                <div class="row mt-1 mb-4">

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
                        <slot v-for="(row, index) in records" :row="row" :index="customIndex(index)"></slot>
                        </tbody>
                    </table>
                    <div>
                        <el-pagination
                            @current-change="getRecords"
                            layout="total, prev, pager, next"
                            :total="pagination.total"
                            :current-page.sync="pagination.current_page"
                            :page-size="pagination.per_page">
                        </el-pagination>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>
<style>
.font-custom {
    font-size: 15px !important
}
</style>
<script>

import moment from 'moment'
import queryString from 'query-string'

export default {
    props: {
        resource: String,
    },
    data() {
        return {
            loading_submit: false,
            loading_search: false,
            loading_items: false,
            columns: [],
            records: [],
            pagination: {},
            search: {},
            totals: {},
            establishment: null,
            establishments: [],
            items: [],
            form: {},
            pickerOptionsDates: {
                disabledDate: (time) => {
                    time = moment(time).format('YYYY-MM-DD')
                    return this.form.date_start > time
                }
            },
            pickerOptionsMonths: {
                disabledDate: (time) => {
                    time = moment(time).format('YYYY-MM')
                    return this.form.month_start > time
                }
            },
            showLeftShadow: false,
            showRightShadow: false,
        }
    },
    computed: {},
    created() {
        this.initForm()
        this.events()
    },
    async mounted() {
        this.$nextTick(() => {
            const el = this.$refs.scrollContainer;
            if (el) {
                el.addEventListener('scroll', this.checkScrollShadows);
                this.checkScrollShadows();
            }
        });

        await this.$http.get(`/${this.resource}/filter`)
            .then(response => {
                this.establishments = response.data.establishments;
            });

        // Cargar todos los productos inicialmente
        await this.loadAllProducts();
    },
    methods: {
        async loadAllProducts() {
            this.loading_items = true;
            try {
                // Intentar cargar todos los productos sin filtro de establishment
                await this.$http.get(`/${this.resource}/records`)
                    .then(response => {
                        // Si la respuesta tiene items directamente
                        if (response.data.items) {
                            this.items = response.data.items;
                        }
                        // Si la respuesta tiene data (paginado) - este es tu caso
                        else if (response.data.data) {
                            // Extraer items únicos de los records
                            const uniqueItems = [];
                            const itemIds = new Set();
                            
                            response.data.data.forEach(record => {
                                // Tu estructura: record.id, record.item_description
                                if (record.id && !itemIds.has(record.id)) {
                                    itemIds.add(record.id);
                                    uniqueItems.push({
                                        id: record.id,
                                        full_description: record.item_description || record.description || 'Sin descripción'
                                    });
                                }
                            });
                            
                            this.items = uniqueItems;
                        }
                    });
            } catch (error) {
                console.error('Error al cargar productos:', error);
                this.items = [];
            } finally {
                this.loading_items = false;
            }
        },
        async loadProductsByEstablishment() {
            if (!this.form.establishment_id) {
                // Si no hay establishment, cargar todos
                await this.loadAllProducts();
                return;
            }

            this.loading_items = true;
            this.items = [];
            this.form.item_id = null;
            
            try {
                // Intentar cargar productos por establishment
                await this.$http.get(`/${this.resource}/records?establishment_id=${this.form.establishment_id}`)
                    .then(response => {
                        // Extraer items únicos de los records
                        const uniqueItems = [];
                        const itemIds = new Set();
                        
                        if (response.data.data) {
                            response.data.data.forEach(record => {
                                // Tu estructura: record.id, record.item_description
                                if (record.id && !itemIds.has(record.id)) {
                                    itemIds.add(record.id);
                                    uniqueItems.push({
                                        id: record.id,
                                        full_description: record.item_description || record.description || 'Sin descripción'
                                    });
                                }
                            });
                        }
                        
                        this.items = uniqueItems;
                    });
            } catch (error) {
                console.error('Error al cargar productos por establishment:', error);
            } finally {
                this.loading_items = false;
            }
        },
        handleProductSelect(value) {
            // Cuando se selecciona un producto del dropdown
            console.log('Producto seleccionado ID:', value);
            
            // Encontrar el producto seleccionado para obtener su descripción
            const selectedProduct = this.items.find(item => item.id === value);
            
            if (selectedProduct) {
                // Guardar la descripción en search_query para que el backend la use
                this.form.search_query = selectedProduct.full_description;
                console.log('Descripción guardada:', this.form.search_query);
            }
        },
        checkScrollShadows() {
            const el = this.$refs.scrollContainer;
            if (!el) return;
            
            const scrollLeft = el.scrollLeft;
            const scrollRight = el.scrollWidth - el.clientWidth - scrollLeft;
            
            this.showLeftShadow = scrollLeft > 1;
            this.showRightShadow = scrollRight > 1;
        },
        exportFormatSunat(item_id){
            
            let data = this.form
            data.item_id = item_id

            let query = queryString.stringify({
                ...data
            })

            window.open(`/${this.resource}/excel-format-sunat/?${query}`, '_blank')

        },
        events(){
            
            this.$eventHub.$on('exportFormatSunat', (item_id) => {
                this.exportFormatSunat(item_id)
            })

            this.$eventHub.$on('reloadData', () => {
                this.getRecords()
            })

        },
        clickDownload(type) {
            let query = queryString.stringify({
                ...this.form
            });
            window.open(`/${this.resource}/${type}/?${query}`, '_blank');
        },
        initForm() {

            this.form = {
                establishment_id: null,
                period: 'month',
                date_start: moment().format('YYYY-MM-DD'),
                date_end: moment().format('YYYY-MM-DD'),
                month_start: moment().format('YYYY-MM'),
                month_end: moment().format('YYYY-MM'),
                search_query: '',
                item_id: null
            }

        },
        customIndex(index) {
            return (this.pagination.per_page * (this.pagination.current_page - 1)) + index + 1
        },
        async getRecordsByFilter() {

            this.loading_submit = true;
            await this.getRecords();
            this.loading_submit = false;

        },
        getRecords() {
            return this.$http.get(`/${this.resource}/records?${this.getQueryParameters()}`).then((response) => {
                this.records = response.data.data
                this.pagination = response.data.meta
                this.pagination.per_page = parseInt(response.data.meta.per_page)
                this.loading_submit = false
                // this.initTotals()
            });


        },
        getQueryParameters() {
            // Crear una copia del form para enviar al backend
            const params = {
                page: this.pagination.current_page,
                limit: this.limit,
                ...this.form
            };
            
            // Si hay un item_id seleccionado, usarlo como search_query también
            if (this.form.item_id) {
                // Encontrar el producto para enviar su descripción
                const selectedProduct = this.items.find(item => item.id === this.form.item_id);
                if (selectedProduct) {
                    params.search_query = selectedProduct.full_description;
                }
            }
            
            console.log('Parámetros enviados al backend:', params);
            return queryString.stringify(params);
        },

        changeDisabledDates() {
            if (this.form.date_end < this.form.date_start) {
                this.form.date_end = this.form.date_start
            }
            // this.loadAll();
        },
        changeDisabledMonths() {
            if (this.form.month_end < this.form.month_start) {
                this.form.month_end = this.form.month_start
            }
            // this.loadAll();
        },
        changePeriod() {
            if (this.form.period === 'month') {
                this.form.month_start = moment().format('YYYY-MM');
                this.form.month_end = moment().format('YYYY-MM');
            }
            if (this.form.period === 'between_months') {
                this.form.month_start = moment().startOf('year').format('YYYY-MM'); //'2019-01';
                this.form.month_end = moment().endOf('year').format('YYYY-MM');
                ;
            }
            if (this.form.period === 'date') {
                this.form.date_start = moment().format('YYYY-MM-DD');
                this.form.date_end = moment().format('YYYY-MM-DD');
            }
            if (this.form.period === 'between_dates') {
                this.form.date_start = moment().startOf('month').format('YYYY-MM-DD');
                this.form.date_end = moment().endOf('month').format('YYYY-MM-DD');
            }
            // this.loadAll();
        },
    }
}
</script>