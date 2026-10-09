<template>
  <div class="container text-center mt-5">
    <div class="row justify-content-center email-verificate">
      <div class="col-md-11">
        <h1 class="auth__title">Registro de invitados</h1>
        <p class="text-justify mt-3 text-center">
          El registro de invitados no está habilitado en este momento. Por favor, contacte al administrador para más información.
        </p>
        <el-button class="btn-back" type="primary" @click="goBack">
          Volver al inicio
        </el-button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  methods: {
    goBack() {
      window.location.href = '/';
    },
  },
};
</script>

<style scoped>
.auth__title {
  font-size: 2rem;
  font-weight: bold;
}
</style>