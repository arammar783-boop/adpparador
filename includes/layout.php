<?php
function renderHead(string $t='ADP Gestión'): void {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>'.h($t).' — ADP Club</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="'.BASE_URL.'/css/style.css">
</head>';
}

function renderSidebar(string $active=''): void {
    global $pdo,$TEMPORADA_ACTIVA;
    $tlist=[];
    try { $tlist=$pdo->query("SELECT * FROM temporadas ORDER BY anio_inicio DESC")->fetchAll(); } catch(Exception $e){}
    $real=calcularTemporadaActual(); $hist=$TEMPORADA_ACTIVA!==$real;
    $nav=[
        ['🏠','Inicio','dashboard.php','dashboard'],
        ['⚽','Jugadores','jugadores.php','jugadores'],
        ['💰','Pagos','pagos.php','pagos'],
        ['📣','Notificaciones','notificaciones.php','notificaciones'],
        ['🔗','Invitar padre','generar_enlace.php','enlace'],
        ['📅','Temporadas','temporadas.php','temporadas'],
    ]; ?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">⚽</div>
    <div class="sidebar-logo-text">ADP Gestión<span>Portal interno</span></div>
  </div>
  <div style="padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.12)">
    <div style="font-size:.65rem;color:rgba(255,255,255,.45);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:5px">Temporada</div>
    <form method="POST" action="<?=BASE_URL?>/cambiar_temporada.php">
      <select name="temporada" onchange="this.form.submit()"
        style="width:100%;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;padding:6px 8px;border-radius:6px;font-size:.82rem;cursor:pointer">
        <?php foreach($tlist as $t): ?>
        <option value="<?=$t['anio_inicio']?>" <?=$TEMPORADA_ACTIVA==$t['anio_inicio']?'selected':''?>>
          <?=h($t['nombre'])?><?=$t['activa']?' ★':''?>
        </option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="redirect" value="<?=h($_SERVER['REQUEST_URI']??'')?>">
    </form>
    <?php if($hist): ?>
    <div style="margin-top:5px;font-size:.7rem;color:#fcd34d;text-align:center">
      👁 Vista histórica
      <a href="<?=BASE_URL?>/cambiar_temporada.php?temporada=<?=$real?>&redirect=<?=urlencode($_SERVER['REQUEST_URI']??'')?>"
         style="color:#fcd34d;text-decoration:underline;margin-left:4px">Volver a actual</a>
    </div>
    <?php endif; ?>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Principal</div>
    <?php foreach($nav as[$ico,$lbl,$href,$key]): ?>
    <a href="<?=BASE_URL.'/'.$href?>" class="nav-item <?=$active===$key?'active':''?>">
      <span class="icon"><?=$ico?></span><?=$lbl?>
    </a>
    <?php endforeach; ?>
    <div class="nav-section-label" style="margin-top:12px">Categorías</div>
    <?php foreach(['prebenjamin','benjamin','alevin','infantil','cadete','juvenil'] as $cat): ?>
    <a href="<?=BASE_URL?>/jugadores.php?categoria=<?=$cat?>"
       class="nav-item <?=$active==='cat_'.$cat?'active':''?>">
      <span class="icon">👦</span><?=getCategoriaLabel($cat)?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="<?=BASE_URL?>/perfil_admin.php">⚙️ Mi perfil / Contraseña</a>
    <a href="<?=BASE_URL?>/logout.php">🚪 Cerrar sesión</a>
    <div style="margin-top:8px">T. <?=getTemporadaLabel($TEMPORADA_ACTIVA)?></div>
  </div>
</aside>
<?php }

function renderTopHeader(string $title, string $sub=''): void {
    global $TEMPORADA_ACTIVA; $real=calcularTemporadaActual(); $hist=$TEMPORADA_ACTIVA!==$real; ?>
<header class="top-header">
  <button class="btn-menu-toggle" onclick="toggleSidebar()">☰</button>
  <div class="top-header-title">
    <?=h($title)?><?php if($sub): ?> <span>/ <?=h($sub)?></span><?php endif; ?>
    <?php if($hist): ?>
    <span style="display:inline-flex;align-items:center;gap:4px;background:#fef9c3;color:#92400e;
                 padding:2px 10px;border-radius:999px;font-size:.72rem;font-weight:600;margin-left:8px">
      👁 Histórico <?=getTemporadaLabel($TEMPORADA_ACTIVA)?>
    </span>
    <?php endif; ?>
  </div>
  <div class="d-flex align-center gap-8">
    <span class="text-sm text-gray">👤 <?=h($_SESSION['admin_nombre']??'Admin')?></span>
  </div>
</header>
<?php }

function renderFlash(): void {
    if (!empty($_SESSION['flash'])) {
        $f=$_SESSION['flash']; unset($_SESSION['flash']);
        $m=['success'=>'alert-success','error'=>'alert-error','info'=>'alert-info','warn'=>'alert-warn'];
        echo '<div class="alert '.($m[$f['type']]??'alert-info').'">'.h($f['msg']).'</div>';
    }
}
function flash(string $msg, string $type='success'): void { $_SESSION['flash']=['msg'=>$msg,'type'=>$type]; }
function renderFoto(?string $foto, string $ini='', string $cls='jugador-avatar'): void {
    if ($foto && file_exists(__DIR__.'/../uploads/'.$foto))
        echo '<div class="'.$cls.'"><img src="'.BASE_URL.'/uploads/'.h($foto).'" alt="Foto"></div>';
    else echo '<div class="'.$cls.'">'.mb_strtoupper(mb_substr($ini,0,2)).'</div>';
}
