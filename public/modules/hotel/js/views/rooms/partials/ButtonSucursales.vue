<template>
    <div class="col-md-12 pr-0 pl-0 mt-1">
        <div class="form-group">
            <label class="mt-0">Cambiar Sucursal:</label>

            <!-- <button
            aria-expanded="false"
            class="btn btn-custom btn-sm mt-2 mr-2 dropdown-toggle"
            data-toggle="dropdown"
            type="button"
        >
                <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-list-numbers"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M11 6h9" /><path d="M11 12h9" /><path d="M12 18h8" /><path d="M4 16a2 2 0 1 1 4 0c0 .591 -.5 1 -1 1.5l-3 2.5h4" /><path d="M6 10v-6l-2 2" /></svg>
                    Cambiar Sucursal
                <span class="caret"></span>
        </button> -->
            <select
                class="form-control"
                name="multi_user_id"
                @change="clickChangeEstablishment()"
                v-model="establishment_id"
            >
                <option
                    v-for="item in establishments"
                    :key="item.id"
                    :label="item.description"
                    :value="item.id"
                >
                </option>
            </select>
            <!-- <div
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
            <a v-for="item in establishments" :key="item.id" :value="item.id" :label="item.description"
                class="dropdown-item text-1"
                href="#"
                @click.prevent="clickChangeEstablishment(item.id)"
                        > {{ item.description }}</a>
        </div> -->
        </div>
    </div>
</template>
<script>
export default {
    props: {
        establishments: {
            type: Array,
            required: true
        },
        current_establishment: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            establishment_id: undefined
        };
    },
    mounted() {
        this.establishment_id = this.current_establishment;
    },
    methods: {
        clickChangeEstablishment() {
            this.loading = true;
            const payload = {
                establishment_id: this.establishment_id
            };
            this.$http
                .post(`/hotels/reception/change-user-establishment`, payload)
                .then(response => {
                    this.$message({
                        type: "success",
                        message: response.data.message
                    });

                    this.$eventHub.$emit('establishmentChanged', this.establishment_id);
                    //location.reload();
                })
                .finally(() => (this.loading = false));
        }
    }
};
</script>
