<?php
/**
 * =====================================================================
 * TACÓN DE ORO - CONEXIÓN A BASE DE DATOS (php/conexion.php)
 * Maneja la conexión PDO a MySQL e inicializa tablas de verificación,
 * catálogo de calzado (incluyendo la colección Nike del PDF), avisos de
 * descuentos por temporada, guías de envío y los 3 administradores.
 * =====================================================================
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'tacon_de_oro');
define('DB_USER', 'root');
define('DB_PASS', '12345678');
define('DB_CHARSET', 'utf8mb4');

/**
 * Devuelve una instancia PDO lista para usar.
 */
function obtenerConexion(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);

    inicializarSistema($pdo);

    return $pdo;
}

/**
 * Inicializa tablas auxiliares, columna de paquetería, administradores
 * y carga inicial del catálogo con los modelos Nike del PDF.
 */
function inicializarSistema(PDO $pdo): void {
    // 1. Tabla para códigos de verificación por correo
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
    ");

    // 2. Tabla auxiliar para guardar el detalle de artículos de pedidos web
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
    ");

    // 3. Columna paqueteria en envios para gestión completa de guías de envío
    $colPaqueteria = $pdo->query("SHOW COLUMNS FROM envios LIKE 'paqueteria'")->fetch();
    if (!$colPaqueteria) {
        $pdo->exec("ALTER TABLE envios ADD COLUMN paqueteria VARCHAR(50) NOT NULL DEFAULT 'DHL' AFTER costo_envio");
    }

    // 4. Tabla de catálogo administrable en tiempo real
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
    ");

    // 5. Tabla de avisos de descuentos por temporada publicados por administradores
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS avisos_temporada (
            id_aviso             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            titulo               VARCHAR(120) NOT NULL,
            mensaje              VARCHAR(300) NOT NULL,
            etiqueta_descuento   VARCHAR(40)  NULL,
            activo               TINYINT(1)   NOT NULL DEFAULT 1,
            creado_en            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_aviso)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
    ");

    // 6. Semilla inicial de catálogo (modelos Nike del PDF + modelos de la boutique)
    $totalCatalogo = (int) $pdo->query('SELECT COUNT(*) FROM catalogo_productos')->fetchColumn();
    if ($totalCatalogo === 0) {
        sembrarCatalogoInicial($pdo);
    }

    // Semilla inicial de aviso de temporada si no hay ninguno registrado
    $totalAvisos = (int) $pdo->query('SELECT COUNT(*) FROM avisos_temporada')->fetchColumn();
    if ($totalAvisos === 0) {
        $stmtAviso = $pdo->prepare('
            INSERT INTO avisos_temporada (titulo, mensaje, etiqueta_descuento, activo)
            VALUES (?, ?, ?, 1)
        ');
        $stmtAviso->execute([
            'Nueva Colección Nike & Ofertas de Temporada',
            'Descubre los nuevos modelos Nike Air Max, Air Force 1, Blazer Mid \'77 y aprovecha descuentos especiales de temporada con envío nacional.',
            'Hasta 25% OFF'
        ]);
    }

    // 7. Migración de correos reales de administradores y semilla inicial
    $migracionesAdmin = [
        'andres.flores@tacondeoro.com' => [
            'nuevo_correo' => 'andresflores0003@gmail.com',
            'nombre'       => 'Andrés Antonio Flores',
        ],
        'ethan.mata@tacondeoro.com' => [
            'nuevo_correo' => 'etanceron123@gmail.com',
            'nombre'       => 'Ethan Armando Mata Cerón',
        ],
    ];

    $stmtPorCorreo = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = ? LIMIT 1');
    foreach ($migracionesAdmin as $correoViejo => $datosMig) {
        $stmtPorCorreo->execute([$correoViejo]);
        $filaVieja = $stmtPorCorreo->fetch();
        if ($filaVieja) {
            $stmtPorCorreo->execute([$datosMig['nuevo_correo']]);
            $filaNueva = $stmtPorCorreo->fetch();
            if ($filaNueva) {
                // Si el correo real ya existía, asegurar rol Administrador y limpiar el ficticio
                $pdo->prepare("UPDATE usuarios SET rol = 'Administrador' WHERE id_usuario = ?")
                    ->execute([$filaNueva['id_usuario']]);
                $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ? AND NOT EXISTS (SELECT 1 FROM pedidos WHERE id_usuario = ?)")
                    ->execute([$filaVieja['id_usuario'], $filaVieja['id_usuario']]);
            } else {
                $pdo->prepare('UPDATE usuarios SET correo = ? WHERE id_usuario = ?')
                    ->execute([$datosMig['nuevo_correo'], $filaVieja['id_usuario']]);
            }
        }
    }

    // Sembrar administradores iniciales únicamente si no existe ningún administrador aún
    $totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Administrador'")->fetchColumn();
    if ($totalAdmins === 0) {
        $administradores = [
            [
                'nombre'     => 'Andrés Antonio Flores',
                'correo'     => 'andresflores0003@gmail.com',
                'contrasena' => 'AdminAndres2026!',
                'telefono'   => '4771002001',
            ],
            [
                'nombre'     => 'Ethan Armando Mata Cerón',
                'correo'     => 'etanceron123@gmail.com',
                'contrasena' => 'AdminEthan2026!',
                'telefono'   => '4771002002',
            ],
            [
                'nombre'     => 'Enrique Soto Andrade',
                'correo'     => 'enrique.soto@tacondeoro.com',
                'contrasena' => 'AdminEnrique2026!',
                'telefono'   => '4771002003',
            ],
        ];

        $stmtInsertar = $pdo->prepare("
            INSERT INTO usuarios (nombre, correo, contrasena, rol, telefono)
            VALUES (?, ?, ?, 'Administrador', ?)
        ");

        foreach ($administradores as $admin) {
            $stmtPorCorreo->execute([$admin['correo']]);
            if (!$stmtPorCorreo->fetch()) {
                $hash = password_hash($admin['contrasena'], PASSWORD_DEFAULT);
                $stmtInsertar->execute([
                    $admin['nombre'],
                    $admin['correo'],
                    $hash,
                    $admin['telefono'],
                ]);
            }
        }
    }
}

/**
 * Inserta los 9 modelos de tenis Nike del catálogo PDF junto con los modelos base.
 */
function sembrarCatalogoInicial(PDO $pdo): void {
    $modelos = [
        // ================= COLECCIÓN NIKE (DEL CATÁLOGO PDF) =================
        [
            'nombre'        => 'Nike Air Max Excee',
            'marca'         => 'Nike',
            'categoria'     => 'Caballero',
            'linea'         => 'Deportivo',
            'descripcion'   => 'Inspirado en el icónico Air Max 90 con unidad de aire visible en tres ventanas, malla transpirable y diseño alargado.',
            'tallas'        => [25.0, 25.5, 26.0, 26.5, 27.0, 27.5, 28.0],
            'colores'       => ['Blanco', 'Negro', 'Beige', 'Café'],
            'precio_base'   => 1839.00,
            'descuento_pct' => 0,
            'precio_final'  => 1839.00,
            'stock'         => 18,
            'es_nuevo'      => 1,
        ],
        [
            'nombre'        => "Nike Air More Uptempo '96",
            'marca'         => 'Nike',
            'categoria'     => 'Caballero',
            'linea'         => 'Deportivo',
            'descripcion'   => 'Clásico del básquetbol de los años 90 con amortiguación Air de largo completo, correas elásticas y gráfico AIR audaz.',
            'tallas'        => [25.5, 26.0, 26.5, 27.0, 27.5, 28.0, 28.5],
            'colores'       => ['Blanco', 'Negro', 'Rosa', 'Azul'],
            'precio_base'   => 4099.00,
            'descuento_pct' => 0,
            'precio_final'  => 4099.00,
            'stock'         => 10,
            'es_nuevo'      => 1,
        ],
        [
            'nombre'        => 'Nike Pantheos Drot',
            'marca'         => 'Nike',
            'categoria'     => 'Caballero',
            'linea'         => 'Casual',
            'descripcion'   => 'Silueta retro de running de los 90 que combina gamuza sintética, malla ligera y entresuela de espuma suave para uso diario.',
            'tallas'        => [25.0, 25.5, 26.0, 26.5, 27.0, 27.5],
            'colores'       => ['Blanco', 'Negro', 'Verde', 'Gris'],
            'precio_base'   => 1499.00,
            'descuento_pct' => 0,
            'precio_final'  => 1499.00,
            'stock'         => 16,
            'es_nuevo'      => 1,
        ],
        [
            'nombre'        => "Nike Air Force 1 '07 Triple White",
            'marca'         => 'Nike',
            'categoria'     => 'Caballero',
            'linea'         => 'Casual',
            'descripcion'   => 'Leyenda del calzado urbano con revestimientos cosidos duraderos, amortiguación Nike Air y cuello acolchado de corte bajo.',
            'tallas'        => [24.0, 24.5, 25.0, 25.5, 26.0, 26.5, 27.0, 27.5, 28.0],
            'colores'       => ['Blanco', 'Gris', 'Negro', 'Azul', 'Dorado'],
            'precio_base'   => 2499.00,
            'descuento_pct' => 0,
            'precio_final'  => 2499.00,
            'stock'         => 24,
            'es_nuevo'      => 1,
        ],
        [
            'nombre'        => 'Nike Flex Trainer 7',
            'marca'         => 'Nike',
            'categoria'     => 'Dama',
            'linea'         => 'Deportivo',
            'descripcion'   => 'Tenis de entrenamiento ligero con malla transpirable, cables de soporte en el mediopié y suela de tracción flexible.',
            'tallas'        => [22.5, 23.0, 23.5, 24.0, 24.5, 25.0, 25.5],
            'colores'       => ['Blanco', 'Negro', 'Morado', 'Celeste'],
            'precio_base'   => 1490.00,
            'descuento_pct' => 0,
            'precio_final'  => 1490.00,
            'stock'         => 20,
            'es_nuevo'      => 0,
        ],
        [
            'nombre'        => "Nike Blazer Mid '77 Vintage",
            'marca'         => 'Nike',
            'categoria'     => 'Caballero',
            'linea'         => 'Casual',
            'descripcion'   => 'Diseño clásico de cancha de los años 70 en corte medio con acabado retro en la entresuela y suela de goma tipo espiga.',
            'tallas'        => [24.0, 24.5, 25.0, 25.5, 26.0, 26.5, 27.0, 27.5],
            'colores'       => ['Blanco', 'Negro', 'Verde', 'Dorado'],
            'precio_base'   => 2399.00,
            'descuento_pct' => 0,
            'precio_final'  => 2399.00,
            'stock'         => 15,
            'es_nuevo'      => 1,
        ],
        [
            'nombre'        => 'Nike Air Max Sequent 4',
            'marca'         => 'Nike',
            'categoria'     => 'Caballero',
            'linea'         => 'Deportivo',
            'descripcion'   => 'Tenis de running con parte superior tejida adaptable y unidad Max Air en forma de U para absorción reactiva en cada pisada.',
            'tallas'        => [25.0, 25.5, 26.0, 26.5, 27.0, 27.5, 28.0],
            'colores'       => ['Gris', 'Negro', 'Rojo', 'Azul'],
            'precio_base'   => 1199.00,
            'descuento_pct' => 25,
            'precio_final'  => 899.00,
            'stock'         => 22,
            'es_nuevo'      => 0,
        ],
        [
            'nombre'        => 'Nike Renew Serenity Run',
            'marca'         => 'Nike',
            'categoria'     => 'Dama',
            'linea'         => 'Deportivo',
            'descripcion'   => 'Diseñado para corredoras que buscan espuma ultrasuave Nike Renew, ajuste envolvente y confort prolongado.',
            'tallas'        => [22.5, 23.0, 23.5, 24.0, 24.5, 25.0, 25.5],
            'colores'       => ['Blanco', 'Negro', 'Rojo', 'Rosa'],
            'precio_base'   => 1899.00,
            'descuento_pct' => 0,
            'precio_final'  => 1899.00,
            'stock'         => 14,
            'es_nuevo'      => 1,
        ],
        [
            'nombre'        => 'Nike Kyrie Flytrap IV',
            'marca'         => 'Nike',
            'categoria'     => 'Caballero',
            'linea'         => 'Deportivo',
            'descripcion'   => 'Calzado de básquetbol ligero con bordes de tracción lateral, unidad Zoom Air en el antepié y soporte firme en el tobillo.',
            'tallas'        => [25.5, 26.0, 26.5, 27.0, 27.5, 28.0, 28.5],
            'colores'       => ['Blanco', 'Negro', 'Rojo', 'Azul'],
            'precio_base'   => 2399.00,
            'descuento_pct' => 0,
            'precio_final'  => 2399.00,
            'stock'         => 12,
            'es_nuevo'      => 1,
        ],

        // ================= LÍNEA CLÁSICA BOUTIQUE TACÓN DE ORO =================
        [
            'nombre'        => 'Zapato de Vestir Oxford Clásico',
            'marca'         => 'Tacón de Oro',
            'categoria'     => 'Caballero',
            'linea'         => 'Formal',
            'descripcion'   => 'Calzado de corte formal con ajuste de agujeta y suela resistente.',
            'tallas'        => [25.5, 26.0, 26.5, 27.0, 27.5, 28.0],
            'colores'       => ['Negro', 'Café'],
            'precio_base'   => 1650.00,
            'descuento_pct' => 12,
            'precio_final'  => 1450.00,
            'stock'         => 14,
            'es_nuevo'      => 0,
        ],
        [
            'nombre'        => 'Zapatilla de Gala Tacón Stiletto',
            'marca'         => 'Tacón de Oro',
            'categoria'     => 'Dama',
            'linea'         => 'Formal',
            'descripcion'   => 'Zapatilla elegante con tacón estilizado y plantilla confortable.',
            'tallas'        => [23.0, 23.5, 24.0, 24.5, 25.0],
            'colores'       => ['Dorado', 'Negro', 'Rojo'],
            'precio_base'   => 1299.00,
            'descuento_pct' => 0,
            'precio_final'  => 1299.00,
            'stock'         => 10,
            'es_nuevo'      => 0,
        ],
        [
            'nombre'        => 'Mocasín Cómodo de Descanso',
            'marca'         => 'Tacón de Oro',
            'categoria'     => 'Caballero',
            'linea'         => 'Casual',
            'descripcion'   => 'Calzado casual sin agujetas con plantilla acolchonada de uso diario.',
            'tallas'        => [26.0, 26.5, 27.0, 27.5],
            'colores'       => ['Café', 'Azul'],
            'precio_base'   => 1150.00,
            'descuento_pct' => 15,
            'precio_final'  => 980.00,
            'stock'         => 18,
            'es_nuevo'      => 0,
        ],
        [
            'nombre'        => 'Botín Casual de Gamuza',
            'marca'         => 'Tacón de Oro',
            'categoria'     => 'Dama',
            'linea'         => 'Casual',
            'descripcion'   => 'Botín de media caña con cierre lateral y tacón cuadrado cómodo.',
            'tallas'        => [23.5, 24.0, 24.5, 25.0, 25.5],
            'colores'       => ['Café', 'Negro'],
            'precio_base'   => 1100.00,
            'descuento_pct' => 0,
            'precio_final'  => 1100.00,
            'stock'         => 8,
            'es_nuevo'      => 0,
        ],
        [
            'nombre'        => 'Sandalia Tacón Medio con Pulsera',
            'marca'         => 'Tacón de Oro',
            'categoria'     => 'Dama',
            'linea'         => 'Formal',
            'descripcion'   => 'Sandalia de vestir con pulsera al tobillo y tacón ancho de 6 cm.',
            'tallas'        => [23.0, 24.0, 24.5, 25.0],
            'colores'       => ['Dorado', 'Blanco', 'Negro'],
            'precio_base'   => 1050.00,
            'descuento_pct' => 15,
            'precio_final'  => 890.00,
            'stock'         => 12,
            'es_nuevo'      => 0,
        ],
        [
            'nombre'        => 'Zapato Escolar Resistente',
            'marca'         => 'Tacón de Oro',
            'categoria'     => 'Infantil',
            'linea'         => 'Formal',
            'descripcion'   => 'Calzado escolar con puntera reforzada y correa de ajuste.',
            'tallas'        => [18.0, 19.0, 20.0, 21.0, 22.0],
            'colores'       => ['Negro'],
            'precio_base'   => 550.00,
            'descuento_pct' => 0,
            'precio_final'  => 550.00,
            'stock'         => 25,
            'es_nuevo'      => 0,
        ],
        [
            'nombre'        => 'Tenis Infantil con Velcro',
            'marca'         => 'Tacón de Oro',
            'categoria'     => 'Infantil',
            'linea'         => 'Deportivo',
            'descripcion'   => 'Tenis suave y ligero con doble tira de velcro para mayor seguridad.',
            'tallas'        => [17.0, 18.0, 19.0, 20.0],
            'colores'       => ['Azul', 'Rojo'],
            'precio_base'   => 620.00,
            'descuento_pct' => 21,
            'precio_final'  => 490.00,
            'stock'         => 15,
            'es_nuevo'      => 0,
        ],
    ];

    $stmt = $pdo->prepare('
        INSERT INTO catalogo_productos
            (nombre, marca, categoria, linea, descripcion, tallas_json, colores_json,
             precio_base, descuento_pct, precio_final, stock, es_nuevo, visible)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ');

    foreach ($modelos as $m) {
        $stmt->execute([
            $m['nombre'],
            $m['marca'],
            $m['categoria'],
            $m['linea'],
            $m['descripcion'],
            json_encode($m['tallas']),
            json_encode($m['colores'], JSON_UNESCAPED_UNICODE),
            $m['precio_base'],
            $m['descuento_pct'],
            $m['precio_final'],
            $m['stock'],
            $m['es_nuevo'],
        ]);
    }
}
