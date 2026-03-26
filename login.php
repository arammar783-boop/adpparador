<?php
require_once __DIR__.'/config.php';
if(!empty($_SESSION['admin_id'])) redirect(BASE_URL.'/dashboard.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $em=trim($_POST['email']??''); $pw=$_POST['password']??'';
    if($em&&$pw){
        $s=$pdo->prepare("SELECT * FROM admins WHERE email=? AND activo=1"); $s->execute([$em]); $a=$s->fetch();
        if($a&&password_verify($pw,$a['password'])){
            $_SESSION['admin_id']=$a['id']; $_SESSION['admin_nombre']=$a['nombre']; $_SESSION['admin_rol']=$a['rol'];
            redirect(BASE_URL.'/dashboard.php');
        } else $error='Email o contraseña incorrectos.';
    } else $error='Completa todos los campos.';
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Acceso — ADP Club</title><link rel="stylesheet" href="<?=BASE_URL?>/css/style.css"></head>
<body><div class="login-wrapper"><div class="login-card">
  <div class="login-header"><div class="login-logo">⚽</div><h1>A.D. Parador</h1><p>Portal de gestión del club</p></div>
  <div class="login-body">
    <?php if($error): ?><div class="alert alert-error"><?=h($error)?></div><?php endif; ?>
    <form method="POST" novalidate>
      <div class="form-group"><label>Email</label>
        <input type="email" name="email" class="form-control" value="<?=h($_POST['email']??'')?>" placeholder="admin@adpparador.com" required autocomplete="email"></div>
      <div class="form-group"><label>Contraseña</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password"></div>
      <button type="submit" class="btn btn-primary w-100 btn-lg" style="margin-top:8px">Entrar al portal →</button>
    </form>
    <p class="text-center text-sm text-gray" style="margin-top:20px">¿Eres padre/madre? Usa el enlace de registro que te enviaron.</p>
  </div>
</div></div></body></html>
