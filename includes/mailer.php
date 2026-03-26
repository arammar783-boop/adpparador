<?php
$phpmailer_autoload = __DIR__ . '/../vendor/autoload.php';
$phpmailer_manual   = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
if (file_exists($phpmailer_autoload)) {
    require_once $phpmailer_autoload; define('PHPMAILER_DISPONIBLE', true);
} elseif (file_exists($phpmailer_manual)) {
    require_once $phpmailer_manual;
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    define('PHPMAILER_DISPONIBLE', true);
} else { define('PHPMAILER_DISPONIBLE', false); }

class ADPMailer {
    private array $log = [];
    public function enviar(string|array $to, string $asunto, string $html, ?string $texto = null): bool {
        $dest = is_string($to) ? [[$to,'']] : (is_array($to[0]??null)?$to:[[$to[0],$to[1]??'']]);
        $texto = $texto ?? strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html));
        if (PHPMAILER_DISPONIBLE && SMTP_HOST) return $this->enviarSMTP($dest,$asunto,$html,$texto);
        return $this->enviarNativo($dest,$asunto,$html,$texto);
    }
    private function enviarSMTP(array $dest, string $asunto, string $html, string $texto): bool {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP(); $mail->Host=SMTP_HOST; $mail->Port=SMTP_PORT;
            $mail->SMTPAuth = !empty(SMTP_USER);
            if (!empty(SMTP_USER)) { $mail->Username=SMTP_USER; $mail->Password=SMTP_PASS; }
            $mail->SMTPSecure = SMTP_PORT===465
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMIME
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME); $mail->CharSet='UTF-8';
            foreach ($dest as [$email,$nombre]) $mail->addAddress($email,$nombre);
            $mail->isHTML(true); $mail->Subject=$asunto; $mail->Body=$html; $mail->AltBody=$texto;
            $mail->send(); return true;
        } catch (Exception $e) { return $this->enviarNativo($dest,$asunto,$html,$texto); }
    }
    private function enviarNativo(array $dest, string $asunto, string $html, string $texto): bool {
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: ".EMAIL_FROM_NAME." <".EMAIL_FROM.">\r\nX-Mailer: ADPClub/2.0\r\n";
        $ok=true;
        foreach ($dest as [$email,$nombre]) { if(!mail($nombre?"$nombre <$email>":$email,$asunto,$html,$headers)) $ok=false; }
        return $ok;
    }
    public function getLogs(): array { return $this->log; }
}
$mailer = new ADPMailer();
