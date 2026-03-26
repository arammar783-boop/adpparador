<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/layout.php';
requireLogin();

$categoria=$_GET['categoria']??''; $buscar=trim($_GET['q']??''); $estado=$_GET['activo']??'1';
$params=[$TEMPORADA_ACTIVA,$TEMPORADA_ACTIVA]; $where=['i.anio_inicio=?'];
if($categoria){$where[]='i.categoria=?'; $params[]=$categoria;}
if($buscar){
    $where[]="(j.nombre LIKE ? OR j.apellidos LIKE ? OR j.tutor_nombre LIKE ? OR j.tutor_apellidos LIKE ?)";
    $params=array_merge($params,array_fill(0,4,'%'.$buscar.'%'));
}
if($estado!==''){$where[]='i.activo=?'; $params[]=(int)$estado;}
$sql="SELECT j.*,i.categoria,i.equipo,i.activo as inscrito,i.motivo_baja,i.club_destino,i.fecha_baja,
    (SELECT COUNT(*) FROM pagos p WHERE p.jugador_id=j.id AND p.anio_inicio=? AND p.pagado=0) AS pagos_pendientes
    FROM jugadores j JOIN inscripciones i ON i.jugador_id=j.id
    WHERE ".implode(' AND ',$where)." ORDER BY j.apellidos,j.nombre";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $jugadores=$stmt->fetchAll();
$titulo=$categoria?getCategoriaLabel($categoria):'Todos los jugadores';
$es_historica=$TEMPORADA_ACTIVA!==calcularTemporadaActual();
renderHead('Jugadores');
?>
<body><div class="app-wrapper">
<?php renderSidebar($categoria?'cat_'.$categoria:'jugadores') ?>
<div class="main-content">
<?php renderTopHeader('Jugadores',$titulo) ?>
<div class="page-content">
<?php renderFlash() ?>
<div class="card" style="margin-bottom:18px"><div class="card-body" style="padding:14px 18px">
  <form method="GET" class="filtros-bar">
    <?php if($categoria): ?><input type="hidden" name="categoria" value="<?=h($categoria)?>"> <?php endif; ?>
    <div class="search-input-wrap"><span class="search-icon">🔍</span>
      <input type="text" name="q" class="form-control" placeholder="Buscar jugador o tutor..." value="<?=h($buscar)?>">
    </div>
    <?php if(!$categoria): ?>
    <select name="categoria" class="form-control" style="width:auto" onchange="this.form.submit()">
      <option value="">Todas las categorías</option>
      <?php foreach(['prebenjamin','benjamin','alevin','infantil','cadete','juvenil'] as $c): ?>
      <option value="<?=$c?>" <?=$categoria===$c?'selected':''?>><?=getCategoriaLabel($c)?></option>
      <?php endforeach; ?>
    </select><?php endif; ?>
    <select name="activo" class="form-control" style="width:auto" onchange="this.form.submit()">
      <option value="1" <?=$estado==='1'?'selected':''?>>Activos</option>
      <option value="0" <?=$estado==='0'?'selected':''?>>Bajas</option>
      <option value=""  <?=$estado===''?'selected':''?>>Todos</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filtrar</button>
    <a href="<?=BASE_URL?>/jugadores.php" class="btn btn-secondary">Limpiar</a>
  </form>
</div></div>

<div class="card">
  <div class="card-header">
    <div class="card-title"><?=$titulo?> — <?=getTemporadaLabel($TEMPORADA_ACTIVA)?>
      <span class="badge badge-gray" style="margin-left:8px"><?=count($jugadores)?></span>
    </div>
    <?php if(!$es_historica): ?>
    <div class="d-flex gap-8">
      <a href="<?=BASE_URL?>/generar_enlace.php" class="btn btn-outline btn-sm">🔗 Invitar padre</a>
      <a href="<?=BASE_URL?>/jugador_nuevo.php" class="btn btn-primary btn-sm">+ Nuevo jugador</a>
    </div>
    <?php endif; ?>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Jugador</th><th>Nacimiento</th><th>Categoría</th><th>Equipo</th><th>Tutor</th><th>Pagos</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach($jugadores as $j): ?>
    <tr>
      <td><div class="jugador-info">
        <?php renderFoto($j['foto'],($j['nombre'][0]??'').($j['apellidos'][0]??'')) ?>
        <div><div class="jugador-name"><?=h($j['nombre'].' '.$j['apellidos'])?></div>
        <?php if($j['jugador_email']): ?><div class="jugador-sub">📧 <?=h($j['jugador_email'])?></div><?php endif; ?>
        </div></div></td>
      <td class="text-sm"><?=date('d/m/Y',strtotime($j['fecha_nacimiento']))?>
        <div class="text-xs text-gray"><?=edadActual($j['fecha_nacimiento'])?> años</div></td>
      <td><span class="cat-tag badge" style="background:<?=getCategoriaColor($j['categoria'])?>22;color:<?=getCategoriaColor($j['categoria'])?>">
        <?=getCategoriaLabel($j['categoria'])?></span></td>
      <td class="text-sm text-gray"><?=h($j['equipo']??'—')?></td>
      <td class="text-sm"><?=h($j['tutor_nombre'].' '.$j['tutor_apellidos'])?>
        <?php if($j['tutor_telefono']): ?><div class="text-xs text-gray">📞 <?=h($j['tutor_telefono'])?></div><?php endif; ?></td>
      <td><?php if($j['inscrito']): ?>
        <?php if($j['pagos_pendientes']>0): ?>
          <span class="badge badge-red">⚠ <?=$j['pagos_pendientes']?> pendiente<?=$j['pagos_pendientes']>1?'s':''?></span>
        <?php else: ?><span class="badge badge-green">✓ Al día</span><?php endif; ?>
        <?php else: ?><span class="badge badge-gray">—</span><?php endif; ?></td>
      <td><?php if($j['inscrito']): ?><span class="badge badge-green">Activo</span>
        <?php else: ?><span class="badge badge-red" title="<?=h($j['club_destino']??'')?>">
          Baja<?=$j['club_destino']?' → '.h($j['club_destino']):''?></span><?php endif; ?></td>
      <td><div class="d-flex gap-8">
        <a href="<?=BASE_URL?>/jugador_perfil.php?id=<?=$j['id']?>" class="btn btn-secondary btn-sm">Ver</a>
        <?php if(!$es_historica): ?>
        <a href="<?=BASE_URL?>/jugador_editar.php?id=<?=$j['id']?>" class="btn btn-outline btn-sm">✏</a>
        <?php endif; ?>
      </div></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($jugadores)): ?>
    <tr><td colspan="8" class="text-center text-gray" style="padding:32px">
      <?php if($buscar||$categoria): ?>Sin resultados. <a href="<?=BASE_URL?>/jugadores.php">Ver todos</a>
      <?php elseif($es_historica): ?>No hay jugadores en la temporada <?=getTemporadaLabel($TEMPORADA_ACTIVA)?>.
      <?php else: ?>Sin jugadores aún. <a href="<?=BASE_URL?>/jugador_nuevo.php">+ Registrar el primero</a><?php endif; ?>
    </td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
</div>
</div></div></div>
<script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}</script>
</body></html>
