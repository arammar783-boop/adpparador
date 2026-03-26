<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/layout.php';
requireLogin();

$tid = getTemporadaId($pdo, $TEMPORADA_ACTIVA);
$total    = $tid ? $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE temporada_id=? AND activo=1") : null;
if($tid){$total->execute([$tid]); $total=$total->fetchColumn();} else $total=0;
$pendientes_count = $tid ? $pdo->prepare("SELECT COUNT(*) FROM pagos WHERE temporada_id=? AND pagado=0") : null;
if($tid){$pendientes_count->execute([$tid]); $pendientes_count=$pendientes_count->fetchColumn();} else $pendientes_count=0;
$pagados_count = $tid ? $pdo->prepare("SELECT COUNT(*) FROM pagos WHERE temporada_id=? AND pagado=1") : null;
if($tid){$pagados_count->execute([$tid]); $pagados_count=$pagados_count->fetchColumn();} else $pagados_count=0;
$ingresos = 0;
if($tid){$s=$pdo->prepare("SELECT COALESCE(SUM(cuota),0) FROM pagos WHERE temporada_id=? AND pagado=1"); $s->execute([$tid]); $ingresos=$s->fetchColumn();}

$por_categoria = [];
if($tid){
    $s=$pdo->prepare("SELECT i.categoria,COUNT(*) as total FROM inscripciones i WHERE i.temporada_id=? AND i.activo=1 GROUP BY i.categoria ORDER BY FIELD(i.categoria,'prebenjamin','benjamin','alevin','infantil','cadete','juvenil')");
    $s->execute([$tid]); $por_categoria=$s->fetchAll();
}

$ultimos=$pdo->query("SELECT * FROM jugadores ORDER BY creado_en DESC LIMIT 5")->fetchAll();

$mes_actual=date('m');
$pendientes_mes=[];
if($tid){
    $s=$pdo->prepare("SELECT j.nombre,j.apellidos,i.categoria,p.cuota FROM pagos p JOIN jugadores j ON j.id=p.jugador_id JOIN inscripciones i ON i.jugador_id=j.id AND i.temporada_id=p.temporada_id WHERE p.temporada_id=? AND p.mes=? AND p.pagado=0 ORDER BY j.apellidos LIMIT 8");
    $s->execute([$tid,$mes_actual]); $pendientes_mes=$s->fetchAll();
}

// Alertas: temporada próxima a terminar / nueva temporada disponible
$anio_real = calcularTemporadaActual();
$existe_siguiente = $pdo->prepare("SELECT id FROM temporadas WHERE anio_inicio=?");
$existe_siguiente->execute([$TEMPORADA_ACTIVA+1]);
$hay_siguiente = (bool)$existe_siguiente->fetch();

renderHead('Inicio');
?>
<body><div class="app-wrapper">
<?php renderSidebar('dashboard') ?>
<div class="main-content">
<?php renderTopHeader('Panel principal','Temporada '.getTemporadaLabel($TEMPORADA_ACTIVA)) ?>
<div class="page-content">
<?php renderFlash() ?>

<?php if($TEMPORADA_ACTIVA===$anio_real && !$hay_siguiente && (int)date('m')>=6): ?>
<div class="alert alert-warn">
  📅 La temporada <?=getTemporadaLabel($TEMPORADA_ACTIVA)?> está próxima a terminar.
  <a href="<?=BASE_URL?>/temporadas.php" style="font-weight:600;margin-left:8px">Crear nueva temporada →</a>
</div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-card-icon" style="background:#e8f5eb">⚽</div>
    <div class="stat-card-value"><?=$total?></div><div class="stat-card-label">Jugadores activos</div></div>
  <div class="stat-card"><div class="stat-card-icon" style="background:#dcfce7">✅</div>
    <div class="stat-card-value"><?=$pagados_count?></div><div class="stat-card-label">Pagos cobrados</div></div>
  <div class="stat-card"><div class="stat-card-icon" style="background:#fee2e2">⏳</div>
    <div class="stat-card-value"><?=$pendientes_count?></div><div class="stat-card-label">Pagos pendientes</div></div>
  <div class="stat-card"><div class="stat-card-icon" style="background:#fef9c3">💶</div>
    <div class="stat-card-value"><?=number_format($ingresos,0,'.','.') ?>€</div><div class="stat-card-label">Ingresos temporada</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
  <div class="card">
    <div class="card-header"><div class="card-title">Jugadores por categoría</div>
      <a href="<?=BASE_URL?>/jugadores.php" class="btn btn-secondary btn-sm">Ver todos</a></div>
    <div class="card-body" style="padding:0"><table>
      <thead><tr><th>Categoría</th><th>Jugadores</th><th></th></tr></thead>
      <tbody>
      <?php foreach($por_categoria as $row): ?>
      <tr>
        <td><span class="cat-tag badge" style="background:<?=getCategoriaColor($row['categoria'])?>22;color:<?=getCategoriaColor($row['categoria'])?>">
          <?=getCategoriaLabel($row['categoria'])?></span></td>
        <td><strong><?=$row['total']?></strong></td>
        <td><a href="<?=BASE_URL?>/jugadores.php?categoria=<?=$row['categoria']?>" class="btn btn-outline btn-sm">Ver</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($por_categoria)): ?>
      <tr><td colspan="3" class="text-center text-gray" style="padding:24px">Sin jugadores esta temporada</td></tr>
      <?php endif; ?>
      </tbody>
    </table></div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Pendientes — <?=NOMBRES_MESES[$mes_actual]??'Mes actual'?></div>
      <a href="<?=BASE_URL?>/pagos.php" class="btn btn-secondary btn-sm">Ver pagos</a></div>
    <div class="card-body" style="padding:0">
      <?php if($pendientes_mes): ?>
      <table><thead><tr><th>Jugador</th><th>Categoría</th><th>Cuota</th></tr></thead><tbody>
        <?php foreach($pendientes_mes as $p): ?>
        <tr><td><?=h($p['nombre'].' '.$p['apellidos'])?></td>
          <td><span class="cat-tag badge" style="background:<?=getCategoriaColor($p['categoria'])?>22;color:<?=getCategoriaColor($p['categoria'])?>">
            <?=getCategoriaLabel($p['categoria'])?></span></td>
          <td><?=number_format($p['cuota'],2,',','.')?>€</td></tr>
        <?php endforeach; ?>
      </tbody></table>
      <?php else: ?><div class="text-center text-gray" style="padding:32px"><div style="font-size:2rem">🎉</div><div>Todos al día este mes</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><div class="card-title">Últimas altas</div>
    <a href="<?=BASE_URL?>/jugador_nuevo.php" class="btn btn-primary btn-sm">+ Nuevo jugador</a></div>
  <div class="card-body" style="padding:0"><div class="table-wrap"><table>
    <thead><tr><th>Jugador</th><th>Fecha nacimiento</th><th>Alta</th><th>Tutor</th><th></th></tr></thead>
    <tbody>
    <?php foreach($ultimos as $j): ?>
    <tr>
      <td><div class="jugador-info">
        <?php renderFoto($j['foto'],$j['nombre'][0].($j['apellidos'][0]??'')) ?>
        <div><div class="jugador-name"><?=h($j['nombre'].' '.$j['apellidos'])?></div></div>
      </div></td>
      <td class="text-sm"><?=date('d/m/Y',strtotime($j['fecha_nacimiento']))?></td>
      <td class="text-sm text-gray"><?=date('d/m/Y',strtotime($j['creado_en']))?></td>
      <td class="text-sm"><?=h($j['tutor_nombre'].' '.$j['tutor_apellidos'])?></td>
      <td><a href="<?=BASE_URL?>/jugador_perfil.php?id=<?=$j['id']?>" class="btn btn-secondary btn-sm">Ver</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($ultimos)): ?>
    <tr><td colspan="5" class="text-center text-gray" style="padding:32px">
      Sin jugadores aún. <a href="<?=BASE_URL?>/jugador_nuevo.php">Registra el primero →</a></td></tr>
    <?php endif; ?>
    </tbody>
  </table></div></div>
</div>

</div></div></div>
<script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}</script>
</body></html>
