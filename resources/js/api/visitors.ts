import { reactive, onMounted, onUnmounted } from 'vue';
import { post } from './client';
export interface VisitorStats {
  today: number;
  yesterday: number;
  week: number;
  month: number;
  year: number;
  total: number;
  online: number;
  trackingSince: string | null;
  updatedAt: string;
  timezone: string;
  onlineWindowMinutes: number;
  demo: boolean;
}
export const visitorState = reactive<{ data: VisitorStats | null; unavailable: boolean }>({
  data: null,
  unavailable: false,
});
export function useVisitorTracking() {
  let timer: ReturnType<typeof setInterval>;
  let pending = false;
  let disposed = false;
  async function heartbeat() {
    if (disposed || pending || document.visibilityState !== 'visible') return;
    pending = true;
    try {
      // Stateless endpoint: background presence never refreshes an authenticated session.
      const data = await post<VisitorStats>('public/visitors/heartbeat', {});
      if (!disposed) {
        visitorState.data = data;
        visitorState.unavailable = false;
      }
    } catch {
      if (!disposed) visitorState.unavailable = true;
    } finally {
      pending = false;
    }
  }
  onMounted(() => {
    void heartbeat();
    timer = setInterval(() => void heartbeat(), 60_000);
    document.addEventListener('visibilitychange', heartbeat);
    window.addEventListener('online', heartbeat);
  });
  onUnmounted(() => {
    disposed = true;
    clearInterval(timer);
    document.removeEventListener('visibilitychange', heartbeat);
    window.removeEventListener('online', heartbeat);
  });
}
