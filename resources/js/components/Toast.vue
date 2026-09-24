<script setup lang="ts">
import { notification } from '@/composables';
import Icon from './Icon.vue';
</script>
<template>
  <Transition name="toast"
    ><div v-if="notification" class="toast-message" role="status">
      <Icon name="CheckCircle2" /><span>{{ notification }}</span
      ><button class="icon-button" aria-label="Tutup pemberitahuan" @click="notification = ''">
        <Icon name="X" :size="16" />
      </button></div
  ></Transition>
</template>
