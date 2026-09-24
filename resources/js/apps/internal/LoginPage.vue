<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Icon from '@/components/Icon.vue';
import { api, post, session, loadSession } from '@/api/client';
import type { Role } from '@/types';
const recoveryOpen = ref(false),
  recoveryRole = ref('ADMIN'),
  recoveryEmail = ref(''),
  recoveryMessage = ref(''),
  recoveryBusy = ref(false),
  recoveryError = ref('');
async function recover() {
  recoveryBusy.value = true;
  recoveryError.value = '';
  recoveryMessage.value = '';
  try {
    await api('csrf');
    const result = await post('auth/recovery', { email: recoveryEmail.value });
    recoveryMessage.value = result.message;
  } catch (e) {
    recoveryError.value = (e as Error).message;
  } finally {
    recoveryBusy.value = false;
  }
}
const router = useRouter(),
  nip = ref(''),
  password = ref(''),
  code = ref(''),
  error = ref(''),
  busy = ref(false),
  challenge = ref(false),
  demoRole = ref<Role>('ADMIN');
onMounted(async () => {
  try {
    await loadSession();
    if (session.user) router.replace('/internal');
  } catch {
    error.value = 'Koneksi ke server belum tersedia.';
  }
});
async function login(demo = false) {
  busy.value = true;
  error.value = '';
  try {
    if (demo) {
      const result = await post('demo/login', { role: demoRole.value });
      session.user = result.user;
      session.demo = true;
      router.push('/internal');
    } else {
      await api('csrf');
      const result = await post(
        challenge.value ? 'auth/totp' : 'auth/login',
        challenge.value ? { code: code.value } : { nip: nip.value, password: password.value },
      );
      if (result.challenge) {
        challenge.value = true;
      } else {
        await loadSession();
        router.push('/internal');
      }
    }
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <div class="login-page">
    <aside class="login-story">
      <img src="/logo-nadi-asn.png" alt="NADI ASN DPMPTSP Prov. Sulawesi Tengah" />
      <div>
        <span class="eyebrow">RUANG KERJA KEPEGAWAIAN</span>
        <h1>Data terhubung.<br />Kerja lebih terarah.</h1>
        <p>
          Satu ruang untuk mengelola data, memantau prioritas, dan menghadirkan informasi ASN yang
          dapat dipercaya.
        </p>
        <div class="login-principles">
          <span><Icon name="Database" />Satu sumber data</span
          ><span><Icon name="ShieldCheck" />Akses yang terlindungi</span
          ><span><Icon name="History" />Perubahan terlacak</span>
        </div>
      </div>
      <span class="login-copyright"
        >© 2026 NADI ASN · DPMPTSP Prov. Sulawesi Tengah · Developer: Angga Jufran</span
      >
    </aside>
    <main class="login-form-wrap">
      <nav class="login-toolbar" aria-label="Navigasi halaman login">
        <RouterLink class="text-link back-public" to="/"
          ><Icon name="ArrowLeft" :size="16" /><span>Kembali ke portal publik</span></RouterLink
        >
        <ThemeToggle />
      </nav>
      <div class="login-form">
        <div class="login-lock"><Icon name="LockKeyhole" :size="27" /></div>
        <h2>{{ challenge ? 'Verifikasi dua langkah' : 'Selamat datang kembali' }}</h2>
        <p>
          {{
            challenge
              ? 'Masukkan kode dari aplikasi autentikator Anda.'
              : 'Masuk untuk mengakses ruang internal NADI ASN.'
          }}
        </p>
        <form @submit.prevent="login()">
          <template v-if="!challenge"
            ><label
              >NIP<input
                v-model="nip"
                type="text"
                autocomplete="username"
                placeholder="Masukkan 18 digit NIP"
                inputmode="numeric"
                pattern="[0-9]{18}"
                maxlength="18"
                required
                :disabled="session.demo" /></label
            ><label
              >Kata sandi<input
                v-model="password"
                type="password"
                autocomplete="current-password"
                placeholder="Masukkan kata sandi"
                required
                :disabled="session.demo" /></label></template
          ><label v-else
            >Kode autentikator<input
              v-model="code"
              inputmode="numeric"
              pattern="[0-9]{6}"
              maxlength="6"
              autocomplete="one-time-code"
              placeholder="000000"
              required
          /></label>
          <div v-if="error" class="form-error" role="alert">{{ error }}</div>
          <button class="button button-primary" :disabled="busy || session.demo">
            {{ busy ? 'Memproses…' : challenge ? 'Verifikasi & masuk' : 'Masuk ke ruang internal'
            }}<Icon name="ArrowRight" :size="17" />
          </button>
        </form>
        <button v-if="!session.demo" class="text-link recovery-button" @click="recoveryOpen = true">
          Lupa password atau kehilangan OTP?
        </button>
        <p class="login-help">
          <Icon name="ShieldCheck" :size="14" />Akun dikelola oleh Super Admin instansi.
        </p>
        <section v-if="session.demo" class="demo-login">
          <div class="badge amber">RUANG SIMULASI</div>
          <h3>Jelajahi aplikasi dengan data contoh</h3>
          <p>
            Data sintetis disimpan di server lokal. Pilih peran untuk mencoba alur kerja dan batas
            aksesnya.
          </p>
          <label
            >Masuk sebagai<select v-model="demoRole">
              <option value="ADMIN">Admin Kepegawaian — pengaju</option>
              <option value="SUPER_ADMIN">Super Admin — pemeriksa</option>
            </select></label
          ><button class="button button-outline" :disabled="busy" @click="login(true)">
            Buka ruang simulasi<Icon name="ArrowRight" :size="16" />
          </button>
        </section>
      </div>
    </main>
  </div>
  <Modal v-if="recoveryOpen" title="Pulihkan akses akun" @close="recoveryOpen = false"
    ><p>Pilih jenis akun yang ingin dipulihkan.</p>
    <label class="section-space"
      >Jenis akun<select v-model="recoveryRole">
        <option value="ADMIN">Admin Kepegawaian</option>
        <option value="SUPER_ADMIN">Super Admin</option>
      </select></label
    >
    <div v-if="recoveryRole === 'ADMIN'" class="notice section-space">
      <Icon name="Info" />
      <p>
        Silakan hubungi Super Admin untuk mendapatkan tautan aktivasi ulang atau reset password akun
        Admin Kepegawaian.
      </p>
    </div>
    <form v-else @submit.prevent="recover">
      <p class="section-space">
        Masukkan email pemulihan Super Admin yang telah didaftarkan. Tautan untuk mengatur password
        dan autentikator baru berlaku 30 menit.
      </p>
      <label
        >Email pemulihan<input
          v-model="recoveryEmail"
          type="email"
          required
          autocomplete="email"
          placeholder="Email yang terdaftar"
      /></label>
      <p v-if="recoveryError" class="form-error" role="alert">{{ recoveryError }}</p>
      <p v-if="recoveryMessage" class="notice" role="status">{{ recoveryMessage }}</p>
      <button class="button button-primary section-space" :disabled="recoveryBusy">
        {{ recoveryBusy ? 'Mengirim…' : 'Kirim tautan aktivasi ulang' }}
      </button>
    </form></Modal
  >
</template>
