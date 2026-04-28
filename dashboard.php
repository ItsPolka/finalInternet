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

                <!-- ── Gestión de Productos ─────────────────────────────── -->
                <div class="card border-0 shadow-sm rounded-4 mt-5" id="seccion-productos">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><i class="bi-box-seam me-2"></i>Gestión de productos</h5>
                        <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregarProducto">
                            <i class="bi-plus-lg me-1"></i>Agregar producto
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="tabla-productos">
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
                                        <span class="spinner-border spinner-border-sm me-2"></span>Cargando productos...
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
                    <h5 class="modal-title fw-bold"><i class="bi-plus-circle me-2"></i>Agregar producto</h5>
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
                    <button class="btn btn-dark" id="btn-guardar-producto" onclick="guardarProducto()">
                        <i class="bi-check-lg me-1"></i>Guardar producto
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Confirmar eliminación -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi-trash me-2"></i>Eliminar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-0">
                    <p class="mb-0">¿Eliminar <strong id="nombre-a-eliminar"></strong>? Esta acción no se puede deshacer.</p>
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
    <script>
        // ── Productos ────────────────────────────────────────────────────────
        let productoIdAEliminar = null;

        async function cargarProductos() {
            try {
                const res  = await fetch('driver_productos.php');
                const data = await res.json();
                const tbody = document.getElementById('productos-tbody');
                if (!data.ok || !data.productos.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Sin productos en el catálogo</td></tr>';
                    return;
                }
                tbody.innerHTML = data.productos.map(p => `
                    <tr>
                        <td class="px-4 text-muted">${p.id_producto}</td>
                        <td>
                            ${p.imagen
                                ? `<img src="${p.imagen}" alt="${escHtml(p.nombre)}" style="width:56px;height:42px;object-fit:cover;border-radius:6px;">`
                                : `<div class="d-flex align-items-center justify-content-center bg-light rounded" style="width:56px;height:42px"><i class="bi-image text-muted"></i></div>`}
                        </td>
                        <td class="fw-semibold">${escHtml(p.nombre)}</td>
                        <td>$${parseFloat(p.precio).toFixed(2)}</td>
                        <td>
                            <span class="badge ${p.inventario > 0 ? 'bg-success' : 'bg-secondary'}">
                                ${p.inventario}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-outline-danger btn-sm"
                                    onclick="pedirEliminar(${p.id_producto}, '${escHtml(p.nombre).replace(/'/g,"\\'")}')">
                                <i class="bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            } catch (e) {
                document.getElementById('productos-tbody').innerHTML =
                    '<tr><td colspan="6" class="text-center text-danger py-3">Error al cargar productos</td></tr>';
            }
        }

        function escHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // Vista previa de imagen en el modal
        document.getElementById('prod-imagen').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const url = URL.createObjectURL(file);
                document.getElementById('preview-img').src = url;
                document.getElementById('preview-imagen').classList.remove('d-none');
            } else {
                document.getElementById('preview-imagen').classList.add('d-none');
            }
        });

        async function guardarProducto() {
            const nombre     = document.getElementById('prod-nombre').value.trim();
            const precio     = parseFloat(document.getElementById('prod-precio').value);
            const inventario = parseInt(document.getElementById('prod-inventario').value) || 0;
            const imagenFile = document.getElementById('prod-imagen').files[0];
            const msgEl      = document.getElementById('msg-agregar');

            msgEl.className = 'alert d-none';

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
                const res  = await fetch('driver_productos.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.ok) {
                    msgEl.className = 'alert alert-success';
                    msgEl.textContent = '✓ Producto agregado correctamente.';
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
            } catch (e) {
                msgEl.className = 'alert alert-danger';
                msgEl.textContent = 'Error de conexión.';
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="bi-check-lg me-1"></i>Guardar producto';
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
                const res  = await fetch('driver_productos.php', {
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
                alert('Error de conexión.');
            }
            this.disabled = false;
            this.textContent = 'Eliminar';
            productoIdAEliminar = null;
        });

        // Limpiar modal al cerrarse
        document.getElementById('modalAgregarProducto').addEventListener('hidden.bs.modal', function() {
            document.getElementById('msg-agregar').className = 'alert d-none';
        });

        // Cargar productos cuando el admin esté autenticado
        const _origCheckAuth = window.onload;
        window.addEventListener('DOMContentLoaded', () => {
            const user = JSON.parse(localStorage.getItem('currentUser') || 'null');
            if (user && user.rol === 'administrador') cargarProductos();
        });
    </script>
</body>
</html>
