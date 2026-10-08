# HB WHMCS Invoice Editor & Ledger Manager

<p align="center">
  <img src="https://img.shields.io/badge/WHMCS-v8.x%20--%20v9.x%20Siap%20Pakai-0070ba?style=for-the-badge&logo=whmcs&logoColor=white" alt="Versi WHMCS" />
  <img src="https://img.shields.io/badge/PHP-7.4%20|%208.1%20|%208.2%20|%208.3%20|%208.4-777bb4?style=for-the-badge&logo=php&logoColor=white" alt="Versi PHP" />
  <img src="https://img.shields.io/badge/Lisensi-MIT-green.svg?style=for-the-badge" alt="Lisensi MIT" />
  <img src="https://img.shields.io/badge/Versi-1.2.0-blue.svg?style=for-the-badge" alt="Versi 1.2.0" />
  <img src="https://img.shields.io/badge/Keamanan-Audit%20Log%20Lengkap-success?style=for-the-badge&logo=shield" alt="Audit Log" />
</p>

---

## 📌 Latar Belakang & Masalah Utama di WHMCS v9

Pada **WHMCS versi 9**, sistem menerapkan pembatasan akuntansi yang sangat ketat (*Strict Invoicing Lockout*):
* **Faktur yang sudah terbit tidak dapat diedit sama sekali** (deskripsi item, nominal harga, penambahan/penghapusan baris item, hingga tanggal faktur terkunci secara permanen di antarmuka bawaan WHMCS).
* Ketika terjadi kesalahan input harga atau negosiasi revisi harga dengan klien, WHMCS memaksa administrator untuk **membatalkan faktur lama (*Cancel*), menerbitkan *Credit Note*, lalu membuat faktur baru dari nol**.
* **Dampak Buruk Sistem Default WHMCS**:
  1. **Nomor Faktur Berantakan**: Klien menerima banyak nomor faktur untuk satu transaksi yang sama sehingga memicu kebingungan pembukuan.
  2. **Bug Sisa Saldo / Potongan Mass Payment**: Jika faktur lama yang pernah di-*Cancel* diaktifkan kembali ke status *Unpaid*, WHMCS meninggalkan jejak penyesuaian kredit (*Credit Note Adjustment*) di ledger yang membuat sisa saldo menjadi **Rp 0,00** dan memunculkan potongan aneh **`Partial Payments: -Rp ...`** di halaman *Mass Payment* klien.

**HB WHMCS Invoice Editor & Ledger Manager** hadir sebagai solusi definitif: mengembalikan kebebasan admin untuk mengoreksi faktur secara langsung tanpa perlu repot menerbitkan faktur baru, sekaligus menjaga kepatuhan akuntansi melalui sistem **Audit Trail Log (Before vs After)** dan **Manajemen Pembersihan Ledger Otomatis**.

---

## 🚀 Fitur Unggulan

| Fitur | Penjelasan Singkat |
| :--- | :--- |
| 📝 **Koreksi Langsung Tanpa Bikin Faktur Baru** | Edit nominal harga, deskripsi item, hapus baris, atau tambah item baru langsung pada faktur yang sama di WHMCS v9. |
| 📅 **Pengaturan Tanggal & Due Date (Backdate)** | Sesuaikan tanggal faktur dan batas jatuh tempo tanpa mengganggu siklus cron perpanjangan otomatis. |
| 🛡️ **Zero-Deduction Recovery Engine** | Otomatis membersihkan *Credit Note* (`tblbillingnotes`) & penyesuaian ledger (`tblaccounts`) saat mengembalikan status ke Unpaid agar tidak muncul saldo minus. |
| 🧹 **Manajemen Ledger & Transaksi Terkunci** | Tinjau dan hapus transaksi penyesuaian kredit bawaan WHMCS yang terkunci hanya dengan satu klik. |
| 🔍 **Jejak Audit Visual (Before vs After Diff)** | Setiap koreksi dicatat secara permanen dalam format snapshot JSON lengkap sehingga histori keuangan tetap transparan dan terlacak. |
| 🔒 **Wajib Justifikasi / Alasan Koreksi** | Mencegah perubahan sepihak dengan mewajibkan admin mengisi alasan revisi faktur untuk kebutuhan audit internal. |
| ⚡ **Shortcut Button di Admin WHMCS** | Tombol navigasi **"Edit Invoice (HB)"** terintegrasi langsung di layar bawaan `invoices.php?action=edit`. |

---

## 🔄 Alur Kerja & Perbandingan Solusi

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
│ 1. Koreksi Langsung di TKP  │             │   2. Bersihkan Ledger & Saldo   │
├─────────────────────────────┤             ├─────────────────────────────────┤
│ • Revisi Harga / Deskripsi  │             │ • Deteksi Credit Note Tertinggal│
│ • Tambah / Hapus Baris Item │             │ • Hapus Transaksi Penyesuaian   │
│ • Ubah Tanggal / Due Date   │             │ • Pulihkan Balance Menjadi 100% │
│ • Status: Unpaid/Paid/Draft │             │ • Hapus Potongan Minus Klien    │
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

### Perbandingan Masalah WHMCS v9 vs Solusi Modul HB Invoice Edit

```
[ Masalah Standar WHMCS v9 ]
Ada salah input harga / deskripsi pada Invoice #100
   ├──> ❌ WHMCS v9 melarang edit faktur yang sudah terbit.
   ├──> Admin terpaksa mengubah status menjadi 'Cancelled' (Terbit Credit Note otomatis).
   ├──> Admin terpaksa membuat Invoice Baru #101 (Nomor faktur ganda, klien bingung).
   └──> Jika Invoice #100 coba dibuka lagi ke 'Unpaid', saldonya menjadi Rp 0 dan 
        muncul potongan minus "-Rp 100.000 Partial Payment" di halaman Mass Payment.

[ Solusi Praktis HB Invoice Edit ]
Invoice #100 langsung dibuka melalui modul HB Invoice Edit
   ├──> ✅ Admin langsung merevisi harga/item pada Invoice #100 tanpa perlu membuat invoice baru.
   ├──> ✅ Opsi [✓] "Otomatis Bersihkan Credit Note" aktif saat status diset ke Unpaid.
   ├──> ✅ Modul membersihkan record penyesuaian di tblaccounts & tblbillingnotes.
   └──> 🎯 HASIL: Faktur tetap menggunakan nomor asli #100, nominal tagihan utuh,
        dan halaman Mass Payment klien 100% bersih tanpa potongan minus.
```

---

## 🗄️ Dampak Database & Tabel yang Dimodifikasi

Modul ini beroperasi secara aman menggunakan `WHMCS\Database\Capsule` (Laravel Database Query Builder) tanpa merusak integritas database WHMCS.

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
