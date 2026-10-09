<template>
    <div>
        <!-- Filtros -->
        <div class="btn-filter-content">
            <el-button
                type="secondary"
                class="btn-show-filter"
                :class="{ shift: isVisible }"
                @click="toggleInformation"
            >
                {{ isVisible ? "Ocultar filtros" : "Mostrar filtros" }}
            </el-button>
        </div>
        <div class="row" v-if="isVisible">
            <!-- Filtros de periodo y marca igual que antes -->
            <!-- ...código de filtros igual que antes... -->
            <div class="col-md-3 form-modern">
                <label class="control-label">Periodo</label>
                <el-select v-model="form.period" @change="changePeriod">
                    <el-option key="month" label="Por mes" value="month"></el-option>
                    <el-option key="between_months" label="Entre meses" value="between_months"></el-option>
                    <el-option key="date" label="Por fecha" value="date"></el-option>
                    <el-option key="between_dates" label="Entre fechas" value="between_dates"></el-option>
                </el-select>
            </div>
            <template v-if="form.period === 'month' || form.period === 'between_months'">
                <div class="col-md-3 form-modern">
                    <label class="control-label">Mes de</label>
                    <el-date-picker v-model="form.month_start"
                        :clearable="false"
                        format="MM/yyyy"
                        type="month"
                        value-format="yyyy-MM"
                        @change="changeDisabledMonths"></el-date-picker>
                </div>
            </template>
            <template v-if="form.period === 'between_months'">
                <div class="col-md-3">
                    <label class="control-label">Mes al</label>
                    <el-date-picker v-model="form.month_end"
                        :clearable="false"
                        :picker-options="pickerOptionsMonths"
                        format="MM/yyyy"
                        type="month"
                        value-format="yyyy-MM"></el-date-picker>
                </div>
            </template>
            <template v-if="form.period === 'date' || form.period === 'between_dates'">
                <div class="col-md-3">
                    <label class="control-label">Fecha del</label>
                    <el-date-picker v-model="form.date_start"
                        :clearable="false"
                        format="dd/MM/yyyy"
                        type="date"
                        value-format="yyyy-MM-dd"
                        @change="changeDisabledDates"></el-date-picker>
                </div>
            </template>
            <template v-if="form.period === 'between_dates'">
                <div class="col-md-3">
                    <label class="control-label">Fecha al</label>
                    <el-date-picker v-model="form.date_end"
                        :clearable="false"
                        :picker-options="pickerOptionsDates"
                        format="dd/MM/yyyy"
                        type="date"
                        value-format="yyyy-MM-dd"></el-date-picker>
                </div>
            </template>
            <!-- Filtro de marca -->
            <div class="col-md-4">
                <label class="control-label">Marca</label>
                <el-select v-model="form.brand_id"
                    filterable
                    clearable
                    placeholder="Nombre de la marca"
                    :loading="loading_search_brands">
                    <el-option :value="null" label="Todas las marcas"></el-option>
                    <el-option v-for="option in brands" :key="option.id" :value="option.id" :label="option.name"></el-option>
                </el-select>
            </div>
            <div class="col-lg-7 col-md-7 col-sm-12 mt-3">
                <el-button :loading="loading_submit"
                    class="submit"
                    icon="el-icon-search"
                    type="primary"
                    @click.prevent="getRecordsByFilter">Buscar
                </el-button>
                <template v-if="records && records.length > 0">
                    <el-button
                        class="submit"
                        type="success"
                        @click.prevent="clickDownload('excel')"
                    >
                        <i class="fa fa-file-excel"></i> Exportar Excel
                    </el-button>
                    <el-button
                        class="submit"
                        type="danger"
                        icon="el-icon-tickets"
                        @click.prevent="clickDownload('pdf')"
                    >
                        Exportar PDF
                    </el-button>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
import moment from 'moment'
import queryString from 'query-string'

export default {
    props: {
        resource: String
    },
    data() {
        return {
            isVisible: true,
            loading_submit: false,
            brands: [],
            brand_id: null,
            loading_search_brands: false,
            form: {
                period: 'month',
                date_start: moment().format('YYYY-MM-DD'),
                date_end: moment().format('YYYY-MM-DD'),
                month_start: moment().format('YYYY-MM'),
                month_end: moment().format('YYYY-MM'),
                brand_id: null
            },
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
            records: []
        }
    },
    mounted() {
        this.getBrands()
        this.getRecordsByFilter()
    },
    methods: {
        toggleInformation() {
            this.isVisible = !this.isVisible
        },
        changeDisabledDates() {
            if (this.form.date_end < this.form.date_start) {
                this.form.date_end = this.form.date_start
            }
        },
        changeDisabledMonths() {
            if (this.form.month_end < this.form.month_start) {
                this.form.month_end = this.form.month_start
            }
        },
        changePeriod() {
            if (this.form.period === 'month') {
                this.form.month_start = moment().format('YYYY-MM');
                this.form.month_end = moment().format('YYYY-MM');
            }
            if (this.form.period === 'between_months') {
                this.form.month_start = moment().startOf('year').format('YYYY-MM');
                this.form.month_end = moment().endOf('year').format('YYYY-MM');
            }
            if (this.form.period === 'date') {
                this.form.date_start = moment().format('YYYY-MM-DD');
                this.form.date_end = moment().format('YYYY-MM-DD');
            }
            if (this.form.period === 'between_dates') {
                this.form.date_start = moment().startOf('month').format('YYYY-MM-DD');
                this.form.date_end = moment().endOf('month').format('YYYY-MM-DD');
            }
        },
        async getBrands() {
            this.loading_search_brands = true
            await this.$http.get('/reports/sales-by-brand/filter')
                .then(response => {
                    this.brands = response.data.brands
                    this.loading_search_brands = false
                })
                .catch(() => {
                    this.loading_search_brands = false
                })
        },
        // searchRemoteBrands(input) {
        //     this.getBrands(input)
        // },
        async getRecordsByFilter() {
            this.loading_submit = true
            this.records = []
            this.$emit('update:records', [])
            let params = queryString.stringify(this.form)
            await this.$http.get(`/${this.resource}/records?${params}`)
                .then(response => {
                    this.records = response.data.data   
                    this.$emit('update:records', response.data.data)
                    this.loading_submit = false
                })
                .catch(() => {
                    this.records = []
                    this.$emit('update:records', [])
                    this.loading_submit = false
                })
        },
        clickDownload(type) {
            let params = queryString.stringify(this.form)
            window.open(`/${this.resource}/${type}/?${params}`, '_blank')
        }
    }
}
</script>