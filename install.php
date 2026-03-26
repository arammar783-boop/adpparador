<?php
/**
 * ============================================================
 *  ADP Club — INSTALADOR v2 (Multi-temporada)
 *  Ejecutar UNA SOLA VEZ tras subir los archivos al servidor.
 *  IMPORTANTE: Eliminar o renombrar este archivo después.
 * ============================================================
 *
 *  INSTRUCCIONES:
 *  1. Edita los datos de conexión justo debajo.
 *  2. Accede a este archivo desde el navegador.
 *  3. Si todo va bien, ve al login y cambia la contraseña.
 *  4. Elimina este archivo del servidor.
 */

// ── Datos de conexión ──────────────────────────────────────────────────────────
// XAMPP local:  DB_USER='root', DB_PASS=''
// Hosting cPanel: usa los datos del asistente MySQL de cPanel
define('DB_HOST', 'localhost');
define('DB_NAME', 'adp_club');       // nombre de la base de datos
define('DB_USER', 'root');           // usuario MySQL
define('DB_PASS', '');               // contraseña MySQL (vacía en XAMPP local)
// ──────────────────────────────────────────────────────────────────────────────

function calcularTemporadaActual(): int {
    $mes = (int)date('m');
    $anio = (int)date('Y');
    return ($mes >= 8) ? $anio : $anio - 1;
}

try {
    // Conectar sin seleccionar BD para poder crearla si no existe
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Crear BD si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."`
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `".DB_NAME."`");

    // ── TABLAS ────────────────────────────────────────────────────────────────

    $pdo->exec("
CREATE TABLE IF NOT EXISTS admins (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(100) NOT NULL,
    email     VARCHAR(150) NOT NULL UNIQUE,
    password  VARCHAR(255) NOT NULL,
    rol       ENUM('admin','entrenador','lectura') DEFAULT 'admin',
    activo    TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

    $pdo->exec("
CREATE TABLE IF NOT EXISTS temporadas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    anio_inicio  INT NOT NULL UNIQUE,
    nombre       VARCHAR(20) NOT NULL,
    activa       TINYINT(1) DEFAULT 0,
    fecha_inicio DATE NOT NULL,
    fecha_fin    DATE DEFAULT NULL,
    notas        TEXT DEFAULT NULL,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

    $pdo->exec("
CREATE TABLE IF NOT EXISTS jugadores (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(100) NOT NULL,
    apellidos        VARCHAR(150) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    foto             VARCHAR(255) DEFAULT NULL,
    tutor_nombre     VARCHAR(150) NOT NULL,
    tutor_apellidos  VARCHAR(150) NOT NULL,
    tutor_email      VARCHAR(150) NOT NULL,
    tutor_telefono   VARCHAR(20)  DEFAULT NULL,
    jugador_email    VARCHAR(150) DEFAULT NULL,
    notas            TEXT DEFAULT NULL,
    creado_en        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

    $pdo->exec("
CREATE TABLE IF NOT EXISTS inscripciones (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    jugador_id   INT NOT NULL,
    temporada_id INT NOT NULL,
    anio_inicio  INT NOT NULL,
    categoria    VARCHAR(20) NOT NULL,
    equipo       VARCHAR(100) DEFAULT NULL,
    activo       TINYINT(1) DEFAULT 1,
    motivo_baja  ENUM('otro_club','personal','lesion','otro') DEFAULT NULL,
    club_destino VARCHAR(150) DEFAULT NULL,
    fecha_baja   DATE DEFAULT NULL,
    notas_temp   TEXT DEFAULT NULL,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jugador_id)   REFERENCES jugadores(id)  ON DELETE CASCADE,
    FOREIGN KEY (temporada_id) REFERENCES temporadas(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_jugador_temporada (jugador_id, temporada_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

    $pdo->exec("
CREATE TABLE IF NOT EXISTS pagos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    jugador_id   INT NOT NULL,
    temporada_id INT NOT NULL,
    anio_inicio  INT NOT NULL,
    mes          CHAR(2) NOT NULL,
    cuota        DECIMAL(6,2) NOT NULL,
    pagado       TINYINT(1) DEFAULT 0,
    fecha_pago   DATE DEFAULT NULL,
    metodo_pago  ENUM('efectivo','transferencia','domiciliacion','otro') DEFAULT NULL,
    notas        VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (jugador_id)   REFERENCES jugadores(id)  ON DELETE CASCADE,
    FOREIGN KEY (temporada_id) REFERENCES temporadas(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_pago (jugador_id, temporada_id, mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

    $pdo->exec("
CREATE TABLE IF NOT EXISTS tokens_registro (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    token        VARCHAR(120) NOT NULL UNIQUE,
    email        VARCHAR(150) DEFAULT NULL,
    telefono     VARCHAR(20)  DEFAULT NULL,
    usado        TINYINT(1) DEFAULT 0,
    jugador_id   INT DEFAULT NULL,
    temporada_id INT DEFAULT NULL,
    caduca_en    DATETIME NOT NULL,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jugador_id)   REFERENCES jugadores(id)  ON DELETE SET NULL,
    FOREIGN KEY (temporada_id) REFERENCES temporadas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

    // ── TEMPORADA INICIAL ─────────────────────────────────────────────────────
    $anio = calcularTemporadaActual();
    $nombre_temp  = $anio.'/'.($anio+1);
    $fecha_inicio = $anio.'-08-01';
    $fecha_fin    = ($anio+1).'-06-30';

    $pdo->prepare("
        INSERT IGNORE INTO temporadas (anio_inicio, nombre, activa, fecha_inicio, fecha_fin)
        VALUES (?, ?, 1, ?, ?)
    ")->execute([$anio, $nombre_temp, $fecha_inicio, $fecha_fin]);

    $temp_id = $pdo->query(
        "SELECT id FROM temporadas WHERE anio_inicio={$anio}"
    )->fetchColumn();

    // ── ADMIN POR DEFECTO ─────────────────────────────────────────────────────
    // Contraseña: Admin1234!  — CÁMBIALA INMEDIATAMENTE tras el primer acceso
    $hash = password_hash('Admin1234!', PASSWORD_BCRYPT);
    $pdo->prepare("
        INSERT IGNORE INTO admins (nombre, email, password, rol)
        VALUES (?, ?, ?, 'admin')
    ")->execute(['Administrador', 'admin@adpparador.com', $hash]);

    // ── RESULTADO ─────────────────────────────────────────────────────────────
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Instalación — ADP Club</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0fdf4;
     display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;}
.card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.10);
      width:100%;max-width:560px;overflow:hidden;}
.head{background:linear-gradient(135deg,#1e5228,#2d7a3a);padding:32px;text-align:center;color:#fff;}
.head h1{font-size:1.4rem;margin-top:12px;}
.body{padding:32px;}
.ok{background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px;margin-bottom:20px;}
.ok p{color:#166534;font-size:.9rem;}
.row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f4f0;font-size:.9rem;}
.row:last-child{border-bottom:none;}
.lbl{color:#6b7a6b;font-weight:500;}.val{font-weight:600;}
code{background:#f1f5f1;padding:2px 6px;border-radius:4px;font-size:.85rem;}
.warn{background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:14px;margin-top:20px;}
.warn p{color:#92400e;font-size:.85rem;}
.btn{display:inline-block;margin-top:20px;padding:12px 28px;background:#2d7a3a;color:#fff;
     text-decoration:none;border-radius:8px;font-weight:600;font-size:.95rem;}
</style></head><body>
<div class="card">
  <div class="head">
    <div style="font-size:3rem">✅</div>
    <h1>Instalación completada</h1>
  </div>
  <div class="body">
    <div class="ok"><p>Base de datos <strong>'.DB_NAME.'</strong> creada correctamente con soporte multi-temporada.</p></div>
    <div class="row"><span class="lbl">Temporada creada</span><span class="val">'.$nombre_temp.' (ID: '.$temp_id.')</span></div>
    <div class="row"><span class="lbl">Período</span><span class="val">'.date('d/m/Y',strtotime($fecha_inicio)).' → '.date('d/m/Y',strtotime($fecha_fin)).'</span></div>
    <div class="row"><span class="lbl">Email de acceso</span><span class="val"><code>admin@adpparador.com</code></span></div>
    <div class="row"><span class="lbl">Contraseña</span><span class="val"><code>Admin1234!</code></span></div>
    <div class="warn">
      <p>⚠️ <strong>Pasos obligatorios tras la instalación:</strong></p>
      <p style="margin-top:8px">1. Elimina o renombra <code>install.php</code> del servidor.<br>
      2. Entra al portal y <strong>cambia la contraseña</strong> desde Mi perfil.<br>
      3. Configura el email SMTP en <code>config.php</code>.</p>
    </div>
    <a href="login.php" class="btn">Ir al portal →</a>
  </div>
</div>
</body></html>';

} catch (PDOException $e) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<style>body{font-family:sans-serif;padding:40px;} .err{background:#fef2f2;border:2px solid #dc2626;
border-radius:8px;padding:24px;max-width:560px;margin:0 auto;}</style></head>
<body><div class="err">
<h2 style="color:#dc2626">❌ Error en la instalación</h2>
<p style="margin-top:12px">'.$e->getMessage().'</p>
<p style="margin-top:12px;color:#666">Revisa los datos de conexión al inicio de <code>install.php</code>.</p>
</div></body></html>';
}
