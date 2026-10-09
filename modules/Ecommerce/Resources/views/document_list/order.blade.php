@extends('ecommerce::layouts.layout_ecommerce_cart.index')
@section('content')
<style>

    .table-loader {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.6);
  backdrop-filter: blur(3px);
  z-index: 10;
  display: none; /* lo ocultamos por defecto */
}
.table.table-cart tr th{
    padding: 1rem !important;
}
</style>

<div id="app">
    <div class="mb-2 mt-4">
        <div>
            <h2 class="mb-0">Listado de Pedidos</h4>
        </div>
    </div>
    <div class="col-lg-12">
        <div class="cart-table-container">
            <div class="dropdown dropdown-table d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center mt-1">                    
                    <template v-if="filterId == 1">
                        <span>
                            Fecha de inicio
                        </span>
                        <el-date-picker
                            v-model="filters.date_of_start"
                            type="date"
                            placeholder="Seleccionar fecha"
                            size="small"
                            style="width: 200px; margin-left: 8px;"
                            format="dd/MM/yyyy"
                            value-format="yyyy-MM-dd"
                            clearable>
                        </el-date-picker>
                        <span style="margin-left: 10px">
                            Fecha de fin
                        </span>
                        <el-date-picker
                            v-model="filters.date_of_end"
                            type="date"
                            placeholder="Seleccionar fecha"
                            size="small"
                            style="width: 200px; margin-left: 8px;"
                            format="dd/MM/yyyy"
                            value-format="yyyy-MM-dd"
                            clearable>
                        </el-date-picker>
                        <button
                            class="btn btn-filter-search ml-2 p-0"
                            @click="getRecords()"
                            type="button"
                            aria-label="Buscar">
                            <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-search"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg>
                        </button>
                    </template>
                    <template v-else-if="filterId == 2">
                        <span>
                            Por estado:
                        </span>
                        <el-select 
                            v-model="filters.state_order_id" 
                            placeholder="Seleccionar estado"
                            size="small"
                            style="width: 200px; margin-left: 8px;"
                            class="select-state"
                            @change="getRecords()"
                            clearable>
                            <el-option
                                label="Todos"
                                value="all">
                            </el-option>
                            <el-option
                                label="Pago sin verificar"
                                value="1">
                            </el-option>
                            <el-option
                                label="Pago verificado"
                                value="2">
                            </el-option>
                            <el-option
                                label="Despachado"
                                value="3">
                            </el-option>
                            <el-option
                                label="Confirmado por el cliente"
                                value="4">
                            </el-option>
                        </el-select>
                    </template>
                </div>

                <button class="btn btn-default dropdown-toggle mr-2 mt-1" style="height: 35px" type="button" id="dropdownFilter" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                  Filtros <span class="caret"></span>
                </button>
                <ul class="dropdown-menu  dropdown-menu-table" aria-labelledby="dropdownFilter">
                  {{-- <li><a href="#" @click="filterRecords('all')" >Todos</a></li>
                  <li><a href="#" @click="filterRecords('1')" >Pago sin verificar</a></li>
                  <li><a href="#" @click="filterRecords('2')" >Pago verificado</a></li>
                  <li><a href="#" @click="filterRecords('3')" >Despachado</a></li>
                  <li><a href="#" @click="filterRecords('4')" >Confirmado por el cliente</a></li> --}}
                  <li><a @click="filterId = 1; filters={date_of_issue: new Date().toISOString().split('T')[0]}; getRecords()" href="#">Por fecha</a></li> 
                  <li><a @click="filterId = 2; filters={state_order_id: 'all'}; getRecords()" href="#">Estado</a></li>
                </ul>
              </div>        
            <table class="table table-cart">
                <thead>
                    <tr>
                        <th class="product-col">Código de Pedido</th>
                        <th class="price-col">Total</th>
                        <th class="qty-col">Fecha de creación</th>
                        <th class="qty-col">Estado</th>
                        <th class="qty-col" v-if="phone_whatsapp"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in records" class="product-row">
                        <td class="text-left">
                            @{{ row.order_id }}
                        </td>
                        <td>S/ @{{ row.total }}</td>
                        <td>
                            @{{ row.created_at }}
                        </td>
                        <td>@{{ row.status_order_description }}</td>
                        <td v-if="phone_whatsapp">
                            <div v-if="row.status_order_id < 4">
                                <button class="btn btn-default btn-sm text-success ml-auto" @click="clickSendWhatsapp(row.order_id)" ><i class="fab fa-whatsapp fa-2x"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                </tbody>

            </table>
            <nav aria-label="Page navigation example">
                <ul class="pagination">
                  <li class="page-item" :class="{
                    disabled: pagL
                  }">
                    <a class="page-link" href="#" tabindex="-1" @click="clickRecord('back')" >&laquo;</a>
                  </li>
                  <li class="page-item active" aria-current="page">
                    <a class="page-link" href="#">@{{ page }}</a>
                  </li>
                  <li class="page-item" :class="{
                    disabled: pagR
                  }">
                    <a class="page-link" @click="clickRecord('front')">&raquo;</a>
                  </li>
                </ul>
              </nav>
              <div id="tableLoader" class=" d-flex justify-content-center align-items-center" :class="{
                'table-loader': loading,
              }">
                <div class="loader" role="status">
                </div>
              </div>
        </div><!-- End .cart-table-container -->
    </div><!-- End .col-lg-8 -->

</div>

<input type="hidden" id="total_amount" data-total="0.0">

@endsection

@push('scripts')
<!-- script src="https://checkout.culqi.com/js/v3"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@7.31.1/dist/sweetalert2.all.min.js"></script>
<script src="https://momentjs.com/downloads/moment.min.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script -->


<script type="text/javascript">
    Vue.use(ELEMENT, { locale: ELEMENT.lang.es });
    var app_cart = new Vue({
        el: '#app',
        data: {
            records: [],
            filter_records: [],
            page: 1,
            links: null,
            loading: false,
            filters: {},
            last_page: null,
            filterId: 1,
        },
        computed: {
            pagL: function () {
                return this.page == 1 ? true: false
            }, 
            pagR: function () {
                return this.page != this.last_page ? false: true
            }

        },
        created() {
            this.getRecords();
            // this.filter_records = this.records;
        },
        methods: {
            filterRecords(state_id)
            {
                this.page = 1;
                this.filters = {
                    state_order_id: state_id
                }

                this.getRecords()
            },
            async getRecords()
            {
                this.loading = true;

                let parameters = `?page=${this.page}`;

                if(!(this.filters && Object.keys(this.filters).length === 0)) {
                    for (const obj in this.filters) {
                        parameters += `&${obj}=${this.filters[obj]}`;
                    }
                }

                try {
                    let response = await axios.get(`/ecommerce/orders${parameters}`);            
                    
                    this.records = response.data.data || [];
                    this.filter_records = response.data.data || [];
                    this.last_page = response.data.last_page || 1;
                    this.loading = false;   
                } catch (error) {
                    console.error('Error al cargar pedidos:', error);
                    this.records = [];
                    this.filter_records = [];
                    this.loading = false;
                    
                    // Mostrar mensaje de error al usuario
                    if (error.response && error.response.status === 401) {
                        alert('Sesión expirada. Por favor, inicie sesión nuevamente.');
                        window.location.href = '/ecommerce/login';
                    }
                }
            },
            clickRecord(type)
            {
                if (type == "front") {
                    this.filter_records = [];
                    if(!this.pagR) {
                        this.page += 1;
                        this.getRecords();
                    }
                    
                } else if (type == "back") {
                    this.filter_records = [];
                    if(!this.pagL) {
                        this.page -= 1;
                        this.getRecords();
                    }
                }
            },

        }

    })

</script>


@endpush
