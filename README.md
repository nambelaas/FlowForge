# FlowForge: Real-Time Multi-Tenant Workflow Orchestration Engine

FlowForge adalah core engine orkestrasi alur kerja (*workflow engine*) berbasis **Directed Acyclic Graph (DAG)** yang dirancang untuk mengompilasi, memvalidasi, dan mengeksekusi serangkaian tugas berdependensi secara asinkronus dan aman. Dilengkapi dengan arsitektur *multi-tenancy* yang terisolasi ketat serta dasbor pemantauan *real-time*.

Proyek ini dibangun sebagai solusi pemenuhan tantangan teknis (*technical test*) untuk posisi Software Engineer dengan fokus pada ketahanan performa *backend*, skalabilitas antrean (*queue scalability*), dan interaktivitas data.

---

## 🚀 Fitur Utama (Core MVP)

1. **DAG-Based Execution Engine**: Kemampuan mengeksekusi *nodes* (langkah tugas) sesuai dengan urutan topologi dependensi. Sistem secara otomatis mendeteksi langkah yang siap jalan (*ready to dispatch*) setelah dependensinya sukses (`SUCCESS`).
2. **Race-Condition & Deadlock Safety**: Menggunakan mekanisme **Pagar Database** (`affected rows validation` melalui kondisi status `PENDING`) untuk menjamin tidak ada instruksi ganda atau eksekusi paralel yang tumpang tindih saat dievaluasi oleh *multiple queue workers*.
3. **Real-Time Monitoring Dashboard**: Dasbor interaktif menggunakan **Inertia.js + React** yang terintegrasi penuh dengan **Laravel Reverb (WebSockets)** untuk menyiarkan status eksekusi (`PENDING` -> `RUNNING` -> `SUCCESS`/`FAILED`) dan log sistem secara instan tanpa membebani server dengan HTTP polling berkala.
4. **Intelligent Failure Analysis**: Mengintegrasikan **Google Gemini AI API** untuk melakukan analisis kegagalan otomatis secara cerdas dan memberikan saran perbaikan langsung pada komponen tugas yang berstatus `FAILED`.
5. **Robust Multi-Tenancy**: Isolasi data yang ketat antar penyewa (*tenant*) di level basis data dan saluran siaran (*broadcast channels isolation*) menggunakan identitas `tenant_id`.

---

## 🛠️ Tech Stack

* **Backend Framework**: Laravel 12 (PHP 8.2+)
* **Frontend Stack**: React, Inertia.js, Tailwind CSS, Lucide React
* **Real-Time Server**: Laravel Reverb (Native WebSocket)
* **Queue Driver**: Database / Redis (Asynchronous Job Batching)
* **Database**: MySQL 8.0 / PostgreSQL

---
## ⚙️ Panduan Instalasi Manual (Tanpa Docker)
Jika anda lebih memilih untuk menjalankan aplikasi ini secara manual tanpa menggunakan Docker, berikut adalah langkah-langkah yang perlu Anda ikuti:

### Prasyarat
* PHP 8.2 atau lebih tinggi
* Composer
* Node.js 16 atau lebih tinggi
* MySQL 8.0
* Redis

### Langkah Setup Aplikasi Manual
1. **Salin Environment Variables**
   Salin file konfigurasi lingkungan dan sesuaikan kunci API jika diperlukan:
   ```
   cp .env.example .env
   ```
2. **Instalasi Dependencies**
   Jalankan perintah berikut untuk menginstal dependencies PHP dan Javascript:
   ```
   composer install
   npm install
   npm run build
   ```
3. **Jalankan Migrasi & Database Seeder**
   Isi database dengan struktur tabel untuk keperluan pengujian awal:
   ```
   php artisan migrate --seed
   ```
4. **Jalankan Queue Worker**
   Untuk memproses langkah-langkah alur kerja secara asinkronus, jalankan queue worker di terminal terpisah:
   ```
   php artisan queue:work
   ```
5. **Jalankan Reverb**
   Untuk mengaktifkan server WebSocket Reverb yang menangani komunikasi real-time, jalankan perintah berikut di terminal terpisah:
   ```
   php artisan reverb:start
   ```
6. **Jalankan Server Aplikasi**
   Jalankan server Laravel untuk mengakses dashboard:
   ```
   php artisan serve
   ```
7. **Akses Aplikasi**
   Buka browser anda dan akses Dasbor FlowForge melalui alamat:
   - Web Dashboard: http://localhost:8000/dashboard
   - WebSocket Port: 8080
   - Pastikan Redis berjalan di latar belakang
  
## 🐳 Panduan Instalasi Menggunakan Docker

Aplikasi ini juga dikemas menggunakan Docker agar dapat dijalankan secara instan di lingkungan lokal Anda tanpa perlu menginstal PHP atau Node.js secara manual.

### Prasyarat
* Sudah menginstal [Docker Desktop](https://www.docker.com/products/docker-desktop/) di perangkat Anda.

### Langkah Setup Aplikasi via Docker

1. **Salin Environment Variables**
   Salin file konfigurasi lingkungan dan sesuaikan kunci API jika diperlukan:
   ```
   cp .env.example .env
   ```


2. **Build dan Nyalakan Kontainer**
    Jalankan perintah ini untuk membangun image dan menyalakan seluruh stack (Aplikasi, Database, Queue Worker, dan Reverb) di background:

    ```
    docker-compose up -d --build
    ```

3. **Jalankan Migrasi & Database Seeder**
    Isi database dengan struktur tabel untuk keperluan pengujian awal:
    ```
    docker-compose exec app php artisan migrate --seed
    ```


4. **Akses Aplikasi**
    Buka browser Anda dan akses Dasbor FlowForge melalui alamat:
    - Web Dashboard: http://localhost:8000/dashboard
    - WebSocket Port: 8080 (Internal Reverb Communication)

## 🔍 Alur Kerja Arsitektur & Penyelesaian Masalah
1. Validasi Siklus (Topological Sort)
    Sebelum alur kerja dijalankan, sistem akan mengevaluasi seluruh susunan steps menggunakan Kahn's Algorithm (BFS) di level backend (validateAndSort). Jika pengguna tidak sengaja membuat hubungan yang berputar (looping), sistem akan langsung melempar exception:
    ```
    throw new Exception("Circular dependency detected! Alur kerja Anda memiliki perulangan yang dilarang.");
    ```

2. Penanganan Race Condition pada Multi-Worker
    Untuk mencegah dua worker mengeksekusi node anak yang sama secara bersamaan ketika dua parent dependency selesai secara paralel, FlowForge menerapkan taktik pembaruan baris database atomik:
    ```
    $affectedRows = StepRun::where('workflow_run_id', $this->workflowRunId)
        ->where('step_id', $nextStep['id'])
        ->where('status', 'PENDING') // Pagar Pengaman
        ->update(['status' => 'RUNNING', 'started_at' => now()]);

    if ($affectedRows > 0) {
        // Hanya 1 worker yang berhasil merebut hak eksekusi yang boleh melakukan dispatch!
        dispatch(new ExecuteWorkflowStep(...));
    }
    ```

## 🖥️ Pengujian Mandiri (Testing)
Untuk memastikan seluruh fungsi orkestrasi dan logika bisnis berjalan sesuai dengan spesifikasi ekspektasi, Anda dapat menjalankan unit testing bawaan melalui kontainer aplikasi:
**Docker:**
```
docker-compose exec app php artisan test
```
**Manual:**
```
php artisan test
```
