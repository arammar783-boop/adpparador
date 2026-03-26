<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/mailer.php';
require_once __DIR__.'/includes/emails.php';
requireLogin();

$tid=getTemporadaId($pdo,$TEMPORADA_ACTIVA);
$enlace_generado=null; $email_enviado=false; $email_error=''; $error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $email=trim($_POST['email']??''); $telefono=trim($_POST['telefono']??''); $horas=(int)($_POST['horas']??72);
    if($email&&!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='El email no es válido.';
    else {
        $token=generateToken(); $caduca=date('Y-m-d H:i:s',strtotime("+{$horas} hours"));
        $pdo->prepare("INSERT INTO tokens_registro (token,email,telefono,caduca_en,temporada_id) VALUES(?,?,?,?,?)")
            ->execute([$token,$email?:null,$telefono?:null,$caduca,$tid]);
        $enlace_generado=BASE_URL.'/registro_publico.php?token='.$token;
        if($email){
            try{
                [$as,$ht]=array_values(emailEnlaceRegistro('Padre/Madre/Tutor',$enlace_generado,$caduca));
                $email_enviado=$mailer->enviar($email,$as,$ht);
                if(!$email_enviado) $email_error='Enlace generado pero error al enviar email. Cópialo manualmente.';
            } catch(Exception $e){ $email_error='Error: '.$e->getMessage(); }
        }
    }
}

$tokens=$pdo->query("SELECT t.*,j.nombre,j.apellidos FROM tokens_registro t LEFT JOIN jugadores j ON j.id=t.jugador_id ORDER BY t.creado_en DESC LIMIT 30")->fetchAll();
renderHead('Generar enlace de registro');
?>
<body><div class="app-wrapper">
<?php renderSidebar('enlace') ?>
<div class="main-content">
<?php renderTopHeader('Invitar padre / tutor','Enlace único de registro') ?>
<div class="page-content">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
  <div class="card">
    <div class="card-header"><div class="card-title">🔗 Generar enlace de registro</div></div>
    <div class="card-body">
      <div class="alert alert-info">Genera un enlace único para que el padre rellene los datos del jugador. Expira tras el tiempo elegido.</div>
      <?php if($error): ?><div class="alert alert-error"><?=h($error)?></div><?php endif; ?>
      <?php if($enlace_generado): ?>
      <div class="alert alert-success">✅ Enlace generado.
        <?php if($email_enviado): ?> 📧 Email enviado a <strong><?=h($_POST['email']??'')?></strong>.
        <?php elseif($email_error): ?><div class="alert alert-warn" style="margin-top:8px;margin-bottom:0"><?=h($email_error)?></div><?php endif; ?>
      </div>
      <div class="form-group"><label>Enlace de registro</label>
        <div class="token-box" id="token-box"><?=h($enlace_generado)?></div>
        <button class="btn btn-secondary btn-sm" onclick="copiarEnlace()">📋 Copiar</button></div>
      <hr style="border:none;border-top:1px solid var(--gris-3);margin:18px 0">
      <?php endif; ?>
      <form method="POST" novalidate>
        <div class="form-group"><label>Email del padre/tutor <span class="optional">(opcional)</span></label>
          <input type="email" name="email" class="form-control" placeholder="padre@email.com" value="<?=h($_POST['email']??'')?>">
          <div class="form-hint">Si lo rellenas, el enlace estará protegido: solo quien tenga ese email podrá usarlo.</div></div>
        <div class="form-group"><label>Teléfono <span class="optional">(opcional)</span></label>
          <input type="tel" name="telefono" class="form-control" placeholder="6XX XXX XXX" value="<?=h($_POST['telefono']??'')?>"></div>
        <div class="form-group"><label>Caduca en</label>
          <select name="horas" class="form-control">
            <option value="24">24 horas</option><option value="48">48 horas</option>
            <option value="72" selected>72 horas (3 días)</option><option value="168">1 semana</option>
          </select></div>
        <button type="submit" class="btn btn-primary">🔗 Generar enlace</button>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Historial de enlaces</div></div>
    <div class="card-body" style="padding:0"><div class="table-wrap"><table>
      <thead><tr><th>Creado</th><th>Email</th><th>Estado</th><th>Jugador</th></tr></thead>
      <tbody>
      <?php foreach($tokens as $t): $cad=strtotime($t['caduca_en'])<time(); ?>
      <tr>
        <td class="text-sm text-gray"><?=date('d/m H:i',strtotime($t['creado_en']))?></td>
        <td class="text-sm"><?=h($t['email']??'—')?></td>
        <td><?=$t['usado']?'<span class="badge badge-green">✓ Usado</span>':($cad?'<span class="badge badge-red">Caducado</span>':'<span class="badge badge-yellow">Pendiente</span>')?></td>
        <td class="text-sm"><?=$t['nombre']?'<a href="'.BASE_URL.'/jugador_perfil.php?id='.$t['jugador_id'].'">'.h($t['nombre'].' '.$t['apellidos']).'</a>':'—'?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($tokens)): ?><tr><td colspan="4" class="text-center text-gray" style="padding:24px">Sin enlaces</td></tr><?php endif; ?>
      </tbody>
    </table></div></div>
  </div>
</div></div></div></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}
function copiarEnlace(){const t=document.getElementById('token-box').textContent.trim();
  navigator.clipboard.writeText(t).then(()=>{const b=event.target;b.textContent='✅ Copiado';setTimeout(()=>b.textContent='📋 Copiar',2000);});}
</script></body></html>
