<?php
/**
 * =====================================================================
 * TACÓN DE ORO - CONFIGURACIÓN DE RESEND (php/config_correo.php)
 * =====================================================================
 * Carga credenciales locales desde php/config_correo.local.php si existe,
 * o usa las constantes definidas abajo.
 */

if (file_exists(__DIR__ . '/config_correo.local.php')) {
    require_once __DIR__ . '/config_correo.local.php';
}

if (!defined('RESEND_API_KEY')) {
    define('RESEND_API_KEY', ''); // Coloca tu API Key en php/config_correo.local.php
}

if (!defined('RESEND_FROM')) {
    define('RESEND_FROM', 'Tacón de Oro <no-reply@tacondeorosupport.com>');
}
