import { ref, onMounted } from 'vue';
import { api } from '@/api/client';
export function useUnits() {
  const units = ref<string[]>([]);
  onMounted(async () => {
    try {
      units.value = (await api('units')).map((u: any) => u.label);
    } catch {}
  });
  return units;
}
