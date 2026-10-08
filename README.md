# HB WHMCS Invoice Editor & Ledger Manager

<p align="center">
  <img src="https://img.shields.io/badge/WHMCS-v8.x%20--%20v9.x%20Siap%20Pakai-0070ba?style=for-the-badge&logo=whmcs&logoColor=white" alt="Versi WHMCS" />
  <img src="https://img.shields.io/badge/PHP-7.4%20|%208.1%20|%208.2%20|%208.3%20|%208.4-777bb4?style=for-the-badge&logo=php&logoColor=white" alt="Versi PHP" />
  <img src="https://img.shields.io/badge/Lisensi-MIT-green.svg?style=for-the-badge" alt="Lisensi MIT" />
  <img src="https://img.shields.io/badge/Versi-1.2.0-blue.svg?style=for-the-badge" alt="Versi 1.2.0" />
  <img src="https://img.shields.io/badge/Keamanan-Audit%20Log%20Lengkap-success?style=for-the-badge&logo=shield" alt="Audit Log" />
</p>

---

## 📌 Ringkasan Modul

**HB WHMCS Invoice Editor & Ledger Manager** adalah modul addon resmi untuk WHMCS yang dirancang khusus untuk memberikan kendali penuh kepada staf dan administrator dalam mengoreksi faktur (invoice), menyesuaikan rincian item, mengatur ulang pajak, serta membersihkan riwayat ledger / penyesuaian kredit.

Pada sistem standar WHMCS, proses pengeditan invoice memiliki banyak keterbatasan. Masalah paling umum terjadi ketika admin mengubah status invoice dari **Cancelled** kembali menjadi **Unpaid**:
* Sistem WHMCS secara otomatis menerbitkan **Credit Note / Billing Adjustment** saat pembatalan.
* Ketika status diubah kembali ke Unpaid, penyesuaian kredit tersebut tetap tertinggal di ledger akun (`tblaccounts`).
* Akibatnya, saldo tagihan menjadi **Rp 0,00** dan pada halaman checkout *Mass Payment* klien muncul potongan minus **`Partial Payments: -Rp ...`** yang membingungkan pelanggan.

**Modul HB Invoice Edit menyelesaikan masalah ini secara tuntas** melalui manajemen ledger cerdas dan pencatatan audit log perubahan yang lengkap (*Before vs After*).

---

## 🚀 Fitur Unggulan

| Fitur | Penjelasan Singkat |
| :--- | :--- |
| 📝 **Edit Rincian Item Bebas** | Tambah baris baru, ubah deskripsi, nominal harga, dan status pajak per item secara dinamis. |
| 📅 **Pengaturan Tanggal & Due Date** | Koreksi tanggal penerbitan invoice (*backdate*) dan batas jatuh tempo tanpa merusak siklus layanan. |
| 🛡️ **Zero-Deduction Recovery** | Fitur otomatisasi pembersihan Credit Note (`tblbillingnotes`) & penyesuaian ledger (`tblaccounts`) saat mengembalikan status ke Unpaid. |
| 🧹 **Manajemen Ledger Transaksi** | Tinjau seluruh transaksi pembayaran/penyesuaian dan hapus record penyesuaian yang terkunci langsung dengan satu klik. |
| 🔍 **Jejak Audit Visual (Diff)** | Mencatat setiap sesi perubahan di tabel audit log dengan snapshot JSON lengkap (*Kondisi Sebelum vs Sesudah*). |
| 🔒 **Wajib Isi Alasan Koreksi** | Memastikan kepatuhan akuntansi dengan mewajibkan admin mengisi justifikasi perubahan sebelum data disimpan. |
| ⚡ **Shortcut Button di Admin WHMCS** | Tombol navigasi cepat **"Edit Invoice (HB)"** langsung muncul di halaman bawaan `invoices.php?action=edit`. |

---

## 🔄 Alur Kerja & Diagram Arsitektur

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       ALUR KERJA HB INVOICE EDIT                            │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
                 ┌───────────────────────────────────────────┐
                 │  Halaman Detail Invoice di Admin WHMCS    │
                 │        (invoices.php?action=edit)         │
                 └───────────────────────────────────────────┘
                                       │
                   [ Klik Tombol "Edit Invoice (HB)" ]
                                       │
                                       ▼
                 ┌───────────────────────────────────────────┐
                 │     Modul Addon: HB Invoice Editor        │
                 └───────────────────────────────────────────┘
                                       │
             ┌─────────────────────────┴─────────────────────────┐
             ▼                                                   ▼
┌─────────────────────────────┐             ┌─────────────────────────────────┐
│   1. Koreksi Data Faktur    │             │    2. Kelola Ledger/Transaksi   │
├─────────────────────────────┤             ├─────────────────────────────────┤
│ • Ubah Deskripsi / Harga    │             │ • Pantau Riwayat tblaccounts    │
│ • Tambah / Hapus Baris Item │             │ • Hapus Single Transaksi        │
│ • Ubah Tanggal / Due Date   │             │ • Bersihkan Semua Penyesuaian   │
│ • Status: Unpaid/Paid/Draft │             │ • Kembalikan Tagihan Jadi Penuh │
└─────────────────────────────┘             └─────────────────────────────────┘
             │                                                   │
             ▼                                                   ▼
┌─────────────────────────────┐             ┌─────────────────────────────────┐
│  Otomatis Bersihkan Credit  │             │   Sinkronisasi Saldo & Balance  │
│    Note saat Revert Unpaid  │             │    Klien Bebas Potongan Minus   │
└─────────────────────────────┘             └─────────────────────────────────┘
             │                                                   │
             └─────────────────────────┬─────────────────────────┘
                                       │
                                       ▼
                 ┌───────────────────────────────────────────┐
                 │       Pencatatan Audit Trail Log          │
                 ├───────────────────────────────────────────┤
                 │ • Simpan Snapshot JSON Sebelum & Sesudah  │
                 │ • Catat Alasan Perubahan, Admin, & IP     │
                 │ • Tulis Catatan ke WHMCS Activity Log     │
                 └───────────────────────────────────────────┘
```

---

### Perbandingan Masalah WHMCS Bawaan vs Solusi Modul

```
[ Masalah WHMCS Standar ]
Invoice #100 (Status: Unpaid, Nominal: Rp 100.000)
   └──> Admin mengubah status ke 'Cancelled' (WHMCS otomatis membuat Credit Note #19)
   └──> Admin mengembalikan status ke 'Unpaid'
   └──> ❌ MASALAH: Balance terhitung Rp 0,00 dan di Mass Payment muncul "-Rp 100.000 Partial Payment"

[ Solusi HB Invoice Edit ]
Invoice #100 dibuka via modul HB Invoice Edit
   └──> Admin memilih 'Unpaid' + [✓] Otomatis Bersihkan Credit Note / Adjustment
   └──> 🛡️ Modul otomatis menghapus riwayat penyesuaian di tblaccounts & tblbillingnotes
   └──> ✅ HASIL: Status Unpaid bersih, Sisa Tagihan Rp 100.000 utuh, Mass Payment normal tanpa minus!
```

---

## 🗄️ Dampak Database & Tabel yang Dimodifikasi

Modul ini beroperasi secara aman menggunakan `WHMCS\Database\Capsule` (Laravel Database Query Builder) tanpa merusak relasi integritas data WHMCS.

### 1. Tabel Khusus Modul: `mod_hb_invoice_corrections_log`
Dibuat otomatis saat modul pertama kali diaktifkan untuk merekam jejak audit keuangan.

| Kolom | Tipe Data | Keterangan |
| :--- | :--- | :--- |
| `id` | `INT(10) AUTO_INCREMENT` | Primary Key |
| `invoice_id` | `INT(11)` | ID Faktur WHMCS yang diedit (`tblinvoices.id`) |
| `client_id` | `INT(11)` | ID Pengguna / Klien (`tblclients.id`) |
| `client_name` | `VARCHAR(150)` | Nama lengkap klien saat koreksi dilakukan |
| `admin_user` | `VARCHAR(50)` | Username staf/admin yang mengeksekusi koreksi |
| `old_total` | `DECIMAL(10,2)` | Total tagihan sebelum diedit |
| `new_total` | `DECIMAL(10,2)` | Total tagihan baru setelah diedit |
| `reason` | `TEXT` | Alasan justifikasi koreksi yang diinput oleh admin |
| `old_snapshot` | `LONGTEXT (JSON)` | Salinan lengkap seluruh field invoice & item lama |
| `new_snapshot` | `LONGTEXT (JSON)` | Salinan lengkap seluruh field invoice & item baru |
| `ip_address` | `VARCHAR(50)` | Alamat IP admin pengeksekusi |
| `created_at` | `TIMESTAMP` | Waktu pencatatan riwayat |

### 2. Tabel Bawaan WHMCS yang Terhubung

| Nama Tabel | Operasi | Penanganan Keamanan Data |
| :--- | :--- | :--- |
| `tblinvoices` | `UPDATE` | Memperbarui tanggal faktur, jatuh tempo, status, metode bayar, subtotal, pajak, dan total tagihan. |
| `tblinvoiceitems` | `DELETE` & `INSERT` | Menata ulang item tagihan secara akurat agar sinkron dengan total nilai faktur. |
| `tblaccounts` | `DELETE` *(Kondisional)* | Menghapus record penyesuaian (`type = 'invoice_billing_adjustment_credit'`) atau transaksi yang dipilih admin saat reset saldo. |
| `tblbillingnotes` | `DELETE` *(Kondisional)* | Membersihkan data header Credit Note saat penyesuaian dibatalkan. |
| `tblbillingnoteitems`| `DELETE` *(Kondisional)* | Membersihkan rincian item Credit Note yang terikat pada invoice. |

---

## 💻 Kebutuhan Sistem (System Requirements)

* **Versi WHMCS**: `v8.0.0` sampai `v9.x` (Kompatibel penuh)
* **Versi PHP**: `7.4`, `8.0`, `8.1`, `8.2`, `8.3`, `8.4`
* **Database**: `MySQL 5.7+` atau `MariaDB 10.3+`
* **Ekstensi PHP**: `PDO`, `pdo_mysql`, `json`, `mbstring`
* **Hak Akses Admin**: Administrator atau Role Group dengan izin Addon Modules

---

## 📦 Panduan Instalasi & Pemasangan

### Langkah 1: Unggah Berkas Modul
Ekstrak atau salin folder `hb_invoice_editor` ke direktori modul WHMCS Anda:
```bash
/path-ke-whmcs/modules/addons/hb_invoice_editor/
├── hb_invoice_editor.php
├── hooks.php
├── README.md
└── LICENSE
```

### Langkah 2: Aktivasi di Admin WHMCS
1. Masuk ke halaman **Admin WHMCS**.
2. Buka menu **System Settings** (ikon kunci) > **Addon Modules** (atau **Setup > Addon Modules** di versi WHMCS sebelumnya).
3. Cari **HB Invoice Edit** lalu klik tombol **Activate**.
4. Klik tombol **Configure** pada modul tersebut, lalu centang grup admin yang diizinkan mengakses (misalnya: *Full Administrator*).

### Langkah 3: Penggunaan
* Buka menu **Addons > HB Invoice Edit** pada panel admin.
* Atau buka salah satu faktur di menu **Billing > Invoices > Edit Invoice**, lalu klik tombol shortcut **"Edit Invoice (HB)"** di bagian atas.

---

## 🛡️ Standar Keamanan & Perlindungan Data

* **Perlindungan CSRF**: Semua aksi form dan mutasi database diproteksi oleh token resmi WHMCS (`check_token('WHMCS.admin.default')`).
* **Prepared Statements**: Menggunakan *WHMCS Capsule Query Builder* sehingga kebal terhadap ancaman SQL Injection.
* **Double-Layer Audit Logging**: Setiap modifikasi dicatat ganda pada tabel database modul dan fungsi `logActivity()` bawaan WHMCS.
* **Tanpa Dependensi Luar**: Murni berjalan mandiri menggunakan pustaka internal WHMCS tanpa script eksternal atau pelacak pihak ketiga.

---

## 📄 Lisensi

Modul ini merupakan perangkat lunak *open-source* di bawah lisensi **[MIT License](LICENSE)**.

---

<p align="center">
  Dikembangkan oleh <strong>HB Dev Team</strong> untuk Komunitas Web Hosting &amp; WHMCS.
</p>
