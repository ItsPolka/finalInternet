<?php
header('Content-Type: application/json');

$host = 'db';
$db   = 'tienda_db';
$user = 'tienda_user';
$pass = 'tienda_pass';

$data      = json_decode(file_get_contents('php://input'), true);
$nombre    = trim($data['nombre']    ?? '');
$apellido  = trim($data['apellido']  ?? '');
$usuario   = trim($data['usuario']   ?? '');
$correo    = trim($data['correo']    ?? '');
$contrasena = $data['contrasena']    ?? '';

if (!$nombre || !$apellido || !$usuario || !$correo || !$contrasena) {
    echo json_encode(['ok' => false, 'msg' => 'Por favor completa todos los campos.']);
    exit;
}
if (strlen($contrasena) < 6) {
    echo json_encode(['ok' => false, 'msg' => 'La contrasena debe tener al menos 6 caracteres.']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $chk = $pdo->prepare('SELECT usuario, correo FROM CUENTA WHERE usuario = ? OR correo = ?');
    $chk->execute([$usuario, $correo]);
    $existing = $chk->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ($existing['usuario'] === $usuario) {
            echo json_encode(['ok' => false, 'msg' => 'El nombre de usuario ya esta en uso.']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'El correo ya esta registrado.']);
        }
        exit;
    }

    $ins = $pdo->prepare(
        'INSERT INTO CUENTA (usuario, contrasena, nombre, apellido, correo, rol)
         VALUES (?, ?, ?, ?, ?, "cliente")'
    );
    $ins->execute([$usuario, $contrasena, $nombre, $apellido, $correo]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'ok'   => true,
        'user' => [
            'id'       => (int)$newId,
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'usuario'  => $usuario,
            'correo'   => $correo,
            'rol'      => 'cliente',
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}
