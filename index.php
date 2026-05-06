<?php
$host = 'db';
$db   = 'tienda_db';
$user = 'tienda_user';
$pass = 'tienda_pass';

$productos = [];
$dbError   = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $productos = $pdo->query(
        'SELECT id_producto, nombre, inventario, precio, imagen FROM CATALOGO WHERE inventario > 0 ORDER BY id_producto DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Inicio</title>
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
        <link href="css/styles.css" rel="stylesheet" />
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container px-4 px-lg-5">
                <a class="navbar-brand fw-bold" href="index.php">Mi Tienda</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="index.php">Inicio</a></li>
                        <li class="nav-item"><a class="nav-link" href="about.html">Nosotros</a></li>
                        <li class="nav-item"><a class="nav-link" href="historial.php" id="nav-historial" style="display:none">Historial</a></li>
                        <li class="nav-item"><a class="nav-link" href="dashboard.php" id="nav-dashboard" style="display:none">Dashboard</a></li>
                    </ul>
                    <div class="d-flex align-items-center gap-2">
                        <a href="carrito.php" class="btn btn-outline-dark">
                            Carrito
                            <span class="badge bg-dark text-white ms-1 rounded-pill" id="cart-count">0</span>
                        </a>
                        <a href="login.html" class="btn btn-dark" id="btn-login">Iniciar Sesion</a>
                        <span class="navbar-text me-2 fw-semibold" id="user-greeting" style="display:none"></span>
                        <button class="btn btn-outline-danger" id="btn-logout" style="display:none" onclick="logout()">Cerrar Sesion</button>
                    </div>
                </div>
            </div>
        </nav>

        <header class="bg-dark py-5">
            <div class="container px-4 px-lg-5 my-5">
                <div class="text-center text-white">
                    <h1 class="display-4 fw-bolder">Compra con estilo</h1>
                    <p class="lead fw-normal text-white-50 mb-0">Encuentra los mejores productos al mejor precio</p>
                </div>
            </div>
        </header>

        <section class="py-5">
            <div class="container px-4 px-lg-5 mt-5">

                <?php if ($dbError): ?>
                <div class="alert alert-danger">
                    Error al cargar productos: <?= htmlspecialchars($dbError) ?>
                </div>
                <?php endif; ?>

                <?php if (empty($productos) && !$dbError): ?>
                <div class="text-center py-5">
                    <h4 class="text-muted">Sin productos disponibles</h4>
                    <p class="text-muted">El catalogo esta vacio. El administrador debe agregar productos.</p>
                </div>
                <?php else: ?>
                <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">
                    <?php foreach ($productos as $p): ?>
                    <div class="col mb-5">
                        <div class="card h-100">
                            <?php if ($p['imagen']): ?>
                                <img class="card-img-top"
                                     src="<?= htmlspecialchars($p['imagen']) ?>"
                                     alt="<?= htmlspecialchars($p['nombre']) ?>"
                                     style="height:220px;object-fit:cover;" />
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center bg-light text-muted" style="height:220px;font-size:0.85rem;">
                                    Sin imagen
                                </div>
                            <?php endif; ?>

                            <div class="card-body p-4">
                                <div class="text-center">
                                    <h5 class="fw-bolder"><?= htmlspecialchars($p['nombre']) ?></h5>
                                    <span class="fw-bold fs-5">$<?= number_format($p['precio'], 2) ?></span>
                                </div>
                            </div>

                            <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                                <div class="text-center">
                                    <?php if ($p['inventario'] > 0): ?>
                                    <button class="btn btn-outline-dark mt-auto"
                                            onclick="addToCart(<?= $p['id_producto'] ?>, '<?= htmlspecialchars(addslashes($p['nombre'])) ?>', <?= $p['precio'] ?>, this)">
                                        Agregar al carrito
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-secondary mt-auto" disabled>Sin stock</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </section>

        <footer class="py-5 bg-dark">
            <div class="container"><p class="m-0 text-center text-white">Copyright &copy; Mi Tienda 2024</p></div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function checkAuth() {
                const user    = JSON.parse(localStorage.getItem('currentUser') || 'null');
                const isAdmin = user && user.rol === 'administrador';
                if (user) {
                    document.getElementById('btn-login').style.display = 'none';
                    document.getElementById('btn-logout').style.display = '';
                    document.getElementById('user-greeting').style.display = '';
                    document.getElementById('user-greeting').innerHTML = 'Hola, ' + user.nombre +
                        (isAdmin ? ' <span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem">Admin</span>' : '');
                    document.getElementById('nav-historial').style.display = '';
                    if (isAdmin) document.getElementById('nav-dashboard').style.display = '';
                } else {
                    document.getElementById('btn-login').style.display = '';
                    document.getElementById('btn-logout').style.display = 'none';
                    document.getElementById('user-greeting').style.display = 'none';
                    document.getElementById('nav-historial').style.display = 'none';
                    document.getElementById('nav-dashboard').style.display = 'none';
                }
            }

            function logout() {
                localStorage.removeItem('currentUser');
                window.location.href = 'login.html';
            }

            async function updateCartCount() {
                const user = JSON.parse(localStorage.getItem('currentUser') || 'null');
                if (!user) { document.getElementById('cart-count').textContent = '0'; return; }
                try {
                    const r = await fetch('carrito.php?count=1&id_usuario=' + user.id);
                    const d = await r.json();
                    document.getElementById('cart-count').textContent = d.count || 0;
                } catch(e) { document.getElementById('cart-count').textContent = '0'; }
            }

            async function addToCart(id, nombre, precio, btn) {
                const user = JSON.parse(localStorage.getItem('currentUser') || 'null');
                if (!user) {
                    if (confirm('Necesitas iniciar sesion para agregar productos al carrito.')) {
                        window.location.href = 'login.html';
                    }
                    return;
                }
                btn.disabled = true;
                try {
                    const res  = await fetch('carrito.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'add', id_usuario: user.id, id_producto: id, cantidad: 1 })
                    });
                    const data = await res.json();
                    if (data.ok) {
                        document.getElementById('cart-count').textContent = data.cart_count;
                        btn.textContent = 'Agregado';
                        btn.classList.replace('btn-outline-dark', 'btn-success');
                        setTimeout(() => {
                            btn.textContent = 'Agregar al carrito';
                            btn.classList.replace('btn-success', 'btn-outline-dark');
                            btn.disabled = false;
                        }, 1200);
                    } else {
                        alert(data.msg || 'Error al agregar al carrito.');
                        btn.disabled = false;
                    }
                } catch(e) {
                    alert('Error de conexion.');
                    btn.disabled = false;
                }
            }

            checkAuth();
            updateCartCount();
        </script>
    </body>
</html>
