<template>
    <div class="d-none ml-1 d-lg-block" style="height: inherit;">
        <a
            v-if="
                menu.menu_a != '' &&
                    menu.menu_a !== undefined &&
                    menu.menu_a.route_path !== undefined
            "
            :href="menu.menu_a.route_path"
            :title="menu.menu_a.description"
            class="topbar-links"
            data-placement="bottom"
            data-toggle="tooltip"
        >
            <span v-html="getIcon(menu.menu_a.icon_id)"></span>
            <span>{{ menu.menu_a.label_menu }}</span>
        </a>
        <a
            v-if="
                menu.menu_b != '' &&
                    menu.menu_b !== undefined &&
                    menu.menu_b.route_path !== undefined
            "
            :href="menu.menu_b.route_path"
            :title="menu.menu_b.description"
            class="topbar-links"
            data-placement="bottom"
            data-toggle="tooltip"
        >
            <span v-html="getIcon(menu.menu_b.icon_id)"></span>
            <span>{{ menu.menu_b.label_menu }}</span>
        </a>
        <a
            v-if="
                menu.menu_c != '' &&
                    menu.menu_c !== undefined &&
                    menu.menu_c.route_path !== undefined
            "
            :href="menu.menu_c.route_path"
            :title="menu.menu_c.description"
            class="topbar-links"
            data-placement="bottom"
            data-toggle="tooltip"
        >
            <span v-html="getIcon(menu.menu_c.icon_id)"></span>
            <span>{{ menu.menu_c.label_menu }}</span>
        </a>
        <a
            v-if="
                menu.menu_d != '' &&
                    menu.menu_d !== undefined &&
                    menu.menu_d.route_path !== undefined
            "
            :href="menu.menu_d.route_path"
            :title="menu.menu_d.description"
            class="topbar-links"
            data-placement="bottom"
            data-toggle="tooltip"
        >
            <span v-html="getIcon(menu.menu_d.icon_id)"></span>
            <span>{{ menu.menu_d.label_menu }}</span>
        </a>
        <a
            class="topbar-links"
            data-placement="bottom"
            data-toggle="tooltip"
            href="#"
            title="editar accesos directos"
            @click="showDialog = true"
        >
            <span>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="icon icon-tabler icons-tabler-outline icon-tabler-edit"
                >
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path
                        d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"
                    />
                    <path
                        d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"
                    />
                    <path d="M16 5l3 3" />
                </svg>
            </span>
            <span class="fix-m"><i class="fas fa-ellipsis-h"></i></span>
        </a>
        <el-dialog
            :visible="showDialog"
            title="Accesos Directos"
            top="20vh"
            width="40%"
            @close="close"
        >
            <el-form :model="form">
                <el-form-item :label-width="formLabelWidth" label="Menu 1">
                    <el-select v-model="form.menu_a" placeholder="Menu 1">
                        <el-option
                            v-for="option in modules"
                            :key="option.id"
                            :label="
                                routeNames[option.route_path] ||
                                    option.route_path
                            "
                            :value="option.id"
                        ></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item :label-width="formLabelWidth" label="Menu 2">
                    <el-select
                        v-model="form.menu_b"
                        placeholder="Menu 2"
                        required
                    >
                        <el-option
                            v-for="option in modules"
                            :key="option.id"
                            :label="
                                routeNames[option.route_path] ||
                                    option.route_path
                            "
                            :value="option.id"
                        ></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item :label-width="formLabelWidth" label="Menu 3">
                    <el-select
                        v-model="form.menu_c"
                        placeholder="Menu 3"
                        required
                    >
                        <el-option
                            v-for="option in modules"
                            :key="option.id"
                            :label="
                                routeNames[option.route_path] ||
                                    option.route_path
                            "
                            :value="option.id"
                        ></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item :label-width="formLabelWidth" label="Menu 4">
                    <el-select
                        v-model="form.menu_d"
                        placeholder="Menu 4"
                        required
                    >
                        <el-option
                            v-for="option in modules"
                            :key="option.id"
                            :label="
                                routeNames[option.route_path] ||
                                    option.route_path
                            "
                            :value="option.id"
                        ></el-option>
                    </el-select>
                </el-form-item>
            </el-form>
            <span slot="footer" class="dialog-footer">
                <el-button class="second-buton" @click.prevent="close()"
                    >Cancel</el-button
                >
                <el-button type="primary" @click.prevent="clickSubmit"
                    >Guardar</el-button
                >
            </span>
        </el-dialog>
    </div>
</template>

<script>
export default {
    data() {
        return {
            form: {
                menu_a: null,
                menu_b: null,
                menu_c: null,
                menu_d: null
            },
            formLabelWidth: "120px",
            modules: [],
            showDialog: false,
            menu: {},
            routeNames: {
                "/documents/create": "Nuevo Comprobante",
                "/sale-notes": "Notas de Venta",
                "/order-notes": "Pedidos",
                "/pos": "Punto de Venta",
                "/items": "Productos",
                "/purchases/create": "Nueva Compra",
                "/inventory": "Inventario",
                "/users": "Usuarios",
                "/establishments": "Sucursales",
                "/companies/create": "Empresa"
            }
        };
    },
    created() {
        this.getRecords();
        this.loadIcons();
    },
    methods: {
        async loadIcons() {
            try {
                const cachedIcons = localStorage.getItem("icons");
                if (cachedIcons) {
                    this.icons = JSON.parse(cachedIcons);
                    return;
                }
    
                const response = await fetch("/json/icons/icons.json");
                this.icons = await response.json();
                localStorage.setItem("icons", JSON.stringify(this.icons));
            } catch (error) {
                console.error("Error al cargar los íconos:", error);
            }
        },
        getIcon(icon_id) {
            if (!this.icons || !this.icons[icon_id]) {
                return '<span style="color: red;">⚠️ Icono no disponible</span>';
            }
            return this.icons[icon_id];
        },
        getRecords() {
            this.$http.get(`/configurations/visual/get_menu`).then(response => {
                if (response.data !== "") {
                    this.modules = response.data.modules;
                    this.menu = {
                        menu_a: response.data.menu.top_menu_a,
                        menu_b: response.data.menu.top_menu_b,
                        menu_c: response.data.menu.top_menu_c,
                        menu_d: response.data.menu.top_menu_d
                    };
                    this.form = {
                        menu_a: response.data.menu.top_menu_a.id,
                        menu_b: response.data.menu.top_menu_b.id,
                        menu_c: response.data.menu.top_menu_c.id,
                        menu_d: response.data.menu.top_menu_d.id
                    };
                }
            });
        },
        clickSubmit() {
            this.$http
                .post(`/configurations/visual/set_menu`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.menu = {
                            menu_a: response.data.menu.top_menu_a,
                            menu_b: response.data.menu.top_menu_b,
                            menu_c: response.data.menu.top_menu_c,
                            menu_d: response.data.menu.top_menu_d
                        };
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data.errors;
                    } else {
                        console.log(error);
                    }
                })
                .then(() => {
                    this.loading_submit = false;
                });
            this.close();
        },
        close() {
            this.showDialog = false;
        }
    }
};
</script>

<style>
.v-modal {
    display: none !important;
}
.el-dialog {
    z-index: 2002 !important;
}
.sidebar-left {
    z-index: 1020 !important;
}
.el-form-item__label {
    width: 70px !important;
    text-align: left;
}
.el-form-item__content {
    margin-left: 70px !important;
}
</style>
