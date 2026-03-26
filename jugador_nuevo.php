<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/layout.php';
requireLogin();

// No se puede añadir jugadores en vista histórica
if($TEMPORADA_ACTIVA!==calcularTemporadaActual()){
    flash('No puedes añadir jugadores en una temporada histórica.','error');
    redirect(BASE_URL.'/jugadores.php');
}

$tid = getTemporadaId($pdo, $TEMPORADA_ACTIVA);
if(!$tid){ flash('No existe la temporada activa en la base de datos.','error'); redirect(BASE_URL.'/dashboard.php'); }

$errors=[];$data=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $data=['nombre'=>trim($_POST['nombre']??''),'apellidos'=>trim($_POST['apellidos']??''),
           'fecha_nacimiento'=>trim($_POST['fecha_nacimiento']??''),
           'tutor_nombre'=>trim($_POST['tutor_nombre']??''),'tutor_apellidos'=>trim($_POST['tutor_apellidos']??''),
           'tutor_email'=>trim($_POST['tutor_email']??''),'tutor_telefono'=>trim($_POST['tutor_telefono']??''),
           'jugador_email'=>trim($_POST['jugador_email']??''),'notas'=>trim($_POST['notas']??''),
           'equipo'=>trim($_POST['equipo']??'')];
    if(!$data['nombre'])           $errors['nombre']='Requerido';
    if(!$data['apellidos'])        $errors['apellidos']='Requerido';
    if(!$data['fecha_nacimiento']) $errors['fecha_nacimiento']='Requerido';
    elseif(!strtotime($data['fecha_nacimiento'])) $errors['fecha_nacimiento']='Fecha inválida';
    if(!$data['tutor_nombre'])     $errors['tutor_nombre']='Requerido';
    if(!$data['tutor_apellidos'])  $errors['tutor_apellidos']='Requerido';
    if(!$data['tutor_email'])      $errors['tutor_email']='Requerido';
    elseif(!filter_var($data['tutor_email'],FILTER_VALIDATE_EMAIL)) $errors['tutor_email']='Email inválido';
    if($data['jugador_email']&&!filter_var($data['jugador_email'],FILTER_VALIDATE_EMAIL)) $errors['jugador_email']='Email inválido';

    $foto_nombre=null;
    if(!empty($_FILES['foto']['name'])){
        $ext=strtolower(pathinfo($_FILES['foto']['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['jpg','jpeg','png','webp'])) $errors['foto']='Solo JPG/PNG/WEBP';
        elseif($_FILES['foto']['size']>3*1024*1024) $errors['foto']='Máximo 3MB';
        else $foto_nombre=uniqid('jug_',true).'.'.$ext;
    }

    if(empty($errors)){
        $categoria=getCategoria($data['fecha_nacimiento'],$TEMPORADA_ACTIVA);
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO jugadores (nombre,apellidos,fecha_nacimiento,foto,tutor_nombre,tutor_apellidos,tutor_email,tutor_telefono,jugador_email,notas) VALUES(?,?,?,?,?,?,?,?,?,?)")
                ->execute([$data['nombre'],$data['apellidos'],$data['fecha_nacimiento'],$foto_nombre,
                           $data['tutor_nombre'],$data['tutor_apellidos'],$data['tutor_email'],
                           $data['tutor_telefono'],$data['jugador_email']?:null,$data['notas']?:null]);
            $jid=$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inscripciones (jugador_id,temporada_id,anio_inicio,categoria,equipo,activo) VALUES(?,?,?,?,?,1)")
                ->execute([$jid,$tid,$TEMPORADA_ACTIVA,$categoria,$data['equipo']?:null]);
            $cuota=CUOTAS[$categoria]??40;
            foreach(MESES_TEMPORADA as $mes)
                $pdo->prepare("INSERT INTO pagos (jugador_id,temporada_id,anio_inicio,mes,cuota) VALUES(?,?,?,?,?)")
                    ->execute([$jid,$tid,$TEMPORADA_ACTIVA,$mes,$cuota]);
            if($foto_nombre) move_uploaded_file($_FILES['foto']['tmp_name'],__DIR__.'/uploads/'.$foto_nombre);
            $pdo->commit();
            flash('Jugador registrado. Se han generado los recibos de la temporada.','success');
            redirect(BASE_URL.'/jugador_perfil.php?id='.$jid);
        } catch(Exception $e){ $pdo->rollBack(); $errors['_general']='Error: '.$e->getMessage(); }
    }
}
$necesita_email=!empty($data['fecha_nacimiento'])&&strtotime($data['fecha_nacimiento'])&&necesitaEmailJugador($data['fecha_nacimiento']);
renderHead('Nuevo jugador');
?>
<body><div class="app-wrapper">
<?php renderSidebar('jugadores') ?>
<div class="main-content">
<?php renderTopHeader('Nuevo jugador','Registro manual') ?>
<div class="page-content">
<?php if(!empty($errors['_general'])): ?><div class="alert alert-error"><?=h($errors['_general'])?></div><?php endif; ?>
<div class="card" style="max-width:860px">
  <div class="card-header"><div class="card-title">Datos del jugador</div>
    <a href="<?=BASE_URL?>/generar_enlace.php" class="btn btn-outline btn-sm">🔗 Enviar enlace al padre</a></div>
  <div class="card-body">
  <form method="POST" enctype="multipart/form-data" novalidate>
    <div class="form-section"><div class="form-section-title">Fotografía de perfil</div>
      <div class="foto-upload-area">
        <div class="foto-preview" id="foto-preview">👤</div>
        <div><label class="btn btn-secondary btn-sm" style="cursor:pointer">📷 Subir foto
          <input type="file" name="foto" accept="image/*" style="display:none" onchange="previewFoto(this)"></label>
          <div class="form-hint" style="margin-top:6px">JPG/PNG/WEBP. Máx 3MB.</div>
          <?php if(!empty($errors['foto'])): ?><div class="form-error"><?=h($errors['foto'])?></div><?php endif; ?>
        </div></div></div>

    <div class="form-section"><div class="form-section-title">Datos del jugador</div>
    <div class="form-grid">
      <div class="form-group"><label>Nombre <span class="required">*</span></label>
        <input type="text" name="nombre" class="form-control <?=isset($errors['nombre'])?'error':''?>" value="<?=h($data['nombre']??'')?>">
        <?php if(!empty($errors['nombre'])): ?><div class="form-error"><?=h($errors['nombre'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Apellidos <span class="required">*</span></label>
        <input type="text" name="apellidos" class="form-control <?=isset($errors['apellidos'])?'error':''?>" value="<?=h($data['apellidos']??'')?>">
        <?php if(!empty($errors['apellidos'])): ?><div class="form-error"><?=h($errors['apellidos'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Fecha de nacimiento <span class="required">*</span></label>
        <input type="date" name="fecha_nacimiento" id="fecha-nac" class="form-control <?=isset($errors['fecha_nacimiento'])?'error':''?>"
          value="<?=h($data['fecha_nacimiento']??'')?>" max="<?=date('Y-m-d')?>" onchange="actualizarCategoria(this.value)">
        <?php if(!empty($errors['fecha_nacimiento'])): ?><div class="form-error"><?=h($errors['fecha_nacimiento'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Categoría asignada</label>
        <div id="cat-display" style="padding:9px 12px;background:var(--gris-2);border-radius:var(--radio);font-size:.9rem;color:var(--gris-5)">
          <?php if(!empty($data['fecha_nacimiento'])): ?><strong><?=getCategoriaLabel(getCategoria($data['fecha_nacimiento'],$TEMPORADA_ACTIVA))?></strong>
          <?php else: ?>Se calculará al elegir fecha de nacimiento<?php endif; ?>
        </div><div class="form-hint">Automática según año de nacimiento (RFEF), temporada <?=getTemporadaLabel($TEMPORADA_ACTIVA)?></div></div>
      <div class="form-group"><label>Equipo / Grupo <span class="optional">(opcional)</span></label>
        <input type="text" name="equipo" class="form-control" value="<?=h($data['equipo']??'')?>" placeholder="Ej: Benjamín A"></div>
      <div class="form-group" id="grupo-email-jugador" style="<?=$necesita_email?'':'display:none'?>">
        <label>Email del jugador <?=$necesita_email?'<span class="required">*</span>':''?></label>
        <input type="email" name="jugador_email" class="form-control" value="<?=h($data['jugador_email']??'')?>">
        <?php if(!empty($errors['jugador_email'])): ?><div class="form-error"><?=h($errors['jugador_email'])?></div><?php endif; ?></div>
    </div></div>

    <div class="form-section"><div class="form-section-title">Padre / Madre / Tutor legal</div>
    <div class="form-grid">
      <div class="form-group"><label>Nombre <span class="required">*</span></label>
        <input type="text" name="tutor_nombre" class="form-control" value="<?=h($data['tutor_nombre']??'')?>">
        <?php if(!empty($errors['tutor_nombre'])): ?><div class="form-error"><?=h($errors['tutor_nombre'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Apellidos <span class="required">*</span></label>
        <input type="text" name="tutor_apellidos" class="form-control" value="<?=h($data['tutor_apellidos']??'')?>">
        <?php if(!empty($errors['tutor_apellidos'])): ?><div class="form-error"><?=h($errors['tutor_apellidos'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Email <span class="required">*</span></label>
        <input type="email" name="tutor_email" class="form-control" value="<?=h($data['tutor_email']??'')?>">
        <?php if(!empty($errors['tutor_email'])): ?><div class="form-error"><?=h($errors['tutor_email'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Teléfono <span class="optional">(opcional)</span></label>
        <input type="tel" name="tutor_telefono" class="form-control" value="<?=h($data['tutor_telefono']??'')?>" placeholder="6XX XXX XXX"></div>
    </div></div>

    <div class="form-section"><div class="form-section-title">Notas internas</div>
      <div class="form-group"><textarea name="notas" class="form-control" rows="3"><?=h($data['notas']??'')?></textarea></div></div>

    <div class="d-flex gap-8">
      <button type="submit" class="btn btn-primary btn-lg">💾 Registrar jugador</button>
      <a href="<?=BASE_URL?>/jugadores.php" class="btn btn-secondary btn-lg">Cancelar</a>
    </div>
  </form>
  </div>
</div>
</div></div></div>
<script>
const TEMP=<?=$TEMPORADA_ACTIVA?>;
const CATS={prebenjamin:'Prebenjamín',benjamin:'Benjamín',alevin:'Alevín',infantil:'Infantil',cadete:'Cadete',juvenil:'Juvenil',bebe:'Bebé',senior:'Sénior'};
function getCat(f){if(!f)return null;const a=new Date(f).getFullYear(),e=TEMP-a;
  if(e<=5)return'bebe';if(e<=7)return'prebenjamin';if(e<=9)return'benjamin';
  if(e<=11)return'alevin';if(e<=13)return'infantil';if(e<=15)return'cadete';
  if(e<=17)return'juvenil';return'senior';}
function edad(f){const h=new Date(),n=new Date(f);let e=h.getFullYear()-n.getFullYear();
  if(h.getMonth()-n.getMonth()<0||(h.getMonth()===n.getMonth()&&h.getDate()<n.getDate()))e--;return e;}
function actualizarCategoria(f){
  const cat=getCat(f),d=document.getElementById('cat-display');
  d.innerHTML=cat?'<strong>'+(CATS[cat]||cat)+'</strong>':'Se calculará al elegir fecha de nacimiento';
  const g=document.getElementById('grupo-email-jugador');
  if(g)g.style.display=f&&edad(f)>=16?'':'none';}
function previewFoto(i){if(i.files&&i.files[0]){const r=new FileReader();r.onload=e=>{document.getElementById('foto-preview').innerHTML='<img src="'+e.target.result+'">';};r.readAsDataURL(i.files[0]);}}
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}
</script>
</body></html>
