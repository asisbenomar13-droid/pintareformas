<?php
// Recibe el formulario de presupuesto de pintareformas.es y lo envía por email.
// Respuesta JSON siempre. Exito: {"ok":true,"message":...}
// Error:  {"ok":false,"code":...,"error":...,"hint":...} con 405 / 422 / 502.
// El formulario de la web, si recibe ok:false, abre WhatsApp con los datos del cliente.
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok'      => false,
        'code'    => 'method_not_allowed',
        'error'   => 'Este endpoint solo acepta POST.',
        'hint'    => 'Envia los campos como application/x-www-form-urlencoded con POST.',
        'expects' => ['nombre', 'telefono', 'servicio', 'mensaje', 'email (opcional)', 'ciudad (opcional)', 'superficie (opcional)'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Honeypot anti-spam: el campo "web" está oculto; si viene relleno, es un bot.
if (!empty($_POST['web'])) {
    echo json_encode(['ok' => true]);
    exit;
}

function campo($k) {
    return trim(str_replace(["\r", "\n"], ' ', $_POST[$k] ?? ''));
}

$nombre   = campo('nombre');
$telefono = campo('telefono');
$email    = campo('email');
$servicio = campo('servicio');
$ciudad   = campo('ciudad');
$sup      = campo('superficie');
$mensaje  = trim($_POST['mensaje'] ?? '');

$obligatorios = ['nombre' => $nombre, 'telefono' => $telefono, 'servicio' => $servicio, 'mensaje' => $mensaje];
$faltan = array_keys(array_filter($obligatorios, function ($v) { return $v === ''; }));
if ($faltan) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'code'    => 'missing_fields',
        'error'   => 'Faltan campos obligatorios: ' . implode(', ', $faltan) . '.',
        'missing' => array_values($faltan),
        'hint'    => 'Rellena esos campos y vuelve a enviar. Si prefieres, escribe por WhatsApp al +34633915898.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = '';
}

$cuerpo = "Nueva solicitud de presupuesto desde pintareformas.es\n\n"
    . "Nombre: $nombre\n"
    . "Teléfono: $telefono\n"
    . ($email ? "Email: $email\n" : '')
    . "Servicio: $servicio\n"
    . ($ciudad ? "Ciudad: $ciudad\n" : '')
    . ($sup ? "Superficie: $sup\n" : '')
    . "\nProyecto:\n$mensaje\n"
    . "\nFecha: " . date('d/m/Y H:i');

// Envío por SMTP autenticado con el buzón info@pintareformas.es: así el correo sale
// firmado con DKIM y no cae en spam (mail() de PHP sale sin firma).
// Credenciales en un archivo fuera de public_html; si falla el SMTP, se usa mail().
$asunto  = "Presupuesto web: $servicio — $nombre";
$destino = 'asisbenomar13@gmail.com';

function smtp_enviar($cfg, $destinos, $asunto, $cuerpo, $replyTo) {
    $fp = @stream_socket_client('ssl://' . $cfg['host'] . ':' . $cfg['port'], $en, $es, 15);
    if (!$fp) return false;
    stream_set_timeout($fp, 15);
    $leer = function () use ($fp) {
        $r = '';
        while (($l = fgets($fp, 515)) !== false) {
            $r .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') break;
        }
        return $r;
    };
    $cmd = function ($c, $ok) use ($fp, $leer) {
        fwrite($fp, $c . "\r\n");
        $r = $leer();
        return strpos($r, (string)$ok) === 0;
    };
    $leer();
    $res = $cmd('EHLO pintareformas.es', 250)
        && $cmd('AUTH LOGIN', 334)
        && $cmd(base64_encode($cfg['user']), 334)
        && $cmd(base64_encode($cfg['pass']), 235)
        && $cmd('MAIL FROM:<' . $cfg['user'] . '>', 250);
    foreach ($destinos as $d) {
        $res = $res && $cmd('RCPT TO:<' . $d . '>', 250);
    }
    $res = $res && $cmd('DATA', 354);
    if ($res) {
        $h  = 'From: PintaReformas Web <' . $cfg['user'] . ">\r\n";
        $h .= 'To: ' . $destinos[0] . "\r\n";
        if (count($destinos) > 1) $h .= 'Cc: ' . implode(', ', array_slice($destinos, 1)) . "\r\n";
        if ($replyTo) $h .= 'Reply-To: ' . $replyTo . "\r\n";
        $h .= 'Subject: =?UTF-8?B?' . base64_encode($asunto) . "?=\r\n";
        $h .= "X-Enviado-Por: smtp\r\n";
        $h .= 'Date: ' . date('r') . "\r\n";
        $h .= 'Message-ID: <' . bin2hex(random_bytes(8)) . '@pintareformas.es>' . "\r\n";
        $h .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n";
        $msg = $h . "\r\n" . chunk_split(base64_encode($cuerpo));
        fwrite($fp, $msg . "\r\n.\r\n");
        $res = strpos($leer(), '250') === 0;
    }
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $res;
}

$ok = false;
$cfgFile = __DIR__ . '/../smtp-pintareformas.php';
if (is_file($cfgFile)) {
    $cfg = include $cfgFile;
    $replyTo = $email ? "$nombre <$email>" : '';
    $ok = smtp_enviar($cfg, [$destino, 'info@pintareformas.es'], $asunto, $cuerpo, $replyTo);
}
if (!$ok) {
    $headers = "From: PintaReformas Web <info@pintareformas.es>\r\n";
    $headers .= "Cc: info@pintareformas.es\r\n";
    if ($email) {
        $headers .= "Reply-To: $nombre <$email>\r\n";
    }
    $headers .= "X-Enviado-Por: mail\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    $ok = mail($destino, $asunto, $cuerpo, $headers);
}

// Copia de seguridad de cada lead en un log fuera de public_html
@file_put_contents(
    __DIR__ . '/../leads-pintareformas.log',
    date('c') . " | $nombre | $telefono | $email | $servicio | $ciudad | $sup | " . str_replace("\n", ' ', $mensaje) . "\n",
    FILE_APPEND | LOCK_EX
);

if ($ok) {
    echo json_encode(['ok' => true, 'message' => 'Solicitud recibida. Te contesto en breve.'], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(502);
    echo json_encode([
        'ok'    => false,
        'code'  => 'mail_failed',
        'error' => 'No se ha podido enviar el correo.',
        'hint'  => 'Escribe por WhatsApp al +34633915898 o llama al 633 915 898.',
    ], JSON_UNESCAPED_UNICODE);
}
