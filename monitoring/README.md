# Pemantauan (P11): Prometheus + Grafana + cAdvisor + node-exporter

Semua layanan pemantau berjalan di `compose.yaml` yang sama dengan aplikasi.

| Layanan | Image (versi dikunci) | Fungsi | Akses |
|---|---|---|---|
| prometheus | `prom/prometheus:v3.5.0` | Mengumpulkan metrik dan mengevaluasi alarm | http://localhost:9090 |
| grafana | `grafana/grafana:12.1.1` | Dashboard | http://localhost:3000 |
| cadvisor | `gcr.io/cadvisor/cadvisor:v0.52.1` | CPU dan memori per container | internal |
| node-exporter | `prom/node-exporter:v1.9.1` | CPU, memori, disk mesin host | internal |
| nginx-exporter | `nginx/nginx-prometheus-exporter:1.4.2` | Jumlah request ke nginx frontend | internal |

Data Prometheus dan Grafana disimpan di volume `prometheus-data` dan `grafana-data`,
jadi tidak hilang ketika container dibuat ulang.

## Sumber metrik aplikasi

* **Permintaan per detik**: semua request pengguna (halaman Vue dan `/api/*`) lewat nginx
  di container `frontend`. nginx membuka `stub_status` di port 8081 (tidak dipublikasikan ke
  host), lalu `nginx-exporter` mengubahnya menjadi `nginx_http_requests_total`.
* **Status hidup backend**: Laravel membuka `GET /metrics` (`MetricsController`) berisi
  `app_database_up`, `app_products_total`, `app_users_total`. Kalau backend mati, scrape gagal
  dan Prometheus mencatat `up{job="laravel-backend"} = 0`.

## Menjalankan

```bash
cp .env.example .env      # lalu isi APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, GRAFANA_ADMIN_PASSWORD
docker compose up -d --build
```

* Target Prometheus: http://localhost:9090/targets (harus 6 target UP)
* Alarm: http://localhost:9090/alerts
* Dashboard: http://localhost:3000, login `admin` + password dari `.env`, buka
  **Dashboards → UTS → UTS App: Kesehatan Layanan**. Dashboard dimuat otomatis dari
  `monitoring/grafana/dashboards/uts-app.json`.

Panel "permintaan per detik" baru bergerak kalau ada request. Buka aplikasi di
http://localhost:8080 beberapa kali, atau jalankan di PowerShell:

```powershell
1..300 | % { Invoke-WebRequest -UseBasicParsing http://localhost:8080/api/products > $null; Start-Sleep -Milliseconds 200 }
```

## Dashboard

Setiap judul panel adalah pertanyaan tentang aplikasi:

1. Apakah setiap layanan sedang hidup sekarang? (`up`)
2. Berapa permintaan per detik yang masuk ke aplikasi? (`rate(nginx_http_requests_total[5m])`, satuan req/s)
3. Apakah backend masih bisa terhubung ke database? (`app_database_up`)
4. Container mana yang paling banyak memakai CPU? (`rate(container_cpu_usage_seconds_total[5m])`, satuan %)
5. Berapa memori yang dipakai tiap container? (`container_memory_working_set_bytes`, satuan byte)
6. Kapan saja layanan pernah mati dalam rentang waktu ini? (riwayat `up`)
7. Seberapa sibuk CPU dan memori mesin host? (node-exporter, satuan %)
8. Berapa koneksi yang sedang dilayani nginx? (`nginx_connections_active`)

## Alarm `LayananMati` (monitoring/prometheus/alerts.yml)

`up == 0` selama `for: 1m`. Anotasi `tindakan_pertama` memberi tahu penerima perintah
pertama yang harus dijalankan untuk service yang mati.

Cara membuktikan alarm berbunyi:

```bash
docker compose stop backend
# buka http://localhost:9090/alerts : sekitar 15 detik jadi PENDING, setelah 1 menit jadi FIRING
docker compose start backend   # alarm kembali normal
```

## Kenapa password admin Grafana harus diganti

`admin/admin` adalah password bawaan yang diketahui semua orang dan dicoba pertama kali oleh
pemindai otomatis. Siapa pun yang bisa membuka port 3000 bisa masuk sebagai admin, mengubah
atau menghapus dashboard dan alarm (sehingga gangguan tidak ketahuan), membaca data
pemantauan yang membuka struktur sistem, dan menambah data source untuk menjangkau layanan
internal lain. Karena itu:

* Password diambil dari `GRAFANA_ADMIN_PASSWORD` di `.env`. Compose menolak jalan kalau
  variabel itu kosong, jadi Grafana tidak pernah hidup dengan `admin/admin`.
* `.env` diabaikan git; yang di-commit hanya `.env.example` berisi contoh.
* Pendaftaran akun baru dan akses anonim dimatikan.

Catatan: Grafana hanya memakai `GRAFANA_ADMIN_PASSWORD` saat volume `grafana-data` pertama
kali dibuat. Kalau Grafana sudah pernah jalan dengan admin/admin, ganti lewat UI
(Profile → Change password) atau
`docker compose exec grafana grafana cli admin reset-admin-password <password-baru>`.

## Jika panel CPU/memori "No data"

Buka /targets dulu. Jika target cadvisor UP tetapi panel tetap kosong, lihat
`docker compose logs cadvisor`. Pesan `failed to identify the read-write layer ID` berarti
Docker memakai *containerd image store*, yang belum dikenali cAdvisor v0.52.1, sehingga
label nama container tidak muncul. Di Docker Desktop matikan
**Settings → General → Use containerd for pulling and storing images**, lalu
`docker compose up -d --build` lagi.
