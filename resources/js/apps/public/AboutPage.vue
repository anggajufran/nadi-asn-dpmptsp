<script setup lang="ts">
import ThemeToggle from '@/components/ThemeToggle.vue';
import Icon from '@/components/Icon.vue';
</script>
<template>
  <div class="about-page">
    <ThemeToggle />
    <RouterLink to="/" class="text-link"><Icon name="ArrowLeft" />Kembali ke dashboard</RouterLink
    ><img
      class="about-logo"
      src="/logo-nadi-asn.png"
      alt="NADI ASN DPMPTSP Prov. Sulawesi Tengah"
    />
    <div class="eyebrow">MENGENAL NADI ASN</div>
    <h1>Satu sumber data.<br />Banyak manfaat.</h1>
    <p class="lead">
      Navigasi dan Analitik Data Internal ASN adalah platform pengelolaan data kepegawaian DPMPTSP
      Prov. Sulawesi Tengah untuk informasi yang akurat, terintegrasi, dan dapat ditindaklanjuti.
    </p>
    <section class="card">
      <h2>Data ASN dalam Satu Pandangan</h2>
      <p>
        NADI ASN membantu pengelola kepegawaian memahami kondisi ASN, menyusun prioritas pekerjaan,
        serta memantau kualitas data. Satu data yang telah diverifikasi digunakan kembali untuk
        statistik, DUK, radar KP/KGB, dan laporan.
      </p>
      <p>
        NADI ASN merupakan sistem pendukung internal dan tidak menggantikan SIASN, ASN Digital,
        maupun e-Kinerja.
      </p>
    </section>
    <section id="privasi" class="card">
      <Icon name="ShieldCheck" :size="32" />
      <h2>Informasi terbuka, privasi terjaga.</h2>
      <p>
        Portal publik hanya menampilkan statistik agregat. Nama, NIP, tanggal lahir, serta dokumen
        pegawai tidak ditampilkan. Angka agregat tetap tersedia saat difilter, termasuk kelompok
        berjumlah kecil. Total ASN dalam rekap terdiri dari PNS dan PPPK Penuh Waktu; PPPK Paruh
        Waktu disajikan terpisah.
      </p>
      <p>
        Data pada mode simulasi sepenuhnya sintetis dan bukan merupakan statistik resmi instansi.
        Akses internal menggunakan pembatasan peran; Super Admin memeriksa perubahan data dan dapat
        menyetujui usulannya sendiri.
      </p>
    </section>
    <RouterLink to="/" class="button button-primary"
      >Jelajahi statistik <Icon name="ArrowRight"
    /></RouterLink>
  </div>
</template>
