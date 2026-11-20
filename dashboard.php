<?php
session_start();
include 'config.php'; 

if(!isset($_SESSION['status']) || $_SESSION['status'] != 'login') {
    header("Location: index.php");
    exit();
}

// Inisialisasi STACK untuk memori Undo
if(!isset($_SESSION['riwayat_stack'])) {
    $_SESSION['riwayat_stack'] = [];
}

$notif_wa = "";


// A. CREATE (Tambah Data ke Database)

if(isset($_POST['tambah'])) {
    $id_nota  = "LDR-" . date("Ymd-His");
    $nama     = $_POST['nama'];
    $nowa     = $_POST['nowa'];
    $berat    = $_POST['berat'];
    $kategori = $_POST['kategori']; // 1=Express, 2=Reguler

    // Membersihkan nomor WA
    $nowa = preg_replace('/[^0-9]/', '', $nowa);
    if (substr($nowa, 0, 1) == '0') { 
        $nowa = '62' . substr($nowa, 1); 
    }

    // Query INSERT 
    $query = "INSERT INTO pesanan (id_nota, nama_pelanggan, no_wa, kategori, berat, status) 
              VALUES ('$id_nota', '$nama', '$nowa', '$kategori', '$berat', 'Menunggu')";

    if(mysqli_query($conn, $query)) {
        $harga_per_kg  = ($kategori == 1) ? 7000 : 4000;
        $total_bayar   = $berat * $harga_per_kg;
        $total_format  = number_format($total_bayar, 0, ',', '.');
        $jenis_layanan = ($kategori == 1) ? "EXPRESS VIP" : "REGULER";

        // Link Nota Digital & Pembayaran
       $link_qris = "https://s.id/GoGOT";

        $pesan = "Halo Kak *$nama*! Cucian Anda dengan nota *$id_nota* telah masuk antrean.\n" .
         "• Layanan: $jenis_layanan\n" .
         "• Berat: $berat Kg\n" .
         "• Estimasi Biaya: *Rp$total_format*\n\n" .
         "Untuk pembayaran non-tunai (QRIS), silakan klik tautan berikut:\n$link_qris";
        $link_wa = "https://api.whatsapp.com/send?phone=" . $nowa . "&text=" . urlencode($pesan);

        $notif_wa = "<div class='alert alert-success mt-3 d-flex justify-content-between align-items-center'>
                        <span><b>Data Tersimpan ke Database!</b> Simulasi API WhatsApp siap.</span>
                        <a href='$link_wa' target='_blank' class='btn btn-sm btn-success fw-bold'>Kirim WA Penerimaan</a>
                     </div>";
    } else {
        $notif_wa = "<div class='alert alert-danger mt-3'>Error: " . mysqli_error($conn) . "</div>";
    }
}

//  UPDATE STATUS & PUSH STACK 

if(isset($_GET['update_status'])) {
    $id = $_GET['update_status'];
    $status_baru = isset($_GET['status_ke']) ? $_GET['status_ke'] : '';

    if($status_baru !== '') {
        // 1. Ambil data sebelum diupdate untuk dimasukkan ke STACK (Undo)
        $cek = mysqli_query($conn, "SELECT * FROM pesanan WHERE id_nota = '$id'");
        if($data_lama = mysqli_fetch_assoc($cek)) {
            // PUSH ke Stack
            array_push($_SESSION['riwayat_stack'], $data_lama);

            // 2. Update ke status baru di Database
            mysqli_query($conn, "UPDATE pesanan SET status = '$status_baru' WHERE id_nota = '$id'");

            // 3. Notifikasi WA Khusus
            if($status_baru == 'Selesai') {
                $nama_layanan = ($data_lama['kategori'] == 1) ? "EXPRESS VIP" : "REGULER";
                $harga_per_kg = ($data_lama['kategori'] == 1) ? 7000 : 4000;
                $total_bayar  = $data_lama['berat'] * $harga_per_kg;
                $total_format = number_format($total_bayar, 0, ',', '.');

                // Link Nota Digital & Pembayaran
                $link_qris = "https://s.id/GoGOT";

               $pesan = "Halo Kak *{$data_lama['nama_pelanggan']}*!\n\n" .
                 "Cucian Anda dengan nomor nota *{$id}* *SUDAH SELESAI* dan siap diambil.\n\n" .
                 "*Rincian Pesanan:*\n" .
                 "• Layanan: $nama_layanan\n" .
                 "• Berat: {$data_lama['berat']} Kg\n" .
                 "• Total Tagihan: *Rp$total_format*\n\n" .
                 "Silakan scan QRIS pada tautan berikut untuk pembayaran:\n$link_qris\n\n" .
                 "Terima kasih! 🙏";

                $link_wa = "https://api.whatsapp.com/send?phone=" . $data_lama['no_wa'] . "&text=" . urlencode($pesan);

                $notif_wa = "<div class='alert alert-info mt-3 d-flex justify-content-between align-items-center'>
                                <span>Status diperbarui menjadi <b>Selesai</b>.</span>
                                <a href='$link_wa' target='_blank' class='btn btn-sm btn-success fw-bold'>Kirim WA Selesai</a>
                             </div>";

            } elseif($status_baru == 'Lunas') {
                $pesan = "Halo Kak *{$data_lama['nama_pelanggan']}*!\n\nPembayaran untuk nota *{$id}* telah kami terima dan berstatus *LUNAS*. Terima kasih telah mempercayakan pakaian Anda di Zahra Laundry! 🥰";
                $link_wa = "https://api.whatsapp.com/send?phone=" . $data_lama['no_wa'] . "&text=" . urlencode($pesan);

                $notif_wa = "<div class='alert alert-success mt-3 d-flex justify-content-between align-items-center'>
                                <span>Status diperbarui menjadi <b>LUNAS</b>.</span>
                                <a href='$link_wa' target='_blank' class='btn btn-sm btn-success fw-bold'>Kirim WA Terima Kasih</a>
                             </div>";
            } else {
                $notif_wa = "<div class='alert alert-primary mt-3'>Status diperbarui menjadi <b>$status_baru</b>.</div>";
            }
        }
    }
}

//  UNDO

if(isset($_GET['undo'])) {
    if(!empty($_SESSION['riwayat_stack'])) {
        // Ambil data status terakhir (POP)
        $dataUndo = array_pop($_SESSION['riwayat_stack']);
        $id_undo = $dataUndo['id_nota'];
        $status_undo = $dataUndo['status'];
        
        // Kembalikan ke status sebelumnya di database
        mysqli_query($conn, "UPDATE pesanan SET status = '$status_undo' WHERE id_nota = '$id_undo'");
        $notif_wa = "<div class='alert alert-warning mt-3'><b>Undo Berhasil!</b> Status dikembalikan ke '$status_undo'.</div>";
    } else {
        $notif_wa = "<div class='alert alert-secondary mt-3'>Tidak ada riwayat status untuk di-undo.</div>";
    }
}

//  DELETE 

if(isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM pesanan WHERE id_nota = '$id'");
    $notif_wa = "<div class='alert alert-danger mt-3'>Data nota $id telah dihapus.</div>";
}

// E. PENCARIAN HASH TABLE (Cari Rak)

if(isset($_POST['btn_cari'])) {
    $kunci_wa = $_POST['cari_rak'];
    
    // Mencari pesanan berdasarkan 4 digit terakhir WA pelanggan
    $q_cari = mysqli_query($conn, "SELECT * FROM pesanan WHERE no_wa LIKE '%$kunci_wa'");
    
    if($hasil_cari = mysqli_fetch_assoc($q_cari)) {
        $nama_pelanggan = $hasil_cari['nama_pelanggan'];
        $status_baju = $hasil_cari['status'];
        
        // Cek apakah lokasi rak sudah diisi di database, jika kosong tampilkan pesan default
        
        $notif_wa = "<div class='alert alert-success mt-3 shadow-sm'>
                        <h6 class='fw-bold mb-1'> Pencarian Instan (Hash Table) Berhasil!</h6>
                        Pelanggan: <b>$nama_pelanggan</b> | Status: <b>$status_baju</b> | </b>
                     </div>";
    } else {
        $notif_wa = "<div class='alert alert-danger mt-3'>Data cucian dengan akhiran WA '$kunci_wa' tidak ditemukan di sistem.</div>";
    }
}

?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dasbor Kasir - Zahra Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .express-row { background-color: #fff3cd !important; border-left: 5px solid #ffc107; }
        .reguler-row { background-color: #ffffff !important; border-left: 5px solid #0dcaf0; }
    </style>
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-info shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="#"> LaundrySmart</a>
        <div class="d-flex text-white align-items-center">
            <span class="me-3">Halo, <?= $_SESSION['user']; ?></span>
            <a href="logout.php" class="btn btn-sm btn-light text-info fw-bold">Keluar</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    
    <?= $notif_wa; ?>

    <!-- Row Atas: Pencarian & Input -->    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body bg-white rounded">
                    <h5 class="fw-bold text-success"> Cari Baju</h5>
                    <p class="text-muted small">Cari lokasi rak dengan 4 digit WA</p>
                    <form method="POST" action="">
    <div class="input-group">
        <input type="text" name="cari_rak" class="form-control" placeholder="Contoh: 7890" required>
        <button type="submit" name="btn_cari" class="btn btn-success">Cari Instan</button>
    </div>
</form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="fw-bold text-secondary"> Tambah Cucian </h5>
                    <form class="row g-2" method="POST" action="">
                        <div class="col-md-3">
                            <input type="text" name="nama" class="form-control" placeholder="Nama Pelanggan" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="nowa" class="form-control" placeholder="No WA (08...)" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" step="0.1" name="berat" class="form-control" placeholder="Berat(Kg)" required>
                        </div>
                        <div class="col-md-2">
                            <select name="kategori" class="form-select" required>
                                <option value="2">Reguler</option>
                                <option value="1">Express VIP</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="tambah" class="btn btn-primary w-100 fw-bold">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL PRIORITY QUEUE -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white pt-3 pb-2 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-primary mb-0"> Papan Antrean </h5>
            <?php if(!empty($_SESSION['riwayat_stack'])): ?>
                <a href="?undo=true" class="btn btn-sm btn-warning fw-bold"> Undo Terakhir</a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>ID Nota</th>
                        <th>Pelanggan</th>
                        <th>No. WA</th>
                        <th>Layanan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    
                    <?php 
                    
                    // MENGAMBIL DATA DARI DATABASE (Priority Queue menggunakan ORDER BY)
                    $query = "SELECT * FROM pesanan ORDER BY kategori ASC, id_nota DESC";
                    $result = mysqli_query($conn, $query);
                    $no = 1;

                    if(mysqli_num_rows($result) == 0) {
                        echo "<tr><td colspan='7' class='text-center text-muted py-3'>Belum ada antrean cucian.</td></tr>";
                    }

                    while($pesanan = mysqli_fetch_assoc($result)) { 
                        $rowClass = ($pesanan['kategori'] == 1) ? 'express-row' : 'reguler-row';
                        $badgeLayanan = ($pesanan['kategori'] == 1) ? '<span class="badge bg-warning text-dark">Express VIP</span>' : '<span class="badge bg-info">Reguler</span>';
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="fw-bold">#<?= $no++; ?></td>
                        <td><small><?= $pesanan['id_nota']; ?></small></td>
                        <td class="fw-bold"><?= $pesanan['nama_pelanggan']; ?><br><small class="text-muted fw-normal"><?= $pesanan['berat']; ?> Kg</small></td>
                        <td><?= $pesanan['no_wa']; ?></td>
                        <td><?= $badgeLayanan; ?></td>
                        <td>
                            <?php if(strtolower($pesanan['status']) == 'menunggu' || strtolower($pesanan['status']) == 'antrian'): ?>
                                <span class="badge bg-secondary">Menunggu</span>
                            <?php elseif(strtolower($pesanan['status']) == 'dicuci'): ?>
                                <span class="badge bg-primary">Sedang Dicuci</span>
                            <?php else: ?>
                                <span class="badge bg-success">Selesai</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- Dropdown Update Status -->
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                    Update Status
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="?update_status=<?= $pesanan['id_nota'] ?>&status_ke=Dicuci">Mulai Cuci</a></li>
                                    <li><a class="dropdown-item text-success" href="?update_status=<?= $pesanan['id_nota'] ?>&status_ke=Selesai">Tandai Selesai & Kirim WA</a></li>
                                </ul>
                            </div>
                            <!-- Delete -->
                            <a href="?hapus=<?= $pesanan['id_nota'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus pesanan ini?');">Hapus</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>