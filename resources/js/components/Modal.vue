<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Icon from './Icon.vue';
defineProps<{ title: string; wide?: boolean }>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement>();
const previous = document.activeElement as HTMLElement;
onMounted(async () => {
  dialog.value?.showModal();
  document.body.style.overflow = 'hidden';
  await nextTick();
  dialog.value?.querySelector<HTMLElement>('input,select,textarea,button')?.focus();
});
onBeforeUnmount(() => {
  document.body.style.overflow = '';
  previous?.focus();
});
</script>
<template>
  <Teleport to="body"
    ><dialog
      ref="dialog"
      class="modal"
      :class="{ wide }"
      @cancel.prevent="emit('close')"
      @click="
        (e) => {
          if (e.target === dialog) emit('close');
        }
      "
    >
      <div class="modal-head">
        <h2>{{ title }}</h2>
        <button class="icon-button" aria-label="Tutup dialog" @click="emit('close')">
          <Icon name="X" />
        </button>
      </div>
      <div class="modal-body"><slot /></div></dialog
  ></Teleport>
</template>
