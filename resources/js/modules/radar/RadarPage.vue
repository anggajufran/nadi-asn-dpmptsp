<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import Icon from '@/components/Icon.vue';
import Modal from '@/components/Modal.vue';
import { api, post } from '@/api/client';
import { dateLabel as formatDate, notify } from '@/composables';
const dateLabel = (value?: string | null) => (value ? formatDate(value) : '—');
const props = defineProps<{ kind: string }>();
const rows = ref<any[]>([]),
  search = ref(''),
  status = ref(''),
  process = ref(''),
  page = ref(1),
  pageSize = ref(10),
  loading = ref(false),
  error = ref(''),
  explain = ref(false),
  editing = ref<any>(null),
  form = ref<any>({}),
  saving = ref(false),
  formError = ref('');
const name = computed(() => (props.kind === 'kp' ? 'Kenaikan Pangkat' : 'Kenaikan Gaji Berkala'));
const statuses = ['Data belum lengkap', 'Belum memenuhi', 'Siap diusulkan'];
const processes = ['Belum diusulkan', 'Diusulkan', 'Perbaikan', 'Keputusan terbit', 'Ditolak'];
const performances = ['Buruk', 'Kurang', 'Cukup', 'Baik', 'Sangat Baik'];
const filtered = computed(() =>
  rows.value.filter(
    (r) =>
      (!search.value ||
        (r.employee.name + ' ' + r.employee.nip)
          .toLowerCase()
          .includes(search.value.toLowerCase())) &&
      (!status.value || r.result.status === status.value) &&
      (!process.value || (r.data.process || 'Belum diusulkan') === process.value),
  ),
);
const pages = computed(() =>
  Math.max(1, Math.ceil(filtered.value.length / (pageSize.value || filtered.value.length || 1))),
);
const visible = computed(() =>
  pageSize.value
    ? filtered.value.slice((page.value - 1) * pageSize.value, page.value * pageSize.value)
    : filtered.value,
);
const stats = computed(() => [
  ...statuses.map((label) => ({
    label,
    value: rows.value.filter((r) => r.result.status === label).length,
  })),
  {
    label: 'Memasuki masa pengingat',
    value: rows.value.filter((r) => r.result.reminderActive).length,
  },
]);
watch([search, status, process, pageSize], () => (page.value = 1));
let request = 0;
async function load() {
  const id = ++request;
  loading.value = true;
  error.value = '';
  try {
    const result = await api('radar/' + props.kind);
    if (id === request) rows.value = result.rows;
  } catch (e) {
    if (id === request) error.value = (e as Error).message;
  } finally {
    if (id === request) loading.value = false;
  }
}
watch(
  () => props.kind,
  () => {
    rows.value = [];
    editing.value = null;
    page.value = 1;
    status.value = '';
    process.value = '';
    load();
  },
  { immediate: true },
);
function edit(row: any) {
  editing.value = row;
  form.value = {
    process: 'Belum diusulkan',
    performance1: null,
    performance2: null,
    educationRecognized: null,
    examVerified: null,
    functionalVerified: null,
    managerialVerified: null,
    documentsVerified: null,
    ...row.data,
    reason: '',
    targetDate: row.data.targetDate || row.result.targetDate || '',
    performanceYear1: row.data.performanceYear1 ?? row.result.performanceYears[0],
    performanceYear2: row.data.performanceYear2 ?? row.result.performanceYears[1],
  };
  formError.value = '';
}
async function save() {
  saving.value = true;
  formError.value = '';
  try {
    await post(`radar/${props.kind}/${editing.value.employee.id}`, {
      revision: editing.value.revision,
      employeeRevision: editing.value.employee.revision,
      data: form.value,
    });
    editing.value = null;
    notify('Pemeriksaan radar tersimpan dan perhitungan diperbarui.');
    await load();
  } catch (e) {
    formError.value = (e as Error).message;
  } finally {
    saving.value = false;
  }
}
function badge(s: string) {
  return s === 'Siap diusulkan' ? 'green' : s === 'Belum memenuhi' ? 'red' : 'amber';
}
const checks = computed(() => [
  ...(props.kind === 'kp' && editing.value?.employee.positionType === 'Pelaksana'
    ? [
        { key: 'educationRecognized', label: 'Ijazah Master Pegawai diakui administrasi' },
        {
          key: 'examVerified',
          label: 'Ujian dinas / pembebasan sah (jika lintas golongan II/d atau III/d)',
        },
      ]
    : []),
  ...(props.kind === 'kp' && editing.value?.employee.positionType === 'Fungsional'
    ? [
        {
          key: 'functionalVerified',
          label: 'Kesesuaian jenjang dan persyaratan fungsional telah diperiksa',
        },
      ]
    : []),
  ...(props.kind === 'kp' && editing.value?.employee.positionType === 'Struktural'
    ? [
        {
          key: 'managerialVerified',
          label: 'Rentang pangkat, riwayat pelantikan, dan jalur struktural sesuai',
        },
      ]
    : []),
  { key: 'documentsVerified', label: 'Dokumen sah dan seluruh syarat relevan telah diperiksa' },
]);
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow">PENGINGAT ADMINISTRASI PNS</span>
      <h1>{{ kind.toUpperCase() }} Radar<span class="heading-dot">.</span></h1>
      <p>Pantau jadwal dan kesiapan {{ name.toLowerCase() }} berdasarkan dokumen pegawai.</p>
    </div>
    <button class="button button-outline" @click="explain = true">
      <Icon name="CircleHelp" :size="17" />Dasar perhitungan
    </button>
  </div>
  <div class="stats-grid radar-stats">
    <article v-for="s in stats" :key="s.label" class="stat-card">
      <div>
        <span>{{ s.label }}</span
        ><Icon name="Radar" :size="19" />
      </div>
      <strong>{{ s.value }}</strong>
      <p>PNS aktif · {{ kind.toUpperCase() }}</p>
    </article>
  </div>
  <div class="notice compact">
    <Icon name="Info" :size="18" />
    <p>
      {{
        kind === 'kgb'
          ? 'Isi tanggal SK dan TMT KGB terakhir. Pengingat muncul dua bulan sebelum perkiraan TMT berikutnya.'
          : 'Periode KP tanggal 1 setiap bulan. Masa pangkat diperiksa terhadap TMT target; tenggat berkas dapat disesuaikan berdasarkan surat layanan.'
      }}
      Status kesiapan terpisah dari proses usulan dan bukan penetapan hak.
    </p>
  </div>
  <section class="card section-space">
    <div class="table-toolbar directory-filters">
      <div class="search-field">
        <Icon name="Search" :size="16" /><input
          v-model="search"
          placeholder="Cari nama atau NIP…"
          aria-label="Cari pegawai radar"
        />
      </div>
      <select v-model="status" aria-label="Filter kesiapan radar">
        <option value="">Semua kesiapan</option>
        <option v-for="s in statuses" :key="s">{{ s }}</option></select
      ><select v-model="process" aria-label="Filter proses radar">
        <option value="">Semua proses</option>
        <option v-for="s in processes" :key="s">{{ s }}</option>
      </select>
    </div>
    <div v-if="loading" class="loading-state">Memuat radar…</div>
    <div v-else-if="error" role="alert" class="form-error">
      {{ error }} <button class="text-link" @click="load">Coba lagi</button>
    </div>
    <div v-else class="table-wrap">
      <table class="radar-table">
        <thead>
          <tr>
            <th>Nama pegawai</th>
            <th>Jenis ASN</th>
            <th>Golongan</th>
            <th>{{ kind === 'kp' ? 'Angka kredit terakhir' : 'Tanggal SK KGB terakhir' }}</th>
            <th>Perkiraan TMT</th>
            <th>Status pemeriksaan</th>
            <th>Proses</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in visible" :key="r.employee.id">
            <td>
              <div class="table-detail">
                <strong>{{ r.employee.name }}</strong
                ><small>{{ r.employee.position }}</small
                ><small>{{ r.employee.unit }}</small>
              </div>
            </td>
            <td>{{ r.employee.type }}</td>
            <td>
              {{ r.employee.grade || '—'
              }}<small v-if="kind === 'kp' && r.result.nextGrade" class="radar-subline"
                >Tujuan {{ r.result.nextGrade }}</small
              >
            </td>
            <td>
              <template v-if="kind === 'kp'"
                >{{
                  r.employee.positionType === 'Fungsional' ? (r.data.credit ?? 'Belum diisi') : '—'
                }}<small v-if="r.result.creditNeeded" class="radar-subline"
                  >Kebutuhan {{ r.result.creditNeeded }} AK</small
                ></template
              ><template v-else
                >{{ dateLabel(r.data.lastKgbDate)
                }}<small class="radar-subline"
                  >TMT terakhir {{ dateLabel(r.data.lastKgbTmt) }}</small
                ></template
              >
            </td>
            <td>
              {{ dateLabel(r.result.targetDate)
              }}<small v-if="r.result.reminderActive" class="radar-subline"
                ><span class="badge amber">Waktunya persiapan</span></small
              ><small v-if="r.result.deadline" class="radar-subline"
                >Batas berkas {{ dateLabel(r.result.deadline) }}</small
              >
            </td>
            <td>
              <span class="badge" :class="badge(r.result.status)">{{ r.result.status }}</span>
              <details class="radar-reasons">
                <summary>
                  {{
                    r.result.reasons.length
                      ? 'Lihat ' + r.result.reasons.length + ' catatan'
                      : 'Lihat hasil pemeriksaan'
                  }}
                </summary>
                <ul>
                  <li v-for="(c, i) in r.result.checks" :key="i">
                    {{ c.state === 'pass' ? '✓' : c.state === 'fail' ? '×' : '○' }} {{ c.label }}
                  </li>
                </ul>
              </details>
            </td>
            <td>
              {{ r.data.process || 'Belum diusulkan'
              }}<small v-if="r.data.process === 'Perbaikan'" class="radar-subline"
                >Tenggat perbaikan {{ dateLabel(r.data.correctionDeadline) }}</small
              >
            </td>
            <td>
              <button
                class="button button-outline small"
                :aria-label="'Perbarui radar ' + r.employee.name"
                @click="edit(r)"
              >
                <Icon name="Pencil" :size="15" />Perbarui
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!filtered.length" class="empty-inline">
        Tidak ada PNS aktif yang sesuai filter.
      </div>
    </div>
    <div class="pagination">
      <label class="page-size"
        >Tampilkan<select v-model.number="pageSize" aria-label="Jumlah pegawai radar">
          <option :value="10">10</option>
          <option :value="30">30</option>
          <option :value="50">50</option>
          <option :value="0">All</option>
        </select></label
      ><span>{{ filtered.length }} PNS</span>
      <div>
        <button class="icon-button" :disabled="page === 1" aria-label="Sebelumnya" @click="page--">
          <Icon name="ChevronLeft" /></button
        ><span>{{ page }} / {{ pages }}</span
        ><button
          class="icon-button"
          :disabled="page >= pages"
          aria-label="Berikutnya"
          @click="page++"
        >
          <Icon name="ChevronRight" />
        </button>
      </div>
    </div>
  </section>
  <Modal
    v-if="editing"
    :title="'Perbarui ' + kind.toUpperCase() + ' Radar'"
    wide
    @close="!saving && (editing = null)"
    ><h3>{{ editing.employee.name }}</h3>
    <p>
      {{ editing.employee.position }} · {{ editing.employee.grade }} · Pendidikan
      {{ editing.employee.education }}
    </p>
    <p class="muted-small">
      Data disimpan oleh Admin / Super Admin dan dicatat dalam Audit Log. Perubahan Master
      memerlukan pemeriksaan ulang.
    </p>
    <form class="section-space" @submit.prevent="save">
      <fieldset :disabled="saving" class="radar-fieldset">
        <div class="form-grid">
          <template v-if="kind === 'kgb'"
            ><label
              >Tanggal penerbitan SK KGB terakhir<input
                v-model="form.lastKgbDate"
                type="date" /></label
            ><label
              >TMT KGB terakhir pada SK<input v-model="form.lastKgbTmt" type="date" /><small
                >Acuan perhitungan dua tahun.</small
              ></label
            ></template
          >
          <template v-else
            ><label
              >TMT target KP<input v-model="form.targetDate" type="date" /><small
                >Tanggal 1. Kosongkan untuk perkiraan otomatis Pelaksana/Fungsional.</small
              ></label
            ><label
              >TMT pangkat terakhir<input
                :value="editing.employee.rankTmt || ''"
                type="date"
                disabled
              /><small>Perbaiki melalui Master Pegawai jika belum sesuai.</small></label
            >
            <template v-if="editing.employee.positionType === 'Fungsional'"
              ><label
                >AK untuk naik pangkat (PAK terakhir)<input
                  v-model.number="form.credit"
                  type="number"
                  min="0"
                  max="1000000"
                  step="0.001" /></label
              ><label
                >AK kumulatif untuk naik jenjang<input
                  v-model.number="form.cumulativeCredit"
                  type="number"
                  min="0"
                  max="1000000"
                  step="0.001" /></label
              ><label class="full"
                >Nomor/tanggal PAK sah<input v-model="form.pakReference" maxlength="1500" /><small
                  >Jenjang dibaca dari Nama Jabatan; koreksi di Master Pegawai bila tidak
                  sesuai.</small
                ></label
              ></template
            >
            <label v-if="editing.employee.positionType === 'Struktural'" class="full"
              >Riwayat pelantikan dan dasar jalur struktural<textarea
                v-model="form.appointmentReference"
                maxlength="1500"
              /><small
                >TMT target ditentukan pengelola setelah memeriksa jalur dan rentang pangkat
                jabatan.</small
              ></label
            >
          </template>
          <template v-for="i in [1, 2]" :key="i"
            ><label
              >Tahun penilaian {{ i
              }}<input
                v-model.number="form['performanceYear' + i]"
                type="number"
                min="1900"
                max="2200" /></label
            ><label
              >Predikat kinerja {{ i
              }}<select v-model="form['performance' + i]">
                <option :value="null">Belum diperiksa</option>
                <option v-for="p in performances" :key="p">{{ p }}</option>
              </select></label
            ></template
          >
          <p class="full muted-small">
            Gunakan dua tahun sebelum tahun TMT target. Jika TMT target berubah, periksa kembali
            tahun dan dokumen kinerja.
          </p>
          <label v-for="c in checks" :key="c.key" class="full"
            >{{ c.label
            }}<select v-model="form[c.key]">
              <option :value="null">Belum diperiksa</option>
              <option :value="true">Memenuhi</option>
              <option :value="false">Tidak memenuhi</option>
            </select></label
          >
          <label class="full"
            >Referensi dokumen pemeriksaan<textarea
              v-model="form.documentReference"
              maxlength="1500"
              placeholder="Nomor/tanggal SK, dokumen kinerja, ijazah, atau berkas pendukung yang diperiksa"
            />
          </label>
          <template v-if="kind === 'kp'"
            ><label
              >Tenggat khusus berkas (opsional)<input v-model="form.deadline" type="date" /></label
            ><label
              >Surat layanan acuan tenggat<input v-model="form.scheduleReference" maxlength="1500"
            /></label>
            <p class="full muted-small">
              Baseline usul: tanggal 16 bulan M−2 sampai 15 bulan M−1. Tenggat khusus harus sebelum
              TMT target dan dilengkapi surat layanan.
            </p></template
          >
          <label
            >Status proses<select v-model="form.process">
              <option v-for="s in processes" :key="s">{{ s }}</option>
            </select></label
          ><label v-if="form.process === 'Perbaikan'"
            >Tenggat perbaikan sesuai surat<input
              v-model="form.correctionDeadline"
              type="date"
              required
          /></label>
          <label class="full"
            >Catatan / nomor usulan atau keputusan<textarea
              v-model="form.processNote"
              maxlength="1500"
              :required="form.process !== 'Belum diusulkan'"
            />
          </label>
          <label class="full"
            >Alasan perubahan<textarea
              v-model="form.reason"
              minlength="5"
              maxlength="1500"
              required
            />
          </label>
        </div>
        <p v-if="formError" role="alert" class="form-error section-space">{{ formError }}</p>
        <div class="modal-actions">
          <button type="button" class="button button-outline" @click="editing = null">Batal</button
          ><button class="button button-primary">
            {{ saving ? 'Menyimpan…' : 'Simpan pemeriksaan' }}
          </button>
        </div>
      </fieldset>
    </form></Modal
  >
  <Modal v-if="explain" title="Dasar perhitungan Radar" wide @close="explain = false"
    ><h3>{{ name }} · PNS aktif</h3>
    <p class="section-space">
      Aturan instansi: NADI-ASN-Rules-KP dan KGB.md · versi 2026-09-22.1. Hasil merupakan
      pemeriksaan administratif; keputusan tetap melalui proses yang berlaku.
    </p>
    <ul class="radar-rules">
      <template v-if="kind === 'kgb'"
        ><li>
          TMT berikutnya = TMT KGB terakhir + 2 tahun. Tanggal penerbitan SK disimpan terpisah.
        </li>
        <li>
          Kinerja dua tahun minimal Cukup. Pengingat aktif dua bulan sebelum TMT, termasuk saat
          syarat belum lengkap.
        </li>
        <li>AK, jenjang fungsional dan masa pangkat empat tahun tidak digunakan.</li></template
      ><template v-else
        ><li>
          Pelaksana: 4 tahun pada TMT target, kinerja dua tahun minimal Baik, batas pendidikan yang
          diakui, serta ujian/pembebasan bila diperlukan.
        </li>
        <li>
          Fungsional: 2 tahun, kinerja minimal Baik, PAK sah, AK pangkat dan jenjang sesuai. AK
          pangkat 50 / 100 / 150 / 200 sesuai kelompok pangkat. Perpindahan jenjang memeriksa AK
          kumulatif 100 / 200 / 450 secara terpisah.
        </li>
        <li>
          Struktural: pengelola menentukan target dan memverifikasi rentang pangkat serta riwayat
          pelantikan. Tidak memakai rumus Pelaksana.
        </li>
        <li>
          Periode tanggal 1 setiap bulan. Perkiraan otomatis memilih periode yang masa pangkatnya
          cukup dan baseline tenggatnya belum lewat. Target yang sudah disimpan tidak bergeser
          otomatis.
        </li>
        <li>
          Baseline berkas 16 bulan M−2 sampai 15 bulan M−1; tenggat khusus dan perbaikan mengikuti
          surat layanan. Pangkat atasan tidak membatasi KP reguler.
        </li></template
      >
      <li>
        Data belum lengkap: pemeriksaan/dokumen wajib kosong. Belum memenuhi: ada syarat terbukti
        gagal, dengan daftar kekurangan tetap ditampilkan. Siap diusulkan: seluruh pemeriksaan
        relevan terpenuhi.
      </li>
      <li>
        Proses usulan dicatat terpisah, termasuk Perbaikan dan Ditolak. Keputusan terbit tidak
        otomatis mengubah pangkat di Master atau memulai siklus baru; perbarui dari SK yang sah.
      </li>
    </ul>
    <p class="section-space">
      <a
        class="text-link"
        href="https://www.bkn.go.id/wp-content/uploads/2023/05/SE-16-TAHUN-2023.pdf"
        target="_blank"
        rel="noopener"
        >SE BKN 16/2023</a
      >
      ·
      <a
        class="text-link"
        href="https://www.bkn.go.id/wp-content/uploads/2023/07/Peraturan-BKN-Nomor-3-Tahun-2023-tentang-AK-Kenaikan-Pagkat-Jenjang-JF.pdf"
        target="_blank"
        rel="noopener"
        >Peraturan BKN 3/2023</a
      >
      ·
      <a
        class="text-link"
        href="https://peraturan.bpk.go.id/Details/330957/peraturan-bkn-no-4-tahun-2025"
        target="_blank"
        rel="noopener"
        >Peraturan BKN 4/2025</a
      >
      ·
      <a
        class="text-link"
        href="https://peraturan.bpk.go.id/Details/67316/pp-no-7-tahun-1977"
        target="_blank"
        rel="noopener"
        >PP 7/1977</a
      >
    </p></Modal
  >
</template>
