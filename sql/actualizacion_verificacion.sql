-- =====================================================================
-- ACTUALIZACIÓN DE BASE DE DATOS: tacon_de_oro
-- Verificación por correo (Resend), catálogo administrable (Colección Nike),
-- avisos de descuentos por temporada y guías de envío.
-- =====================================================================
USE tacon_de_oro;

CREATE TABLE IF NOT EXISTS codigos_verificacion (
    id_codigo    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    correo       VARCHAR(120) NOT NULL,
    codigo       CHAR(6)      NOT NULL,
    tipo         ENUM('registro','login') NOT NULL,
    datos_extra  JSON         NULL,
    expira_en    DATETIME     NOT NULL,
    usado        TINYINT(1)   NOT NULL DEFAULT 0,
    creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_codigo),
    KEY idx_verificacion_busqueda (correo, tipo, usado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos_articulos (
    id_item         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pedido       INT UNSIGNED NOT NULL,
    nombre_articulo VARCHAR(150) NOT NULL,
    talla           DECIMAL(3,1) NOT NULL,
    color           VARCHAR(40)  NOT NULL,
    precio          DECIMAL(10,2) NOT NULL,
    cantidad        INT          NOT NULL DEFAULT 1,
    PRIMARY KEY (id_item),
    KEY idx_pa_pedido (id_pedido),
    CONSTRAINT fk_pa_pedido FOREIGN KEY (id_pedido)
        REFERENCES pedidos (id_pedido) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS catalogo_productos (
    id_articulo   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(150) NOT NULL,
    marca         VARCHAR(60)  NOT NULL DEFAULT 'Nike',
    categoria     ENUM('Caballero','Dama','Infantil') NOT NULL,
    linea         ENUM('Casual','Deportivo','Formal') NOT NULL,
    descripcion   TEXT         NULL,
    tallas_json   JSON         NOT NULL,
    colores_json  JSON         NOT NULL,
    precio_base   DECIMAL(10,2) NOT NULL,
    descuento_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
    precio_final  DECIMAL(10,2) NOT NULL,
    stock         INT          NOT NULL DEFAULT 0,
    es_nuevo      TINYINT(1)   NOT NULL DEFAULT 0,
    visible       TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_articulo),
    KEY idx_cat_visible (visible),
    KEY idx_cat_categoria (categoria)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS avisos_temporada (
    id_aviso             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo               VARCHAR(120) NOT NULL,
    mensaje              VARCHAR(300) NOT NULL,
    etiqueta_descuento   VARCHAR(40)  NULL,
    activo               TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_aviso)
) ENGINE=InnoDB;

-- Actualizar correos reales de los administradores Andrés y Ethan
UPDATE usuarios SET correo = 'andresflores0003@gmail.com' WHERE correo = 'andres.flores@tacondeoro.com';
UPDATE usuarios SET correo = 'etanceron123@gmail.com' WHERE correo = 'ethan.mata@tacondeoro.com';
