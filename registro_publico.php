<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/mailer.php';
require_once __DIR__.'/includes/emails.php';

$token=trim($_GET['token']??''); $error_token=''; $registro_ok=false; $token_data=null;
if(!$token) $error_token='Enlace inválido.';
else {
    $s=$pdo->prepare("SELECT * FROM tokens_registro WHERE token=?"); $s->execute([$token]); $token_data=$s->fetch();
    if(!$token_data) $error_token='Este enlace no es válido.';
    elseif($token_data['usado']) $error_token='Este enlace ya ha sido utilizado.';
    elseif(strtotime($token_data['caduca_en'])<time()) $error_token='Este enlace ha caducado.';
}

$requiere_ver=!$error_token&&!empty($token_data['email']);
$email_verificado=false; $error_ver=''; $clave_ses='ev_'.md5($token);
if($requiere_ver){
    if(!empty($_SESSION[$clave_ses])) $email_verificado=true;
    elseif($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['verificar_email'])){
        $ei=strtolower(trim($_POST['email_verificacion']??'')); $ee=strtolower(trim($token_data['email']));
        if(!$ei) $error_ver='Introduce tu email.';
        elseif($ei!==$ee) $error_ver='El email no coincide. Inténtalo de nuevo.';
        else { $_SESSION[$clave_ses]=true; $email_verificado=true; }
    }
}
$mostrar_form=(!$error_token)&&(!$requiere_ver||$email_verificado);

// Obtener temporada del token o usar la actual
$tid_reg=$token_data?$token_data['temporada_id']:null;
if(!$tid_reg){
    $ta=calcularTemporadaActual();
    $s=$pdo->prepare("SELECT id FROM temporadas WHERE anio_inicio=?"); $s->execute([$ta]); $r=$s->fetch();
    $tid_reg=$r?$r['id']:null; $ta_reg=$ta;
} else {
    $s=$pdo->prepare("SELECT anio_inicio FROM temporadas WHERE id=?"); $s->execute([$tid_reg]); $r=$s->fetch();
    $ta_reg=$r?(int)$r['anio_inicio']:calcularTemporadaActual();
}

$errors=[]; $data=[];
if($mostrar_form&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['nombre'])){
    $data=['nombre'=>trim($_POST['nombre']??''),'apellidos'=>trim($_POST['apellidos']??''),
           'fecha_nacimiento'=>trim($_POST['fecha_nacimiento']??''),
           'tutor_nombre'=>trim($_POST['tutor_nombre']??''),'tutor_apellidos'=>trim($_POST['tutor_apellidos']??''),
           'tutor_email'=>trim($_POST['tutor_email']??''),'tutor_telefono'=>trim($_POST['tutor_telefono']??''),
           'jugador_email'=>trim($_POST['jugador_email']??''),'notas'=>trim($_POST['notas']??'')];
    if(!$data['nombre'])$errors['nombre']='Requerido'; if(!$data['apellidos'])$errors['apellidos']='Requerido';
    if(!$data['fecha_nacimiento'])$errors['fecha_nacimiento']='Requerido';
    if(!$data['tutor_nombre'])$errors['tutor_nombre']='Requerido'; if(!$data['tutor_apellidos'])$errors['tutor_apellidos']='Requerido';
    if(!$data['tutor_email'])$errors['tutor_email']='Requerido';
    elseif(!filter_var($data['tutor_email'],FILTER_VALIDATE_EMAIL))$errors['tutor_email']='Email inválido';
    if($data['jugador_email']&&!filter_var($data['jugador_email'],FILTER_VALIDATE_EMAIL))$errors['jugador_email']='Email inválido';
    $foto_nombre=null;
    if(!empty($_FILES['foto']['name'])){
        $ext=strtolower(pathinfo($_FILES['foto']['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['jpg','jpeg','png','webp']))$errors['foto']='Solo JPG/PNG/WEBP';
        elseif($_FILES['foto']['size']>3*1024*1024)$errors['foto']='Máximo 3MB';
        else $foto_nombre=uniqid('jug_',true).'.'.$ext;
    }
    if(empty($errors)&&$tid_reg){
        $cat=getCategoria($data['fecha_nacimiento'],$ta_reg); $cuota=CUOTAS[$cat]??40;
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO jugadores (nombre,apellidos,fecha_nacimiento,foto,tutor_nombre,tutor_apellidos,tutor_email,tutor_telefono,jugador_email,notas) VALUES(?,?,?,?,?,?,?,?,?,?)")
                ->execute([$data['nombre'],$data['apellidos'],$data['fecha_nacimiento'],$foto_nombre,
                           $data['tutor_nombre'],$data['tutor_apellidos'],$data['tutor_email'],
                           $data['tutor_telefono']?:null,$data['jugador_email']?:null,$data['notas']?:null]);
            $jid=$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inscripciones (jugador_id,temporada_id,anio_inicio,categoria,activo) VALUES(?,?,?,?,1)")
                ->execute([$jid,$tid_reg,$ta_reg,$cat]);
            foreach(MESES_TEMPORADA as $mes)
                $pdo->prepare("INSERT INTO pagos (jugador_id,temporada_id,anio_inicio,mes,cuota) VALUES(?,?,?,?,?)")
                    ->execute([$jid,$tid_reg,$ta_reg,$mes,$cuota]);
            $pdo->prepare("UPDATE tokens_registro SET usado=1,jugador_id=? WHERE token=?")->execute([$jid,$token]);
            if($foto_nombre) move_uploaded_file($_FILES['foto']['tmp_name'],__DIR__.'/uploads/'.$foto_nombre);
            $pdo->commit(); $registro_ok=true; unset($_SESSION[$clave_ses]);
            try { [$as,$ht]=array_values(emailBienvenida($data['nombre'].' '.$data['apellidos'],$data['tutor_nombre'],$cat,$cuota));
                  $mailer->enviar($data['tutor_email'],$as,$ht); } catch(Exception $e){}
        } catch(Exception $e){ $pdo->rollBack(); $errors['_general']='Error al guardar: '.$e->getMessage(); }
    }
}
$necesita_email=!empty($data['fecha_nacimiento'])&&strtotime($data['fecha_nacimiento'])&&necesitaEmailJugador($data['fecha_nacimiento']);
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Registro — A.D. Parador</title><link rel="stylesheet" href="<?=BASE_URL?>/css/style.css"></head>
<body><div class="registro-wrapper"><div class="registro-card">
  <div class="registro-header"><div class="icon">⚽</div>
    <div><h1>A.D. Parador — Registro de jugador</h1><p>Rellena los datos del jugador.</p></div></div>
  <div class="registro-body">
  <?php if($error_token): ?>
  <div class="alert alert-error">⚠️ <strong><?=h($error_token)?></strong></div>
  <?php elseif($registro_ok): ?>
  <div style="text-align:center;padding:40px 20px">
    <div style="font-size:4rem;margin-bottom:16px">🎉</div>
    <h2 style="color:var(--verde)">¡Registro completado!</h2>
    <p style="color:var(--gris-5);max-width:400px;margin:12px auto">
      Los datos de <strong><?=h($data['nombre'].' '.$data['apellidos'])?></strong> han sido enviados al club correctamente.</p>
    <div class="alert alert-success" style="margin-top:24px;text-align:left">
      <strong>Categoría:</strong> <?=getCategoriaLabel(getCategoria($data['fecha_nacimiento'],$ta_reg))?><br>
      <small>Temporada <?=getTemporadaLabel($ta_reg)?></small></div>
  </div>
  <?php elseif(!$mostrar_form): ?>
  <div style="max-width:400px;margin:0 auto;padding:20px 0">
    <div style="text-align:center;margin-bottom:28px">
      <div style="font-size:3rem;margin-bottom:12px">🔒</div>
      <h2 style="font-size:1.2rem;margin-bottom:8px">Verifica tu identidad</h2>
      <p style="color:var(--gris-5);font-size:.9rem">Introduce el email con el que el club te envió este enlace.</p></div>
    <?php if($error_ver): ?><div class="alert alert-error"><?=h($error_ver)?></div><?php endif; ?>
    <form method="POST" novalidate><input type="hidden" name="verificar_email" value="1">
      <div class="form-group"><label>Tu email *</label>
        <input type="email" name="email_verificacion" class="form-control <?=$error_ver?'error':''?>"
          placeholder="padre@email.com" autocomplete="email" autofocus required>
        <div class="form-hint">El email al que el club te envió este enlace.</div></div>
      <button type="submit" class="btn btn-primary w-100 btn-lg">Continuar →</button>
    </form></div>
  <?php else: ?>
  <?php if(!empty($errors['_general'])): ?><div class="alert alert-error"><?=h($errors['_general'])?></div><?php endif; ?>
  <div class="alert alert-info" style="margin-bottom:24px">
    Enlace válido hasta el <strong><?=date('d/m/Y \a \l\a\s H:i',strtotime($token_data['caduca_en']))?></strong>
    <?php if($requiere_ver): ?> · <span style="color:var(--verde)">✓ Identidad verificada</span><?php endif; ?>
  </div>
  <form method="POST" enctype="multipart/form-data" novalidate>
    <div class="form-section"><div class="form-section-title">📷 Fotografía del jugador</div>
      <div class="foto-upload-area"><div class="foto-preview" id="foto-preview">👤</div>
        <div><label class="btn btn-secondary btn-sm" style="cursor:pointer">Subir fotografía
          <input type="file" name="foto" accept="image/*" style="display:none" onchange="previewFoto(this)"></label>
          <div class="form-hint" style="margin-top:6px">Opcional. JPG/PNG/WEBP. Máx 3MB.</div>
          <?php if(!empty($errors['foto'])): ?><div class="form-error"><?=h($errors['foto'])?></div><?php endif; ?>
        </div></div></div>
    <div class="form-section"><div class="form-section-title">⚽ Datos del jugador</div>
    <div class="form-grid">
      <div class="form-group"><label>Nombre *</label>
        <input type="text" name="nombre" class="form-control" value="<?=h($data['nombre']??'')?>" required>
        <?php if(!empty($errors['nombre'])): ?><div class="form-error"><?=h($errors['nombre'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Apellidos *</label>
        <input type="text" name="apellidos" class="form-control" value="<?=h($data['apellidos']??'')?>" required>
        <?php if(!empty($errors['apellidos'])): ?><div class="form-error"><?=h($errors['apellidos'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Fecha de nacimiento *</label>
        <input type="date" name="fecha_nacimiento" id="fecha-nac" class="form-control" value="<?=h($data['fecha_nacimiento']??'')?>"
          max="<?=date('Y-m-d')?>" required onchange="actualizarCategoria(this.value)">
        <?php if(!empty($errors['fecha_nacimiento'])): ?><div class="form-error"><?=h($errors['fecha_nacimiento'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Categoría</label>
        <div id="cat-display" style="padding:9px 12px;background:var(--gris-2);border-radius:var(--radio);font-size:.9rem;color:var(--gris-5)">
          <?php if(!empty($data['fecha_nacimiento'])): ?><strong><?=getCategoriaLabel(getCategoria($data['fecha_nacimiento'],$ta_reg))?></strong>
          <?php else: ?>Se calculará al elegir la fecha<?php endif; ?></div></div>
      <div class="form-group" id="grupo-email-jugador" style="<?=$necesita_email?'':'display:none'?>">
        <label>Email del jugador *</label>
        <input type="email" name="jugador_email" class="form-control" value="<?=h($data['jugador_email']??'')?>" placeholder="jugador@email.com">
        <?php if(!empty($errors['jugador_email'])): ?><div class="form-error"><?=h($errors['jugador_email'])?></div><?php endif; ?></div>
    </div></div>
    <div class="form-section"><div class="form-section-title">👨‍👩‍👦 Padre / Madre / Tutor legal</div>
    <div class="form-grid">
      <div class="form-group"><label>Tu nombre *</label>
        <input type="text" name="tutor_nombre" class="form-control" value="<?=h($data['tutor_nombre']??'')?>" required>
        <?php if(!empty($errors['tutor_nombre'])): ?><div class="form-error"><?=h($errors['tutor_nombre'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Tus apellidos *</label>
        <input type="text" name="tutor_apellidos" class="form-control" value="<?=h($data['tutor_apellidos']??'')?>" required>
        <?php if(!empty($errors['tutor_apellidos'])): ?><div class="form-error"><?=h($errors['tutor_apellidos'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Tu email *</label>
        <input type="email" name="tutor_email" class="form-control" value="<?=h($data['tutor_email']??($token_data['email']??''))?>" required>
        <?php if(!empty($errors['tutor_email'])): ?><div class="form-error"><?=h($errors['tutor_email'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Teléfono (opcional)</label>
        <input type="tel" name="tutor_telefono" class="form-control" value="<?=h($data['tutor_telefono']??($token_data['telefono']??''))?>" placeholder="6XX XXX XXX"></div>
    </div></div>
    <div class="form-section"><div class="form-section-title">💬 Info adicional</div>
      <div class="form-group"><textarea name="notas" class="form-control" rows="3" placeholder="Alergias, observaciones..."><?=h($data['notas']??'')?></textarea></div></div>
    <button type="submit" class="btn btn-primary btn-lg w-100">✅ Enviar registro al club</button>
    <p class="text-center text-sm text-gray" style="margin-top:16px">Tus datos se guardan de forma segura y solo serán usados por A.D. Parador.</p>
  </form>
  <?php endif; ?>
  </div></div></div>
<script>
const TEMP=<?=$ta_reg?>;
const CATS={prebenjamin:'Prebenjamín',benjamin:'Benjamín',alevin:'Alevín',infantil:'Infantil',cadete:'Cadete',juvenil:'Juvenil',bebe:'Bebé',senior:'Sénior'};
function getCat(f){if(!f)return null;const a=new Date(f).getFullYear(),e=TEMP-a;
  if(e<=5)return'bebe';if(e<=7)return'prebenjamin';if(e<=9)return'benjamin';
  if(e<=11)return'alevin';if(e<=13)return'infantil';if(e<=15)return'cadete';if(e<=17)return'juvenil';return'senior';}
function edad(f){const h=new Date(),n=new Date(f);let e=h.getFullYear()-n.getFullYear();
  if(h.getMonth()-n.getMonth()<0||(h.getMonth()===n.getMonth()&&h.getDate()<n.getDate()))e--;return e;}
function actualizarCategoria(f){const c=getCat(f),d=document.getElementById('cat-display');
  d.innerHTML=c?'<strong>'+(CATS[c]||c)+'</strong>':'Se calculará al elegir la fecha';
  const g=document.getElementById('grupo-email-jugador');if(g)g.style.display=f&&edad(f)>=16?'':'none';}
function previewFoto(i){if(i.files&&i.files[0]){const r=new FileReader();r.onload=e=>{document.getElementById('foto-preview').innerHTML='<img src="'+e.target.result+'">';};r.readAsDataURL(i.files[0]);}}
</script></body></html>
