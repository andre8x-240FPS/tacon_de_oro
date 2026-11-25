
DROP DATABASE IF EXISTS tacon_de_oro;
CREATE DATABASE tacon_de_oro
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_spanish_ci;
USE tacon_de_oro;

CREATE TABLE usuarios (
    id_usuario      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(100) NOT NULL,
    correo          VARCHAR(120) NOT NULL,
    contrasena      VARCHAR(255) NOT NULL,
    fecha_registro  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rol             ENUM('Cliente','Administrador') NOT NULL DEFAULT 'Cliente',
    telefono        VARCHAR(20)  NULL,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uk_usuarios_correo (correo)        
) ENGINE=InnoDB;

CREATE TABLE productos (
    id_producto  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(120) NOT NULL,
    categoria    ENUM('Caballero','Dama','Infantil') NOT NULL,
    marca        VARCHAR(60)  NOT NULL,
    linea        ENUM('Casual','Deportivo','Formal') NOT NULL,
    descripcion  TEXT         NULL,
    fecha_alta   DATE         NOT NULL DEFAULT (CURRENT_DATE),
    imagen_url   VARCHAR(255) NULL,
    activo       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id_producto),
    KEY idx_productos_categoria (categoria),
    KEY idx_productos_marca (marca)
) ENGINE=InnoDB;


CREATE TABLE variantes (
    id_variante  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_producto  INT UNSIGNED NOT NULL,
    talla        DECIMAL(3,1) NOT NULL,
    color        VARCHAR(40)  NOT NULL,
    precio       DECIMAL(10,2) NOT NULL,
    stock        INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (id_variante),
    UNIQUE KEY uk_variante (id_producto, talla, color),
    CONSTRAINT fk_variantes_producto FOREIGN KEY (id_producto)
        REFERENCES productos (id_producto) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_variantes_precio CHECK (precio > 0),   -- Regla 5
    CONSTRAINT ck_variantes_stock  CHECK (stock >= 0)    -- Regla 5
) ENGINE=InnoDB;


CREATE TABLE carrito (
    id_carrito      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario      INT UNSIGNED NOT NULL,
    fecha_creacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id_carrito),
    UNIQUE KEY uk_carrito_usuario (id_usuario),
    CONSTRAINT fk_carrito_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE detalle_carrito (
    id_detalle_carrito  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_carrito          INT UNSIGNED NOT NULL,
    id_variante         INT UNSIGNED NOT NULL,
    cantidad            INT          NOT NULL,
    precio              DECIMAL(10,2) NOT NULL,
    importe             DECIMAL(12,2) GENERATED ALWAYS AS (cantidad * precio) STORED,
    PRIMARY KEY (id_detalle_carrito),
    UNIQUE KEY uk_carrito_variante (id_carrito, id_variante),
    CONSTRAINT fk_dcarrito_carrito FOREIGN KEY (id_carrito)
        REFERENCES carrito (id_carrito) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_dcarrito_variante FOREIGN KEY (id_variante)
        REFERENCES variantes (id_variante) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_dcarrito_cantidad CHECK (cantidad > 0),
    CONSTRAINT ck_dcarrito_precio   CHECK (precio > 0)
) ENGINE=InnoDB;


CREATE TABLE pedidos (
    id_pedido   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario  INT UNSIGNED NOT NULL,
    fecha       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado      ENUM('Pendiente','Empaquetando','Entregado') NOT NULL DEFAULT 'Pendiente',
    total       DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_pedido),
    KEY idx_pedidos_usuario (id_usuario),
    KEY idx_pedidos_estado (estado),
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_pedidos_total CHECK (total >= 0)
) ENGINE=InnoDB;

CREATE TABLE detalle_pedido (
    id_detallepedido  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pedido         INT UNSIGNED NOT NULL,
    id_variante       INT UNSIGNED NOT NULL,
    cantidad          INT          NOT NULL,
    precio            DECIMAL(10,2) NOT NULL COMMENT 'Precio congelado al momento de la compra',
    importe           DECIMAL(12,2) GENERATED ALWAYS AS (cantidad * precio) STORED,
    PRIMARY KEY (id_detallepedido),
    UNIQUE KEY uk_pedido_variante (id_pedido, id_variante),
    CONSTRAINT fk_dpedido_pedido FOREIGN KEY (id_pedido)
        REFERENCES pedidos (id_pedido) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_dpedido_variante FOREIGN KEY (id_variante)
        REFERENCES variantes (id_variante) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_dpedido_cantidad CHECK (cantidad > 0),
    CONSTRAINT ck_dpedido_precio   CHECK (precio > 0)
) ENGINE=InnoDB;


CREATE TABLE pagos (
    id_pago     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pedido   INT UNSIGNED NOT NULL,
    metodo      ENUM('Tarjeta') NOT NULL DEFAULT 'Tarjeta' COMMENT 'Pago simulado con tarjeta',
    fecha_pago  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    referencia  VARCHAR(60)  NULL,
    monto       DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id_pago),
    UNIQUE KEY uk_pagos_pedido (id_pedido),
    CONSTRAINT fk_pagos_pedido FOREIGN KEY (id_pedido)
        REFERENCES pedidos (id_pedido) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT ck_pagos_monto CHECK (monto > 0)
) ENGINE=InnoDB;

CREATE TABLE envios (
    id_envio       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pedido      INT UNSIGNED NOT NULL,
    costo_envio    DECIMAL(10,2) NOT NULL DEFAULT 0,
    fecha_envio    DATETIME NULL,
    fecha_entrega  DATETIME NULL COMMENT 'Se llena cuando DHL confirma la entrega',
    numero_guia    VARCHAR(40) NULL,
    PRIMARY KEY (id_envio),
    UNIQUE KEY uk_envios_pedido (id_pedido),
    CONSTRAINT fk_envios_pedido FOREIGN KEY (id_pedido)
        REFERENCES pedidos (id_pedido) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT ck_envios_costo CHECK (costo_envio >= 0)
) ENGINE=InnoDB;


CREATE TABLE proveedores (
    id_proveedor  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(120) NOT NULL,
    contacto      VARCHAR(100) NULL,
    telefono      VARCHAR(20)  NULL,
    correo        VARCHAR(120) NULL,
    PRIMARY KEY (id_proveedor)
) ENGINE=InnoDB;

CREATE TABLE orden_compra (
    id_orden      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_proveedor  INT UNSIGNED NOT NULL,
    fecha         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado        ENUM('Pendiente','Recibida','Cancelada') NOT NULL DEFAULT 'Pendiente',
    total         DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_orden),
    CONSTRAINT fk_orden_proveedor FOREIGN KEY (id_proveedor)
        REFERENCES proveedores (id_proveedor) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_orden_total CHECK (total >= 0)
) ENGINE=InnoDB;

CREATE TABLE detalle_orden (
    id_detalle_orden  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_orden          INT UNSIGNED NOT NULL,
    id_variante       INT UNSIGNED NOT NULL,
    cantidad          INT          NOT NULL,
    costo_unitario    DECIMAL(10,2) NOT NULL,
    subtotal          DECIMAL(12,2) GENERATED ALWAYS AS (cantidad * costo_unitario) STORED,
    PRIMARY KEY (id_detalle_orden),
    UNIQUE KEY uk_orden_variante (id_orden, id_variante),
    CONSTRAINT fk_dorden_orden FOREIGN KEY (id_orden)
        REFERENCES orden_compra (id_orden) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_dorden_variante FOREIGN KEY (id_variante)
        REFERENCES variantes (id_variante) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_dorden_cantidad CHECK (cantidad > 0),
    CONSTRAINT ck_dorden_costo    CHECK (costo_unitario > 0)
) ENGINE=InnoDB;


DELIMITER $$

-- Regla 3: no agregar al carrito más pares que el stock disponible
CREATE TRIGGER trg_dcarrito_bi BEFORE INSERT ON detalle_carrito
FOR EACH ROW
BEGIN
    DECLARE v_stock INT;
    SELECT stock INTO v_stock FROM variantes WHERE id_variante = NEW.id_variante;
    IF NEW.cantidad > v_stock THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cantidad solicitada mayor al stock disponible';
    END IF;
END$$

CREATE TRIGGER trg_dcarrito_bu BEFORE UPDATE ON detalle_carrito
FOR EACH ROW
BEGIN
    DECLARE v_stock INT;
    SELECT stock INTO v_stock FROM variantes WHERE id_variante = NEW.id_variante;
    IF NEW.cantidad > v_stock THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cantidad solicitada mayor al stock disponible';
    END IF;
END$$

-- Reglas 3 y 4: al registrar el detalle de un pedido valida y descuenta el stock
CREATE TRIGGER trg_dpedido_bi BEFORE INSERT ON detalle_pedido
FOR EACH ROW
BEGIN
    DECLARE v_stock INT;
    SELECT stock INTO v_stock FROM variantes WHERE id_variante = NEW.id_variante FOR UPDATE;
    IF NEW.cantidad > v_stock THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Stock insuficiente para confirmar el pedido';
    END IF;
END$$

CREATE TRIGGER trg_dpedido_ai AFTER INSERT ON detalle_pedido
FOR EACH ROW
BEGIN
    UPDATE variantes SET stock = stock - NEW.cantidad
    WHERE id_variante = NEW.id_variante;
END$$

-- Regla 6: cancelar un pedido = eliminarlo, y solo si está "Pendiente".
-- Al eliminarlo, los pares regresan al inventario.
-- (Los detalles, el pago y el envío se borran solos por ON DELETE CASCADE)
CREATE TRIGGER trg_pedidos_bd BEFORE DELETE ON pedidos
FOR EACH ROW
BEGIN
    IF OLD.estado <> 'Pendiente' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Solo se puede cancelar un pedido en estado Pendiente';
    END IF;
    UPDATE variantes v
    JOIN detalle_pedido d ON d.id_variante = v.id_variante
    SET v.stock = v.stock + d.cantidad
    WHERE d.id_pedido = OLD.id_pedido;
END$$

-- Al marcar una orden de compra como Recibida, el stock aumenta
CREATE TRIGGER trg_orden_au AFTER UPDATE ON orden_compra
FOR EACH ROW
BEGIN
    IF OLD.estado <> 'Recibida' AND NEW.estado = 'Recibida' THEN
        UPDATE variantes v
        JOIN detalle_orden d ON d.id_variante = v.id_variante
        SET v.stock = v.stock + d.cantidad
        WHERE d.id_orden = NEW.id_orden;
    END IF;
END$$

-- =====================================================================
--  PROCEDIMIENTO: confirmar pedido desde el carrito (checkout)
--  Uso:  CALL sp_confirmar_pedido(id_usuario, 'REF123');
--  El pago es simulado con tarjeta y el envío tiene costo FIJO (v_envio).
--  Todo ocurre en una transacción: si algo falla, no se guarda nada.
-- =====================================================================
CREATE PROCEDURE sp_confirmar_pedido(
    IN p_id_usuario  INT UNSIGNED,
    IN p_referencia  VARCHAR(60)
)
BEGIN
    DECLARE v_envio   DECIMAL(10,2) DEFAULT 150.00;   -- << COSTO FIJO DE ENVÍO: cámbialo aquí
    DECLARE v_carrito INT UNSIGNED;
    DECLARE v_pedido  INT UNSIGNED;
    DECLARE v_total   DECIMAL(12,2);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SELECT id_carrito INTO v_carrito FROM carrito
    WHERE id_usuario = p_id_usuario AND activo = 1;

    IF v_carrito IS NULL OR
       NOT EXISTS (SELECT 1 FROM detalle_carrito WHERE id_carrito = v_carrito) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El carrito está vacío o no existe';
    END IF;

    -- Copia temporal del carrito con el precio vigente. Se necesita porque el
    -- trigger modifica "variantes" y MySQL no permite leerla en el mismo INSERT.
    DROP TEMPORARY TABLE IF EXISTS tmp_items;
    CREATE TEMPORARY TABLE tmp_items AS
        SELECT dc.id_variante, dc.cantidad, v.precio
        FROM detalle_carrito dc JOIN variantes v ON v.id_variante = dc.id_variante
        WHERE dc.id_carrito = v_carrito;

    START TRANSACTION;

    SELECT SUM(cantidad * precio) INTO v_total FROM tmp_items;

    INSERT INTO pedidos (id_usuario, estado, total)
    VALUES (p_id_usuario, 'Pendiente', v_total + v_envio);
    SET v_pedido = LAST_INSERT_ID();

    INSERT INTO detalle_pedido (id_pedido, id_variante, cantidad, precio)
    SELECT v_pedido, id_variante, cantidad, precio FROM tmp_items;  -- el trigger descuenta el stock

    INSERT INTO pagos (id_pedido, metodo, referencia, monto)
    VALUES (v_pedido, 'Tarjeta', p_referencia, v_total + v_envio);

    INSERT INTO envios (id_pedido, costo_envio)
    VALUES (v_pedido, v_envio);

    DELETE FROM detalle_carrito WHERE id_carrito = v_carrito;   -- vacía el carrito

    COMMIT;
    DROP TEMPORARY TABLE IF EXISTS tmp_items;
    SELECT v_pedido AS id_pedido_generado;
END$$

DELIMITER ;
