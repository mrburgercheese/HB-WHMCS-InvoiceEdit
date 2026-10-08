<?php
/**
 * WHMCS Addon Module: HB Invoice Edit
 * Koreksi invoice dengan riwayat audit log lengkap & manajemen ledger / transaksi
 * 
 * @author HB Dev Team
 * @version 1.2.0
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * Modul Konfigurasi
 */
function hb_invoice_editor_config(): array
{
    return [
        'name' => 'HB Invoice Edit',
        'description' => 'Koreksi invoice langsung di tempat (deskripsi, harga, status, tanggal backdate/due date, item) serta kelola & bersihkan transaksi/credit note adjustment dengan audit log detail.',
        'version' => '1.2.0',
        'author' => 'HB Dev Team',
        'fields' => [
            'allow_date_edit' => [
                'FriendlyName' => 'Izinkan Ubah Tanggal Terbit (Backdate)',
                'Type' => 'yesno',
                'Default' => 'on',
            ],
            'require_reason' => [
                'FriendlyName' => 'Wajibkan Alasan Koreksi',
                'Type' => 'yesno',
                'Default' => 'on',
            ],
        ]
    ];
}

/**
 * Aktivasi Modul & Inisialisasi Skema Database
 */
function hb_invoice_editor_activate(): array
{
    try {
        if (!Capsule::schema()->hasTable('mod_hb_invoice_corrections_log')) {
            Capsule::schema()->create('mod_hb_invoice_corrections_log', function ($table) {
                $table->increments('id');
                $table->integer('invoice_id')->index();
                $table->integer('client_id');
                $table->string('client_name', 150);
                $table->string('admin_user', 50)->index();
                $table->decimal('old_total', 10, 2);
                $table->decimal('new_total', 10, 2);
                $table->text('reason');
                $table->longText('old_snapshot');
                $table->longText('new_snapshot');
                $table->string('ip_address', 50)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
        return ['status' => 'success', 'description' => 'Modul HB Invoice Edit v1.2.0 berhasil diaktifkan.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Gagal inisialisasi tabel: ' . $e->getMessage()];
    }
}

/**
 * Deaktivasi Modul
 */
function hb_invoice_editor_deactivate(): array
{
    return ['status' => 'success', 'description' => 'Modul HB Invoice Edit dinonaktifkan.'];
}

/**
 * Handler Utama Tampilan & Aksi Admin
 */
function hb_invoice_editor_output(array $vars): void
{
    $modulelink = $vars['modulelink'];
    $adminUser = !empty($_SESSION['adminid'])
        ? (Capsule::table('tbladmins')->where('id', $_SESSION['adminid'])->value('username') ?: 'Admin')
        : 'Admin';

    $alertMessage = '';

    // 1. Action: Hapus Single Transaksi / Penyesuaian
    if (isset($_POST['action']) && $_POST['action'] === 'delete_single_transaction') {
        if (function_exists('check_token')) {
            check_token('WHMCS.admin.default');
        }

        $transId = (int) ($_POST['trans_id'] ?? 0);
        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);

        if ($transId > 0 && $invoiceId > 0) {
            try {
                Capsule::transaction(function () use ($transId, $invoiceId, $adminUser, &$alertMessage) {
                    $trans = Capsule::table('tblaccounts')->where('id', $transId)->where('invoiceid', $invoiceId)->first();
                    if (!$trans) {
                        $alertMessage = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Transaksi ID #' . $transId . ' tidak ditemukan pada Invoice #' . $invoiceId . '!</div>';
                        return;
                    }

                    // Bersihkan relasi billing note / credit note jika ada
                    if (!empty($trans->billingnoteid) && (int)$trans->billingnoteid > 0) {
                        Capsule::table('tblbillingnoteitems')->where('billingnote_id', $trans->billingnoteid)->delete();
                        Capsule::table('tblbillingnotes')->where('id', $trans->billingnoteid)->delete();
                    }

                    Capsule::table('tblaccounts')->where('id', $transId)->delete();

                    logActivity('[HB Invoice Edit] Admin (' . $adminUser . ') menghapus transaksi/ledger adjustment ID #' . $transId . ' (Nominal: Rp ' . number_format((float)$trans->amountin, 0, ',', '.') . ') pada Invoice #' . $invoiceId);

                    $alertMessage = '<div class="alert alert-success" style="padding: 15px 20px; border-radius: 8px; font-size: 14px;">' .
                        '<i class="fas fa-check-circle"></i> Transaksi/Ledger Adjustment ID <strong>#' . $transId . '</strong> berhasil dihapus. Saldo/Balance Invoice #' . $invoiceId . ' telah diperbarui!' .
                        '</div>';
                });
            } catch (\Throwable $e) {
                $alertMessage = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Terjadi kesalahan: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // 2. Action: Bersihkan Seluruh Transaksi & Reset Balance Jadi Utuh
    if (isset($_POST['action']) && $_POST['action'] === 'clear_all_transactions') {
        if (function_exists('check_token')) {
            check_token('WHMCS.admin.default');
        }

        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);

        if ($invoiceId > 0) {
            try {
                Capsule::transaction(function () use ($invoiceId, $adminUser, &$alertMessage) {
                    $allTrans = Capsule::table('tblaccounts')->where('invoiceid', $invoiceId)->get();
                    $count = count($allTrans);

                    foreach ($allTrans as $t) {
                        if (!empty($t->billingnoteid) && (int)$t->billingnoteid > 0) {
                            Capsule::table('tblbillingnoteitems')->where('billingnote_id', $t->billingnoteid)->delete();
                            Capsule::table('tblbillingnotes')->where('id', $t->billingnoteid)->delete();
                        }
                    }

                    Capsule::table('tblaccounts')->where('invoiceid', $invoiceId)->delete();

                    logActivity('[HB Invoice Edit] Admin (' . $adminUser . ') membersihkan seluruh transaksi (' . $count . ' data) pada Invoice #' . $invoiceId . ' sehingga sisa tagihan kembali utuh 100%.');

                    $alertMessage = '<div class="alert alert-success" style="padding: 15px 20px; border-radius: 8px; font-size: 14px;">' .
                        '<i class="fas fa-check-circle"></i> Seluruh transaksi/ledger adjustment (' . $count . ' record) pada Invoice <strong>#' . $invoiceId . '</strong> berhasil dibersihkan. Sisa tagihan kembali utuh 100%!' .
                        '</div>';
                });
            } catch (\Throwable $e) {
                $alertMessage = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Terjadi kesalahan: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // 3. Action: Simpan Koreksi Invoice
    if (isset($_POST['action']) && $_POST['action'] === 'save_correction') {
        if (function_exists('check_token')) {
            check_token('WHMCS.admin.default');
        }

        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
        $reason = trim((string)($_POST['correction_reason'] ?? ''));
        $autoClearAdjustments = !empty($_POST['auto_clear_adjustments']);

        if (empty($reason)) {
            $alertMessage = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Gagal: <strong>Alasan Koreksi wajib diisi</strong> agar jejak audit keuangan tercatat!</div>';
        } else {
            $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
            if (!$invoice) {
                $alertMessage = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Invoice #' . $invoiceId . ' tidak ditemukan!</div>';
            } else {
                try {
                    Capsule::transaction(function () use ($invoice, $invoiceId, $reason, $autoClearAdjustments, $adminUser, $modulelink, &$alertMessage) {
                        $oldItems = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->get();
                        $oldSnapshot = [
                            'invoice' => (array) $invoice,
                            'items' => $oldItems->toArray()
                        ];

                        $newDate = !empty($_POST['invoice_date']) ? (string)$_POST['invoice_date'] : $invoice->date;
                        $newDueDate = !empty($_POST['due_date']) ? (string)$_POST['due_date'] : $invoice->duedate;
                        $newStatus = !empty($_POST['invoice_status']) ? (string)$_POST['invoice_status'] : $invoice->status;
                        $newPaymentMethod = !empty($_POST['payment_method']) ? (string)$_POST['payment_method'] : $invoice->paymentmethod;
                        $newTaxRate = (float) ($_POST['tax_rate'] ?? $invoice->taxrate);

                        // Process Items
                        $itemDescs = (array)($_POST['item_desc'] ?? []);
                        $itemAmounts = (array)($_POST['item_amount'] ?? []);
                        $itemTaxeds = (array)($_POST['item_taxed'] ?? []);
                        $itemTypes = (array)($_POST['item_type'] ?? []);
                        $itemRelids = (array)($_POST['item_relid'] ?? []);

                        $subtotal = 0.0;
                        $taxTotal = 0.0;
                        $newItemsData = [];

                        // Re-insert line items
                        Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->delete();

                        for ($i = 0; $i < count($itemDescs); $i++) {
                            $desc = trim((string)($itemDescs[$i] ?? ''));
                            $rawAmt = str_replace(',', '', (string)($itemAmounts[$i] ?? '0'));
                            $amt = (float) $rawAmt;
                            $isTaxed = !empty($itemTaxeds[$i]) ? 1 : 0;
                            $type = (string)($itemTypes[$i] ?? 'Item');
                            $relid = (int) ($itemRelids[$i] ?? 0);

                            if ($desc !== '') {
                                $subtotal += $amt;
                                if ($isTaxed && $newTaxRate > 0) {
                                    $taxTotal += round(($amt * $newTaxRate) / 100, 2);
                                }

                                Capsule::table('tblinvoiceitems')->insert([
                                    'invoiceid' => $invoiceId,
                                    'userid' => $invoice->userid,
                                    'type' => $type,
                                    'relid' => $relid,
                                    'description' => $desc,
                                    'amount' => $amt,
                                    'taxed' => $isTaxed,
                                    'duedate' => $newDueDate,
                                    'paymentmethod' => $newPaymentMethod
                                ]);

                                $newItemsData[] = [
                                    'description' => $desc,
                                    'amount' => $amt,
                                    'taxed' => $isTaxed,
                                    'type' => $type,
                                    'relid' => $relid
                                ];
                            }
                        }

                        $total = $subtotal + $taxTotal;

                        // Pembersihan penyesuaian jika status Unpaid dan opsi aktif
                        if ($newStatus === 'Unpaid' && $autoClearAdjustments) {
                            $adjTrans = Capsule::table('tblaccounts')
                                ->where('invoiceid', $invoiceId)
                                ->where(function ($q) {
                                    $q->where('type', 'invoice_billing_adjustment_credit')
                                        ->orWhere('billingnoteid', '>', 0)
                                        ->orWhere('description', 'LIKE', '%Credit Note%');
                                })
                                ->get();

                            foreach ($adjTrans as $at) {
                                if (!empty($at->billingnoteid) && (int)$at->billingnoteid > 0) {
                                    Capsule::table('tblbillingnoteitems')->where('billingnote_id', $at->billingnoteid)->delete();
                                    Capsule::table('tblbillingnotes')->where('id', $at->billingnoteid)->delete();
                                }
                                Capsule::table('tblaccounts')->where('id', $at->id)->delete();
                            }
                        }

                        // Update tabel utama tblinvoices
                        Capsule::table('tblinvoices')->where('id', $invoiceId)->update([
                            'date' => $newDate,
                            'duedate' => $newDueDate,
                            'status' => $newStatus,
                            'paymentmethod' => $newPaymentMethod,
                            'subtotal' => $subtotal,
                            'tax' => $taxTotal,
                            'taxrate' => $newTaxRate,
                            'total' => $total,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                        // Profil Klien
                        $client = Capsule::table('tblclients')->where('id', $invoice->userid)->first();
                        $clientName = $client ? ($client->firstname . ' ' . $client->lastname) : 'Client #' . $invoice->userid;

                        $newSnapshot = [
                            'invoice' => [
                                'id' => $invoiceId,
                                'date' => $newDate,
                                'duedate' => $newDueDate,
                                'status' => $newStatus,
                                'paymentmethod' => $newPaymentMethod,
                                'subtotal' => $subtotal,
                                'tax' => $taxTotal,
                                'total' => $total
                            ],
                            'items' => $newItemsData
                        ];

                        // Log ke mod_hb_invoice_corrections_log
                        Capsule::table('mod_hb_invoice_corrections_log')->insert([
                            'invoice_id' => $invoiceId,
                            'client_id' => $invoice->userid,
                            'client_name' => $clientName,
                            'admin_user' => $adminUser,
                            'old_total' => $invoice->total,
                            'new_total' => $total,
                            'reason' => $reason,
                            'old_snapshot' => json_encode($oldSnapshot),
                            'new_snapshot' => json_encode($newSnapshot),
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);

                        logActivity('[HB Invoice Edit] Admin (' . $adminUser . ') mengoreksi Invoice #' . $invoiceId . ' (Klien: ' . $clientName . ', Total: Rp ' . number_format((float)$invoice->total, 0, ',', '.') . ' -> Rp ' . number_format($total, 0, ',', '.') . '). Alasan: ' . $reason);

                        $alertMessage = '<div class="alert alert-success" style="padding: 18px 22px; font-size: 14px; border-radius: 8px; background-color: #15803d; color: #ffffff; border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">' .
                            '<div style="font-size: 15px; font-weight: 600; margin-bottom: 12px;"><i class="fas fa-check-circle" style="font-size: 18px; margin-right: 8px;"></i> Berhasil! <strong>Invoice #' . $invoiceId . '</strong> telah diperbarui dengan total: <strong>Rp ' . number_format($total, 0, ',', '.') . '</strong>.</div>' .
                            '<div>' .
                            '<a href="' . $modulelink . '" class="btn btn-sm" style="background: #ffffff !important; color: #15803d !important; font-weight: 700 !important; border: 1px solid #ffffff !important; margin-right: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard Modul</a>' .
                            '<a href="invoices.php?action=edit&id=' . $invoiceId . '" target="_blank" class="btn btn-sm" style="background: rgba(255,255,255,0.2) !important; color: #ffffff !important; font-weight: 600 !important; border: 1px solid rgba(255,255,255,0.6) !important;"><i class="fas fa-external-link-alt"></i> Buka Faktur di WHMCS</a>' .
                            '</div>' .
                            '</div>';
                    });
                } catch (\Throwable $e) {
                    $alertMessage = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Gagal menyimpan: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }

    // Inisialisasi Data Invoice yang Dipilih
    $searchId = (int) ($_GET['inv_id'] ?? $_POST['search_inv_id'] ?? 0);
    $selectedInvoice = null;
    $selectedItems = [];
    $selectedTransactions = [];
    $client = null;

    if ($searchId > 0) {
        $selectedInvoice = Capsule::table('tblinvoices')->where('id', $searchId)->first();
        if ($selectedInvoice) {
            $selectedItems = Capsule::table('tblinvoiceitems')->where('invoiceid', $searchId)->get();
            $selectedTransactions = Capsule::table('tblaccounts')->where('invoiceid', $searchId)->orderBy('id', 'asc')->get();
            $client = Capsule::table('tblclients')->where('id', $selectedInvoice->userid)->first();
        } else {
            $alertMessage = '<div class="alert alert-warning"><i class="fas fa-search"></i> Invoice #' . $searchId . ' tidak ditemukan di database. <a href="' . $modulelink . '" class="btn btn-xs btn-default" style="margin-left: 10px;">Kembali</a></div>';
        }
    }

    // Daftar Payment Gateway
    $gateways = Capsule::table('tblpaymentgateways')->where('setting', 'name')->pluck('value', 'gateway');

    // Riwayat Audit Log Terbaru
    $logs = Capsule::table('mod_hb_invoice_corrections_log')->orderBy('id', 'desc')->limit(30)->get();

    // Render Tampilan UI
    echo '<div style="margin-top: 15px;">';
    echo $alertMessage;

    // Panel Utama
    echo '<div class="panel panel-default" style="border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">';
    echo '  <div class="panel-heading" style="background: #1e293b; color: #fff; padding: 15px 20px; border-top-left-radius: 8px; border-top-right-radius: 8px; display: flex; justify-content: space-between; align-items: center;">';
    echo '    <h3 class="panel-title" style="font-weight: 600; font-size: 16px;"><i class="fas fa-edit" style="margin-right: 8px;"></i> HB WHMCS Invoice Editor & Ledger Manager (v1.2.0)</h3>';
    if ($selectedInvoice) {
        echo '    <a href="' . $modulelink . '" class="btn btn-sm btn-default" style="background: #ffffff !important; color: #0f172a !important; font-weight: 700 !important; border: 1px solid #ffffff !important; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard & Riwayat</a>';
    }
    echo '  </div>';
    echo '  <div class="panel-body" style="padding: 25px;">';

    // Kotak Pencarian Invoice
    echo '    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 25px;">';
    echo '      <form method="get" action="' . $modulelink . '" class="form-inline" style="display: flex; gap: 10px; align-items: center;">';
    echo '        <input type="hidden" name="module" value="hb_invoice_editor">';
    echo '        <label style="font-weight: 600; font-size: 14px;"><i class="fas fa-search"></i> Cari ID Invoice:</label>';
    echo '        <input type="number" name="inv_id" class="form-control" placeholder="Contoh: 13887" value="' . ($searchId ?: '') . '" style="width: 200px; font-weight: bold;" required>';
    echo '        <button type="submit" class="btn btn-primary" style="font-weight: 600;"><i class="fas fa-arrow-right"></i> Buka Invoice</button>';
    if ($selectedInvoice) {
        echo '        <a href="' . $modulelink . '" class="btn btn-default" style="margin-left: 10px;"><i class="fas fa-times"></i> Reset</a>';
    }
    echo '      </form>';
    echo '    </div>';

    // Form Editor jika Invoice Terpilih
    if ($selectedInvoice) {
        $clientName = $client ? ($client->firstname . ' ' . $client->lastname) : 'Klien #' . $selectedInvoice->userid;

        // Hitung Saldo Real
        $totalPaid = 0.0;
        foreach ($selectedTransactions as $tx) {
            $totalPaid += ((float)$tx->amountin - (float)$tx->amountout);
        }
        $realBalance = max(0.0, (float)$selectedInvoice->total - ((float)$selectedInvoice->credit + $totalPaid));

        // Panel Status Saldo & Manajemen Ledger
        echo '    <div class="panel panel-default" style="border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 25px; overflow: hidden;">';
        echo '      <div class="panel-heading" style="background: #0f172a; color: #fff; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">';
        echo '        <span><i class="fas fa-money-bill-wave"></i> Status Saldo &amp; Manajemen Ledger / Transaksi Invoice #' . $selectedInvoice->id . '</span>';
        echo '        <div>';
        echo '          <span class="badge" style="background: #334155; font-size: 13px; margin-right: 5px;">Total Faktur: Rp ' . number_format((float)$selectedInvoice->total, 0, ',', '.') . '</span>';
        echo '          <span class="badge" style="background: ' . ($realBalance > 0 ? '#b91c1c' : '#15803d') . '; font-size: 13px;">Sisa Balance: Rp ' . number_format($realBalance, 0, ',', '.') . '</span>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div class="panel-body" style="background: #f8fafc;">';

        if (count($selectedTransactions) > 0) {
            echo '        <div class="alert alert-warning" style="margin-bottom: 15px; font-size: 13px;">';
            echo '          <i class="fas fa-info-circle"></i> <strong>Catatan Ledger:</strong> Invoice ini memiliki <strong>' . count($selectedTransactions) . ' riwayat transaksi/penyesuaian kredit</strong> dengan total <strong>Rp ' . number_format($totalPaid, 0, ',', '.') . '</strong>. Untuk mengembalikan tagihan menjadi utuh tanpa potongan kredit, klik tombol <em>"Bersihkan Semua Transaksi"</em> di bawah.';
            echo '        </div>';

            echo '        <table class="table table-bordered table-striped" style="background: #fff; margin-bottom: 15px;">';
            echo '          <thead><tr style="background: #f1f5f9;"><th>ID Trans</th><th>Waktu</th><th>Tipe</th><th>Deskripsi</th><th>Gateway</th><th>Nominal (Masuk)</th><th style="text-align: center;">Aksi</th></tr></thead>';
            echo '          <tbody>';
            foreach ($selectedTransactions as $tx) {
                echo '            <tr>';
                echo '              <td><strong>#' . $tx->id . '</strong></td>';
                echo '              <td>' . htmlspecialchars($tx->date) . '</td>';
                echo '              <td><span class="label label-default">' . htmlspecialchars($tx->type ?: 'Payment') . '</span></td>';
                echo '              <td>' . htmlspecialchars($tx->description) . (!empty($tx->billingnoteid) ? ' <span class="badge" style="background: #d97706;">Credit Note #' . $tx->billingnoteid . '</span>' : '') . '</td>';
                echo '              <td>' . htmlspecialchars($tx->gateway ?: '—') . '</td>';
                echo '              <td><strong class="text-success">Rp ' . number_format((float)$tx->amountin, 0, ',', '.') . '</strong></td>';
                echo '              <td style="width: 140px; text-align: center;">';
                echo '                <form method="post" action="' . $modulelink . '&inv_id=' . $selectedInvoice->id . '" style="display: inline;" onsubmit="return confirm(\'Apakah Anda yakin ingin menghapus transaksi #' . $tx->id . ' ini? Sisa balance invoice akan otomatis bertambah kembali.\');">';
                echo '                  ' . generate_token('form');
                echo '                  <input type="hidden" name="action" value="delete_single_transaction">';
                echo '                  <input type="hidden" name="invoice_id" value="' . $selectedInvoice->id . '">';
                echo '                  <input type="hidden" name="trans_id" value="' . $tx->id . '">';
                echo '                  <button type="submit" class="btn btn-danger btn-xs" style="font-weight: 600;"><i class="fas fa-trash"></i> Hapus</button>';
                echo '                </form>';
                echo '              </td>';
                echo '            </tr>';
            }
            echo '          </tbody>';
            echo '        </table>';

            echo '        <div style="display: flex; justify-content: flex-end;">';
            echo '          <form method="post" action="' . $modulelink . '&inv_id=' . $selectedInvoice->id . '" onsubmit="return confirm(\'PERINGATAN: Seluruh riwayat pembayaran & penyesuaian credit note pada Invoice ini akan dihapus permanen, dan sisa tagihan (balance) akan kembali menjadi Rp ' . number_format((float)$selectedInvoice->total, 0, ',', '.') . ' penuh. Lanjutkan?\');">';
            echo '            ' . generate_token('form');
            echo '            <input type="hidden" name="action" value="clear_all_transactions">';
            echo '            <input type="hidden" name="invoice_id" value="' . $selectedInvoice->id . '">';
            echo '            <button type="submit" class="btn btn-danger btn-sm" style="font-weight: 700;"><i class="fas fa-eraser"></i> Bersihkan Semua Transaksi &amp; Reset Balance Jadi Tagihan Penuh</button>';
            echo '          </form>';
            echo '        </div>';
        } else {
            echo '        <div style="color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; padding: 12px 18px; border-radius: 6px; font-size: 13px;">';
            echo '          <i class="fas fa-check-circle"></i> <strong>Bersih:</strong> Tidak ada transaksi atau Credit Note adjustment pada invoice ini. Tagihan murni 100% utuh.';
            echo '        </div>';
        }

        echo '      </div>';
        echo '    </div>';

        // Formulir Editor Invoice
        echo '    <form method="post" action="' . $modulelink . '&inv_id=' . $selectedInvoice->id . '" id="formCorrection">';
        echo '      ' . generate_token('form');
        echo '      <input type="hidden" name="action" value="save_correction">';
        echo '      <input type="hidden" name="invoice_id" value="' . $selectedInvoice->id . '">';

        echo '      <div class="panel panel-info" style="border-radius: 8px;">';
        echo '        <div class="panel-heading" style="font-weight: 600; display: flex; justify-content: space-between; align-items: center;">';
        echo '          <span><i class="fas fa-edit"></i> Formulir Edit Data Invoice #' . $selectedInvoice->id . ' — Klien: ' . htmlspecialchars($clientName) . '</span>';
        echo '          <a href="' . $modulelink . '" class="btn btn-xs btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>';
        echo '        </div>';
        echo '        <div class="panel-body">';

        echo '          <div class="row" style="margin-bottom: 15px;">';
        echo '            <div class="col-md-3">';
        echo '              <label>Tanggal Faktur (Invoice Date):</label>';
        echo '              <input type="date" name="invoice_date" class="form-control" value="' . $selectedInvoice->date . '" required>';
        echo '            </div>';
        echo '            <div class="col-md-3">';
        echo '              <label>Jatuh Tempo (Due Date):</label>';
        echo '              <input type="date" name="due_date" class="form-control" value="' . $selectedInvoice->duedate . '" required>';
        echo '            </div>';
        echo '            <div class="col-md-3">';
        echo '              <label>Status Invoice:</label>';
        echo '              <select name="invoice_status" class="form-control" id="selectInvoiceStatus">';
        foreach (['Unpaid', 'Paid', 'Cancelled', 'Draft', 'Payment Pending', 'Refunded'] as $st) {
            echo '                <option value="' . $st . '"' . ($selectedInvoice->status === $st ? ' selected' : '') . '>' . $st . '</option>';
        }
        echo '              </select>';
        echo '            </div>';
        echo '            <div class="col-md-3">';
        echo '              <label>Metode Pembayaran:</label>';
        echo '              <select name="payment_method" class="form-control">';
        foreach ($gateways as $gwKey => $gwName) {
            echo '                <option value="' . $gwKey . '"' . ($selectedInvoice->paymentmethod === $gwKey ? ' selected' : '') . '>' . htmlspecialchars($gwName) . '</option>';
        }
        echo '              </select>';
        echo '            </div>';
        echo '          </div>';

        // Checkbox Auto-Clean
        echo '          <div style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px;">';
        echo '            <label style="margin: 0; font-weight: 600; cursor: pointer; color: #1e293b; display: flex; align-items: center; gap: 8px;">';
        echo '              <input type="checkbox" name="auto_clear_adjustments" value="1" checked style="margin: 0;">';
        echo '              <span><i class="fas fa-shield-alt text-primary"></i> <strong>Otomatis Bersihkan Credit Note / Penyesuaian saat ubah status ke Unpaid</strong> (Mencegah saldo jadi Rp 0 atau muncul potongan minus di Mass Payment)</span>';
        echo '            </label>';
        echo '          </div>';

        // Tabel Rincian Item
        echo '          <h4 style="margin-top: 25px; font-weight: 600;"><i class="fas fa-list"></i> Rincian Item Tagihan</h4>';
        echo '          <table class="table table-bordered table-striped" id="tableItems">';
        echo '            <thead><tr style="background: #f1f5f9;"><th style="width: 60%;">Deskripsi Item</th><th style="width: 25%;">Nominal (Rp)</th><th style="width: 10%; text-align: center;">Pajak</th><th style="width: 5%; text-align: center;">Hapus</th></tr></thead>';
        echo '            <tbody id="itemsBody">';

        foreach ($selectedItems as $item) {
            echo '              <tr>';
            echo '                <td>';
            echo '                  <input type="hidden" name="item_type[]" value="' . htmlspecialchars($item->type) . '">';
            echo '                  <input type="hidden" name="item_relid[]" value="' . $item->relid . '">';
            echo '                  <textarea name="item_desc[]" class="form-control" rows="2" required>' . htmlspecialchars($item->description) . '</textarea>';
            echo '                </td>';
            echo '                <td><input type="number" step="0.01" name="item_amount[]" class="form-control item-amount-input" value="' . $item->amount . '" required></td>';
            echo '                <td style="text-align: center; vertical-align: middle;"><input type="checkbox" name="item_taxed[]" value="1"' . ($item->taxed ? ' checked' : '') . '></td>';
            echo '                <td style="text-align: center; vertical-align: middle;"><button type="button" class="btn btn-danger btn-xs btn-delete-row"><i class="fas fa-trash"></i></button></td>';
            echo '              </tr>';
        }

        echo '            </tbody>';
        echo '          </table>';
        echo '          <button type="button" class="btn btn-default btn-sm" id="btnAddRow" style="margin-bottom: 20px;"><i class="fas fa-plus"></i> Tambah Baris Item</button>';

        // Field Alasan Koreksi Wajib
        echo '          <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 20px; margin-top: 15px;">';
        echo '            <label style="font-weight: 700; color: #92400e; font-size: 14px;"><i class="fas fa-clipboard-check"></i> Alasan Koreksi Tagihan (Wajib Diisi):</label>';
        echo '            <textarea name="correction_reason" class="form-control" rows="2" placeholder="Contoh: Koreksi harga langsung tanpa membuat invoice baru / negosiasi khusus" required></textarea>';
        echo '          </div>';

        echo '          <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">';
        echo '            <a href="' . $modulelink . '" class="btn btn-default" style="font-weight: 600;"><i class="fas fa-arrow-left"></i> Batal / Kembali ke Dashboard</a>';
        echo '            <button type="submit" class="btn btn-success btn-lg" style="font-weight: 600;"><i class="fas fa-save"></i> Simpan Perubahan Invoice #' . $selectedInvoice->id . '</button>';
        echo '          </div>';

        echo '        </div>';
        echo '      </div>';
        echo '    </form>';

        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tableItems = document.getElementById('tableItems');
            if (tableItems) {
                tableItems.addEventListener('click', function(e) {
                    if (e.target.closest('.btn-delete-row')) {
                        e.target.closest('tr').remove();
                    }
                });
            }
            var btnAdd = document.getElementById('btnAddRow');
            if (btnAdd) {
                btnAdd.addEventListener('click', function() {
                    var tbody = document.getElementById('itemsBody');
                    if (tbody) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td><input type="hidden" name="item_type[]" value="Item"><input type="hidden" name="item_relid[]" value="0"><textarea name="item_desc[]" class="form-control" rows="2" placeholder="Deskripsi item baru..." required></textarea></td>' +
                            '<td><input type="number" step="0.01" name="item_amount[]" class="form-control item-amount-input" value="0.00" required></td>' +
                            '<td style="text-align: center; vertical-align: middle;"><input type="checkbox" name="item_taxed[]" value="1"></td>' +
                            '<td style="text-align: center; vertical-align: middle;"><button type="button" class="btn btn-danger btn-xs btn-delete-row"><i class="fas fa-trash"></i></button></td>';
                        tbody.appendChild(tr);
                    }
                });
            }
        });
        </script>
        <?php
    }

    // Tabel Riwayat Audit Log
    echo '    <h4 style="margin-top: 35px; font-weight: 600;"><i class="fas fa-history"></i> Riwayat Audit Koreksi Invoice</h4>';
    echo '    <hr style="margin: 10px 0 15px 0;">';
    echo '    <table class="table table-hover table-striped table-bordered">';
    echo '      <thead><tr style="background: #f8f9fa;"><th>Waktu</th><th>ID Invoice</th><th>Klien</th><th>Admin</th><th>Nominal Lama</th><th>Nominal Baru</th><th>Alasan</th><th style="text-align: center; width: 120px;">Rincian Detail</th></tr></thead>';
    echo '      <tbody>';

    if ($logs->isEmpty()) {
        echo '        <tr><td colspan="8" class="text-center text-muted" style="padding: 25px;">Belum ada riwayat koreksi invoice yang dicatat.</td></tr>';
    } else {
        foreach ($logs as $log) {
            $modalId = 'modalLog_' . $log->id;
            $oldData = json_decode((string)$log->old_snapshot, true) ?: [];
            $newData = json_decode((string)$log->new_snapshot, true) ?: [];

            echo '        <tr>';
            echo '          <td>' . htmlspecialchars($log->created_at) . '</td>';
            echo '          <td><a href="invoices.php?action=edit&id=' . $log->invoice_id . '" target="_blank" style="font-weight: 600;">#' . $log->invoice_id . '</a></td>';
            echo '          <td>' . htmlspecialchars($log->client_name) . '</td>';
            echo '          <td><span class="label label-primary">' . htmlspecialchars($log->admin_user) . '</span></td>';
            echo '          <td>Rp ' . number_format((float)$log->old_total, 0, ',', '.') . '</td>';
            echo '          <td><strong style="color: #16a34a;">Rp ' . number_format((float)$log->new_total, 0, ',', '.') . '</strong></td>';
            echo '          <td><em style="color: #475569;">' . htmlspecialchars($log->reason) . '</em></td>';
            echo '          <td style="text-align: center;"><button type="button" class="btn btn-info btn-xs" data-toggle="modal" data-target="#' . $modalId . '" style="font-weight: 600;"><i class="fas fa-eye"></i> Lihat Detail</button></td>';
            echo '        </tr>';

            // Modal Detail Diff Perubahan
            echo '        <div class="modal fade" id="' . $modalId . '" tabindex="-1" role="dialog">';
            echo '          <div class="modal-dialog modal-lg" role="document">';
            echo '            <div class="modal-content" style="border-radius: 8px;">';
            echo '              <div class="modal-header" style="background: #1e293b; color: #fff; border-top-left-radius: 8px; border-top-right-radius: 8px;">';
            echo '                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.8;">&times;</button>';
            echo '                <h4 class="modal-title" style="font-weight: 600;"><i class="fas fa-history"></i> Detail Perubahan Invoice #' . $log->invoice_id . ' (' . htmlspecialchars($log->created_at) . ')</h4>';
            echo '              </div>';
            echo '              <div class="modal-body" style="padding: 20px;">';
            echo '                <p><strong>Admin:</strong> ' . htmlspecialchars($log->admin_user) . ' | <strong>Klien:</strong> ' . htmlspecialchars($log->client_name) . '</p>';
            echo '                <p><strong>Alasan Koreksi:</strong> <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 13px; font-weight: 600; padding: 5px 10px;">' . htmlspecialchars($log->reason) . '</span></p>';
            echo '                <hr style="margin: 15px 0;">';

            echo '                <div class="row">';
            // Kolom Sebelum
            echo '                  <div class="col-md-6">';
            echo '                    <div class="panel panel-danger">';
            echo '                      <div class="panel-heading" style="font-weight: 600;"><i class="fas fa-history"></i> KONDISI SEBELUM (OLD)</div>';
            echo '                      <div class="panel-body" style="font-size: 13px;">';
            if (!empty($oldData['invoice'])) {
                echo '                        <p><strong>Tgl Faktur:</strong> ' . ($oldData['invoice']['date'] ?? '-') . '<br>';
                echo '                        <strong>Jatuh Tempo:</strong> ' . ($oldData['invoice']['duedate'] ?? '-') . '<br>';
                echo '                        <strong>Status:</strong> ' . ($oldData['invoice']['status'] ?? '-') . '<br>';
                echo '                        <strong>Total:</strong> Rp ' . number_format((float)($oldData['invoice']['total'] ?? 0), 0, ',', '.') . '</p>';
            }
            echo '                        <h5><strong>Rincian Item Lama:</strong></h5>';
            echo '                        <ul>';
            if (!empty($oldData['items'])) {
                foreach ($oldData['items'] as $oi) {
                    echo '                          <li>' . htmlspecialchars($oi['description'] ?? '') . ' — <strong>Rp ' . number_format((float)($oi['amount'] ?? 0), 0, ',', '.') . '</strong></li>';
                }
            } else {
                echo '                          <li class="text-muted">Tidak ada data item</li>';
            }
            echo '                        </ul>';
            echo '                      </div>';
            echo '                    </div>';
            echo '                  </div>';

            // Kolom Sesudah
            echo '                  <div class="col-md-6">';
            echo '                    <div class="panel panel-success">';
            echo '                      <div class="panel-heading" style="font-weight: 600;"><i class="fas fa-check-circle"></i> KONDISI SESUDAH (NEW)</div>';
            echo '                      <div class="panel-body" style="font-size: 13px;">';
            if (!empty($newData['invoice'])) {
                echo '                        <p><strong>Tgl Faktur:</strong> ' . ($newData['invoice']['date'] ?? '-') . '<br>';
                echo '                        <strong>Jatuh Tempo:</strong> ' . ($newData['invoice']['duedate'] ?? '-') . '<br>';
                echo '                        <strong>Status:</strong> ' . ($newData['invoice']['status'] ?? '-') . '<br>';
                echo '                        <strong>Total:</strong> <span class="text-success" style="font-weight: bold;">Rp ' . number_format((float)($newData['invoice']['total'] ?? 0), 0, ',', '.') . '</span></p>';
            }
            echo '                        <h5><strong>Rincian Item Baru:</strong></h5>';
            echo '                        <ul>';
            if (!empty($newData['items'])) {
                foreach ($newData['items'] as $ni) {
                    echo '                          <li>' . htmlspecialchars($ni['description'] ?? '') . ' — <strong class="text-success">Rp ' . number_format((float)($ni['amount'] ?? 0), 0, ',', '.') . '</strong></li>';
                }
            } else {
                echo '                          <li class="text-muted">Tidak ada data item</li>';
            }
            echo '                        </ul>';
            echo '                      </div>';
            echo '                    </div>';
            echo '                  </div>';
            echo '                </div>';

            echo '              </div>';
            echo '              <div class="modal-footer">';
            echo '                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>';
            echo '              </div>';
            echo '            </div>';
            echo '          </div>';
            echo '        </div>';
        }
    }

    echo '      </tbody>';
    echo '    </table>';

    echo '  </div>';
    echo '</div>';
    echo '</div>';
}
