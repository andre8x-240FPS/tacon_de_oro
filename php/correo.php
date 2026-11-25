<?php
/**
 * =====================================================================
 * TACÓN DE ORO - SERVICIO DE ENVÍO DE CORREOS CON RESEND (php/correo.php)
 * Envía los códigos aleatorios de verificación de 6 dígitos al correo
 * electrónico del usuario usando la API de Resend.
 * =====================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config_correo.php';

/**
 * Envía un correo con el código aleatorio de verificación de 6 dígitos vía Resend.
 *
 * @return array{enviado: bool, mensaje: string}
 */
function enviarCodigoVerificacion(string $correoDestino, string $nombre, string $codigo, string $tipo): array {
    if (RESEND_API_KEY === '') {
        return [
            'enviado' => false,
            'mensaje' => 'El servicio de correo Resend no está configurado. Coloca RESEND_API_KEY en php/config_correo.php.'
        ];
    }

    $asunto = $tipo === 'registro'
        ? 'Código de verificación para crear tu cuenta - Tacón de Oro'
        : 'Código de verificación de inicio de sesión - Tacón de Oro';

    $nombreSeguro = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

    $textoPlano = "Hola {$nombre},\n\n"
        . "Tu código de verificación de 6 dígitos para Tacón de Oro es: {$codigo}\n\n"
        . "Este código expira en 10 minutos. Si no solicitaste este acceso, ignora este mensaje.\n\n"
        . "Atentamente,\nTacón de Oro";

    $mensajeHtml = "
        <div style='font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 24px; border: 1px solid #e5e0d5; border-radius: 10px; background: #ffffff;'>
            <h2 style='color: #181512; margin-bottom: 8px;'>Tacón de Oro</h2>
            <p style='color: #4a453e;'>Hola <strong>{$nombreSeguro}</strong>,</p>
            <p style='color: #4a453e;'>Tu código de verificación de 6 dígitos es:</p>
            <div style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #b58d35; background: #faf6eb; padding: 14px; text-align: center; border-radius: 8px; margin: 18px 0;'>
                {$codigo}
            </div>
            <p style='color: #7c7469; font-size: 13px;'>Este código expira en 10 minutos. Si no solicitaste este acceso, ignora este mensaje.</p>
        </div>
    ";

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'from'    => RESEND_FROM,
            'to'      => [$correoDestino],
            'subject' => $asunto,
            'html'    => $mensajeHtml,
            'text'    => $textoPlano,
        ], JSON_UNESCAPED_UNICODE),
    ]);

    $respuesta = curl_exec($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($errorCurl || $httpCode < 200 || $httpCode >= 300) {
        $detalle = $errorCurl;
        if (!$detalle && is_string($respuesta)) {
            $json = json_decode($respuesta, true);
            $detalle = (string) ($json['message'] ?? '');
        }
        return [
            'enviado' => false,
            'mensaje' => 'No se pudo enviar el correo de verificación.' . ($detalle !== '' ? ' Detalle: ' . $detalle : '')
        ];
    }

    return [
        'enviado' => true,
        'mensaje' => 'Código enviado correctamente a tu correo electrónico.'
    ];
}
