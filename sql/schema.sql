CREATE DATABASE IF NOT EXISTS tienda_db;
USE tienda_db;

CREATE TABLE CUENTA (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  contrasena VARCHAR(255) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  rol ENUM('cliente', 'administrador') NOT NULL DEFAULT 'cliente'
);

CREATE TABLE CATALOGO (
  id_producto INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  inventario INT NOT NULL DEFAULT 0,
  precio DECIMAL(10,2) NOT NULL,
  imagen VARCHAR(255)
);

CREATE TABLE CARRITO (
  id_carrito INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES CUENTA(id_usuario)
);

CREATE TABLE CARRITO_PRODUCTO (
  id_carrito INT NOT NULL,
  id_producto INT NOT NULL,
  cantidad INT NOT NULL DEFAULT 1,
  precio_unitario DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id_carrito, id_producto),
  FOREIGN KEY (id_carrito) REFERENCES CARRITO(id_carrito),
  FOREIGN KEY (id_producto) REFERENCES CATALOGO(id_producto)
);

CREATE TABLE HISTORIAL_COMPRA (
  id_compra INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  id_carrito INT NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  fecha_hora_compra DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES CUENTA(id_usuario),
  FOREIGN KEY (id_carrito) REFERENCES CARRITO(id_carrito)
);