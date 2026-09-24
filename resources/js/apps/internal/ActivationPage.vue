<script setup lang="ts">
import ThemeToggle from '@/components/ThemeToggle.vue';
import { ref, onMounted } from 'vue';
import { api } from '@/api/client';
import Icon from '@/components/Icon.vue';
const token = location.hash.slice(1),
  info = ref<any>(null),
  password = ref(''),
  confirm = ref(''),
  code = ref(''),
  error = ref(''),
  busy = ref(false),
  done = ref(false);
onMounted(async () => {
  history.replaceState(null, '', '/aktivasi');
  try {
    await api('csrf');
    info.value = await api('activation', { headers: { 'X-Activation-Token': token } });
  } catch (e) {
    error.value = (e as Error).message;
  }
});
async function activate() {
  busy.value = true;
  error.value = '';
  try {
    await api('activation', {
      method: 'POST',
      headers: { 'X-Activation-Token': token },
      body: JSON.stringify({
        password: password.value,
        password_confirmation: confirm.value,
        code: code.value,
      }),
    });
    done.value = true;
    info.value = null;
    password.value = '';
    confirm.value = '';
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <main class="activation-page">
    <ThemeToggle />
    <img src="/logo-nadi-asn.png" alt="NADI ASN" width="88" />
    <section class="card activation-card">
      <span class="eyebrow">AKUN PRIBADI ANDA</span>
      <h1>Aktivasi & pemulihan akun</h1>
      <p v-if="error" role="alert" class="form-error">{{ error }}</p>
      <template v-if="done"
        ><Icon name="CheckCircle2" />
        <h2>Akun berhasil diaktifkan</h2>
        <p>Masuk menggunakan NIP, kata sandi, dan kode autentikator berikutnya.</p>
        <RouterLink class="button button-primary" to="/login"
          >Masuk ke NADI ASN</RouterLink
        ></template
      >
      <form v-else-if="info" @submit.prevent="activate">
        <p>
          <strong>{{ info.name }}</strong
          ><br />Identitas masuk: {{ info.login }}
        </p>
        <label
          >Kata sandi baru (minimal 12 karakter)<input
            type="password"
            v-model="password"
            minlength="12"
            required
            autocomplete="new-password" /></label
        ><label
          >Ulangi kata sandi<input
            type="password"
            v-model="confirm"
            minlength="12"
            required
            autocomplete="new-password"
        /></label>
        <div class="notice section-space">
          <Icon name="ShieldCheck" />
          <p>
            Tambahkan akun NADI ASN pada aplikasi autentikator menggunakan kunci di bawah. Pilih
            jenis berbasis waktu. Simpan kunci ini secara pribadi untuk pemulihan perangkat.
          </p>
        </div>
        <label
          >Kunci autentikator<input
            :value="info.secret"
            readonly
            aria-label="Kunci autentikator" /></label
        ><label
          >Kode enam digit<input
            v-model="code"
            inputmode="numeric"
            pattern="[0-9]{6}"
            maxlength="6"
            required
            autocomplete="one-time-code" /></label
        ><button class="button button-primary" :disabled="busy">
          {{ busy ? 'Mengaktifkan…' : 'Aktifkan akun' }}
        </button>
      </form>
      <p v-else-if="!error">Memeriksa tautan aktivasi…</p>
    </section>
  </main>
</template>
