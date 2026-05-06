<?php
header('Content-Type: application/json');

$host = 'db';
$db   = 'tienda_db';
$user = 'tienda_user';
$pass = 'tienda_pass';

$data      = json_decode(file_get_contents('php://input'), true);
$usuario   = trim($data['usuario'] ?? '');
$contrasena = $data['contrasena'] ?? '';

if (!$usuario || !$contrasena) {
    echo json_encode(['ok' => false, 'msg' => 'Por favor completa todos los campos.']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare(
        'SELECT id_usuario, nombre, apellido, usuario, correo, rol
         FROM CUENTA
         WHERE (usuario = ? OR correo = ?) AND contrasena = ?'
    );
    $stmt->execute([$usuario, $usuario, $contrasena]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['ok' => false, 'msg' => 'Usuario o contrasena incorrectos.']);
        exit;
    }

    echo json_encode([
        'ok'   => true,
        'user' => [
            'id'       => $row['id_usuario'],
            'nombre'   => $row['nombre'],
            'apellido' => $row['apellido'],
            'usuario'  => $row['usuario'],
            'correo'   => $row['correo'],
            'rol'      => $row['rol'],
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()]);
}
