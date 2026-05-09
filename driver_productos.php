<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$host = 'db';
$db   = 'tienda_db';
$user = 'tienda_user';
$pass = 'tienda_pass';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de conexion: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $productos = $pdo->query(
        'SELECT id_producto, nombre, inventario, precio, imagen FROM CATALOGO ORDER BY id_producto DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'productos' => $productos]);
    exit;
}

if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id_producto = intval($_POST['id_producto'] ?? 0);
    $inventario  = intval($_POST['inventario']  ?? 0);

    if (!$id_producto) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'ID invalido.']);
        exit;
    }

    $updateImagen = false;
    $imagenPath   = null;

    if (!empty($_FILES['imagen']['tmp_name'])) {
        $rowImg = $pdo->prepare('SELECT imagen FROM CATALOGO WHERE id_producto = ?');
        $rowImg->execute([$id_producto]);
        $old = $rowImg->fetch(PDO::FETCH_ASSOC);
        if ($old && $old['imagen'] && file_exists(__DIR__ . '/' . $old['imagen'])) {
            unlink(__DIR__ . '/' . $old['imagen']);
        }

        $uploadDir = __DIR__ . '/uploads/productos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext     = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowed)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Formato de imagen no permitido.']);
            exit;
        }

        $filename = uniqid('prod_', true) . '.' . $ext;
        $destino  = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'Error al guardar la imagen.']);
            exit;
        }

        $imagenPath   = 'uploads/productos/' . $filename;
        $updateImagen = true;
    }

    if ($updateImagen) {
        $pdo->prepare('UPDATE CATALOGO SET inventario = ?, imagen = ? WHERE id_producto = ?')
            ->execute([$inventario, $imagenPath, $id_producto]);
    } else {
        $pdo->prepare('UPDATE CATALOGO SET inventario = ? WHERE id_producto = ?')
            ->execute([$inventario, $id_producto]);
    }

    $updated = $pdo->prepare('SELECT id_producto, nombre, inventario, precio, imagen FROM CATALOGO WHERE id_producto = ?');
    $updated->execute([$id_producto]);
    $producto = $updated->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'msg' => 'Producto actualizado.', 'producto' => $producto]);
    exit;
}

if ($method === 'POST') {
    $nombre     = trim($_POST['nombre']       ?? '');
    $inventario = intval($_POST['inventario'] ?? 0);
    $precio     = floatval($_POST['precio']   ?? 0);

    if (!$nombre || $precio <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Nombre y precio son obligatorios.']);
        exit;
    }

    $imagenPath = null;

    if (!empty($_FILES['imagen']['tmp_name'])) {
        $uploadDir = __DIR__ . '/uploads/productos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext     = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowed)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Formato de imagen no permitido.']);
            exit;
        }

        $filename = uniqid('prod_', true) . '.' . $ext;
        $destino  = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => 'Error al guardar la imagen.']);
            exit;
        }
        $imagenPath = 'uploads/productos/' . $filename;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO CATALOGO (nombre, inventario, precio, imagen) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$nombre, $inventario, $precio, $imagenPath]);
    $id = $pdo->lastInsertId();

    echo json_encode([
        'ok'       => true,
        'msg'      => 'Producto agregado.',
        'producto' => [
            'id_producto' => $id,
            'nombre'      => $nombre,
            'inventario'  => $inventario,
            'precio'      => $precio,
            'imagen'      => $imagenPath,
        ]
    ]);
    exit;
}

if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id    = intval($input['id_producto'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'ID invalido.']);
        exit;
    }

    $row = $pdo->prepare('SELECT imagen FROM CATALOGO WHERE id_producto = ?');
    $row->execute([$id]);
    $producto = $row->fetch(PDO::FETCH_ASSOC);
    if ($producto && $producto['imagen'] && file_exists(__DIR__ . '/' . $producto['imagen'])) {
        unlink(__DIR__ . '/' . $producto['imagen']);
    }

    $stmt = $pdo->prepare('DELETE FROM CATALOGO WHERE id_producto = ?');
    $stmt->execute([$id]);

    echo json_encode(['ok' => true, 'msg' => 'Producto eliminado.']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'msg' => 'Metodo no permitido.']);
