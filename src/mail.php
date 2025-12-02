<?php




if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function get_smtp_config() {
    return [
        'host' => 'smtp-relay.gmail.com',
        'port' => 587,
        'user' => 'fillinhere@mail.com',
        'pass' => 'app-password-here',
        'from' => 'fillinhere@mail.com',
        'from_name' => 'CosplaySites',
        'secure' => 'tls',
        'timeout' => 20,
    ];
}


function smtp_send_mail($to, $subject, $htmlBody, $plainBody = null) {
    
    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $cfg = get_smtp_config();

            $mail->isSMTP();
            $mail->Host = $cfg['host'];
            $mail->Port = intval($cfg['port']);
            $secure = strtolower($cfg['secure']);
            if ($secure === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            if ($cfg['user']) {
                $mail->SMTPAuth = true;
                $mail->Username = $cfg['user'];
                $mail->Password = $cfg['pass'];
            }
            $mail->setFrom($cfg['from'], $cfg['from_name']);

            $tos = is_array($to) ? $to : [$to];
            foreach ($tos as $rcpt) {
                $mail->addAddress($rcpt);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody ?: strip_tags($htmlBody);

            $mail->send();
            return ['ok' => true];
        } catch (Exception $e) {
            return ['ok' => false, 'error' => 'PHPMailer error: ' . $e->getMessage()];
        }
    }

    
    return smtp_send_mail_stream($to, $subject, $htmlBody, $plainBody);
}


function smtp_send_mail_stream($to, $subject, $htmlBody, $plainBody = null) {
    $cfg = get_smtp_config();
    if (!$cfg['host']) return ['ok'=>false,'error'=>'SMTP_HOST not configured'];

    $port = intval($cfg['port']);
    $host = $cfg['host'];
    $secure = strtolower($cfg['secure']);

    $remote = (($secure==='ssl') ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0; $errstr = '';
    $fp = stream_socket_client($remote, $errno, $errstr, $cfg['timeout'], STREAM_CLIENT_CONNECT);
    if (!$fp) return ['ok'=>false,'error'=>"Connection failed: $errstr ($errno)"];

    stream_set_timeout($fp, $cfg['timeout']);
    $res = fgets($fp, 512);

    $send = function($cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
        return trim(fgets($fp, 512));
    };

    
    $ehlo = $send('EHLO ' . gethostname());

    
    if ($secure === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
        $send('STARTTLS');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            
        }
        $ehlo = $send('EHLO ' . gethostname());
    }

    
    if ($cfg['user']) {
        $send('AUTH LOGIN');
        $send(base64_encode($cfg['user']));
        $send(base64_encode($cfg['pass']));
    }

    $from = $cfg['from'];
    $fromName = $cfg['from_name'];
    $send('MAIL FROM: <' . $from . '>');
    $tos = is_array($to) ? $to : [$to];
    foreach ($tos as $rcpt) $send('RCPT TO: <' . $rcpt . '>');

    $send('DATA');
    $boundary = '----=_Part_' . md5(uniqid('', true));
    $plain = $plainBody ?: strip_tags($htmlBody);
    $headers = [];
    $headers[] = 'From: ' . $fromName . ' <' . $from . '>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'Date: ' . date('r');

    $msg = implode("\r\n", $headers) . "\r\n\r\n";
    $msg .= '--' . $boundary . "\r\n";
    $msg .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n\r\n";
    $msg .= $plain . "\r\n\r\n";
    $msg .= '--' . $boundary . "\r\n";
    $msg .= 'Content-Type: text/html; charset=UTF-8' . "\r\n\r\n";
    $msg .= $htmlBody . "\r\n";
    $msg .= "\r\n--" . $boundary . "--\r\n.";

    fwrite($fp, $msg . "\r\n");
    $dataRes = trim(fgets($fp, 512));

    $send('QUIT');
    fclose($fp);

    if (strpos($dataRes, '250') === false) {
        return ['ok'=>false,'error'=>'SMTP DATA error: '.$dataRes];
    }
    return ['ok'=>true];
}

function send_email_reset($toEmail, $link) {
    $subject = 'Password reset instructions for CosplaySites';
    $html = '<p>We received a request to reset your password. Click the link below to reset it (valid 1 hour):</p>';
    $html .= '<p><a href="' . htmlspecialchars($link) . '">Reset your password</a></p>';
    $html .= '<p>If you did not request this, ignore this email.</p>';
    return smtp_send_mail($toEmail, $subject, $html);
}

?>