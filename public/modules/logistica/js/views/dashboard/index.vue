<template>
  <div class="logistica-dashboard">

    <!-- KPIs -->
    <div class="row mb-4">
      <div class="col-md-3" v-for="kpi in kpisUI" :key="kpi.label">
        <div class="card text-center">
          <div class="card-body">
            <h6 class="text-muted">{{ kpi.label }}</h6>
            <h2 :class="kpi.class">{{ kpi.value }}</h2>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div class="card-header">
        <strong>Proyectos Logística</strong>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead>
            <tr>
              <th>Proyecto</th>
              <th>Estado</th>
              <th>Avance</th>
              <th>Inicio</th>
              <th>Entrega</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in projects" :key="p.id">
              <td>{{ p.nombre }}</td>
              <td>
                <span :class="estadoClass(p.estado)">
                  {{ p.estado }}
                </span>
              </td>
              <td>{{ p.avance }}%</td>
              <td>{{ p.fecha_inicio }}</td>
              <td>{{ p.fecha_entrega || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>


<script>
export default {
  data() {
    return {
      loading: false,
      kpis: {},
      projects: []
    };
  },

  computed: {
    kpisUI() {
      return [
        { label: 'Activos', value: this.kpis.activos || 0, class: 'text-primary' },
        { label: 'Entregados', value: this.kpis.entregados || 0, class: 'text-success' },
        { label: 'Pendientes', value: this.kpis.pendientes || 0, class: 'text-warning' },
        { label: 'Avance %', value: this.kpis.avance_total || 0, class: 'text-info' }
      ];
    }
  },

  methods: {
    async loadDashboard() {
      this.loading = true;
      try {
        const { data } = await this.$http.get('/logistica/dashboard/data');
        this.kpis = data.kpis || {};
        this.projects = data.projects || [];
      } catch (e) {
        console.error('Error cargando dashboard logística', e);
      } finally {
        this.loading = false;
      }
    },

    estadoClass(estado) {
      return {
        'badge badge-primary': estado === 'En curso',
        'badge badge-success': estado === 'Entregado',
        'badge badge-secondary': estado === 'Cerrado',
        'badge badge-danger': estado === 'Reproceso'
      };
    }
  },

  mounted() {
    this.loadDashboard();
  }
};
</script>
<style>
  .badge {
  padding: 0.4em 0.6em;
  font-size: 0.85em;
  }
</style>