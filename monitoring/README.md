
# Pemantauan

Stack pemantauan dijalankan bersama aplikasi melalui `compose.yaml`. Versi image
Prometheus, Grafana, cAdvisor, dan Node Exporter dikunci di file tersebut.
Prometheus dan Grafana menyimpan data pada volume Docker bernama
`prometheus-data` dan `grafana-data`.

## Menjalankan

Pastikan Docker Desktop berjalan. Isi variabel yang diwajibkan di `.env` lokal,
termasuk `GF_ADMIN_PASSWORD`, lalu jalankan dari root proyek:

```sh
docker compose up -d --build
docker compose ps
```

Jangan masukkan `.env` atau password ke Git. `GF_ADMIN_PASSWORD` digunakan
Grafana saat inisialisasi pertama. Jika volume `grafana-data` sudah pernah
dibuat, ubah password admin yang tersimpan melalui **Grafana → Profile →
Security → Change password**; mengubah `.env` saja tidak merotasi password pada
volume yang sudah ada.

Password admin yang unik dan kuat mencegah pihak lain mengambil alih Grafana,
mengubah dashboard/aturan alarm, atau membaca data pemantauan.

## Memeriksa target dan dashboard

- Prometheus: <http://localhost:9090/targets>. Pastikan semua target berstatus
  **UP**. Halaman status alarm tersedia di <http://localhost:9090/alerts>.
- Grafana: <http://localhost:3000>. Masuk memakai akun admin yang dikonfigurasi
  dan buka dashboard **Pemantauan Aplikasi UTS**.
- Dashboard berisi panel CPU dan memori per container, laju permintaan backend,
  status target, serta memori host. Sumbu grafik menggunakan satuan yang sesuai.

## Menguji alarm

Aturan `LayananMati` memicu saat target Prometheus berstatus `DOWN` selama satu
menit. Anotasi `tindakan_pertama` menyarankan penerima memeriksa container
dengan `docker compose ps -a`, lalu membaca log layanan.

Untuk menguji secara sengaja dari root proyek:

```sh
docker compose stop node-exporter
```

Tunggu setidaknya satu menit ditambah satu interval scrape (15 detik), lalu
buka <http://localhost:9090/alerts> dan pastikan `LayananMati` berstatus
**FIRING** untuk target `node-exporter`. Pulihkan layanan setelah pengujian:

```sh
docker compose start node-exporter
```

Tunggu sampai target kembali **UP** dan alarm tidak lagi **FIRING**. Pengujian
ini sengaja menghentikan satu container; jalankan hanya pada lingkungan lokal
atau lingkungan uji.
