<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/mailer.php';
require_once __DIR__.'/includes/emails.php';
requireLogin();
$aid=$_SESSION['admin_id'];
$aq=$pdo->prepare("SELECT * FROM admins WHERE id=?"); $aq->execute([$aid]); $admin=$aq->fetch();
$ep=[]; $epa=[]; $okp=false; $okpa=false;

if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['accion']??'')==='perfil'){
    $n=trim($_POST['nombre']??''); $e=trim($_POST['email']??'');
    if(!$n)$ep['nombre']='Requerido'; if(!$e)$ep['email']='Requerido';
    elseif(!filter_var($e,FILTER_VALIDATE_EMAIL))$ep['email']='Email inválido';
    if(empty($ep['email'])){$d=$pdo->prepare("SELECT id FROM admins WHERE email=? AND id!=?");$d->execute([$e,$aid]);if($d->fetch())$ep['email']='Ya en uso';}
    if(empty($ep)){$pdo->prepare("UPDATE admins SET nombre=?,email=? WHERE id=?")->execute([$n,$e,$aid]);$_SESSION['admin_nombre']=$n;$admin['nombre']=$n;$admin['email']=$e;$okp=true;}
}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['accion']??'')==='password'){
    $ac=$_POST['password_actual']??''; $nv=$_POST['password_nueva']??''; $rp=$_POST['password_repite']??'';
    if(!$ac)$epa['password_actual']='Requerido';
    if(!$nv)$epa['password_nueva']='Requerido';
    elseif(strlen($nv)<8)$epa['password_nueva']='Mínimo 8 caracteres';
    elseif(!preg_match('/[A-Z]/',$nv))$epa['password_nueva']='Necesita mayúscula';
    elseif(!preg_match('/[0-9]/',$nv))$epa['password_nueva']='Necesita número';
    if(!$rp)$epa['password_repite']='Requerido';
    elseif($nv!==$rp)$epa['password_repite']='No coinciden';
    if(empty($epa['password_actual'])&&!password_verify($ac,$admin['password']))$epa['password_actual']='Contraseña incorrecta';
    if(empty($epa)){
        $hash=password_hash($nv,PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE admins SET password=? WHERE id=?")->execute([$hash,$aid]);
        $admin['password']=$hash; $okpa=true;
        try{[$as,$ht]=array_values(emailCambioPassword($admin['nombre']));$mailer->enviar($admin['email'],$as,$ht);}catch(Exception $e){}
    }
}
renderHead('Mi perfil');
?>
<body><div class="app-wrapper">
<?php renderSidebar('perfil') ?>
<div class="main-content">
<?php renderTopHeader('Mi perfil','Configuración de cuenta') ?>
<div class="page-content">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;max-width:900px">
  <div class="card"><div class="card-header"><div class="card-title">👤 Datos personales</div></div>
    <div class="card-body">
      <?php if($okp): ?><div class="alert alert-success">✅ Datos actualizados.</div><?php endif; ?>
      <form method="POST" novalidate><input type="hidden" name="accion" value="perfil">
        <div class="form-group"><label>Nombre completo *</label>
          <input type="text" name="nombre" class="form-control" value="<?=h($admin['nombre'])?>" required>
          <?php if(!empty($ep['nombre'])): ?><div class="form-error"><?=h($ep['nombre'])?></div><?php endif; ?></div>
        <div class="form-group"><label>Email *</label>
          <input type="email" name="email" class="form-control" value="<?=h($admin['email'])?>" required>
          <?php if(!empty($ep['email'])): ?><div class="form-error"><?=h($ep['email'])?></div><?php endif; ?>
          <div class="form-hint">Recibe notificaciones de seguridad.</div></div>
        <div class="form-group"><label>Rol</label>
          <div style="padding:9px 12px;background:var(--gris-2);border-radius:var(--radio);font-size:.9rem">
            <?=h(['admin'=>'Administrador','entrenador'=>'Entrenador','lectura'=>'Solo lectura'][$admin['rol']]??$admin['rol'])?></div></div>
        <button type="submit" class="btn btn-primary">💾 Guardar datos</button>
      </form>
    </div></div>

  <div class="card"><div class="card-header"><div class="card-title">🔐 Cambiar contraseña</div></div>
    <div class="card-body">
      <?php if($okpa): ?><div class="alert alert-success">✅ Contraseña actualizada. Email de confirmación enviado.</div><?php endif; ?>
      <div class="alert alert-info" style="margin-bottom:20px">Mínimo 8 caracteres, una mayúscula y un número.</div>
      <form method="POST" novalidate autocomplete="off"><input type="hidden" name="accion" value="password">
        <div class="form-group"><label>Contraseña actual *</label>
          <div style="position:relative">
            <input type="password" name="password_actual" id="pa" class="form-control" autocomplete="current-password">
            <button type="button" onclick="tv('pa','ia')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer"><span id="ia">👁</span></button>
          </div>
          <?php if(!empty($epa['password_actual'])): ?><div class="form-error"><?=h($epa['password_actual'])?></div><?php endif; ?></div>
        <div class="form-group"><label>Nueva contraseña *</label>
          <div style="position:relative">
            <input type="password" name="password_nueva" id="pn" class="form-control" autocomplete="new-password" oninput="checkStr(this.value)">
            <button type="button" onclick="tv('pn','in')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer"><span id="in">👁</span></button>
          </div>
          <div id="sbar" style="margin-top:6px;height:4px;border-radius:2px;background:var(--gris-3);overflow:hidden">
            <div id="sfill" style="height:100%;width:0;transition:width .3s,background .3s;border-radius:2px"></div></div>
          <div id="slbl" class="form-hint" style="margin-top:4px"></div>
          <?php if(!empty($epa['password_nueva'])): ?><div class="form-error"><?=h($epa['password_nueva'])?></div><?php endif; ?></div>
        <div class="form-group"><label>Repite nueva contraseña *</label>
          <div style="position:relative">
            <input type="password" name="password_repite" id="pr" class="form-control" autocomplete="new-password" oninput="checkM()">
            <button type="button" onclick="tv('pr','ir')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer"><span id="ir">👁</span></button>
          </div>
          <div id="mlbl" class="form-hint" style="margin-top:4px"></div>
          <?php if(!empty($epa['password_repite'])): ?><div class="form-error"><?=h($epa['password_repite'])?></div><?php endif; ?></div>
        <button type="submit" class="btn btn-primary">🔐 Cambiar contraseña</button>
      </form>
    </div></div>
</div>

<div class="card" style="max-width:900px;margin-top:24px">
  <div class="card-header"><div class="card-title">⚙️ Estado del sistema de emails</div></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
      <div><div class="text-xs text-gray" style="margin-bottom:4px">PHPMAILER</div>
        <?=PHPMAILER_DISPONIBLE?'<span class="badge badge-green">✓ Instalado</span>':'<span class="badge badge-yellow">⚠ No instalado (mail())</span>'?></div>
      <div><div class="text-xs text-gray" style="margin-bottom:4px">SMTP</div>
        <?=SMTP_HOST?'<span class="badge badge-green">✓ '.h(SMTP_HOST).':'.SMTP_PORT.'</span>':'<span class="badge badge-yellow">⚠ No configurado</span>'?></div>
      <div><div class="text-xs text-gray" style="margin-bottom:4px">REMITENTE</div>
        <span class="badge badge-green"><?=h(EMAIL_FROM)?></span></div>
    </div>
    <div class="alert alert-info" style="margin-top:16px;margin-bottom:0">
      Para cambiar la configuración SMTP edita <code>config.php</code>.</div>
  </div>
</div>
</div></div></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}
function tv(ii,ic){const i=document.getElementById(ii),c=document.getElementById(ic);i.type=i.type==='password'?'text':'password';c.textContent=i.type==='password'?'👁':'🙈';}
function checkStr(v){let s=0;if(v.length>=8)s++;if(v.length>=12)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
  const l=[{p:'20%',c:'#ef4444',t:'Muy débil'},{p:'40%',c:'#f97316',t:'Débil'},{p:'60%',c:'#eab308',t:'Aceptable'},{p:'80%',c:'#22c55e',t:'Buena'},{p:'100%',c:'#16a34a',t:'Excelente'}];
  const lv=l[Math.min(s,4)];document.getElementById('sfill').style.width=v?lv.p:'0';document.getElementById('sfill').style.background=lv.c;
  document.getElementById('slbl').textContent=v?lv.t:'';document.getElementById('slbl').style.color=lv.c;}
function checkM(){const n=document.getElementById('pn').value,r=document.getElementById('pr').value,l=document.getElementById('mlbl');
  if(!r){l.textContent='';return;}if(n===r){l.textContent='✓ Coinciden';l.style.color='#16a34a';}else{l.textContent='✗ No coinciden';l.style.color='#dc2626';}}
</script></body></html>
