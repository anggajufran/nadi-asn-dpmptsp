<script setup lang="ts">
import { ref } from 'vue';
import Icon from './Icon.vue';
const dark = ref(document.documentElement.dataset.theme === 'dark');
function toggle() {
  dark.value = !dark.value;
  document.documentElement.dataset.theme = dark.value ? 'dark' : 'light';
  try {
    localStorage.setItem('nadi-theme', dark.value ? 'dark' : 'light');
  } catch {}
}
</script>
<template>
  <button
    class="theme-toggle icon-button"
    :aria-label="dark ? 'Gunakan mode terang' : 'Gunakan mode gelap'"
    :title="dark ? 'Gunakan mode terang' : 'Gunakan mode gelap'"
    :aria-pressed="dark"
    @click="toggle"
  >
    <Icon :name="dark ? 'Sun' : 'Moon'" :size="19" />
  </button>
</template>
