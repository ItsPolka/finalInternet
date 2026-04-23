<?php
header('Content-Type: text/html; charset=utf-8');

$host = 'db';
$db   = 'tienda_db';
$user = 'tienda_user';
$pass = 'tienda_pass';

$stats = ['usuarios' => 0, 'productos' => 0, 'compras' => 0, 'ingresos' => 0.0];
$usuarios = [];
$compras  = [];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stats['usuarios']  = $pdo->query('SELECT COUNT(*) FROM CUENTA')->fetchColumn();
    $stats['productos'] = $pdo->query('SELECT COUNT(*) FROM CATALOGO')->fetchColumn();
    $stats['compras']   = $pdo->query('SELECT COUNT(*) FROM HISTORIAL_COMPRA')->fetchColumn();
    $stats['ingresos']  = (float)$pdo->query('SELECT COALESCE(SUM(total),0) FROM HISTORIAL_COMPRA')->fetchColumn();

    $usuarios = $pdo->query(
        'SELECT id_usuario, nombre, apellido, usuario, correo, rol FROM CUENTA ORDER BY id_usuario DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $compras = $pdo->query(
        'SELECT h.id_compra, h.total, h.fecha_hora_compra, c.nombre, c.apellido
         FROM HISTORIAL_COMPRA h
         JOIN CUENTA c ON c.id_usuario = h.id_usuario
         ORDER BY h.fecha_hora_compra DESC LIMIT 20'
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Dashboard — Admin</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet"/>
    <link href="css/styles.css" rel="stylesheet"/>
    <style>
        .stat-card { border: none; border-radius: 1rem; box-shadow: 0 2px 16px rgba(0,0,0,0.08); }
        .stat-val  { font-size: 2rem; font-weight: 800; color: #212529; }
        .stat-icon { font-size: 2rem; opacity: .15; }
        .table th  { font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; }
        .badge-admin  { background: #ffc107; color: #212529; }
        .badge-client { background: #e9ecef; color: #495057; }
        #access-denied { min-height: 60vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand fw-bold" href="index.html">Mi Tienda</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item"><a class="nav-link" href="index.html">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.html">Nosotros</a></li>
                    <li class="nav-item"><a class="nav-link" href="historial.html" id="nav-historial" style="display:none">Historial</a></li>
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php" id="nav-dashboard" style="display:none">Dashboard</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <a href="carrito.html" class="btn btn-outline-dark">
                        <i class="bi-cart-fill me-1"></i>Carrito
                        <span class="badge bg-dark text-white ms-1 rounded-pill" id="cart-count">0</span>
                    </a>
                    <a href="login.html" class="btn btn-dark" id="btn-login"><i class="bi-person-fill me-1"></i>Iniciar Sesión</a>
                    <span class="navbar-text me-2 fw-semibold" id="user-greeting" style="display:none"></span>
                    <button class="btn btn-outline-danger" id="btn-logout" style="display:none" onclick="logout()">
                        <i class="bi-box-arrow-right me-1"></i>Cerrar Sesión
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Access denied (shown until JS confirms admin) -->
    <div id="access-denied" style="display:none">
        <i class="bi-shield-lock" style="font-size:5rem;color:#dee2e6"></i>
        <h4 class="mt-3 fw-bold text-muted">Acceso Restringido</h4>
        <p class="text-muted">Esta página es solo para administradores.</p>
        <a href="index.html" class="btn btn-dark mt-2">Volver al inicio</a>
    </div>

    <!-- Dashboard content (hidden until JS confirms admin) -->
    <div id="dashboard-content" style="display:none">
        <header class="bg-dark py-4">
            <div class="container px-4 px-lg-5 d-flex align-items-center justify-content-between">
                <h2 class="text-white fw-bolder mb-0"><i class="bi-speedometer2 me-2"></i>Dashboard</h2>
                <span class="badge bg-warning text-dark fs-6">Admin</span>
            </div>
        </header>

        <section class="py-5">
            <div class="container px-4 px-lg-5">

                <?php if (isset($dbError)): ?>
                <div class="alert alert-danger"><i class="bi-exclamation-triangle me-2"></i>Error de base de datos: <?= htmlspecialchars($dbError) ?></div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="row g-4 mb-5">
                    <div class="col-6 col-md-3">
                        <div class="card stat-card p-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="text-muted small fw-semibold mb-1">Usuarios</div>
                                    <div class="stat-val"><?= $stats['usuarios'] ?></div>
                                </div>
                                <i class="bi-people stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card p-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="text-muted small fw-semibold mb-1">Productos</div>
                                    <div class="stat-val"><?= $stats['productos'] ?></div>
                                </div>
                                <i class="bi-box-seam stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card p-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="text-muted small fw-semibold mb-1">Compras</div>
                                    <div class="stat-val"><?= $stats['compras'] ?></div>
                                </div>
                                <i class="bi-bag-check stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card p-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="text-muted small fw-semibold mb-1">Ingresos</div>
                                    <div class="stat-val">$<?= number_format($stats['ingresos'], 2) ?></div>
                                </div>
                                <i class="bi-currency-dollar stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users table -->
                <div class="card border-0 shadow-sm rounded-4 mb-5">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><i class="bi-people me-2"></i>Usuarios registrados</h5>
                        <span class="badge bg-secondary"><?= count($usuarios) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4">ID</th>
                                        <th>Nombre</th>
                                        <th>Usuario</th>
                                        <th>Correo</th>
                                        <th>Rol</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td class="px-4 text-muted"><?= $u['id_usuario'] ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                                        <td><?= htmlspecialchars($u['usuario']) ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($u['correo']) ?></td>
                                        <td>
                                            <span class="badge <?= $u['rol'] === 'administrador' ? 'badge-admin' : 'badge-client' ?>">
                                                <?= $u['rol'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($usuarios)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Sin usuarios</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent purchases -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><i class="bi-clock-history me-2"></i>Compras recientes</h5>
                        <span class="badge bg-secondary"><?= count($compras) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4">#</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($compras as $c): ?>
                                    <tr>
                                        <td class="px-4 text-muted"><?= $c['id_compra'] ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido']) ?></td>
                                        <td class="fw-bold">$<?= number_format($c['total'], 2) ?></td>
                                        <td class="text-muted small"><?= date('d M Y, H:i', strtotime($c['fecha_hora_compra'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($compras)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Sin compras registradas</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <footer class="py-4 bg-dark mt-5">
        <div class="container"><p class="m-0 text-center text-white">Copyright &copy; Mi Tienda 2024</p></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        document.getElementById('cart-count').textContent = cart.reduce((s,i) => s+i.cantidad, 0);

        function logout() { localStorage.removeItem('currentUser'); window.location.href = 'login.html'; }

        (function checkAuth() {
            const user = JSON.parse(localStorage.getItem('currentUser') || 'null');
            const isAdmin = user && user.rol === 'administrador';

            if (!user) {
                document.getElementById('access-denied').style.display = '';
                return;
            }

            // Show nav state
            document.getElementById('btn-login').style.display = 'none';
            document.getElementById('btn-logout').style.display = '';
            document.getElementById('user-greeting').style.display = '';
            document.getElementById('user-greeting').innerHTML = 'Hola, ' + user.nombre + (isAdmin ? ' <span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem">Admin</span>' : '');
            document.getElementById('nav-historial').style.display = '';

            if (!isAdmin) {
                // Logged in but not admin
                document.getElementById('access-denied').style.display = '';
                return;
            }

            document.getElementById('nav-dashboard').style.display = '';
            document.getElementById('dashboard-content').style.display = '';
        })();
    </script>
</body>
</html>
