<?php
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET' || isset($_GET['productos'])) {
    header('Content-Type: application/json');
    if ($method === 'OPTIONS') { http_response_code(204); exit; }

    try {
        $pdo = new PDO("mysql:host=db;dbname=tienda_db;charset=utf8", 'tienda_user', 'tienda_pass');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($method === 'GET') {
            $productos = $pdo->query(
                'SELECT id_producto, nombre, inventario, precio, imagen FROM CATALOGO ORDER BY id_producto DESC'
            )->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'productos' => $productos]);

        } elseif ($method === 'POST') {
            if (isset($_POST['action']) && $_POST['action'] === 'update') {
                $id  = (int)($_POST['id_producto'] ?? 0);
                $inv = (int)($_POST['inventario']  ?? 0);
                if (!$id) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'ID invalido.']); exit; }

                $imagenPath   = null;
                $updateImagen = false;

                if (!empty($_FILES['imagen']['tmp_name'])) {
                    $row = $pdo->prepare('SELECT imagen FROM CATALOGO WHERE id_producto = ?');
                    $row->execute([$id]);
                    $old = $row->fetch(PDO::FETCH_ASSOC);
                    if ($old && $old['imagen'] && file_exists(__DIR__ . '/' . $old['imagen'])) {
                        unlink(__DIR__ . '/' . $old['imagen']);
                    }
                    $uploadDir = __DIR__ . '/uploads/productos/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                        http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'Formato no permitido.']); exit;
                    }
                    $filename = uniqid('prod_', true) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $filename)) {
                        http_response_code(500); echo json_encode(['ok' => false, 'msg' => 'Error al guardar imagen.']); exit;
                    }
                    $imagenPath   = 'uploads/productos/' . $filename;
                    $updateImagen = true;
                }

                if ($updateImagen) {
                    $pdo->prepare('UPDATE CATALOGO SET inventario = ?, imagen = ? WHERE id_producto = ?')
                        ->execute([$inv, $imagenPath, $id]);
                } else {
                    $pdo->prepare('UPDATE CATALOGO SET inventario = ? WHERE id_producto = ?')
                        ->execute([$inv, $id]);
                }

                $stmt = $pdo->prepare('SELECT id_producto, nombre, inventario, precio, imagen FROM CATALOGO WHERE id_producto = ?');
                $stmt->execute([$id]);
                echo json_encode(['ok' => true, 'producto' => $stmt->fetch(PDO::FETCH_ASSOC)]);

            } else {
                $nombre = trim($_POST['nombre']    ?? '');
                $inv    = (int)($_POST['inventario'] ?? 0);
                $precio = (float)($_POST['precio']   ?? 0);
                if (!$nombre || $precio <= 0) {
                    http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'Nombre y precio son obligatorios.']); exit;
                }

                $imagenPath = null;
                if (!empty($_FILES['imagen']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/uploads/productos/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                        http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'Formato no permitido.']); exit;
                    }
                    $filename = uniqid('prod_', true) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $filename)) {
                        http_response_code(500); echo json_encode(['ok' => false, 'msg' => 'Error al guardar imagen.']); exit;
                    }
                    $imagenPath = 'uploads/productos/' . $filename;
                }

                $pdo->prepare('INSERT INTO CATALOGO (nombre, inventario, precio, imagen) VALUES (?, ?, ?, ?)')
                    ->execute([$nombre, $inv, $precio, $imagenPath]);
                echo json_encode(['ok' => true, 'producto' => [
                    'id_producto' => (int)$pdo->lastInsertId(),
                    'nombre'      => $nombre,
                    'inventario'  => $inv,
                    'precio'      => $precio,
                    'imagen'      => $imagenPath,
                ]]);
            }

        } elseif ($method === 'DELETE') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id    = (int)($input['id_producto'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'ID invalido.']); exit; }

            $row = $pdo->prepare('SELECT imagen FROM CATALOGO WHERE id_producto = ?');
            $row->execute([$id]);
            $p = $row->fetch(PDO::FETCH_ASSOC);
            if ($p && $p['imagen'] && file_exists(__DIR__ . '/' . $p['imagen'])) {
                unlink(__DIR__ . '/' . $p['imagen']);
            }
            $pdo->prepare('DELETE FROM CATALOGO WHERE id_producto = ?')->execute([$id]);
            echo json_encode(['ok' => true]);
        }

    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error de base de datos.']);
    }
    exit;
}

// Pagina HTML
try {
    $pdo = new PDO("mysql:host=db;dbname=tienda_db;charset=utf8", 'tienda_user', 'tienda_pass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stats = [
        'usuarios' => $pdo->query('SELECT COUNT(*) FROM CUENTA')->fetchColumn(),
        'productos' => $pdo->query('SELECT COUNT(*) FROM CATALOGO')->fetchColumn(),
        'compras'   => $pdo->query('SELECT COUNT(*) FROM HISTORIAL_COMPRA')->fetchColumn(),
        'ingresos'  => (float)$pdo->query('SELECT COALESCE(SUM(total),0) FROM HISTORIAL_COMPRA')->fetchColumn(),
    ];

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
    $stats   = ['usuarios' => 0, 'productos' => 0, 'compras' => 0, 'ingresos' => 0.0];
    $usuarios = [];
    $compras  = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Dashboard - Admin</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico"/>
    <link href="css/styles.css" rel="stylesheet"/>
    <style>
        .stat-card { border: none; border-radius: 1rem; box-shadow: 0 2px 16px rgba(0,0,0,0.08); }
        .stat-val  { font-size: 2rem; font-weight: 800; color: #212529; }
        .table th  { font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; }
        .badge-admin  { background: #ffc107; color: #212529; }
        .badge-client { background: #e9ecef; color: #495057; }
        #access-denied { min-height: 60vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .edit-row td { background: #f8f9fa; vertical-align: middle; }
        .edit-row input[type="number"] { width: 90px; }
        .img-thumb { width: 56px; height: 42px; object-fit: cover; border-radius: 6px; }
        .no-img-cell { display: flex; align-items: center; justify-content: center; background: #e9ecef; border-radius: 6px; width: 56px; height: 42px; font-size: 0.65rem; color: #aaa; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand fw-bold" href="index.php">Mi Tienda</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.html">Nosotros</a></li>
                    <li class="nav-item"><a class="nav-link" href="historial.php" id="nav-historial" style="display:none">Historial</a></li>
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php" id="nav-dashboard" style="display:none">Dashboard</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <a href="carrito.php" class="btn btn-outline-dark">
                        Carrito
                        <span class="badge bg-dark text-white ms-1 rounded-pill" id="cart-count">0</span>
                    </a>
                    <a href="login.php" class="btn btn-dark" id="btn-login">Iniciar Sesion</a>
                    <span class="navbar-text me-2 fw-semibold" id="user-greeting" style="display:none"></span>
                    <button class="btn btn-outline-danger" id="btn-logout" style="display:none" onclick="logout()">Cerrar Sesion</button>
                </div>
            </div>
        </div>
    </nav>

    <div id="access-denied" style="display:none">
        <h4 class="mt-3 fw-bold text-muted">Acceso Restringido</h4>
        <p class="text-muted">Esta pagina es solo para administradores.</p>
        <a href="index.php" class="btn btn-dark mt-2">Volver al inicio</a>
    </div>

    <div id="dashboard-content" style="display:none">
        <header class="bg-dark py-4">
            <div class="container px-4 px-lg-5 d-flex align-items-center justify-content-between">
                <h2 class="text-white fw-bolder mb-0">Dashboard</h2>
                <span class="badge bg-warning text-dark fs-6">Admin</span>
            </div>
        </header>

        <section class="py-5">
            <div class="container px-4 px-lg-5">

                <?php if (isset($dbError)): ?>
                <div class="alert alert-danger">Error de base de datos: <?= htmlspecialchars($dbError) ?></div>
                <?php endif; ?>

                <div class="row g-4 mb-5">
                    <div class="col-6 col-md-3">
                        <div class="card stat-card p-4">
                            <div class="text-muted small fw-semibold mb-1">Usuarios</div>
                            <div class="stat-val"><?= $stats['usuarios'] ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card p-4">
                            <div class="text-muted small fw-semibold mb-1">Productos</div>
                            <div class="stat-val"><?= $stats['productos'] ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card p-4">
                            <div class="text-muted small fw-semibold mb-1">Compras</div>
                            <div class="stat-val"><?= $stats['compras'] ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card p-4">
                            <div class="text-muted small fw-semibold mb-1">Ingresos</div>
                            <div class="stat-val">$<?= number_format($stats['ingresos'], 2) ?></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-5">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0">Usuarios registrados</h5>
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

                <div class="card border-0 shadow-sm rounded-4 mb-5">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0">Compras recientes</h5>
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

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0">Gestion de productos</h5>
                        <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregarProducto">
                            Agregar producto
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4">ID</th>
                                        <th>Imagen</th>
                                        <th>Nombre</th>
                                        <th>Precio</th>
                                        <th>Inventario</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="productos-tbody">
                                    <tr><td colspan="6" class="text-center text-muted py-4">
                                        <span class="spinner-border spinner-border-sm me-2"></span>Cargando...
                                    </td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Modal: Agregar Producto -->
    <div class="modal fade" id="modalAgregarProducto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Agregar producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-2">
                    <div id="msg-agregar" class="alert d-none"></div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="prod-nombre" placeholder="Ej: Camiseta Azul" />
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col">
                            <label class="form-label fw-semibold">Precio ($) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="prod-precio" min="0" step="0.01" placeholder="0.00" />
                        </div>
                        <div class="col">
                            <label class="form-label fw-semibold">Inventario</label>
                            <input type="number" class="form-control" id="prod-inventario" min="0" value="0" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Imagen del producto</label>
                        <input type="file" class="form-control" id="prod-imagen" accept="image/jpeg,image/png,image/webp,image/gif" />
                        <div class="form-text">Formatos: JPG, PNG, WEBP, GIF</div>
                    </div>
                    <div id="preview-imagen" class="text-center mt-2 d-none">
                        <img id="preview-img" src="" alt="Vista previa" class="img-thumbnail" style="max-height:160px" />
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-dark" id="btn-guardar-producto" onclick="guardarProducto()">Guardar producto</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Confirmar eliminacion -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-danger">Eliminar producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-0">
                    <p class="mb-0">Eliminar <strong id="nombre-a-eliminar"></strong>? Esta accion no se puede deshacer.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger btn-sm" id="btn-confirmar-eliminar">Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="py-4 bg-dark mt-5">
        <div class="container"><p class="m-0 text-center text-white">Copyright &copy; Mi Tienda 2024</p></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function logout() { localStorage.removeItem('currentUser'); window.location.href = 'login.php'; }

        (function checkAuth() {
            const user    = JSON.parse(localStorage.getItem('currentUser') || 'null');
            const isAdmin = user && user.rol === 'administrador';

            if (!user || !isAdmin) {
                document.getElementById('access-denied').style.display = '';
                if (user) {
                    document.getElementById('btn-login').style.display = 'none';
                    document.getElementById('btn-logout').style.display = '';
                    document.getElementById('user-greeting').style.display = '';
                    document.getElementById('user-greeting').textContent = 'Hola, ' + user.nombre;
                    document.getElementById('nav-historial').style.display = '';
                }
                return;
            }

            document.getElementById('btn-login').style.display = 'none';
            document.getElementById('btn-logout').style.display = '';
            document.getElementById('user-greeting').style.display = '';
            document.getElementById('user-greeting').innerHTML = 'Hola, ' + user.nombre +
                ' <span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem">Admin</span>';
            document.getElementById('nav-historial').style.display = '';
            document.getElementById('nav-dashboard').style.display = '';
            document.getElementById('dashboard-content').style.display = '';
        })();

        async function updateCartCount() {
            const user = JSON.parse(localStorage.getItem('currentUser') || 'null');
            if (!user) return;
            try {
                const r = await fetch('carrito.php?count=1&id_usuario=' + user.id);
                const d = await r.json();
                document.getElementById('cart-count').textContent = d.count || 0;
            } catch(e) {}
        }
        updateCartCount();
    </script>
    <script>
        let productoIdAEliminar = null;
        let productoEnEdicion   = null;

        function escHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function renderFila(p) {
            return `
                <td class="px-4 text-muted">${p.id_producto}</td>
                <td>${p.imagen
                    ? `<img src="${escHtml(p.imagen)}" class="img-thumb" alt="${escHtml(p.nombre)}">`
                    : `<div class="no-img-cell">sin img</div>`}
                </td>
                <td class="fw-semibold">${escHtml(p.nombre)}</td>
                <td>$${parseFloat(p.precio).toFixed(2)}</td>
                <td><span class="badge ${parseInt(p.inventario) > 0 ? 'bg-success' : 'bg-secondary'}">${p.inventario}</span></td>
                <td class="text-center">
                    <button class="btn btn-outline-primary btn-sm me-1"
                            onclick="editarProducto(${p.id_producto}, '${escHtml(p.nombre).replace(/'/g,"\\'")}', ${p.precio}, ${p.inventario}, '${escHtml(p.imagen || '')}')">
                        Editar
                    </button>
                    <button class="btn btn-outline-danger btn-sm"
                            onclick="pedirEliminar(${p.id_producto}, '${escHtml(p.nombre).replace(/'/g,"\\'")}')">
                        Eliminar
                    </button>
                </td>`;
        }

        function renderFilaEdicion(id, nombre, precio, inventario, imagen) {
            return `
                <td class="px-4 text-muted">${id}</td>
                <td>
                    ${imagen ? `<img src="${escHtml(imagen)}" class="img-thumb mb-1" alt="">` : ''}
                    <div class="mt-1">
                        <input type="file" class="form-control form-control-sm" id="edit-img-${id}"
                               accept="image/jpeg,image/png,image/webp,image/gif" style="width:160px">
                        <div class="form-text" style="font-size:0.7rem">Nueva imagen (opcional)</div>
                    </div>
                </td>
                <td class="fw-semibold">${escHtml(nombre)}</td>
                <td>$${parseFloat(precio).toFixed(2)}</td>
                <td><input type="number" class="form-control form-control-sm" id="edit-inv-${id}" value="${inventario}" min="0"></td>
                <td class="text-center">
                    <button class="btn btn-success btn-sm me-1" id="btn-save-${id}" onclick="guardarEdicion(${id})">Guardar</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="cancelarEdicion()">Cancelar</button>
                </td>`;
        }

        async function cargarProductos() {
            productoEnEdicion = null;
            try {
                const res  = await fetch('dashboard.php?productos=1');
                const data = await res.json();
                const tbody = document.getElementById('productos-tbody');
                if (!data.ok || !data.productos.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Sin productos en el catalogo</td></tr>';
                    return;
                }
                tbody.innerHTML = data.productos.map(p =>
                    `<tr data-id="${p.id_producto}">${renderFila(p)}</tr>`
                ).join('');
            } catch(e) {
                document.getElementById('productos-tbody').innerHTML =
                    '<tr><td colspan="6" class="text-center text-danger py-3">Error al cargar productos</td></tr>';
            }
        }

        function editarProducto(id, nombre, precio, inventario, imagen) {
            if (productoEnEdicion && productoEnEdicion !== id) {
                cargarProductos();
                return;
            }
            productoEnEdicion = id;
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (!row) return;
            row.classList.add('edit-row');
            row.innerHTML = renderFilaEdicion(id, nombre, precio, inventario, imagen);
            document.getElementById(`edit-inv-${id}`).focus();
        }

        function cancelarEdicion() {
            productoEnEdicion = null;
            cargarProductos();
        }

        async function guardarEdicion(id) {
            const invInput  = document.getElementById(`edit-inv-${id}`);
            const imgInput  = document.getElementById(`edit-img-${id}`);
            const btn       = document.getElementById(`btn-save-${id}`);
            btn.disabled    = true;
            btn.innerHTML   = '<span class="spinner-border spinner-border-sm"></span>';

            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('id_producto', id);
            formData.append('inventario', invInput.value);
            if (imgInput && imgInput.files[0]) formData.append('imagen', imgInput.files[0]);

            try {
                const res  = await fetch('dashboard.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.ok) {
                    productoEnEdicion = null;
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row && data.producto) {
                        row.classList.remove('edit-row');
                        row.innerHTML = renderFila(data.producto);
                    } else {
                        cargarProductos();
                    }
                } else {
                    alert(data.msg || 'Error al guardar.');
                    btn.disabled = false;
                    btn.textContent = 'Guardar';
                }
            } catch(e) {
                alert('Error de conexion.');
                btn.disabled = false;
                btn.textContent = 'Guardar';
            }
        }

        document.getElementById('prod-imagen').addEventListener('change', function() {
            const file = this.files[0];
            document.getElementById('preview-img').src = file ? URL.createObjectURL(file) : '';
            document.getElementById('preview-imagen').classList.toggle('d-none', !file);
        });

        async function guardarProducto() {
            const nombre    = document.getElementById('prod-nombre').value.trim();
            const precio    = parseFloat(document.getElementById('prod-precio').value);
            const inventario = parseInt(document.getElementById('prod-inventario').value) || 0;
            const imagenFile = document.getElementById('prod-imagen').files[0];
            const msgEl     = document.getElementById('msg-agregar');

            if (!nombre || !precio || precio <= 0) {
                msgEl.className = 'alert alert-danger';
                msgEl.textContent = 'Nombre y precio son obligatorios.';
                return;
            }

            const formData = new FormData();
            formData.append('nombre', nombre);
            formData.append('precio', precio);
            formData.append('inventario', inventario);
            if (imagenFile) formData.append('imagen', imagenFile);

            const btn = document.getElementById('btn-guardar-producto');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

            try {
                const res  = await fetch('dashboard.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.ok) {
                    msgEl.className = 'alert alert-success';
                    msgEl.textContent = 'Producto agregado correctamente.';
                    document.getElementById('prod-nombre').value = '';
                    document.getElementById('prod-precio').value = '';
                    document.getElementById('prod-inventario').value = '0';
                    document.getElementById('prod-imagen').value = '';
                    document.getElementById('preview-imagen').classList.add('d-none');
                    cargarProductos();
                } else {
                    msgEl.className = 'alert alert-danger';
                    msgEl.textContent = data.msg || 'Error al guardar.';
                }
            } catch(e) {
                msgEl.className = 'alert alert-danger';
                msgEl.textContent = 'Error de conexion.';
            }

            btn.disabled = false;
            btn.textContent = 'Guardar producto';
        }

        function pedirEliminar(id, nombre) {
            productoIdAEliminar = id;
            document.getElementById('nombre-a-eliminar').textContent = nombre;
            new bootstrap.Modal(document.getElementById('modalEliminar')).show();
        }

        document.getElementById('btn-confirmar-eliminar').addEventListener('click', async function() {
            if (!productoIdAEliminar) return;
            this.disabled = true;
            this.textContent = 'Eliminando...';
            try {
                const res  = await fetch('dashboard.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_producto: productoIdAEliminar })
                });
                const data = await res.json();
                if (data.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('modalEliminar')).hide();
                    cargarProductos();
                } else {
                    alert(data.msg || 'Error al eliminar.');
                }
            } catch(e) {
                alert('Error de conexion.');
            }
            this.disabled = false;
            this.textContent = 'Eliminar';
            productoIdAEliminar = null;
        });

        document.getElementById('modalAgregarProducto').addEventListener('hidden.bs.modal', function() {
            document.getElementById('msg-agregar').className = 'alert d-none';
        });

        window.addEventListener('DOMContentLoaded', () => {
            const user = JSON.parse(localStorage.getItem('currentUser') || 'null');
            if (user && user.rol === 'administrador') cargarProductos();
        });
    </script>
</body>
</html>
