<?php
session_start();
// Simulasi login statis untuk Prototype (Bisa diganti dengan query MySQL)
if(isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if($username === 'kasir' && $password === 'admin123') {
        $_SESSION['status'] = 'login';
        $_SESSION['user'] = 'Kasir';
        header("Location: dashboard.php");
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Laundry System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e0f7fa 0%, #80deea 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .card-login {
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-login p-4 border-0">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <!-- Icon Laundry -->
                        <h1 class="text-info fs-1"></h1>
                        <h4 class="fw-bold text-secondary">Bersih Kilat Laundry</h4>
                        <p class="text-muted">Masuk ke Sistem Kasir</p>
                    </div>
                    
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger py-2"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" required placeholder="Masukkan username">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" required placeholder="Masukkan password">
                        </div>
                        <button type="submit" name="login" class="btn btn-info w-100 text-white fw-bold py-2">Masuk Sistem</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>