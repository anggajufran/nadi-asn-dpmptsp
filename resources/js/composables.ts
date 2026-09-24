import { ref } from 'vue';
export const notification = ref('');
let timeout: ReturnType<typeof setTimeout>;
export function notify(message: string) {
  notification.value = message;
  clearTimeout(timeout);
  timeout = setTimeout(() => (notification.value = ''), 4500);
}
export const dateLabel = (date: string) =>
  new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'Asia/Makassar',
  }).format(new Date(date.length === 10 ? date + 'T00:00:00+08:00' : date));
export const roleLabel = (role: string) =>
  ({ SUPER_ADMIN: 'Super Admin', ADMIN: 'Admin Kepegawaian' })[role] || role;
export const initials = (name: string) =>
  name
    .split(' ')
    .slice(0, 2)
    .map((x) => x[0])
    .join('');
