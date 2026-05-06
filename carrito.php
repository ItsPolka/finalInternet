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

function getActiveCartId($pdo, $id_usuario) {
    $stmt = $pdo->prepare(
        'SELECT c.id_carrito FROM CARRITO c
         LEFT JOIN HISTORIAL_COMPRA h ON h.id_carrito = c.id_carrito
         WHERE c.id_usuario = ? AND h.id_compra IS NULL
         ORDER BY c.fecha_creacion DESC LIMIT 1'
    );
    $stmt->execute([$id_usuario]);
    return $stmt->fetchColumn();
}

function getOrCreateCart($pdo, $id_usuario) {
    $id = getActiveCartId($pdo, $id_usuario);
    if (!$id) {
        $pdo->prepare('INSERT INTO CARRITO (id_usuario) VALUES (?)')->execute([$id_usuario]);
        $id = $pdo->lastInsertId();
    }
    return $id;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    header('Content-Type: application/json');
    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $action     = $input['action'] ?? '';
    $id_usuario = (int)($input['id_usuario'] ?? 0);

    if (!$id_usuario) {
        echo json_encode(['ok' => false, 'msg' => 'Usuario no autenticado.']);
        exit;
    }

    try {
        $pdo = connectDB();

        if ($action === 'add') {
            $id_producto = (int)($input['id_producto'] ?? 0);
            $cantidad    = max(1, (int)($input['cantidad'] ?? 1));

            $prod = $pdo->prepare('SELECT precio, inventario FROM CATALOGO WHERE id_producto = ?');
            $prod->execute([$id_producto]);
            $producto = $prod->fetch(PDO::FETCH_ASSOC);

            if (!$producto || $producto['inventario'] <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'Producto no disponible.']);
                exit;
            }

            $id_carrito = getOrCreateCart($pdo, $id_usuario);

            $check = $pdo->prepare('SELECT cantidad FROM CARRITO_PRODUCTO WHERE id_carrito = ? AND id_producto = ?');
            $check->execute([$id_carrito, $id_producto]);
            $existing = $check->fetchColumn();

            if ($existing !== false) {
                $pdo->prepare('UPDATE CARRITO_PRODUCTO SET cantidad = cantidad + ? WHERE id_carrito = ? AND id_producto = ?')
                    ->execute([$cantidad, $id_carrito, $id_producto]);
            } else {
                $pdo->prepare('INSERT INTO CARRITO_PRODUCTO (id_carrito, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)')
                    ->execute([$id_carrito, $id_producto, $cantidad, $producto['precio']]);
            }

            $cnt = $pdo->prepare('SELECT COALESCE(SUM(cantidad), 0) FROM CARRITO_PRODUCTO WHERE id_carrito = ?');
            $cnt->execute([$id_carrito]);
            echo json_encode(['ok' => true, 'cart_count' => (int)$cnt->fetchColumn()]);

        } elseif ($action === 'update') {
            $id_producto = (int)($input['id_producto'] ?? 0);
            $cantidad    = (int)($input['cantidad'] ?? 0);
            $id_carrito  = getActiveCartId($pdo, $id_usuario);

            if ($id_carrito) {
                if ($cantidad <= 0) {
                    $pdo->prepare('DELETE FROM CARRITO_PRODUCTO WHERE id_carrito = ? AND id_producto = ?')
                        ->execute([$id_carrito, $id_producto]);
                } else {
                    $pdo->prepare('UPDATE CARRITO_PRODUCTO SET cantidad = ? WHERE id_carrito = ? AND id_producto = ?')
                        ->execute([$cantidad, $id_carrito, $id_producto]);
                }
            }
            echo json_encode(['ok' => true]);

        } elseif ($action === 'remove') {
            $id_producto = (int)($input['id_producto'] ?? 0);
            $id_carrito  = getActiveCartId($pdo, $id_usuario);

            if ($id_carrito) {
                $pdo->prepare('DELETE FROM CARRITO_PRODUCTO WHERE id_carrito = ? AND id_producto = ?')
                    ->execute([$id_carrito, $id_producto]);
            }
            echo json_encode(['ok' => true]);

        } elseif ($action === 'clear') {
            $id_carrito = getActiveCartId($pdo, $id_usuario);
            if ($id_carrito) {
                $pdo->prepare('DELETE FROM CARRITO_PRODUCTO WHERE id_carrito = ?')->execute([$id_carrito]);
            }
            echo json_encode(['ok' => true]);

        } elseif ($action === 'checkout') {
            $id_carrito = getActiveCartId($pdo, $id_usuario);
            if (!$id_carrito) {
                echo json_encode(['ok' => false, 'msg' => 'Carrito vacio.']);
                exit;
            }

            $items = $pdo->prepare(
                'SELECT cp.id_producto, cp.cantidad, cp.precio_unitario, cat.inventario
                 FROM CARRITO_PRODUCTO cp
                 JOIN CATALOGO cat ON cat.id_producto = cp.id_producto
                 WHERE cp.id_carrito = ?'
            );
            $items->execute([$id_carrito]);
            $cartItems = $items->fetchAll(PDO::FETCH_ASSOC);

            if (empty($cartItems)) {
                echo json_encode(['ok' => false, 'msg' => 'Carrito vacio.']);
                exit;
            }

            foreach ($cartItems as $item) {
                if ($item['inventario'] < $item['cantidad']) {
                    echo json_encode(['ok' => false, 'msg' => 'Stock insuficiente para uno o mas productos.']);
                    exit;
                }
            }

            $total = array_sum(array_map(fn($i) => $i['precio_unitario'] * $i['cantidad'], $cartItems));

            $pdo->beginTransaction();
            try {
                foreach ($cartItems as $item) {
                    $pdo->prepare('UPDATE CATALOGO SET inventario = inventario - ? WHERE id_producto = ?')
                        ->execute([$item['cantidad'], $item['id_producto']]);
                }
                $pdo->prepare('INSERT INTO HISTORIAL_COMPRA (id_usuario, id_carrito, total) VALUES (?, ?, ?)')
                    ->execute([$id_usuario, $id_carrito, $total]);
                $pdo->commit();
                echo json_encode(['ok' => true, 'total' => $total]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'msg' => 'Error al procesar la compra.']);
            }

        } else {
            echo json_encode(['ok' => false, 'msg' => 'Accion no valida.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error de base de datos.']);
    }
    exit;
}

if ($method === 'GET' && isset($_GET['count'])) {
    header('Content-Type: application/json');
    $id_usuario = (int)($_GET['id_usuario'] ?? 0);
    if ($id_usuario) {
        try {
            $pdo  = connectDB();
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(cp.cantidad), 0)
                 FROM CARRITO_PRODUCTO cp
                 JOIN CARRITO c ON c.id_carrito = cp.id_carrito
                 LEFT JOIN HISTORIAL_COMPRA h ON h.id_carrito = c.id_carrito
                 WHERE c.id_usuario = ? AND h.id_compra IS NULL'
            );
            $stmt->execute([$id_usuario]);
            echo json_encode(['ok' => true, 'count' => (int)$stmt->fetchColumn()]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => true, 'count' => 0]);
        }
    } else {
        echo json_encode(['ok' => true, 'count' => 0]);
    }
    exit;
}

if ($method === 'GET' && isset($_GET['json'])) {
    header('Content-Type: application/json');
    $id_usuario = (int)($_GET['id_usuario'] ?? 0);
    if ($id_usuario) {
        try {
            $pdo  = connectDB();
            $stmt = $pdo->prepare(
                'SELECT cp.id_producto, cat.nombre, cp.precio_unitario AS precio, cp.cantidad, cat.imagen
                 FROM CARRITO_PRODUCTO cp
                 JOIN CARRITO c ON c.id_carrito = cp.id_carrito
                 JOIN CATALOGO cat ON cat.id_producto = cp.id_producto
                 LEFT JOIN HISTORIAL_COMPRA h ON h.id_carrito = c.id_carrito
                 WHERE c.id_usuario = ? AND h.id_compra IS NULL'
            );
            $stmt->execute([$id_usuario]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['precio']      = (float)$r['precio'];
                $r['cantidad']    = (int)$r['cantidad'];
                $r['id_producto'] = (int)$r['id_producto'];
            }
            echo json_encode(['ok' => true, 'items' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'items' => []]);
        }
    } else {
        echo json_encode(['ok' => true, 'items' => []]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Carrito</title>
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
        <link href="css/styles.css" rel="stylesheet" />
        <style>
            .cart-item { border-radius: 0.75rem; border: 1px solid #dee2e6; background: #fff; }
            .qty-btn { width: 32px; height: 32px; padding: 0; line-height: 1; font-weight: bold; }
            .empty-cart { min-height: 350px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
            .summary-card { border-radius: 1rem; border: none; box-shadow: 0 2px 16px rgba(0,0,0,0.09); position: sticky; top: 80px; }
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
                        <li class="nav-item"><a class="nav-link" href="historial.php" id="nav-historial" style="display:none">Historial</a></li>
                        <li class="nav-item"><a class="nav-link" href="dashboard.php" id="nav-dashboard" style="display:none">Dashboard</a></li>
                    </ul>
                    <div class="d-flex align-items-center gap-2">
                        <a href="carrito.php" class="btn btn-dark">
                            Carrito
                            <span class="badge bg-light text-dark ms-1 rounded-pill" id="cart-count">0</span>
                        </a>
                        <a href="login.html" class="btn btn-outline-dark" id="btn-login">Iniciar Sesion</a>
                        <span class="navbar-text me-2 fw-semibold" id="user-greeting" style="display:none"></span>
                        <button class="btn btn-outline-danger" id="btn-logout" style="display:none" onclick="logout()">Cerrar Sesion</button>
                    </div>
                </div>
            </div>
        </nav>

        <header class="bg-dark py-4">
            <div class="container px-4 px-lg-5">
                <h2 class="text-white fw-bolder mb-0">Mi Carrito</h2>
            </div>
        </header>

        <section class="py-5">
            <div class="container px-4 px-lg-5">
                <div id="not-logged" class="empty-cart" style="display:none">
                    <h4 class="fw-bold text-muted">Acceso Restringido</h4>
                    <p class="text-muted">Necesitas iniciar sesion para ver tu carrito.</p>
                    <a href="login.html" class="btn btn-dark btn-lg mt-2">Iniciar Sesion</a>
                </div>

                <div id="empty-cart" class="empty-cart" style="display:none">
                    <h4 class="fw-bold text-muted">Tu carrito esta vacio</h4>
                    <p class="text-muted">Agrega productos desde la tienda para verlos aqui.</p>
                    <a href="index.php" class="btn btn-dark btn-lg mt-2">Ir a la Tienda</a>
                </div>

                <div id="cart-content" style="display:none">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">Productos (<span id="items-count">0</span>)</h5>
                                <button class="btn btn-sm btn-outline-danger" onclick="clearCart()">Vaciar carrito</button>
                            </div>
                            <div id="cart-items-list"></div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card summary-card p-4">
                                <h5 class="fw-bold mb-4">Resumen de Compra</h5>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal</span>
                                    <span id="subtotal">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Envio</span>
                                    <span class="text-success">Gratis</span>
                                </div>
                                <hr />
                                <div class="d-flex justify-content-between mb-4 fw-bold fs-5">
                                    <span>Total</span>
                                    <span id="total">$0.00</span>
                                </div>
                                <button class="btn btn-dark w-100 py-2 fw-semibold" id="btn-checkout" onclick="checkout()">
                                    Proceder al Pago
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">
                                    Seguir Comprando
                                </a>
                            </div>
                        </div>
                    </div>
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

            function checkAuth() {
                const user    = getUser();
                const isAdmin = user && user.rol === 'administrador';
                if (user) {
                    document.getElementById('btn-login').style.display = 'none';
                    document.getElementById('btn-logout').style.display = '';
                    document.getElementById('user-greeting').style.display = '';
                    document.getElementById('user-greeting').innerHTML = 'Hola, ' + user.nombre +
                        (isAdmin ? ' <span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem">Admin</span>' : '');
                    document.getElementById('nav-historial').style.display = '';
                    if (isAdmin) document.getElementById('nav-dashboard').style.display = '';
                }
            }

            function escHtml(str) {
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function renderCart(items) {
                const total     = items.reduce((s, i) => s + i.precio * i.cantidad, 0);
                const itemCount = items.reduce((s, i) => s + i.cantidad, 0);

                document.getElementById('cart-count').textContent = itemCount;

                if (items.length === 0) {
                    document.getElementById('empty-cart').style.display = '';
                    document.getElementById('cart-content').style.display = 'none';
                    return;
                }

                document.getElementById('empty-cart').style.display = 'none';
                document.getElementById('cart-content').style.display = '';
                document.getElementById('items-count').textContent = itemCount;
                document.getElementById('subtotal').textContent = '$' + total.toFixed(2);
                document.getElementById('total').textContent = '$' + total.toFixed(2);

                const list = document.getElementById('cart-items-list');
                list.innerHTML = items.map(item => `
                    <div class="cart-item p-3 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            ${item.imagen
                                ? `<img src="${escHtml(item.imagen)}" class="rounded" style="width:80px;height:80px;object-fit:cover" alt="${escHtml(item.nombre)}" />`
                                : `<div class="d-flex align-items-center justify-content-center bg-light rounded text-muted" style="width:80px;height:80px;font-size:0.7rem;">Sin imagen</div>`
                            }
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1">${escHtml(item.nombre)}</h6>
                                <div class="text-muted small">$${item.precio.toFixed(2)} c/u</div>
                                <div class="fw-bold mt-1">Subtotal: $${(item.precio * item.cantidad).toFixed(2)}</div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-2">
                                <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${item.id_producto})">Quitar</button>
                                <div class="d-flex align-items-center gap-1">
                                    <button class="btn btn-outline-secondary qty-btn" onclick="changeQty(${item.id_producto}, -1, ${item.cantidad})">-</button>
                                    <span class="fw-bold px-2">${item.cantidad}</span>
                                    <button class="btn btn-outline-secondary qty-btn" onclick="changeQty(${item.id_producto}, 1, ${item.cantidad})">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            async function loadCart() {
                const user = getUser();
                if (!user) {
                    document.getElementById('not-logged').style.display = '';
                    document.getElementById('empty-cart').style.display = 'none';
                    document.getElementById('cart-content').style.display = 'none';
                    return;
                }
                try {
                    const res  = await fetch('carrito.php?json=1&id_usuario=' + user.id);
                    const data = await res.json();
                    renderCart(data.items || []);
                } catch(e) {
                    renderCart([]);
                }
            }

            async function changeQty(id_producto, delta, currentQty) {
                const user = getUser();
                if (!user) return;
                await fetch('carrito.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update', id_usuario: user.id, id_producto, cantidad: currentQty + delta })
                });
                loadCart();
            }

            async function removeItem(id_producto) {
                const user = getUser();
                if (!user) return;
                await fetch('carrito.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'remove', id_usuario: user.id, id_producto })
                });
                loadCart();
            }

            async function clearCart() {
                const user = getUser();
                if (!user || !confirm('Seguro que quieres vaciar el carrito?')) return;
                await fetch('carrito.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear', id_usuario: user.id })
                });
                loadCart();
            }

            async function checkout() {
                const user = getUser();
                if (!user) { window.location.href = 'login.html'; return; }

                const btn = document.getElementById('btn-checkout');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';

                try {
                    const res  = await fetch('carrito.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'checkout', id_usuario: user.id })
                    });
                    const data = await res.json();
                    if (data.ok) {
                        window.location.href = 'historial.php';
                    } else {
                        alert(data.msg || 'Error al procesar la compra.');
                        btn.disabled = false;
                        btn.textContent = 'Proceder al Pago';
                    }
                } catch(e) {
                    alert('Error de conexion.');
                    btn.disabled = false;
                    btn.textContent = 'Proceder al Pago';
                }
            }

            checkAuth();
            loadCart();
        </script>
    </body>
</html>
