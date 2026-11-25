<?php
/**
 * =====================================================================
 * TACÓN DE ORO - API BACKEND EN PHP (php/api.php)
 * Endpoints para Registro y Login con Código de Verificación por correo
 * (Resend), catálogo dinámico (Colección Nike PDF), perfil de cliente,
 * y panel completo de administración (productos, precios, descuentos,
 * ocultar/eliminar, avisos de temporada y guías de envío).
 * =====================================================================
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/correo.php';

// =====================================================================
// FUNCIONES AUXILIARES Y VALIDACIONES DE SEGURIDAD
// =====================================================================
function responderJSON(array $datos, int $codigoHttp = 200): void {
    http_response_code($codigoHttp);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function leerCuerpoJSON(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decodificado = json_decode($raw, true);
    return is_array($decodificado) ? $decodificado : [];
}

function validarCorreoReal(string $correo): ?string {
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return 'Ingresa un correo electrónico con formato válido.';
    }

    $partes = explode('@', $correo);
    if (count($partes) !== 2) {
        return 'Ingresa un correo electrónico válido.';
    }

    $dominio = strtolower(trim($partes[1]));
    $dominiosBloqueados = [
        'example.com', 'example.org', 'example.net', 'test.com', 'localhost',
        'correo.com', 'fake.com', 'mailinator.com', 'tempmail.com', 'yopmail.com',
        '10minutemail.com', 'guerrillamail.com', 'trashmail.com', 'throwaway.email',
    ];

    if (in_array($dominio, $dominiosBloqueados, true) || !str_contains($dominio, '.')) {
        return 'El dominio del correo no está permitido. Usa un correo electrónico real.';
    }

    if (function_exists('checkdnsrr')) {
        $tieneMX = @checkdnsrr($dominio, 'MX');
        $tieneA  = $tieneMX ? true : @checkdnsrr($dominio, 'A');
        if (!$tieneMX && !$tieneA) {
            return 'El dominio del correo electrónico no existe o no puede recibir mensajes. Verifica tu correo.';
        }
    }

    return null;
}

function validarContrasenaSegura(string $contrasena): ?string {
    $errores = [];

    if (strlen($contrasena) < 8) {
        $errores[] = 'mínimo 8 caracteres';
    }
    if (!preg_match('/[A-Z]/', $contrasena)) {
        $errores[] = 'al menos una letra mayúscula';
    }
    if (!preg_match('/[0-9]/', $contrasena)) {
        $errores[] = 'al menos un número';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $contrasena)) {
        $errores[] = 'al menos un símbolo especial (ej. !@#$%&*)';
    }

    if (count($errores) > 0) {
        return 'La contraseña no es segura. Debe contener: ' . implode(', ', $errores) . '.';
    }

    return null;
}

function requerirSesion(): array {
    $sesion = $_SESSION['usuario'] ?? null;
    if (!$sesion || empty($sesion['id_usuario'])) {
        responderJSON(['ok' => false, 'mensaje' => 'Debes iniciar sesión para realizar esta acción.'], 401);
    }
    return $sesion;
}

function requerirAdmin(): array {
    $sesion = $_SESSION['usuario'] ?? null;
    if (!$sesion || ($sesion['rol'] ?? '') !== 'Administrador') {
        responderJSON(['ok' => false, 'mensaje' => 'Acceso denegado. Acción exclusiva para administradores.'], 403);
    }
    return $sesion;
}

function formatearProductoFila(array $row): array {
    $tallasRaw  = json_decode((string) ($row['tallas_json'] ?? '[]'), true);
    $coloresRaw = json_decode((string) ($row['colores_json'] ?? '[]'), true);

    $tallas = is_array($tallasRaw) ? array_map('floatval', $tallasRaw) : [];
    $colores = is_array($coloresRaw) ? array_values(array_map('strval', $coloresRaw)) : [];

    $precioBase   = (float) $row['precio_base'];
    $descuentoPct = (int) $row['descuento_pct'];
    $precioFinal  = (float) $row['precio_final'];
    $esOferta     = $descuentoPct > 0 && $precioFinal < $precioBase;

    return [
        'id'             => (int) $row['id_articulo'],
        'nombre'         => (string) $row['nombre'],
        'marca'          => (string) $row['marca'],
        'categoria'      => (string) $row['categoria'],
        'linea'          => (string) $row['linea'],
        'descripcion'    => (string) ($row['descripcion'] ?? ''),
        'tallas'         => $tallas,
        'colores'        => $colores,
        'precioBase'     => $precioBase,
        'descuentoPct'   => $descuentoPct,
        'precio'         => $precioFinal,
        'precioAnterior' => $esOferta ? $precioBase : null,
        'stock'          => (int) $row['stock'],
        'esOferta'       => $esOferta,
        'esNuevo'        => ((int) $row['es_nuevo']) === 1,
        'visible'        => ((int) $row['visible']) === 1,
    ];
}

function obtenerArticulosDePedido(PDO $pdo, int $idPedido): array {
    $stmtWeb = $pdo->prepare('
        SELECT nombre_articulo, talla, color, precio, cantidad
        FROM pedidos_articulos
        WHERE id_pedido = ?
    ');
    $stmtWeb->execute([$idPedido]);
    $articulos = $stmtWeb->fetchAll();

    if (count($articulos) === 0) {
        $stmtDB = $pdo->prepare('
            SELECT pr.nombre AS nombre_articulo, v.talla, v.color, dp.precio, dp.cantidad
            FROM detalle_pedido dp
            INNER JOIN variantes v ON v.id_variante = dp.id_variante
            INNER JOIN productos pr ON pr.id_producto = v.id_producto
            WHERE dp.id_pedido = ?
        ');
        $stmtDB->execute([$idPedido]);
        $articulos = $stmtDB->fetchAll();
    }

    return $articulos;
}

function generarYGuardarCodigo(PDO $pdo, string $correo, string $tipo, ?array $datosExtra = null): string {
    $stmtLimpiar = $pdo->prepare('UPDATE codigos_verificacion SET usado = 1 WHERE correo = ? AND tipo = ? AND usado = 0');
    $stmtLimpiar->execute([$correo, $tipo]);

    $codigo   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiraEn = date('Y-m-d H:i:s', time() + 600);

    $stmt = $pdo->prepare('
        INSERT INTO codigos_verificacion (correo, codigo, tipo, datos_extra, expira_en)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $correo,
        $codigo,
        $tipo,
        $datosExtra ? json_encode($datosExtra, JSON_UNESCAPED_UNICODE) : null,
        $expiraEn
    ]);

    return $codigo;
}

// =====================================================================
// ENRUTADOR PRINCIPAL DE LA API
// =====================================================================
$accion = $_GET['accion'] ?? '';

try {
    $pdo = obtenerConexion();

    switch ($accion) {

        // -------------------------------------------------------------
        // 0. CATÁLOGO PÚBLICO Y AVISOS DE TEMPORADA ACTIVOS
        // -------------------------------------------------------------
        case 'catalogo_publico': {
            $filas = $pdo->query('
                SELECT * FROM catalogo_productos
                WHERE visible = 1
                ORDER BY id_articulo ASC
            ')->fetchAll();

            $articulos = array_map('formatearProductoFila', $filas);

            $avisos = $pdo->query('
                SELECT id_aviso, titulo, mensaje, etiqueta_descuento, creado_en
                FROM avisos_temporada
                WHERE activo = 1
                ORDER BY id_aviso DESC
            ')->fetchAll();

            responderJSON([
                'ok'        => true,
                'articulos' => $articulos,
                'avisos'    => $avisos,
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 1. REGISTRO PASO 1: Validar seguridad y enviar código por correo
        // -------------------------------------------------------------
        case 'registro_solicitar': {
            $datos      = leerCuerpoJSON();
            $nombre     = trim((string) ($datos['nombre'] ?? ''));
            $correo     = strtolower(trim((string) ($datos['correo'] ?? '')));
            $telefono   = trim((string) ($datos['telefono'] ?? ''));
            $contrasena = (string) ($datos['contrasena'] ?? '');

            if (mb_strlen($nombre) < 3) {
                responderJSON(['ok' => false, 'mensaje' => 'Ingresa tu nombre completo (mínimo 3 caracteres).'], 400);
            }

            $errorCorreo = validarCorreoReal($correo);
            if ($errorCorreo !== null) {
                responderJSON(['ok' => false, 'mensaje' => $errorCorreo], 400);
            }

            $errorContrasena = validarContrasenaSegura($contrasena);
            if ($errorContrasena !== null) {
                responderJSON(['ok' => false, 'mensaje' => $errorContrasena], 400);
            }

            $stmtExiste = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = ? LIMIT 1');
            $stmtExiste->execute([$correo]);
            if ($stmtExiste->fetch()) {
                responderJSON(['ok' => false, 'mensaje' => 'Este correo ya está registrado en la base de datos. Intenta iniciar sesión.'], 409);
            }

            $datosExtra = [
                'nombre'     => $nombre,
                'telefono'   => $telefono !== '' ? $telefono : null,
                'contrasena' => password_hash($contrasena, PASSWORD_DEFAULT),
            ];

            $codigo = generarYGuardarCodigo($pdo, $correo, 'registro', $datosExtra);
            $envio  = enviarCodigoVerificacion($correo, $nombre, $codigo, 'registro');

            if (!$envio['enviado']) {
                $stmtInvalidar = $pdo->prepare("UPDATE codigos_verificacion SET usado = 1 WHERE correo = ? AND tipo = 'registro' AND usado = 0");
                $stmtInvalidar->execute([$correo]);
                responderJSON(['ok' => false, 'mensaje' => $envio['mensaje']], 500);
            }

            responderJSON([
                'ok'      => true,
                'mensaje' => 'Se envió un código de verificación de 6 dígitos a tu correo electrónico.',
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 2. REGISTRO PASO 2: Confirmar código y crear cuenta en MySQL
        // -------------------------------------------------------------
        case 'registro_verificar': {
            $datos  = leerCuerpoJSON();
            $correo = strtolower(trim((string) ($datos['correo'] ?? '')));
            $codigo = trim((string) ($datos['codigo'] ?? ''));

            if ($correo === '' || !preg_match('/^\d{6}$/', $codigo)) {
                responderJSON(['ok' => false, 'mensaje' => 'Ingresa el código de 6 dígitos.'], 400);
            }

            $stmt = $pdo->prepare("
                SELECT id_codigo, datos_extra
                FROM codigos_verificacion
                WHERE correo = ? AND codigo = ? AND tipo = 'registro' AND usado = 0 AND expira_en >= NOW()
                ORDER BY id_codigo DESC
                LIMIT 1
            ");
            $stmt->execute([$correo, $codigo]);
            $registroCodigo = $stmt->fetch();

            if (!$registroCodigo) {
                responderJSON(['ok' => false, 'mensaje' => 'Código inválido o expirado. Solicita uno nuevo.'], 401);
            }

            $stmtExiste = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = ? LIMIT 1');
            $stmtExiste->execute([$correo]);
            if ($stmtExiste->fetch()) {
                responderJSON(['ok' => false, 'mensaje' => 'Este correo ya está registrado. Intenta iniciar sesión.'], 409);
            }

            $extra = json_decode((string) $registroCodigo['datos_extra'], true);
            if (!$extra) {
                responderJSON(['ok' => false, 'mensaje' => 'No se pudieron recuperar los datos del registro.'], 500);
            }

            $pdo->beginTransaction();

            $stmtCrear = $pdo->prepare("
                INSERT INTO usuarios (nombre, correo, contrasena, rol, telefono)
                VALUES (?, ?, ?, 'Cliente', ?)
            ");
            $stmtCrear->execute([
                $extra['nombre'],
                $correo,
                $extra['contrasena'],
                $extra['telefono'],
            ]);
            $idUsuario = (int) $pdo->lastInsertId();

            $stmtCarrito = $pdo->prepare('INSERT INTO carrito (id_usuario, activo) VALUES (?, 1)');
            $stmtCarrito->execute([$idUsuario]);

            $stmtMarcar = $pdo->prepare('UPDATE codigos_verificacion SET usado = 1 WHERE id_codigo = ?');
            $stmtMarcar->execute([$registroCodigo['id_codigo']]);

            $pdo->commit();

            $_SESSION['usuario'] = [
                'id_usuario' => $idUsuario,
                'nombre'     => $extra['nombre'],
                'correo'     => $correo,
                'telefono'   => $extra['telefono'],
                'rol'        => 'Cliente',
            ];

            responderJSON([
                'ok'      => true,
                'mensaje' => '¡Cuenta verificada y registrada con éxito!',
                'usuario' => $_SESSION['usuario'],
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 3. LOGIN PASO 1: Validar credenciales y enviar código por correo
        // -------------------------------------------------------------
        case 'login_solicitar': {
            $datos      = leerCuerpoJSON();
            $correo     = strtolower(trim((string) ($datos['correo'] ?? '')));
            $contrasena = (string) ($datos['contrasena'] ?? '');

            if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $contrasena === '') {
                responderJSON(['ok' => false, 'mensaje' => 'Ingresa tu correo y contraseña.'], 400);
            }

            $stmt = $pdo->prepare('SELECT id_usuario, nombre, correo, telefono, contrasena, rol FROM usuarios WHERE correo = ? LIMIT 1');
            $stmt->execute([$correo]);
            $usuario = $stmt->fetch();

            if (!$usuario || !password_verify($contrasena, $usuario['contrasena'])) {
                responderJSON(['ok' => false, 'mensaje' => 'Correo o contraseña incorrectos.'], 401);
            }

            $codigo = generarYGuardarCodigo($pdo, $correo, 'login', null);
            $envio  = enviarCodigoVerificacion($correo, $usuario['nombre'], $codigo, 'login');

            if (!$envio['enviado']) {
                $stmtInvalidar = $pdo->prepare("UPDATE codigos_verificacion SET usado = 1 WHERE correo = ? AND tipo = 'login' AND usado = 0");
                $stmtInvalidar->execute([$correo]);
                responderJSON(['ok' => false, 'mensaje' => $envio['mensaje']], 500);
            }

            responderJSON([
                'ok'      => true,
                'mensaje' => 'Credenciales correctas. Ingresa el código de verificación enviado a tu correo electrónico.',
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 4. LOGIN PASO 2: Confirmar código e iniciar sesión
        // -------------------------------------------------------------
        case 'login_verificar': {
            $datos  = leerCuerpoJSON();
            $correo = strtolower(trim((string) ($datos['correo'] ?? '')));
            $codigo = trim((string) ($datos['codigo'] ?? ''));

            if ($correo === '' || !preg_match('/^\d{6}$/', $codigo)) {
                responderJSON(['ok' => false, 'mensaje' => 'Ingresa el código de 6 dígitos.'], 400);
            }

            $stmt = $pdo->prepare("
                SELECT id_codigo
                FROM codigos_verificacion
                WHERE correo = ? AND codigo = ? AND tipo = 'login' AND usado = 0 AND expira_en >= NOW()
                ORDER BY id_codigo DESC
                LIMIT 1
            ");
            $stmt->execute([$correo, $codigo]);
            $registroCodigo = $stmt->fetch();

            if (!$registroCodigo) {
                responderJSON(['ok' => false, 'mensaje' => 'Código inválido o expirado.'], 401);
            }

            $stmtMarcar = $pdo->prepare('UPDATE codigos_verificacion SET usado = 1 WHERE id_codigo = ?');
            $stmtMarcar->execute([$registroCodigo['id_codigo']]);

            $stmtUser = $pdo->prepare('SELECT id_usuario, nombre, correo, telefono, rol FROM usuarios WHERE correo = ? LIMIT 1');
            $stmtUser->execute([$correo]);
            $usuario = $stmtUser->fetch();

            if (!$usuario) {
                responderJSON(['ok' => false, 'mensaje' => 'Usuario no encontrado.'], 404);
            }

            $_SESSION['usuario'] = [
                'id_usuario' => (int) $usuario['id_usuario'],
                'nombre'     => $usuario['nombre'],
                'correo'     => $usuario['correo'],
                'telefono'   => $usuario['telefono'],
                'rol'        => $usuario['rol'],
            ];

            responderJSON([
                'ok'      => true,
                'mensaje' => 'Inicio de sesión verificado correctamente.',
                'usuario' => $_SESSION['usuario'],
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 5. CONSULTAR SESIÓN ACTUAL
        // -------------------------------------------------------------
        case 'sesion_actual': {
            $sesion = $_SESSION['usuario'] ?? null;
            if ($sesion && !empty($sesion['id_usuario'])) {
                $stmt = $pdo->prepare('SELECT id_usuario, nombre, correo, telefono, rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
                $stmt->execute([(int) $sesion['id_usuario']]);
                $usuarioDB = $stmt->fetch();
                if ($usuarioDB) {
                    $_SESSION['usuario'] = [
                        'id_usuario' => (int) $usuarioDB['id_usuario'],
                        'nombre'     => $usuarioDB['nombre'],
                        'correo'     => $usuarioDB['correo'],
                        'telefono'   => $usuarioDB['telefono'],
                        'rol'        => $usuarioDB['rol'],
                    ];
                } else {
                    $_SESSION = [];
                    $sesion = null;
                }
            }
            responderJSON([
                'ok'      => true,
                'usuario' => $_SESSION['usuario'] ?? null,
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 6. CERRAR SESIÓN
        // -------------------------------------------------------------
        case 'cerrar_sesion': {
            $_SESSION = [];
            session_destroy();
            responderJSON(['ok' => true, 'mensaje' => 'Sesión cerrada correctamente.']);
            break;
        }

        // -------------------------------------------------------------
        // 7. CREAR PEDIDO DESDE EL CARRITO (DESCUENTA STOCK EN CATÁLOGO)
        // -------------------------------------------------------------
        case 'crear_pedido': {
            $sesion = requerirSesion();
            $datos  = leerCuerpoJSON();
            $items  = $datos['items'] ?? [];

            if (!is_array($items) || count($items) === 0) {
                responderJSON(['ok' => false, 'mensaje' => 'Tu carrito está vacío.'], 400);
            }

            $costoEnvio   = 150.00;
            $subtotal     = 0.0;
            $itemsValidos = [];

            $pdo->beginTransaction();

            $stmtCheckProd = $pdo->prepare('SELECT id_articulo, nombre, precio_final, stock, visible FROM catalogo_productos WHERE id_articulo = ? FOR UPDATE');
            $stmtRestarStock = $pdo->prepare('UPDATE catalogo_productos SET stock = stock - 1 WHERE id_articulo = ?');

            foreach ($items as $item) {
                $idArt  = (int) ($item['id'] ?? 0);
                $nombre = trim((string) ($item['nombre'] ?? ''));
                $talla  = (float) ($item['talla'] ?? 0);
                $color  = trim((string) ($item['color'] ?? ''));
                $precio = (float) ($item['precio'] ?? 0);

                if ($idArt > 0) {
                    $stmtCheckProd->execute([$idArt]);
                    $prodDB = $stmtCheckProd->fetch();
                    if (!$prodDB || (int) $prodDB['visible'] !== 1) {
                        $pdo->rollBack();
                        responderJSON(['ok' => false, 'mensaje' => "El modelo \"{$nombre}\" ya no está disponible."], 400);
                    }
                    if ((int) $prodDB['stock'] <= 0) {
                        $pdo->rollBack();
                        responderJSON(['ok' => false, 'mensaje' => "Stock agotado para \"{$prodDB['nombre']}\"."], 400);
                    }
                    $precio = (float) $prodDB['precio_final'];
                    $nombre = (string) $prodDB['nombre'];
                    $stmtRestarStock->execute([$idArt]);
                }

                if ($nombre === '' || $precio <= 0) {
                    continue;
                }

                $subtotal += $precio;
                $itemsValidos[] = [
                    'nombre' => $nombre,
                    'talla'  => $talla,
                    'color'  => $color !== '' ? $color : 'Estándar',
                    'precio' => $precio,
                ];
            }

            if (count($itemsValidos) === 0) {
                $pdo->rollBack();
                responderJSON(['ok' => false, 'mensaje' => 'No hay artículos válidos en el carrito.'], 400);
            }

            $total      = $subtotal + $costoEnvio;
            $referencia = 'TDO-' . strtoupper(bin2hex(random_bytes(4)));

            $stmtPedido = $pdo->prepare("
                INSERT INTO pedidos (id_usuario, estado, total)
                VALUES (?, 'Pendiente', ?)
            ");
            $stmtPedido->execute([(int) $sesion['id_usuario'], $total]);
            $idPedido = (int) $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("
                INSERT INTO pedidos_articulos (id_pedido, nombre_articulo, talla, color, precio, cantidad)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            foreach ($itemsValidos as $it) {
                $stmtItem->execute([$idPedido, $it['nombre'], $it['talla'], $it['color'], $it['precio']]);
            }

            $stmtPago = $pdo->prepare("
                INSERT INTO pagos (id_pedido, metodo, referencia, monto)
                VALUES (?, 'Tarjeta', ?, ?)
            ");
            $stmtPago->execute([$idPedido, $referencia, $total]);

            $stmtEnvio = $pdo->prepare("
                INSERT INTO envios (id_pedido, costo_envio, paqueteria)
                VALUES (?, ?, 'DHL')
            ");
            $stmtEnvio->execute([$idPedido, $costoEnvio]);

            $pdo->commit();

            responderJSON([
                'ok'         => true,
                'id_pedido'  => $idPedido,
                'referencia' => $referencia,
                'total'      => $total,
                'mensaje'    => "Pedido #{$idPedido} registrado con éxito.",
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 8. PERFIL DE USUARIO: DATOS, PEDIDOS, COMPRAS Y GUÍAS DE ENVÍO
        // -------------------------------------------------------------
        case 'perfil_datos': {
            $sesion    = requerirSesion();
            $idUsuario = (int) $sesion['id_usuario'];

            $stmtUser = $pdo->prepare('
                SELECT id_usuario, nombre, correo, telefono, rol, fecha_registro
                FROM usuarios
                WHERE id_usuario = ?
                LIMIT 1
            ');
            $stmtUser->execute([$idUsuario]);
            $usuario = $stmtUser->fetch();

            if (!$usuario) {
                responderJSON(['ok' => false, 'mensaje' => 'Usuario no encontrado.'], 404);
            }

            $stmtPedidos = $pdo->prepare('
                SELECT p.id_pedido, p.fecha, p.estado, p.total,
                       pg.referencia, e.costo_envio, e.paqueteria, e.numero_guia, e.fecha_envio, e.fecha_entrega
                FROM pedidos p
                LEFT JOIN pagos pg ON pg.id_pedido = p.id_pedido
                LEFT JOIN envios e ON e.id_pedido = p.id_pedido
                WHERE p.id_usuario = ?
                ORDER BY p.fecha DESC, p.id_pedido DESC
            ');
            $stmtPedidos->execute([$idUsuario]);
            $pedidos = $stmtPedidos->fetchAll();

            foreach ($pedidos as &$pedido) {
                $pedido['articulos'] = obtenerArticulosDePedido($pdo, (int) $pedido['id_pedido']);
            }
            unset($pedido);

            responderJSON([
                'ok'      => true,
                'usuario' => $usuario,
                'pedidos' => $pedidos,
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 9. PERFIL: EDITAR INFORMACIÓN PERSONAL
        // -------------------------------------------------------------
        case 'perfil_actualizar': {
            $sesion    = requerirSesion();
            $idUsuario = (int) $sesion['id_usuario'];
            $datos     = leerCuerpoJSON();

            $nombre   = trim((string) ($datos['nombre'] ?? ''));
            $correo   = strtolower(trim((string) ($datos['correo'] ?? '')));
            $telefono = trim((string) ($datos['telefono'] ?? ''));

            if (mb_strlen($nombre) < 3) {
                responderJSON(['ok' => false, 'mensaje' => 'Ingresa tu nombre completo (mínimo 3 caracteres).'], 400);
            }

            $errorCorreo = validarCorreoReal($correo);
            if ($errorCorreo !== null) {
                responderJSON(['ok' => false, 'mensaje' => $errorCorreo], 400);
            }

            $stmtDup = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario <> ? LIMIT 1');
            $stmtDup->execute([$correo, $idUsuario]);
            if ($stmtDup->fetch()) {
                responderJSON(['ok' => false, 'mensaje' => 'Ese correo ya está en uso por otra cuenta.'], 409);
            }

            $stmtUpd = $pdo->prepare('
                UPDATE usuarios
                SET nombre = ?, correo = ?, telefono = ?
                WHERE id_usuario = ?
            ');
            $stmtUpd->execute([
                $nombre,
                $correo,
                $telefono !== '' ? $telefono : null,
                $idUsuario,
            ]);

            $_SESSION['usuario']['nombre']   = $nombre;
            $_SESSION['usuario']['correo']   = $correo;
            $_SESSION['usuario']['telefono'] = $telefono !== '' ? $telefono : null;

            responderJSON([
                'ok'      => true,
                'mensaje' => 'Información de perfil actualizada correctamente.',
                'usuario' => $_SESSION['usuario'],
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 10. PERFIL: CAMBIAR CONTRASEÑA SEGURA
        // -------------------------------------------------------------
        case 'perfil_cambiar_contrasena': {
            $sesion    = requerirSesion();
            $idUsuario = (int) $sesion['id_usuario'];
            $datos     = leerCuerpoJSON();

            $actual = (string) ($datos['contrasena_actual'] ?? '');
            $nueva  = (string) ($datos['contrasena_nueva'] ?? '');

            if ($actual === '') {
                responderJSON(['ok' => false, 'mensaje' => 'Ingresa tu contraseña actual.'], 400);
            }

            $errorNueva = validarContrasenaSegura($nueva);
            if ($errorNueva !== null) {
                responderJSON(['ok' => false, 'mensaje' => $errorNueva], 400);
            }

            $stmt = $pdo->prepare('SELECT contrasena FROM usuarios WHERE id_usuario = ? LIMIT 1');
            $stmt->execute([$idUsuario]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($actual, $row['contrasena'])) {
                responderJSON(['ok' => false, 'mensaje' => 'La contraseña actual es incorrecta.'], 401);
            }

            $stmtUpd = $pdo->prepare('UPDATE usuarios SET contrasena = ? WHERE id_usuario = ?');
            $stmtUpd->execute([password_hash($nueva, PASSWORD_DEFAULT), $idUsuario]);

            responderJSON([
                'ok'      => true,
                'mensaje' => 'Contraseña actualizada correctamente.',
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 11. PERFIL: CANCELAR PEDIDO PENDIENTE
        // -------------------------------------------------------------
        case 'perfil_cancelar_pedido': {
            $sesion    = requerirSesion();
            $idUsuario = (int) $sesion['id_usuario'];
            $datos     = leerCuerpoJSON();
            $idPedido  = (int) ($datos['id_pedido'] ?? 0);

            if ($idPedido <= 0) {
                responderJSON(['ok' => false, 'mensaje' => 'Pedido inválido.'], 400);
            }

            $stmt = $pdo->prepare('SELECT estado FROM pedidos WHERE id_pedido = ? AND id_usuario = ? LIMIT 1');
            $stmt->execute([$idPedido, $idUsuario]);
            $pedido = $stmt->fetch();

            if (!$pedido) {
                responderJSON(['ok' => false, 'mensaje' => 'Pedido no encontrado.'], 404);
            }
            if ($pedido['estado'] !== 'Pendiente') {
                responderJSON(['ok' => false, 'mensaje' => 'Solo se pueden cancelar pedidos en estado Pendiente.'], 400);
            }

            $pdo->beginTransaction();

            // Regresar stock en catalogo_productos de los artículos cancelados
            $stmtItems = $pdo->prepare('SELECT nombre_articulo, cantidad FROM pedidos_articulos WHERE id_pedido = ?');
            $stmtItems->execute([$idPedido]);
            $stmtSumStock = $pdo->prepare('UPDATE catalogo_productos SET stock = stock + ? WHERE nombre = ?');
            foreach ($stmtItems->fetchAll() as $it) {
                $stmtSumStock->execute([(int) $it['cantidad'], $it['nombre_articulo']]);
            }

            $stmtDel = $pdo->prepare('DELETE FROM pedidos WHERE id_pedido = ? AND id_usuario = ?');
            $stmtDel->execute([$idPedido, $idUsuario]);

            $pdo->commit();

            responderJSON([
                'ok'      => true,
                'mensaje' => "Pedido #{$idPedido} cancelado correctamente.",
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 12. PERFIL: ELIMINAR CUENTA PERMANENTEMENTE DE MYSQL
        // -------------------------------------------------------------
        case 'perfil_eliminar_cuenta': {
            $sesion     = requerirSesion();
            $idUsuario  = (int) $sesion['id_usuario'];
            $datos      = leerCuerpoJSON();
            $contrasena = (string) ($datos['contrasena'] ?? '');

            if ($contrasena === '') {
                responderJSON(['ok' => false, 'mensaje' => 'Ingresa tu contraseña para confirmar la eliminación de tu cuenta.'], 400);
            }

            $stmt = $pdo->prepare('SELECT correo, contrasena, rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
            $stmt->execute([$idUsuario]);
            $usuario = $stmt->fetch();

            if (!$usuario || !password_verify($contrasena, $usuario['contrasena'])) {
                responderJSON(['ok' => false, 'mensaje' => 'Contraseña incorrecta. No se eliminó la cuenta.'], 401);
            }

            $pdo->beginTransaction();

            $stmtCod = $pdo->prepare('DELETE FROM codigos_verificacion WHERE correo = ?');
            $stmtCod->execute([$usuario['correo']]);

            $stmtDelPend = $pdo->prepare("DELETE FROM pedidos WHERE id_usuario = ? AND estado = 'Pendiente'");
            $stmtDelPend->execute([$idUsuario]);

            $stmtDelDet = $pdo->prepare('
                DELETE dp FROM detalle_pedido dp
                INNER JOIN pedidos p ON p.id_pedido = dp.id_pedido
                WHERE p.id_usuario = ?
            ');
            $stmtDelDet->execute([$idUsuario]);

            $stmtPrepPed = $pdo->prepare("UPDATE pedidos SET estado = 'Pendiente' WHERE id_usuario = ?");
            $stmtPrepPed->execute([$idUsuario]);

            $stmtDelPed = $pdo->prepare('DELETE FROM pedidos WHERE id_usuario = ?');
            $stmtDelPed->execute([$idUsuario]);

            $stmtDelUser = $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
            $stmtDelUser->execute([$idUsuario]);

            $pdo->commit();

            $_SESSION = [];
            session_destroy();

            responderJSON([
                'ok'      => true,
                'mensaje' => 'Tu cuenta fue eliminada permanentemente de la base de datos.',
            ]);
            break;
        }

        // =============================================================
        // ENDPOINTS EXCLUSIVOS DE ADMINISTRACIÓN
        // =============================================================

        // -------------------------------------------------------------
        // 13. RESUMEN COMPLETO DEL PANEL DE ADMINISTRACIÓN
        // -------------------------------------------------------------
        case 'admin_resumen': {
            requerirAdmin();

            $filasProductos = $pdo->query('
                SELECT * FROM catalogo_productos
                ORDER BY id_articulo ASC
            ')->fetchAll();
            $articulos = array_map('formatearProductoFila', $filasProductos);

            $usuarios = $pdo->query('
                SELECT id_usuario, nombre, correo, rol, telefono, fecha_registro
                FROM usuarios
                ORDER BY rol DESC, fecha_registro DESC
            ')->fetchAll();

            $pedidos = $pdo->query('
                SELECT p.id_pedido, u.nombre AS cliente, u.correo AS correo_cliente,
                       p.fecha, p.estado, p.total,
                       pg.referencia, e.paqueteria, e.numero_guia, e.fecha_envio, e.fecha_entrega
                FROM pedidos p
                INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
                LEFT JOIN pagos pg ON pg.id_pedido = p.id_pedido
                LEFT JOIN envios e ON e.id_pedido = p.id_pedido
                ORDER BY p.fecha DESC, p.id_pedido DESC
            ')->fetchAll();

            foreach ($pedidos as &$pedido) {
                $pedido['articulos'] = obtenerArticulosDePedido($pdo, (int) $pedido['id_pedido']);
            }
            unset($pedido);

            $avisos = $pdo->query('
                SELECT id_aviso, titulo, mensaje, etiqueta_descuento, activo, creado_en
                FROM avisos_temporada
                ORDER BY id_aviso DESC
            ')->fetchAll();

            responderJSON([
                'ok'        => true,
                'articulos' => $articulos,
                'usuarios'  => $usuarios,
                'pedidos'   => $pedidos,
                'avisos'    => $avisos,
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 14. CREAR O EDITAR PRODUCTO COMPLETO (ADMIN)
        // -------------------------------------------------------------
        case 'admin_guardar_producto': {
            requerirAdmin();
            $datos = leerCuerpoJSON();

            $idArticulo   = (int) ($datos['id_articulo'] ?? 0);
            $nombre       = trim((string) ($datos['nombre'] ?? ''));
            $marca        = trim((string) ($datos['marca'] ?? 'Nike'));
            $categoria    = (string) ($datos['categoria'] ?? 'Caballero');
            $linea        = (string) ($datos['linea'] ?? 'Deportivo');
            $descripcion  = trim((string) ($datos['descripcion'] ?? ''));
            $precioBase   = (float) ($datos['precio_base'] ?? 0);
            $descuentoPct = max(0, min(90, (int) ($datos['descuento_pct'] ?? 0)));
            $stock        = max(0, (int) ($datos['stock'] ?? 0));
            $esNuevo      = !empty($datos['es_nuevo']) ? 1 : 0;
            $visible      = isset($datos['visible']) ? (!empty($datos['visible']) ? 1 : 0) : 1;

            $tallasTexto  = (string) ($datos['tallas'] ?? '');
            $coloresTexto = (string) ($datos['colores'] ?? '');

            $tallas = [];
            foreach (explode(',', $tallasTexto) as $t) {
                $val = (float) trim($t);
                if ($val >= 15.0 && $val <= 33.0) {
                    $tallas[] = round($val, 1);
                }
            }
            $tallas = array_values(array_unique($tallas));
            sort($tallas);

            $colores = [];
            foreach (explode(',', $coloresTexto) as $c) {
                $limpio = trim($c);
                if ($limpio !== '') {
                    $colores[] = $limpio;
                }
            }
            $colores = array_values(array_unique($colores));

            $categoriasValidas = ['Caballero', 'Dama', 'Infantil'];
            $lineasValidas     = ['Casual', 'Deportivo', 'Formal'];

            if ($nombre === '' || $precioBase <= 0 || count($tallas) === 0 || count($colores) === 0) {
                responderJSON(['ok' => false, 'mensaje' => 'Completa nombre, precio mayor a $0, al menos una talla y un color.'], 400);
            }
            if (!in_array($categoria, $categoriasValidas, true) || !in_array($linea, $lineasValidas, true)) {
                responderJSON(['ok' => false, 'mensaje' => 'Categoría o línea no válida.'], 400);
            }

            $precioFinal = round($precioBase * (1 - ($descuentoPct / 100)), 2);

            if ($idArticulo > 0) {
                $stmt = $pdo->prepare('
                    UPDATE catalogo_productos
                    SET nombre = ?, marca = ?, categoria = ?, linea = ?, descripcion = ?,
                        tallas_json = ?, colores_json = ?, precio_base = ?, descuento_pct = ?,
                        precio_final = ?, stock = ?, es_nuevo = ?, visible = ?
                    WHERE id_articulo = ?
                ');
                $stmt->execute([
                    $nombre, $marca !== '' ? $marca : 'Nike', $categoria, $linea, $descripcion,
                    json_encode($tallas), json_encode($colores, JSON_UNESCAPED_UNICODE),
                    $precioBase, $descuentoPct, $precioFinal, $stock, $esNuevo, $visible, $idArticulo
                ]);
                responderJSON(['ok' => true, 'mensaje' => "Producto #{$idArticulo} actualizado correctamente."]);
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO catalogo_productos
                        (nombre, marca, categoria, linea, descripcion, tallas_json, colores_json,
                         precio_base, descuento_pct, precio_final, stock, es_nuevo, visible)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $nombre, $marca !== '' ? $marca : 'Nike', $categoria, $linea, $descripcion,
                    json_encode($tallas), json_encode($colores, JSON_UNESCAPED_UNICODE),
                    $precioBase, $descuentoPct, $precioFinal, $stock, $esNuevo, $visible
                ]);
                responderJSON(['ok' => true, 'mensaje' => 'Nuevo producto agregado al catálogo.']);
            }
            break;
        }

        // -------------------------------------------------------------
        // 15. MODIFICAR PRECIO, DESCUENTO Y STOCK RÁPIDO (ADMIN)
        // -------------------------------------------------------------
        case 'admin_precio_descuento': {
            requerirAdmin();
            $datos        = leerCuerpoJSON();
            $idArticulo   = (int) ($datos['id_articulo'] ?? 0);
            $precioBase   = (float) ($datos['precio_base'] ?? 0);
            $descuentoPct = max(0, min(90, (int) ($datos['descuento_pct'] ?? 0)));
            $stock        = max(0, (int) ($datos['stock'] ?? 0));

            if ($idArticulo <= 0 || $precioBase <= 0) {
                responderJSON(['ok' => false, 'mensaje' => 'Ingresa un precio base válido mayor a $0.'], 400);
            }

            $precioFinal = round($precioBase * (1 - ($descuentoPct / 100)), 2);

            $stmt = $pdo->prepare('
                UPDATE catalogo_productos
                SET precio_base = ?, descuento_pct = ?, precio_final = ?, stock = ?
                WHERE id_articulo = ?
            ');
            $stmt->execute([$precioBase, $descuentoPct, $precioFinal, $stock, $idArticulo]);

            responderJSON([
                'ok'           => true,
                'precio_final' => $precioFinal,
                'mensaje'      => "Precio actualizado a \${$precioFinal}" . ($descuentoPct > 0 ? " ({$descuentoPct}% desc.)" : '') . '.',
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 16. OCULTAR O MOSTRAR PRODUCTO EN LA TIENDA (ADMIN)
        // -------------------------------------------------------------
        case 'admin_visibilidad_producto': {
            requerirAdmin();
            $datos      = leerCuerpoJSON();
            $idArticulo = (int) ($datos['id_articulo'] ?? 0);
            $visible    = !empty($datos['visible']) ? 1 : 0;

            if ($idArticulo <= 0) {
                responderJSON(['ok' => false, 'mensaje' => 'Producto inválido.'], 400);
            }

            $stmt = $pdo->prepare('UPDATE catalogo_productos SET visible = ? WHERE id_articulo = ?');
            $stmt->execute([$visible, $idArticulo]);

            responderJSON([
                'ok'      => true,
                'mensaje' => $visible === 1 ? 'Producto visible nuevamente en la tienda.' : 'Producto ocultado de la tienda.',
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 17. ELIMINAR PRODUCTO DE LA BASE DE DATOS (ADMIN)
        // -------------------------------------------------------------
        case 'admin_eliminar_producto': {
            requerirAdmin();
            $datos      = leerCuerpoJSON();
            $idArticulo = (int) ($datos['id_articulo'] ?? 0);

            if ($idArticulo <= 0) {
                responderJSON(['ok' => false, 'mensaje' => 'Producto inválido.'], 400);
            }

            $stmt = $pdo->prepare('DELETE FROM catalogo_productos WHERE id_articulo = ?');
            $stmt->execute([$idArticulo]);

            responderJSON([
                'ok'      => true,
                'mensaje' => "Producto #{$idArticulo} eliminado permanentemente de la base de datos.",
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 18. APLICAR DESCUENTO DE TEMPORADA MASIVO POR CATEGORÍA/MARCA
        // -------------------------------------------------------------
        case 'admin_descuento_temporada': {
            requerirAdmin();
            $datos        = leerCuerpoJSON();
            $objetivo     = (string) ($datos['objetivo'] ?? 'todos'); // todos, Nike, Caballero, Dama, Infantil
            $descuentoPct = max(0, min(90, (int) ($datos['descuento_pct'] ?? 0)));

            if ($objetivo === 'todos') {
                $stmt = $pdo->prepare('
                    UPDATE catalogo_productos
                    SET descuento_pct = ?, precio_final = ROUND(precio_base * (1 - (? / 100)), 2)
                ');
                $stmt->execute([$descuentoPct, $descuentoPct]);
            } elseif ($objetivo === 'Nike' || $objetivo === 'Tacón de Oro') {
                $stmt = $pdo->prepare('
                    UPDATE catalogo_productos
                    SET descuento_pct = ?, precio_final = ROUND(precio_base * (1 - (? / 100)), 2)
                    WHERE marca = ?
                ');
                $stmt->execute([$descuentoPct, $descuentoPct, $objetivo]);
            } else {
                $stmt = $pdo->prepare('
                    UPDATE catalogo_productos
                    SET descuento_pct = ?, precio_final = ROUND(precio_base * (1 - (? / 100)), 2)
                    WHERE categoria = ?
                ');
                $stmt->execute([$descuentoPct, $descuentoPct, $objetivo]);
            }

            responderJSON([
                'ok'      => true,
                'mensaje' => $descuentoPct > 0
                    ? "Descuento de temporada del {$descuentoPct}% aplicado a: {$objetivo}."
                    : "Descuentos restablecidos a 0% para: {$objetivo}.",
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 19. AVISOS DE DESCUENTOS POR TEMPORADA (CREAR / TOGGLE / BORRAR)
        // -------------------------------------------------------------
        case 'admin_crear_aviso': {
            requerirAdmin();
            $datos    = leerCuerpoJSON();
            $titulo   = trim((string) ($datos['titulo'] ?? ''));
            $mensaje  = trim((string) ($datos['mensaje'] ?? ''));
            $etiqueta = trim((string) ($datos['etiqueta_descuento'] ?? ''));

            if ($titulo === '' || $mensaje === '') {
                responderJSON(['ok' => false, 'mensaje' => 'Ingresa un título y el mensaje del aviso de temporada.'], 400);
            }

            $stmt = $pdo->prepare('
                INSERT INTO avisos_temporada (titulo, mensaje, etiqueta_descuento, activo)
                VALUES (?, ?, ?, 1)
            ');
            $stmt->execute([$titulo, $mensaje, $etiqueta !== '' ? $etiqueta : null]);

            responderJSON(['ok' => true, 'mensaje' => 'Aviso de descuento por temporada publicado en la tienda.']);
            break;
        }

        case 'admin_toggle_aviso': {
            requerirAdmin();
            $datos   = leerCuerpoJSON();
            $idAviso = (int) ($datos['id_aviso'] ?? 0);
            $activo  = !empty($datos['activo']) ? 1 : 0;

            $stmt = $pdo->prepare('UPDATE avisos_temporada SET activo = ? WHERE id_aviso = ?');
            $stmt->execute([$activo, $idAviso]);

            responderJSON([
                'ok'      => true,
                'mensaje' => $activo === 1 ? 'Aviso activado en la tienda.' : 'Aviso ocultado de la tienda.',
            ]);
            break;
        }

        case 'admin_eliminar_aviso': {
            requerirAdmin();
            $datos   = leerCuerpoJSON();
            $idAviso = (int) ($datos['id_aviso'] ?? 0);

            $stmt = $pdo->prepare('DELETE FROM avisos_temporada WHERE id_aviso = ?');
            $stmt->execute([$idAviso]);

            responderJSON(['ok' => true, 'mensaje' => 'Aviso eliminado permanentemente.']);
            break;
        }

        // -------------------------------------------------------------
        // 20. ACTUALIZAR ESTADO DE PEDIDO Y GUÍA DE ENVÍO (ADMIN)
        // -------------------------------------------------------------
        case 'admin_actualizar_pedido_envio':
        case 'admin_cambiar_estado_pedido': {
            requerirAdmin();
            $datos      = leerCuerpoJSON();
            $idPedido   = (int) ($datos['id_pedido'] ?? 0);
            $estado     = (string) ($datos['estado'] ?? '');
            $paqueteria = trim((string) ($datos['paqueteria'] ?? 'DHL'));
            $numeroGuia = strtoupper(trim((string) ($datos['numero_guia'] ?? '')));

            $validos = ['Pendiente', 'Empaquetando', 'Entregado'];
            if ($idPedido <= 0 || !in_array($estado, $validos, true)) {
                responderJSON(['ok' => false, 'mensaje' => 'Datos de pedido inválidos.'], 400);
            }

            $pdo->beginTransaction();

            $stmtPed = $pdo->prepare('UPDATE pedidos SET estado = ? WHERE id_pedido = ?');
            $stmtPed->execute([$estado, $idPedido]);

            // Asegurar que exista registro en envios para el pedido
            $stmtExisteEnvio = $pdo->prepare('SELECT id_envio, fecha_envio, fecha_entrega FROM envios WHERE id_pedido = ? LIMIT 1');
            $stmtExisteEnvio->execute([$idPedido]);
            $envioActual = $stmtExisteEnvio->fetch();

            if (!$envioActual) {
                $stmtInsEnv = $pdo->prepare('INSERT INTO envios (id_pedido, costo_envio, paqueteria) VALUES (?, 150.00, ?)');
                $stmtInsEnv->execute([$idPedido, $paqueteria !== '' ? $paqueteria : 'DHL']);
                $fechaEnvio   = null;
                $fechaEntrega = null;
            } else {
                $fechaEnvio   = $envioActual['fecha_envio'];
                $fechaEntrega = $envioActual['fecha_entrega'];
            }

            if ($numeroGuia !== '' && !$fechaEnvio) {
                $fechaEnvio = date('Y-m-d H:i:s');
            }
            if ($estado === 'Entregado' && !$fechaEntrega) {
                $fechaEntrega = date('Y-m-d H:i:s');
                if (!$fechaEnvio) {
                    $fechaEnvio = $fechaEntrega;
                }
            }

            $stmtUpdEnv = $pdo->prepare('
                UPDATE envios
                SET paqueteria = ?, numero_guia = ?, fecha_envio = ?, fecha_entrega = ?
                WHERE id_pedido = ?
            ');
            $stmtUpdEnv->execute([
                $paqueteria !== '' ? $paqueteria : 'DHL',
                $numeroGuia !== '' ? $numeroGuia : null,
                $fechaEnvio,
                $fechaEntrega,
                $idPedido,
            ]);

            $pdo->commit();

            responderJSON([
                'ok'      => true,
                'mensaje' => "Pedido #{$idPedido} y guía de envío actualizados correctamente.",
            ]);
            break;
        }

        // -------------------------------------------------------------
        // 21. DAR DE ALTA O EDITAR USUARIOS Y ADMINISTRADORES (ADMIN)
        // -------------------------------------------------------------
        case 'admin_guardar_usuario': {
            $sesionAdmin = requerirAdmin();
            $datos       = leerCuerpoJSON();

            $idUsuario  = (int) ($datos['id_usuario'] ?? 0);
            $nombre     = trim((string) ($datos['nombre'] ?? ''));
            $correo     = strtolower(trim((string) ($datos['correo'] ?? '')));
            $telefono   = trim((string) ($datos['telefono'] ?? ''));
            $rol        = (string) ($datos['rol'] ?? 'Administrador');
            $contrasena = (string) ($datos['contrasena'] ?? '');

            if (mb_strlen($nombre) < 3) {
                responderJSON(['ok' => false, 'mensaje' => 'Ingresa un nombre válido (mínimo 3 caracteres).'], 400);
            }

            $errorCorreo = validarCorreoReal($correo);
            if ($errorCorreo !== null) {
                responderJSON(['ok' => false, 'mensaje' => $errorCorreo], 400);
            }

            if (!in_array($rol, ['Administrador', 'Cliente'], true)) {
                responderJSON(['ok' => false, 'mensaje' => 'Rol de usuario inválido.'], 400);
            }

            if ($idUsuario > 0) {
                // Evitar que el administrador activo se quite a sí mismo el rol de Administrador
                if ($idUsuario === (int) $sesionAdmin['id_usuario'] && $rol !== 'Administrador') {
                    responderJSON(['ok' => false, 'mensaje' => 'No puedes quitarte el rol de Administrador en tu propia sesión activa.'], 400);
                }

                $stmtDup = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario <> ? LIMIT 1');
                $stmtDup->execute([$correo, $idUsuario]);
                if ($stmtDup->fetch()) {
                    responderJSON(['ok' => false, 'mensaje' => 'Ese correo ya está registrado en otra cuenta.'], 409);
                }

                if ($contrasena !== '') {
                    $errorPass = validarContrasenaSegura($contrasena);
                    if ($errorPass !== null) {
                        responderJSON(['ok' => false, 'mensaje' => $errorPass], 400);
                    }
                    $stmtUpd = $pdo->prepare('
                        UPDATE usuarios
                        SET nombre = ?, correo = ?, telefono = ?, rol = ?, contrasena = ?
                        WHERE id_usuario = ?
                    ');
                    $stmtUpd->execute([
                        $nombre,
                        $correo,
                        $telefono !== '' ? $telefono : null,
                        $rol,
                        password_hash($contrasena, PASSWORD_DEFAULT),
                        $idUsuario,
                    ]);
                } else {
                    $stmtUpd = $pdo->prepare('
                        UPDATE usuarios
                        SET nombre = ?, correo = ?, telefono = ?, rol = ?
                        WHERE id_usuario = ?
                    ');
                    $stmtUpd->execute([
                        $nombre,
                        $correo,
                        $telefono !== '' ? $telefono : null,
                        $rol,
                        $idUsuario,
                    ]);
                }

                // Si editó su propia cuenta, actualizar sesión activa
                if ($idUsuario === (int) $sesionAdmin['id_usuario']) {
                    $_SESSION['usuario']['nombre']   = $nombre;
                    $_SESSION['usuario']['correo']   = $correo;
                    $_SESSION['usuario']['telefono'] = $telefono !== '' ? $telefono : null;
                }

                responderJSON([
                    'ok'             => true,
                    'usuario_sesion' => $_SESSION['usuario'],
                    'mensaje'        => "Información del usuario #{$idUsuario} actualizada correctamente.",
                ]);
            } else {
                // Alta de nuevo usuario o administrador
                $errorPass = validarContrasenaSegura($contrasena);
                if ($errorPass !== null) {
                    responderJSON(['ok' => false, 'mensaje' => $errorPass], 400);
                }

                $stmtDup = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE correo = ? LIMIT 1');
                $stmtDup->execute([$correo]);
                if ($stmtDup->fetch()) {
                    responderJSON(['ok' => false, 'mensaje' => 'Este correo ya está registrado en la base de datos.'], 409);
                }

                $pdo->beginTransaction();

                $stmtIns = $pdo->prepare('
                    INSERT INTO usuarios (nombre, correo, contrasena, rol, telefono)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmtIns->execute([
                    $nombre,
                    $correo,
                    password_hash($contrasena, PASSWORD_DEFAULT),
                    $rol,
                    $telefono !== '' ? $telefono : null,
                ]);
                $nuevoId = (int) $pdo->lastInsertId();

                if ($rol === 'Cliente') {
                    $pdo->prepare('INSERT INTO carrito (id_usuario, activo) VALUES (?, 1)')->execute([$nuevoId]);
                }

                $pdo->commit();

                responderJSON([
                    'ok'      => true,
                    'mensaje' => "{$rol} \"{$nombre}\" dado de alta exitosamente.",
                ]);
            }
            break;
        }

        // -------------------------------------------------------------
        // 22. ELIMINAR USUARIO O ADMINISTRADOR DE MYSQL (ADMIN)
        // -------------------------------------------------------------
        case 'admin_eliminar_usuario': {
            $sesionAdmin = requerirAdmin();
            $datos       = leerCuerpoJSON();
            $idEliminar  = (int) ($datos['id_usuario'] ?? 0);

            if ($idEliminar <= 0) {
                responderJSON(['ok' => false, 'mensaje' => 'Usuario inválido.'], 400);
            }
            if ($idEliminar === (int) $sesionAdmin['id_usuario']) {
                responderJSON(['ok' => false, 'mensaje' => 'No puedes eliminar tu propia cuenta mientras tienes la sesión activa.'], 400);
            }

            $stmt = $pdo->prepare('SELECT id_usuario, nombre, correo, rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
            $stmt->execute([$idEliminar]);
            $objetivo = $stmt->fetch();

            if (!$objetivo) {
                responderJSON(['ok' => false, 'mensaje' => 'El usuario ya no existe.'], 404);
            }

            $pdo->beginTransaction();

            $pdo->prepare('DELETE FROM codigos_verificacion WHERE correo = ?')->execute([$objetivo['correo']]);

            // Devolver stock de pedidos pendientes del usuario antes de eliminarlos
            $stmtPend = $pdo->prepare("
                SELECT pa.nombre_articulo, pa.cantidad
                FROM pedidos_articulos pa
                INNER JOIN pedidos p ON p.id_pedido = pa.id_pedido
                WHERE p.id_usuario = ? AND p.estado = 'Pendiente'
            ");
            $stmtPend->execute([$idEliminar]);
            $stmtSumStock = $pdo->prepare('UPDATE catalogo_productos SET stock = stock + ? WHERE nombre = ?');
            foreach ($stmtPend->fetchAll() as $it) {
                $stmtSumStock->execute([(int) $it['cantidad'], $it['nombre_articulo']]);
            }

            $pdo->prepare("DELETE FROM pedidos WHERE id_usuario = ? AND estado = 'Pendiente'")->execute([$idEliminar]);

            $pdo->prepare('
                DELETE dp FROM detalle_pedido dp
                INNER JOIN pedidos p ON p.id_pedido = dp.id_pedido
                WHERE p.id_usuario = ?
            ')->execute([$idEliminar]);

            $pdo->prepare("UPDATE pedidos SET estado = 'Pendiente' WHERE id_usuario = ?")->execute([$idEliminar]);
            $pdo->prepare('DELETE FROM pedidos WHERE id_usuario = ?')->execute([$idEliminar]);
            $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = ?')->execute([$idEliminar]);

            $pdo->commit();

            responderJSON([
                'ok'      => true,
                'mensaje' => "Usuario \"{$objetivo['nombre']}\" eliminado permanentemente de la base de datos.",
            ]);
            break;
        }

        default:
            responderJSON(['ok' => false, 'mensaje' => 'Acción no reconocida en la API.'], 404);
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responderJSON([
        'ok'      => false,
        'mensaje' => 'Error de conexión con la base de datos. Verifica que MySQL y tacon_de_oro estén activos.',
        'detalle' => $e->getMessage(),
    ], 500);
}
