<?php
define('DB_HOST','localhost'); define('DB_NAME','adp_club');
define('DB_USER','root');      define('DB_PASS','');
define('DB_CHARSET','utf8mb4');
define('BASE_URL','http://localhost/adp_parador');
define('SECRET_KEY','adpparadorclubdefutbol_2025');
define('MESES_TEMPORADA',['08','09','10','11','12','01','02','03','04','05','06']);
define('NOMBRES_MESES',[
    '08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre',
    '12'=>'Diciembre','01'=>'Enero','02'=>'Febrero','03'=>'Marzo',
    '04'=>'Abril','05'=>'Mayo','06'=>'Junio'
]);
define('CUOTAS',['prebenjamin'=>30,'benjamin'=>35,'alevin'=>40,'infantil'=>40,'cadete'=>45,'juvenil'=>50]);
define('EMAIL_FROM','noreply@adpparador.com');
define('EMAIL_FROM_NAME','A.D. Parador');
define('SMTP_HOST','localhost'); define('SMTP_PORT',1025);
define('SMTP_USER','');          define('SMTP_PASS','');
define('EMAIL_CONFIRMAR_PAGO',false);

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES=>false]);
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;color:#c0392b"><h2>Error BD</h2><p>'.$e->getMessage().'</p></div>');
}
session_start();

function calcularTemporadaActual(): int {
    $m=(int)date('m'); $a=(int)date('Y'); return ($m>=8)?$a:$a-1;
}
function getTemporadaActiva(PDO $pdo): int {
    if (!empty($_SESSION['temporada_vista'])) return (int)$_SESSION['temporada_vista'];
    try {
        $r=$pdo->query("SELECT anio_inicio FROM temporadas WHERE activa=1 LIMIT 1")->fetch();
        if ($r) return (int)$r['anio_inicio'];
    } catch(Exception $e){}
    return calcularTemporadaActual();
}
function getTemporadaLabel(int $a): string { return $a.'/'.($a+1); }
function getCategoria(string $fn, int $t=0): string {
    if (!$t) $t=calcularTemporadaActual();
    $a=(int)date('Y',strtotime($fn)); $e=$t-$a;
    if ($e<=5) return 'bebe'; if ($e<=7) return 'prebenjamin'; if ($e<=9) return 'benjamin';
    if ($e<=11) return 'alevin'; if ($e<=13) return 'infantil'; if ($e<=15) return 'cadete';
    if ($e<=17) return 'juvenil'; return 'senior';
}
function getCategoriaLabel(string $c): string {
    return ['bebe'=>'Bebé','prebenjamin'=>'Prebenjamín','benjamin'=>'Benjamín','alevin'=>'Alevín',
            'infantil'=>'Infantil','cadete'=>'Cadete','juvenil'=>'Juvenil','senior'=>'Sénior'][$c]??ucfirst($c);
}
function getCategoriaColor(string $c): string {
    return ['bebe'=>'#ff9ff3','prebenjamin'=>'#ffeaa7','benjamin'=>'#74b9ff','alevin'=>'#55efc4',
            'infantil'=>'#a29bfe','cadete'=>'#fd79a8','juvenil'=>'#00b894','senior'=>'#636e72'][$c]??'#b2bec3';
}
function requireLogin(): void {
    if (empty($_SESSION['admin_id'])) { header('Location: '.BASE_URL.'/login.php'); exit; }
}
function h(?string $s): string { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function redirect(string $u): void { header('Location: '.$u); exit; }
function generateToken(int $l=48): string { return bin2hex(random_bytes($l)); }
function edadActual(string $fn): int { return (int)(new DateTime($fn))->diff(new DateTime())->y; }
function necesitaEmailJugador(string $fn): bool { return edadActual($fn)>=16; }
function getTemporadaId(PDO $pdo, int $a): ?int {
    $r=$pdo->prepare("SELECT id FROM temporadas WHERE anio_inicio=?"); $r->execute([$a]);
    $row=$r->fetch(); return $row?(int)$row['id']:null;
}

$TEMPORADA_ACTIVA = getTemporadaActiva($pdo);
