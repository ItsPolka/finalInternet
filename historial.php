<?php
$host    = 'db';
$db_name = 'tienda_db';
$db_user = 'tienda_user';
$db_pass = 'tienda_pass';

function connectDB() {
    global $host, $db_name, $db_user, $db_pass;
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['json'])) {
    header('Content-Type: application/json');
    $id_usuario = (int)($_GET['id_usuario'] ?? 0);
    if ($id_usuario) {
        try {
            $pdo  = connectDB();
            $stmt = $pdo->prepare(
                'SELECT h.id_compra, h.total, h.fecha_hora_compra,
                        cp.id_producto, cp.cantidad, cp.precio_unitario, cat.nombre, cat.imagen
                 FROM HISTORIAL_COMPRA h
                 JOIN CARRITO_PRODUCTO cp ON cp.id_carrito = h.id_carrito
                 JOIN CATALOGO cat ON cat.id_producto = cp.id_producto
                 WHERE h.id_usuario = ?
                 ORDER BY h.fecha_hora_compra DESC'
            );
            $stmt->execute([$id_usuario]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $orders = [];
            foreach ($rows as $row) {
                $id = $row['id_compra'];
                if (!isset($orders[$id])) {
                    $orders[$id] = [
                        'id_compra'         => (int)$row['id_compra'],
                        'total'             => (float)$row['total'],
                        'fecha_hora_compra' => $row['fecha_hora_compra'],
                        'items'             => []
                    ];
                }
                $orders[$id]['items'][] = [
                    'id_producto' => (int)$row['id_producto'],
                    'nombre'      => $row['nombre'],
                    'precio'      => (float)$row['precio_unitario'],
                    'cantidad'    => (int)$row['cantidad'],
                    'imagen'      => $row['imagen']
                ];
            }
            echo json_encode(['ok' => true, 'orders' => array_values($orders)]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'orders' => []]);
        }
    } else {
        echo json_encode(['ok' => true, 'orders' => []]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Historial de Compras</title>
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
        <link href="css/styles.css" rel="stylesheet" />
        <style>
            .order-card { border-radius: 0.75rem; border: 1px solid #dee2e6; background: #fff; transition: box-shadow 0.2s; }
            .order-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.09); }
            .order-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; border-radius: 0.75rem 0.75rem 0 0; padding: 0.85rem 1.25rem; }
            .badge-order { font-size: 0.8rem; }
            .empty-history { min-height: 350px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
            .item-row { border-bottom: 1px solid #f0f0f0; padding: 0.6rem 0; }
            .item-row:last-child { border-bottom: none; }
            .summary-stats .stat-box { background: #f8f9fa; border-radius: 0.75rem; padding: 1.25rem; text-align: center; }
            .stat-val { font-size: 1.8rem; font-weight: 800; color: #212529; }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container px-4 px-lg-5">
                <a class="navbar-brand fw-bold" href="index.php">Mi Tienda</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                        <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                        <li class="nav-item"><a class="nav-link" href="about.html">Nosotros</a></li>
                        <li class="nav-item"><a class="nav-link active" aria-current="page" href="historial.php">Historial</a></li>
                        <li class="nav-item"><a class="nav-link" href="dashboard.php" id="nav-dashboard" style="display:none">Dashboard</a></li>
                    </ul>
                    <div class="d-flex align-items-center gap-2">
                        <a href="carrito.php" class="btn btn-outline-dark">
                            Carrito
                            <span class="badge bg-dark text-white ms-1 rounded-pill" id="cart-count">0</span>
                        </a>
                        <span class="navbar-text me-2 fw-semibold" id="user-greeting"></span>
                        <button class="btn btn-outline-danger" onclick="logout()">Cerrar Sesion</button>
                    </div>
                </div>
            </div>
        </nav>

        <header class="bg-dark py-4">
            <div class="container px-4 px-lg-5">
                <h2 class="text-white fw-bolder mb-0">Historial de Compras</h2>
            </div>
        </header>

        <section class="py-5">
            <div class="container px-4 px-lg-5">

                <div id="not-logged" class="empty-history" style="display:none">
                    <h4 class="fw-bold text-muted">Acceso Restringido</h4>
                    <p class="text-muted">Necesitas iniciar sesion para ver tu historial de compras.</p>
                    <a href="login.html" class="btn btn-dark btn-lg mt-2">Iniciar Sesion</a>
                </div>

                <div id="empty-history" class="empty-history" style="display:none">
                    <h4 class="fw-bold text-muted">Sin compras aun</h4>
                    <p class="text-muted">Aun no has realizado ninguna compra.</p>
                    <a href="index.php" class="btn btn-dark btn-lg mt-2">Ir a la Tienda</a>
                </div>

                <div id="history-content" style="display:none">
                    <div class="row g-3 mb-5 summary-stats">
                        <div class="col-6 col-md-3">
                            <div class="stat-box">
                                <div class="stat-val" id="stat-orders">0</div>
                                <div class="text-muted small fw-semibold">Pedidos</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-box">
                                <div class="stat-val" id="stat-products">0</div>
                                <div class="text-muted small fw-semibold">Productos comprados</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-box">
                                <div class="stat-val" id="stat-total">$0</div>
                                <div class="text-muted small fw-semibold">Total gastado</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-box">
                                <div class="stat-val" id="stat-avg">$0</div>
                                <div class="text-muted small fw-semibold">Promedio por pedido</div>
                            </div>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-4">Mis Pedidos</h5>
                    <div id="orders-list"></div>
                </div>
            </div>
        </section>

        <footer class="py-5 bg-dark">
            <div class="container"><p class="m-0 text-center text-white">Copyright &copy; Mi Tienda 2024</p></div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function getUser() { return JSON.parse(localStorage.getItem('currentUser') || 'null'); }
            function logout() { localStorage.removeItem('currentUser'); window.location.href = 'login.html'; }

            function escHtml(str) {
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function formatDate(isoString) {
                const d = new Date(isoString);
                return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            }

            let _orders = [];

            async function loadHistory() {
                const user = getUser();

                if (!user) {
                    document.getElementById('not-logged').style.display = '';
                    return;
                }

                document.getElementById('user-greeting').innerHTML = 'Hola, ' + escHtml(user.nombre) +
                    (user.rol === 'administrador' ? ' <span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem">Admin</span>' : '');
                if (user.rol === 'administrador') {
                    const d = document.getElementById('nav-dashboard');
                    if (d) d.style.display = '';
                }

                try {
                    const cartRes  = await fetch('carrito.php?count=1&id_usuario=' + user.id);
                    const cartData = await cartRes.json();
                    document.getElementById('cart-count').textContent = cartData.count || 0;
                } catch(e) {}

                try {
                    const res  = await fetch('historial.php?json=1&id_usuario=' + user.id);
                    const data = await res.json();
                    _orders = data.orders || [];
                    renderHistory(_orders);
                } catch(e) {
                    document.getElementById('empty-history').style.display = '';
                }
            }

            function renderHistory(orders) {
                if (orders.length === 0) {
                    document.getElementById('empty-history').style.display = '';
                    return;
                }

                document.getElementById('history-content').style.display = '';

                const totalSpent = orders.reduce((s, o) => s + o.total, 0);
                const totalItems = orders.reduce((s, o) => s + o.items.reduce((a, i) => a + i.cantidad, 0), 0);
                document.getElementById('stat-orders').textContent   = orders.length;
                document.getElementById('stat-products').textContent = totalItems;
                document.getElementById('stat-total').textContent    = '$' + totalSpent.toFixed(2);
                document.getElementById('stat-avg').textContent      = '$' + (totalSpent / orders.length).toFixed(2);

                const list = document.getElementById('orders-list');
                list.innerHTML = orders.map((order, idx) => `
                    <div class="order-card mb-4">
                        <div class="order-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <span class="fw-bold me-2">Pedido #${orders.length - idx}</span>
                                <span class="badge bg-success badge-order">Completado</span>
                            </div>
                            <div class="text-muted small">${formatDate(order.fecha_hora_compra)}</div>
                            <div class="fw-bold fs-5">Total: $${order.total.toFixed(2)}</div>
                        </div>
                        <div class="p-3">
                            ${order.items.map(item => `
                                <div class="item-row d-flex align-items-center gap-3">
                                    ${item.imagen
                                        ? `<img src="${escHtml(item.imagen)}" class="rounded" style="width:60px;height:60px;object-fit:cover" alt="${escHtml(item.nombre)}" />`
                                        : `<div class="d-flex align-items-center justify-content-center bg-light rounded text-muted" style="width:60px;height:60px;font-size:0.7rem;">Sin imagen</div>`
                                    }
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">${escHtml(item.nombre)}</div>
                                        <div class="text-muted small">$${item.precio.toFixed(2)} x ${item.cantidad}</div>
                                    </div>
                                    <div class="fw-bold">$${(item.precio * item.cantidad).toFixed(2)}</div>
                                </div>
                            `).join('')}
                            <div class="mt-3 d-flex justify-content-between align-items-center">
                                <div class="text-muted small">${order.items.reduce((s,i)=>s+i.cantidad,0)} articulo(s)</div>
                                <button class="btn btn-sm btn-outline-dark" onclick="reorder(${idx})">Volver a pedir</button>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            async function reorder(idx) {
                const user = getUser();
                if (!user) { window.location.href = 'login.html'; return; }
                const order = _orders[idx];
                for (const item of order.items) {
                    await fetch('carrito.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'add', id_usuario: user.id, id_producto: item.id_producto, cantidad: item.cantidad })
                    });
                }
                window.location.href = 'carrito.php';
            }

            loadHistory();
        </script>
    </body>
</html>
