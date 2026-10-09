<template> 
    <el-dialog :title="titleDialog" :visible="showDialog" @open="create"  @close="close"   append-to-body top="7vh" :close-on-click-modal="false">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Seleccionar
                                        <el-tooltip class="item" effect="dark" content="Seleccionar almacén desde el cuál se descontará stock" placement="top">
                                            <i class="fa fa-info-circle"></i>
                                        </el-tooltip>
                                    </th>
                                    <th>Ubicación</th>
                                    <th class="text-right">Stock</th>
                                </tr>
                            </thead>
                            <tbody>    
                                <template v-if="isExistWwarehouses">
                                <tr v-for="(row,index) in warehouses" :key="index">
                                        <th align="center">
                                            <el-checkbox   v-model="row.checked" @change="changeWarehouse(index)"></el-checkbox>
                                        </th>
                                        <th>{{ row.warehouse_description }}</th>
                                        <th class="text-right">{{ row.stock }}</th>
                                </tr>
                                </template>
                                    <template v-else>
                                        <tr  class="text-center">
                                            <th colspan="3">
                                                No hay almacenes disponibles.
                                            </th>
                                        </tr>
                                    </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="form-actions text-right pt-2">
                <el-button @click.prevent="close()">Cerrar</el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>
 

    export default { 
        props:['showDialog', 'warehouses', 'item_index'],
        data() {
            return {
                showImportDialog: false,
                resource: 'items',
                recordId: null,
                titleDialog: 'Almacenes/Stock',
                my_warehouses:[],
                warehouse_id:null,

            }
        },
        created() {
            // console.log(this.typeUser)
        },
        computed: { 
            isExistWwarehouses()
            {
                return this.warehouses && this.warehouses.length > 0;
            }
        },
        methods: {
            async create(){

                // this.warehouse_id = null
                if (this.warehouses.length == 1 && this.warehouses[0].checked == false) {
                    this.warehouses[0].checked = true
                }
                 
            },
            async changeWarehouse(index){

                await this.warehouses.forEach((it, ind) => {
                    // console.log(ind, index)
                    if(ind != index){
                        it.checked = false
                    }
                });

                await this.selectWarehouseId()
            },
            async selectWarehouseId(){

                await this.warehouses.forEach((it, ind) => {
                    if(it.checked){
                        this.warehouse_id = it.warehouse_id
                    }
                });

            },
            async close() {

                await this.selectWarehouseId()

                if(this.isExistWwarehouses && !this.warehouse_id)
                    return this.$message.error('Debe seleccionar un almacén');

                await this.$eventHub.$emit('selectWarehouseId', {warehouse_id : this.warehouse_id, index : this.item_index}) 

                this.$emit('update:showDialog', false)
                
            },
        }
    }
</script>
