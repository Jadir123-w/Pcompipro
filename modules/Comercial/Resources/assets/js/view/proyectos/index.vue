<template>
  <div>
    <!-- Header -->
    <div class="page-header">
      <h2>
        <i class="fa fa-folder-open"></i> Proyectos
      </h2>
      <ol class="breadcrumbs">
        <li class="active"><span>Listado de Proyectos</span></li>
      </ol>
      <div class="right-wrapper pull-right">
      </div>
    </div>

    <!-- KPIs -->
    <div class="row mb-3">
      <div class="col-md-2">
        <div class="card text-center p-3">
          <h3 class="mb-0">{{ kpis.activos }}</h3>
          <small class="text-muted">Activos</small>
        </div>
      </div>
      <div class="col-md-2">
        <div class="card text-center p-3">
          <h3 class="mb-0 text-primary">{{ kpis.en_produccion }}</h3>
          <small class="text-muted">En Producción</small>
        </div>
      </div>
      <div class="col-md-2">
        <div class="card text-center p-3">
          <h3 class="mb-0 text-danger">{{ kpis.semaforo_rojo }}</h3>
          <small class="text-muted">Semáforo Rojo</small>
        </div>
      </div>
      <div class="col-md-2">
        <div class="card text-center p-3">
          <h3 class="mb-0 text-warning">{{ kpis.semaforo_amarillo }}</h3>
          <small class="text-muted">Semáforo Amarillo</small>
        </div>
      </div>
      <div class="col-md-2">
        <div class="card text-center p-3">
          <h3 class="mb-0 text-info">{{ kpis.pendientes_cobro }}</h3>
          <small class="text-muted">Pendientes Cobro</small>
        </div>
      </div>
      <div class="col-md-2">
        <div class="card text-center p-3">
          <h3 class="mb-0">S/ {{ formatMonto(kpis.monto_total) }}</h3>
          <small class="text-muted">Monto Total</small>
        </div>
      </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <div class="row">
          <div class="col-md-3">
            <el-input
              v-model="filters.search"
              placeholder="Buscar por código, nombre, empresa..."
              prefix-icon="el-icon-search"
              clearable
              @input="debounceSearch"
            />
          </div>
          <div class="col-md-2">
            <el-select v-model="filters.etapa" placeholder="Etapa" clearable @change="loadData">
              <el-option label="Todas" value="Todos" />
              <el-option v-for="e in etapas" :key="e" :label="e" :value="e" />
            </el-select>
          </div>
          <div class="col-md-2">
            <el-select v-model="filters.semaforo" placeholder="Semáforo" clearable @change="loadData">
              <el-option label="Todos" value="Todos" />
              <el-option label="🟢 Verde" value="Verde" />
              <el-option label="🟡 Amarillo" value="Amarillo" />
              <el-option label="🔴 Rojo" value="Rojo" />
            </el-select>
          </div>
          <div class="col-md-2">
            <el-select v-model="filters.estado" placeholder="Estado" clearable @change="loadData">
              <el-option label="Todos" value="Todos" />
              <el-option label="Activo" value="activo" />
              <el-option label="Terminado" value="terminado" />
              <el-option label="Cancelado" value="cancelado" />
            </el-select>
          </div>
          <div class="col-md-3">
            <el-dropdown :hide-on-click="false">
              <el-button type="secondary" size="small">
                Mostrar/Ocultar columnas <i class="el-icon-arrow-down"></i>
              </el-button>
              <el-dropdown-menu slot="dropdown">
                <el-dropdown-item v-for="(col, key) in columns" :key="key">
                  <el-checkbox v-model="col.visible">{{ col.title }}</el-checkbox>
                </el-dropdown-item>
              </el-dropdown-menu>
            </el-dropdown>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div class="card-body p-0">
        <el-table
          :data="proyectos"
          v-loading="loading"
          stripe
          style="width: 100%"
          @row-click="clickEdit"
        >
          <el-table-column prop="codigo" label="Código" width="120" />
          <el-table-column prop="nombre" label="Nombre" min-width="200" show-overflow-tooltip />
          <el-table-column prop="empresa_razon_social" label="Empresa" min-width="180" show-overflow-tooltip />
          <el-table-column prop="etapa" label="Etapa" width="140">
            <template slot-scope="scope">
              <el-tag size="small">{{ scope.row.etapa }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="Semáforo" width="90" align="center">
            <template slot-scope="scope">
              <span v-if="scope.row.semaforo === 'Verde'" class="semaforo verde">🟢</span>
              <span v-else-if="scope.row.semaforo === 'Amarillo'" class="semaforo amarillo">🟡</span>
              <span v-else class="semaforo rojo">🔴</span>
            </template>
          </el-table-column>
          <el-table-column v-if="columns.monto.visible" label="Monto Total" width="120" align="right">
            <template slot-scope="scope">
              S/ {{ formatMonto(scope.row.costo_total_proyecto || scope.row.monto_total) }}
            </template>
          </el-table-column>
          <el-table-column v-if="columns.avance.visible" label="Avance" width="100" align="center">
            <template slot-scope="scope">
              <el-progress :percentage="Number(scope.row.porcentaje_avance) || 0" :stroke-width="6" />
            </template>
          </el-table-column>
          <el-table-column v-if="columns.ejecutivo.visible" prop="ejecutivo_nombre" label="Ejecutivo" width="140" />
          <el-table-column v-if="columns.fecha_fin.visible" label="Fecha Entrega" width="110">
            <template slot-scope="scope">
              <span :class="{'text-danger': scope.row.esta_vencido}">
                {{ formatFecha(scope.row.fecha_fin) }}
              </span>
            </template>
          </el-table-column>
          <el-table-column v-if="columns.oc.visible" prop="oc_numero" label="O/C" width="120" />
          <el-table-column label="Acciones" width="100" align="center" fixed="right">
            <template slot-scope="scope">
              <el-button size="mini" type="primary" icon="el-icon-edit" circle @click.stop="clickEdit(scope.row)" />
              <el-button size="mini" type="danger" icon="el-icon-delete" circle @click.stop="clickDelete(scope.row)" />
            </template>
          </el-table-column>
        </el-table>
      </div>
    </div>

    <!-- Dialog Formulario -->
    <proyecto-form
      v-if="showDialog"
      :visible.sync="showDialog"
      :proyecto-id="selectedId"
      @saved="onSaved"
    />
  </div>
</template>

<script>
import ProyectoForm from './form.vue';
import debounce from 'lodash/debounce';

export default {
  components: {
    ProyectoForm
  },
  data() {
    return {
      loading: false,
      proyectos: [],
      kpis: {
        activos: 0,
        en_produccion: 0,
        semaforo_rojo: 0,
        semaforo_amarillo: 0,
        pendientes_cobro: 0,
        monto_total: 0,
      },
      filters: {
        search: '',
        etapa: 'Todos',
        semaforo: 'Todos',
        estado: 'activo',
      },
      columns: {
        monto: { title: 'Monto Total', visible: true },
        avance: { title: 'Avance', visible: true },
        ejecutivo: { title: 'Ejecutivo', visible: true },
        fecha_fin: { title: 'Fecha Entrega', visible: true },
        oc: { title: 'O/C', visible: false },
      },
      etapas: [
        'Nuevo',
        'En curso / Pre-producción',
        'En producción',
        'Final producción',
        'En reproceso',
        'Entregado',
        'Pendiente de pago',
        'Terminado',
      ],
      showDialog: false,
      selectedId: null,
    };
  },
  created() {
    this.debounceSearch = debounce(this.loadData, 300);
    this.loadData();
    this.loadKpis();
  },
  methods: {
    async loadData() {
      this.loading = true;
      try {
        const params = {
          search: this.filters.search,
          etapa: this.filters.etapa,
          semaforo: this.filters.semaforo,
          estado: this.filters.estado,
        };
        const response = await this.$http.get('/comercial/proyectos/records', { params });
        this.proyectos = response.data;
      } catch (error) {
        this.$message.error('Error al cargar proyectos');
        console.error(error);
      } finally {
        this.loading = false;
      }
    },
    async loadKpis() {
      try {
        const response = await this.$http.get('/comercial/proyectos/kpis');
        this.kpis = response.data;
      } catch (error) {
        console.error('Error al cargar KPIs:', error);
      }
    },
    clickCreate() {
      this.selectedId = null;
      this.showDialog = true;
    },
    clickEdit(row) {
      this.selectedId = row.id;
      this.showDialog = true;
    },
    async clickDelete(row) {
      try {
        await this.$confirm('¿Está seguro de eliminar este proyecto?', 'Confirmar', {
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar',
          type: 'warning'
        });
        await this.$http.delete(`/comercial/proyectos/record/${row.id}`);
        this.$message.success('Proyecto eliminado');
        this.loadData();
        this.loadKpis();
      } catch (error) {
        if (error !== 'cancel') {
          this.$message.error('Error al eliminar');
        }
      }
    },
    onSaved() {
      this.showDialog = false;
      this.loadData();
      this.loadKpis();
    },
    formatMonto(value) {
      if (!value) return '0.00';
      return parseFloat(value).toLocaleString('es-PE', { minimumFractionDigits: 2 });
    },
    formatFecha(fecha) {
      if (!fecha) return '';
      return new Date(fecha).toLocaleDateString('es-PE');
    },
  }
};
</script>

<style scoped>
.semaforo {
  font-size: 18px;
}
.card h3 {
  color: #333;
}
</style>
