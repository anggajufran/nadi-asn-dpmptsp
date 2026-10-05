<script setup lang="ts">
import { useVisitorTracking } from './api/visitors';
useVisitorTracking();
import Toast from './components/Toast.vue';
</script>
<template>
  <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
  <div id="main-content"><RouterView /></div>
  <Toast />
</template>
