<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/hotels/reception">
                    <svg  xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-building-skyscraper"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0" /><path d="M5 21v-14l8 -4v18" /><path d="M19 21v-10l-6 -4" /><path d="M9 9l0 .01" /><path d="M9 12l0 .01" /><path d="M9 15l0 .01" /><path d="M9 18l0 .01" /></svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>VISTA GENERAL RECEPCIÓN</span></li>
            </ol>
            <div class="right-wrapper pull-right">
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
                        >Reporte recepción</a>

                    </div>
                </div>
            </div>
            
        </div>
        <div class="card tab-content-default row-new mb-0">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">Vista general recepción</h3>
            </div> -->
            <div class="card-body">
                <div class="row">
                    <!-- piso -->
                    <div class="col-md-3 col-sm-12 pb-2">
                        <el-select
                            v-model="hotel_floor_id"
                            :disabled="loading"
                            clearable
                            placeholder="Ubicación"
                            @change="searchRooms"
                        >
                            <el-option
                                v-for="f in floors"
                                :key="f.id"
                                :label="f.description"
                                :value="f.id"
                            >
                            </el-option>
                        </el-select>
                    </div>
                    <!-- Campo de busqueda -->
                    <div class="col-md-4 col-sm-12 pb-2">
                        <el-input
                            v-model="hotel_name_room"
                            clearable
                            placeholder="Buscar habitación"
                            prefix-icon="el-icon-search"
                            style="width: 100%;"
                            @input="searchRooms"
                        >
                        </el-input>
                    </div>
                    <!-- botones de status -->
                    <div class="col-md-5 col-sm-12 pb-2 text-right">
                        <el-button-group
                        >
                            <el-button
                                v-for="st in roomStatus"
                                :key="st"
                                :class="onGetColorStatus(st)"
                                :disabled="loading"
                                class="btn btn-sm"
                                size="mini"
                                @click="onFilterByStatus(st)"
                            >{{ st }}
                            </el-button
                            >
                        </el-button-group>
                    </div>
                </div>
                <div class="card-columns">
                    <div v-for="ro in items"
                         :key="ro.id"
                         class="card hotel-rooms">
                        <el-card
                            :class="onGetColorStatus(ro.status)"
                            shadow="never"
                        >
                            <div class="">
                                <!-- <h4>{{ ro.status }}</h4> -->
                                  
                                   <span class="text-muted">{{ ro.category.description }} - {{ ro.floor.description }}</span>
                                   <h2 class="mt-0">
                                    <svg  xmlns="http://www.w3.org/2000/svg"  width="32"  height="32"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-door"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 12v.01" /><path d="M3 21h18" /><path d="M6 21v-16a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v16" /></svg>
                                      <b>{{ ro.name }}</b>
                                    </h2>
                                   <p class="description">{{ ro.description }}</p>

                                <template v-if="ro.status === 'LIMPIEZA'">
                                    <el-button
                                        :disabled="loading"
                                        :loading="loading"
                                        title="Finalizar limpieza"
                                        class="btn btn-block btn-info"
                                        @click="onFinalizeClean(ro)"
                                    >
                                    <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-spray"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 10m0 2a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v7a2 2 0 0 1 -2 2h-4a2 2 0 0 1 -2 -2z" /><path d="M6 10v-4a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v4" /><path d="M15 7h.01" /><path d="M18 9h.01" /><path d="M18 5h.01" /><path d="M21 3h.01" /><path d="M21 7h.01" /><path d="M21 11h.01" /><path d="M10 7h1" /></svg>
                                        Finalizar limpieza
                                    </el-button>
                                </template>
                                <template v-if="ro.status === 'MANTENIMIENTO'">
                                    <h4 class="text-warning text-center mb-0">
                                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-tool"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 10h3v-3l-3.5 -3.5a6 6 0 0 1 8 8l6 6a2 2 0 0 1 -3 3l-6 -6a6 6 0 0 1 -8 -8l3.5 3.5" /></svg>
                                        <b>En mantenimiento:</b>
                                    </h4>
                                    <p class="text-center">Debe cambiar el estado a <b>Disponible</b> en el módulo Habitaciones.</p>
                                </template>
                                
                                <template v-if="ro.status === 'OCUPADO'">
                                    <div>
                                        <p>
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-user"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg>
                                    <span class="">{{ ro.rent.customer.name }}</span></p>
                                    <p>
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-calendar"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12z" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" /></svg>
                                        <span class="">{{ formatDate(ro.rent.output_date) }}
                                        <el-button
                                            title="Extender Tiempo"
                                            class="btn btn-xs"
                                            @click="ShowDialogExtendTimeRoom(ro)"
                                        > Modificar
                                        </el-button>
                                        <br>
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="18"  height="18"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-clock"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 7v5l3 3" /></svg> 
                                        {{ ro.rent.output_time }}
                                    </span>
                                    </p>
                                    
                                </div>
                                <div class="row">
                                    <div class="col-3">
                                        <button
                                            title="Agregar productos"
                                            class="btn btn-block btn-danger px-0 py-2"
                                            data-toggle="tooltip"
                                            @click="onGoToAddProducts(ro)"
                                        >
                                        <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-hotel-service"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8.5 10a1.5 1.5 0 0 1 -1.5 -1.5a5.5 5.5 0 0 1 11 0v10.5a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-2c0 -1.38 .71 -2.444 1.88 -3.175l4.424 -2.765c1.055 -.66 1.696 -1.316 1.696 -2.56a2.5 2.5 0 1 0 -5 0a1.5 1.5 0 0 1 -1.5 1.5z" /></svg> 
                                        </button>
                                    </div>
                                    <div class="col-9">
                                        <button
                                            title="Ir al checkout"
                                            class="btn btn-block btn-danger px-0 py-2"
                                            @click="onGoToCheckout(ro)"
                                        >Check-out 
                                            <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-door-exit"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13 12v.01" /><path d="M3 21h18" /><path d="M5 21v-16a2 2 0 0 1 2 -2h7.5m2.5 10.5v7.5" /><path d="M14 7h7m-3 -3l3 3l-3 3" /></svg>
                                        </button>
                                    </div>
                                </div>
                                </template>
                                <el-button
                                    v-if="ro.status === 'DISPONIBLE'"
                                    class="btn btn-block btn-success"
                                    @click="onToRent(ro)"
                                >
                                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-door-enter">
                                        <g transform="scale(-1 1) translate(-24 0)">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M13 12v.01" />
                                            <path d="M3 21h18" />
                                            <path d="M5 21v-16a2 2 0 0 1 2 -2h6m4 10.5v7.5" />
                                            <path d="M21 7h-7m3 -3l-3 3l3 3" />
                                        </g>
                                    </svg> Disponible
                                </el-button>
                            </div>
                           
                        </el-card>
                    </div>
                </div>
            </div>
        </div>
        <ModalRoomRates
            :room="room"
            :visible.sync="openModalRoomRates"
            @onAddRoomRate="onAddRoomRate"
            @onDeleteRate="onDeleteRate"
        ></ModalRoomRates>
        <ExtendTimeRoom
            :room="roomToExtend"
            :visible.sync="openDialogExtendTimeRoom"
            @onRefresh="onRefresh">
        </ExtendTimeRoom>
        <reception-export 
            :showDialog.sync="showExportDialog"
            :user-type="userType"
            :establishment-id="establishmentId"
        >
        </reception-export>
    </div>
</template>

<script>
import ExtendTimeRoom from './partials/ExtendTimeRoom.vue';
import ModalRoomRates from "./RoomRates";
import ReceptionExport from './partials/ReceptionExport.vue';

export default {
    components: {
        ModalRoomRates,
        ExtendTimeRoom,
        ReceptionExport,
    },
    props: {
        roomStatus: {
            type: Array,
            required: true,
        },
        floors: {
            type: Array,
            required: true,
        },
        rooms: {
            type: Array,
            required: true,
            default: [],
        },
        userType: {
            type: String,
            required: true,
        },
        establishmentId: {
            type: Number,
            required: true,
        },
        establishments: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            hotel_floor_id: "",
            hotel_name_room: null,
            loading: false,
            items: [],
            room: null,
            openModalRoomRates: false,
            roomToExtend: {},
            openDialogExtendTimeRoom: false,
            showExportDialog: false,
        };
    },
    mounted() {
        this.items = this.rooms;
    },
    /*
    watch: {
        hotel_floor_id() {
            this.onFilterByStatus();
        },
    },
    */
    methods: {
        onFinalizeClean(room) {
            const text = `Está a punto de terminar la limpieza de la habitación ${room.name}`;
            this.$confirm(text, "Atención", {
                confirmButtonText: "Si",
                cancelButtonText: "No",
                type: "warning",
            })
                .then(() => {
                    this.loading = true;
                    const payload = {
                        status: "DISPONIBLE",
                    };
                    this.$http
                        .post(`/hotels/rooms/${room.id}/change-status`, payload)
                        .then((response) => {
                            room.status = "DISPONIBLE";
                            this.items = this.items.map((r) => {
                                if (r.id === room.id) {
                                    return room;
                                }
                                return r;
                            });
                            this.$message({
                                type: "success",
                                message: response.data.message,
                            });
                        })
                        .finally(() => (this.loading = false));
                })
                .catch();
        },
        onGoToCheckout(room) {
            window.location.href = `/hotels/reception/${room.rent.id}/rent/checkout`;
        },
        onGoToAddProducts(room) {
            window.location.href = `/hotels/reception/${room.rent.id}/rent/products`;
        },
        onDeleteRate(rateId) {
            this.room.rates = this.room.rates.filter((r) => r.id !== rateId);
        },
        onAddRoomRate(rate) {
            this.room.rates.push(rate);
        },
        onToRent(room) {
            if (room.rates.length > 0) {
                window.location.href = `/hotels/reception/${room.id}/rent`;
            } else {
                this.room = room;
                this.openModalRoomRates = true;
            }
        },
        searchRooms() {
            this.loading = true;
            let form = {
                hotel_status_room: this.hotel_status_room,
                hotel_name_room: this.hotel_name_room,
                hotel_floor_id: this.hotel_floor_id,

            }
            this.$http
                .post("/hotels/reception/search", form)
                .then((response) => {
                    // console.error(response.data)
                    this.items = response.data.rooms;

                })
                .finally(() => {
                    this.loading = false;
                })
        },
        onFilterByStatus(status = "") {
            // Si se presiona dos veces la misma opcion, se cancelaria
            if(this.hotel_status_room == status){
                this.hotel_status_room = null
            }else {
                this.hotel_status_room = status
            }
            this.searchRooms()
            return null;
            this.loading = true;
            const params = {
                status,
                hotel_floor_id: this.hotel_floor_id,
            };
            this.$http
                .get("/hotels/reception", {params})
                .then((response) => {
                    this.items = response.data.rooms;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        onGetColorStatus(status) {
            if (status === "DISPONIBLE") {
                return "available";
            } else if (status === "MANTENIMIENTO") {
                return "maintenance";
            } else if (status === "OCUPADO") {
                return "occupied";
            } else if (status === "LIMPIEZA") {
                return "cleaning";
            }
            return "";
        },
        ShowDialogExtendTimeRoom(room) {
            this.roomToExtend = room
            if(this.roomToExtend){
                this.openDialogExtendTimeRoom = true
            }
        },
        onRefresh() {
            this.searchRooms()
        },
        clickExport() {
            this.showExportDialog = true;
        },
        clickChangeEstablishment(establishment_id){
            this.loading = true;
            const payload = {
                establishment_id: establishment_id,
            };
            this.$http
                .post(`/hotels/reception/change-user-establishment`, payload)
                .then((response) => {
                    this.$message({
                        type: "success",
                        message: response.data.message,
                    });
                    location.reload();
                })
                .finally(() => (this.loading = false));
        },
        formatDate(dateString) {
            if (!dateString) return "";
            const [year, month, day] = dateString.split("-");
            return `${day}/${month}`; 
        }
    },
};
</script>
