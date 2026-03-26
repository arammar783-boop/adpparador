<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/layout.php';
requireLogin();
if($TEMPORADA_ACTIVA!==calcularTemporadaActual()){flash('No puedes editar en vista histórica.','error');redirect(BASE_URL.'/jugadores.php');}
$id=(int)($_GET['id']??0); if(!$id) redirect(BASE_URL.'/jugadores.php');
$jq=$pdo->prepare("SELECT * FROM jugadores WHERE id=?"); $jq->execute([$id]); $j=$jq->fetch();
if(!$j) redirect(BASE_URL.'/jugadores.php');
$tid=getTemporadaId($pdo,$TEMPORADA_ACTIVA);
$insc=null; if($tid){$s=$pdo->prepare("SELECT * FROM inscripciones WHERE jugador_id=? AND temporada_id=?");$s->execute([$id,$tid]);$insc=$s->fetch();}
$errors=[]; $data=$j;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $data=['nombre'=>trim($_POST['nombre']??''),'apellidos'=>trim($_POST['apellidos']??''),
           'fecha_nacimiento'=>trim($_POST['fecha_nacimiento']??''),
           'tutor_nombre'=>trim($_POST['tutor_nombre']??''),'tutor_apellidos'=>trim($_POST['tutor_apellidos']??''),
           'tutor_email'=>trim($_POST['tutor_email']??''),'tutor_telefono'=>trim($_POST['tutor_telefono']??''),
           'jugador_email'=>trim($_POST['jugador_email']??''),'notas'=>trim($_POST['notas']??''),
           'equipo'=>trim($_POST['equipo']??'')];
    if(!$data['nombre']) $errors['nombre']='Requerido';
    if(!$data['apellidos']) $errors['apellidos']='Requerido';
    if(!$data['fecha_nacimiento']) $errors['fecha_nacimiento']='Requerido';
    if(!$data['tutor_nombre']) $errors['tutor_nombre']='Requerido';
    if(!$data['tutor_apellidos']) $errors['tutor_apellidos']='Requerido';
    if(!$data['tutor_email']) $errors['tutor_email']='Requerido';
    elseif(!filter_var($data['tutor_email'],FILTER_VALIDATE_EMAIL)) $errors['tutor_email']='Email inválido';
    if($data['jugador_email']&&!filter_var($data['jugador_email'],FILTER_VALIDATE_EMAIL)) $errors['jugador_email']='Email inválido';
    $foto_nombre=$j['foto'];
    if(!empty($_FILES['foto']['name'])){
        $ext=strtolower(pathinfo($_FILES['foto']['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['jpg','jpeg','png','webp'])) $errors['foto']='Solo JPG/PNG/WEBP';
        elseif($_FILES['foto']['size']>3*1024*1024) $errors['foto']='Máximo 3MB';
        else $foto_nombre=uniqid('jug_',true).'.'.$ext;
    }
    if(isset($_POST['borrar_foto'])) $foto_nombre=null;
    if(empty($errors)){
        $nueva_cat=getCategoria($data['fecha_nacimiento'],$TEMPORADA_ACTIVA);
        $pdo->prepare("UPDATE jugadores SET nombre=?,apellidos=?,fecha_nacimiento=?,foto=?,tutor_nombre=?,tutor_apellidos=?,tutor_email=?,tutor_telefono=?,jugador_email=?,notas=? WHERE id=?")
            ->execute([$data['nombre'],$data['apellidos'],$data['fecha_nacimiento'],$foto_nombre,
                       $data['tutor_nombre'],$data['tutor_apellidos'],$data['tutor_email'],
                       $data['tutor_telefono']?:null,$data['jugador_email']?:null,$data['notas']?:null,$id]);
        if($insc&&$tid)
            $pdo->prepare("UPDATE inscripciones SET categoria=?,equipo=? WHERE jugador_id=? AND temporada_id=?")
                ->execute([$nueva_cat,$data['equipo']?:null,$id,$tid]);
        if(!empty($_FILES['foto']['name'])&&!isset($errors['foto'])&&$foto_nombre!==$j['foto']){
            move_uploaded_file($_FILES['foto']['tmp_name'],__DIR__.'/uploads/'.$foto_nombre);
            if($j['foto']&&file_exists(__DIR__.'/uploads/'.$j['foto'])) @unlink(__DIR__.'/uploads/'.$j['foto']);
        }
        flash('Datos actualizados','success'); redirect(BASE_URL.'/jugador_perfil.php?id='.$id);
    }
}
$necesita_email=necesitaEmailJugador($data['fecha_nacimiento']??$j['fecha_nacimiento']);
renderHead('Editar — '.$j['nombre'].' '.$j['apellidos']);
?>
<body><div class="app-wrapper">
<?php renderSidebar('jugadores') ?>
<div class="main-content">
<?php renderTopHeader('Editar jugador',h($j['nombre'].' '.$j['apellidos'])) ?>
<div class="page-content">
<?php if(!empty($errors['_general'])): ?><div class="alert alert-error"><?=h($errors['_general'])?></div><?php endif; ?>
<div class="card" style="max-width:860px">
  <div class="card-header"><div class="card-title">Editar datos</div>
    <a href="<?=BASE_URL?>/jugador_perfil.php?id=<?=$id?>" class="btn btn-secondary btn-sm">← Volver</a></div>
  <div class="card-body">
  <form method="POST" enctype="multipart/form-data" novalidate>
    <div class="form-section"><div class="form-section-title">Fotografía</div>
      <div class="foto-upload-area">
        <div class="foto-preview" id="foto-preview">
          <?php if($j['foto']&&file_exists(__DIR__.'/uploads/'.$j['foto'])): ?>
          <img src="<?=BASE_URL?>/uploads/<?=h($j['foto'])?>" alt="foto"><?php else: ?>👤<?php endif; ?></div>
        <div><label class="btn btn-secondary btn-sm" style="cursor:pointer;margin-right:6px">📷 Cambiar
          <input type="file" name="foto" accept="image/*" style="display:none" onchange="previewFoto(this)"></label>
          <?php if($j['foto']): ?><label style="cursor:pointer;font-size:.83rem;color:var(--rojo)">
            <input type="checkbox" name="borrar_foto" value="1"> Eliminar</label><?php endif; ?>
        </div></div></div>
    <div class="form-section"><div class="form-section-title">Datos del jugador</div>
    <div class="form-grid">
      <div class="form-group"><label>Nombre *</label><input type="text" name="nombre" class="form-control" value="<?=h($data['nombre'])?>">
        <?php if(!empty($errors['nombre'])): ?><div class="form-error"><?=h($errors['nombre'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Apellidos *</label><input type="text" name="apellidos" class="form-control" value="<?=h($data['apellidos'])?>">
        <?php if(!empty($errors['apellidos'])): ?><div class="form-error"><?=h($errors['apellidos'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Fecha nacimiento *</label>
        <input type="date" name="fecha_nacimiento" id="fecha-nac" class="form-control" value="<?=h($data['fecha_nacimiento'])?>" max="<?=date('Y-m-d')?>" onchange="actualizarCategoria(this.value)">
        <?php if(!empty($errors['fecha_nacimiento'])): ?><div class="form-error"><?=h($errors['fecha_nacimiento'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Categoría</label>
        <div id="cat-display" style="padding:9px 12px;background:var(--gris-2);border-radius:var(--radio);font-size:.9rem">
          <strong><?=getCategoriaLabel(getCategoria($data['fecha_nacimiento'],$TEMPORADA_ACTIVA))?></strong></div></div>
      <div class="form-group"><label>Equipo</label><input type="text" name="equipo" class="form-control" value="<?=h($insc?$insc['equipo']??'':$data['equipo']??'')?>" placeholder="Ej: Benjamín A"></div>
      <div class="form-group" id="grupo-email-jugador" style="<?=$necesita_email?'':'display:none'?>">
        <label>Email jugador <?=$necesita_email?'<span class="required">*</span>':''?></label>
        <input type="email" name="jugador_email" class="form-control" value="<?=h($data['jugador_email']??'')?>">
        <?php if(!empty($errors['jugador_email'])): ?><div class="form-error"><?=h($errors['jugador_email'])?></div><?php endif; ?></div>
    </div></div>
    <div class="form-section"><div class="form-section-title">Padre / Madre / Tutor</div>
    <div class="form-grid">
      <div class="form-group"><label>Nombre *</label><input type="text" name="tutor_nombre" class="form-control" value="<?=h($data['tutor_nombre'])?>">
        <?php if(!empty($errors['tutor_nombre'])): ?><div class="form-error"><?=h($errors['tutor_nombre'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Apellidos *</label><input type="text" name="tutor_apellidos" class="form-control" value="<?=h($data['tutor_apellidos'])?>">
        <?php if(!empty($errors['tutor_apellidos'])): ?><div class="form-error"><?=h($errors['tutor_apellidos'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Email *</label><input type="email" name="tutor_email" class="form-control" value="<?=h($data['tutor_email'])?>">
        <?php if(!empty($errors['tutor_email'])): ?><div class="form-error"><?=h($errors['tutor_email'])?></div><?php endif; ?></div>
      <div class="form-group"><label>Teléfono</label><input type="tel" name="tutor_telefono" class="form-control" value="<?=h($data['tutor_telefono']??'')?>"></div>
    </div></div>
    <div class="form-section"><div class="form-section-title">Notas</div>
      <div class="form-group"><textarea name="notas" class="form-control" rows="3"><?=h($data['notas']??'')?></textarea></div></div>
    <div class="d-flex gap-8">
      <button type="submit" class="btn btn-primary btn-lg">💾 Guardar</button>
      <a href="<?=BASE_URL?>/jugador_perfil.php?id=<?=$id?>" class="btn btn-secondary btn-lg">Cancelar</a>
    </div>
  </form></div>
</div></div></div></div>
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
  d.innerHTML=cat?'<strong>'+(CATS[cat]||cat)+'</strong>':'—';
  const g=document.getElementById('grupo-email-jugador');if(g)g.style.display=f&&edad(f)>=16?'':'none';}
function previewFoto(i){if(i.files&&i.files[0]){const r=new FileReader();r.onload=e=>{document.getElementById('foto-preview').innerHTML='<img src="'+e.target.result+'">';};r.readAsDataURL(i.files[0]);}}
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}
</script></body></html>
