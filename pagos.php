<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/layout.php';
requireLogin();

$mes_filtro=$_GET['mes']??date('m');
$cat_filtro=$_GET['categoria']??'';
$estado=$_GET['estado']??'';
$buscar=trim($_GET['q']??'');
$tid=getTemporadaId($pdo,$TEMPORADA_ACTIVA);

$pagos=[];$res=['total'=>0,'pagados'=>0,'total_cuota'=>0,'cobrado'=>0];
if($tid){
    $params=[$tid,$mes_filtro]; $where=['p.temporada_id=?','p.mes=?'];
    if($cat_filtro){$where[]='i.categoria=?'; $params[]=$cat_filtro;}
    if($estado==='pendiente') $where[]='p.pagado=0';
    if($estado==='pagado')    $where[]='p.pagado=1';
    if($buscar){$where[]="(j.nombre LIKE ? OR j.apellidos LIKE ?)"; $params[]='%'.$buscar.'%'; $params[]='%'.$buscar.'%';}
    $sql="SELECT p.*,j.nombre,j.apellidos,i.categoria,j.foto,j.tutor_email,j.tutor_telefono
          FROM pagos p JOIN jugadores j ON j.id=p.jugador_id
          JOIN inscripciones i ON i.jugador_id=j.id AND i.temporada_id=p.temporada_id
          WHERE ".implode(' AND ',$where)." ORDER BY p.pagado ASC,j.apellidos,j.nombre";
    $stmt=$pdo->prepare($sql); $stmt->execute($params); $pagos=$stmt->fetchAll();

    $rp=[$tid,$mes_filtro]; $rw=['p.temporada_id=?','p.mes=?'];
    if($cat_filtro){$rw[]='i.categoria=?'; $rp[]=$cat_filtro;}
    $rs=$pdo->prepare("SELECT COUNT(*) as total,SUM(p.pagado) as pagados,SUM(p.cuota) as total_cuota,
        SUM(CASE WHEN p.pagado=1 THEN p.cuota ELSE 0 END) as cobrado
        FROM pagos p JOIN inscripciones i ON i.jugador_id=p.jugador_id AND i.temporada_id=p.temporada_id
        WHERE ".implode(' AND ',$rw));
    $rs->execute($rp); $res=$rs->fetch();
}

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['toggle_pago'])&&$tid){
    $pid=(int)$_POST['pago_id']; $nuevo=(int)$_POST['nuevo_estado']; $met=$_POST['metodo']??'efectivo';
    if($nuevo===1) $pdo->prepare("UPDATE pagos SET pagado=1,fecha_pago=CURDATE(),metodo_pago=? WHERE id=?")->execute([$met,$pid]);
    else $pdo->prepare("UPDATE pagos SET pagado=0,fecha_pago=NULL,metodo_pago=NULL WHERE id=?")->execute([$pid]);
    $qs=http_build_query(['mes'=>$mes_filtro,'categoria'=>$cat_filtro,'estado'=>$estado,'q'=>$buscar]);
    redirect(BASE_URL.'/pagos.php?'.$qs);
}

$es_historica=$TEMPORADA_ACTIVA!==calcularTemporadaActual();
renderHead('Pagos');
?>
<body><div class="app-wrapper">
<?php renderSidebar('pagos') ?>
<div class="main-content">
<?php renderTopHeader('Control de pagos','Temporada '.getTemporadaLabel($TEMPORADA_ACTIVA)) ?>
<div class="page-content">
<?php renderFlash() ?>

<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card"><div class="stat-card-icon" style="background:#e8f5eb">📋</div>
    <div class="stat-card-value"><?=(int)($res['total']??0)?></div>
    <div class="stat-card-label">Recibos <?=NOMBRES_MESES[$mes_filtro]??$mes_filtro?></div></div>
  <div class="stat-card"><div class="stat-card-icon" style="background:#dcfce7">✅</div>
    <div class="stat-card-value"><?=(int)($res['pagados']??0)?></div><div class="stat-card-label">Pagados</div></div>
  <div class="stat-card"><div class="stat-card-icon" style="background:#fee2e2">⏳</div>
    <div class="stat-card-value"><?=(int)($res['total']??0)-(int)($res['pagados']??0)?></div><div class="stat-card-label">Pendientes</div></div>
  <div class="stat-card"><div class="stat-card-icon" style="background:#fef9c3">💶</div>
    <div class="stat-card-value"><?=number_format($res['cobrado']??0,0,'.','.') ?>€</div>
    <div class="stat-card-label">Cobrado / <?=number_format($res['total_cuota']??0,0,'.','.') ?>€</div></div>
</div>

<div class="card" style="margin-bottom:18px"><div class="card-body" style="padding:14px 18px">
  <form method="GET" class="filtros-bar">
    <div class="search-input-wrap"><span class="search-icon">🔍</span>
      <input type="text" name="q" class="form-control" placeholder="Buscar jugador..." value="<?=h($buscar)?>"></div>
    <select name="mes" class="form-control" style="width:auto" onchange="this.form.submit()">
      <?php foreach(MESES_TEMPORADA as $m): ?>
      <option value="<?=$m?>" <?=$mes_filtro===$m?'selected':''?>><?=NOMBRES_MESES[$m]?></option>
      <?php endforeach; ?>
    </select>
    <select name="categoria" class="form-control" style="width:auto" onchange="this.form.submit()">
      <option value="">Todas categorías</option>
      <?php foreach(['prebenjamin','benjamin','alevin','infantil','cadete','juvenil'] as $c): ?>
      <option value="<?=$c?>" <?=$cat_filtro===$c?'selected':''?>><?=getCategoriaLabel($c)?></option>
      <?php endforeach; ?>
    </select>
    <select name="estado" class="form-control" style="width:auto" onchange="this.form.submit()">
      <option value="">Todos</option>
      <option value="pendiente" <?=$estado==='pendiente'?'selected':''?>>Pendientes</option>
      <option value="pagado"    <?=$estado==='pagado'?'selected':''?>>Pagados</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filtrar</button>
    <a href="<?=BASE_URL?>/pagos.php?mes=<?=$mes_filtro?>" class="btn btn-secondary">Limpiar</a>
  </form>
</div></div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Pagos — <?=NOMBRES_MESES[$mes_filtro]??$mes_filtro?>
      <?php if($cat_filtro): ?> · <?=getCategoriaLabel($cat_filtro)?><?php endif; ?>
      <span class="badge badge-gray" style="margin-left:8px"><?=count($pagos)?></span></div>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Jugador</th><th>Categoría</th><th>Cuota</th><th>Estado</th><th>Fecha</th><th>Método</th><th>Tutor</th>
      <?php if(!$es_historica): ?><th>Acción</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach($pagos as $p): ?>
    <tr>
      <td><div class="jugador-info">
        <?php renderFoto($p['foto'],($p['nombre'][0]??'').($p['apellidos'][0]??'')) ?>
        <div><a href="<?=BASE_URL?>/jugador_perfil.php?id=<?=$p['jugador_id']?>" class="jugador-name"><?=h($p['nombre'].' '.$p['apellidos'])?></a></div>
      </div></td>
      <td><span class="cat-tag badge" style="background:<?=getCategoriaColor($p['categoria'])?>22;color:<?=getCategoriaColor($p['categoria'])?>">
        <?=getCategoriaLabel($p['categoria'])?></span></td>
      <td class="fw-600"><?=number_format($p['cuota'],2,',','.')?>€</td>
      <td><?=$p['pagado']?'<span class="badge badge-green">✓ Pagado</span>':'<span class="badge badge-red">⏳ Pendiente</span>'?></td>
      <td class="text-sm text-gray"><?=$p['fecha_pago']?date('d/m/Y',strtotime($p['fecha_pago'])):'—'?></td>
      <td class="text-sm text-gray"><?=$p['metodo_pago']?ucfirst($p['metodo_pago']):'—'?></td>
      <td class="text-sm"><?=h($p['tutor_email'])?>
        <?php if($p['tutor_telefono']): ?><div class="text-xs text-gray">📞 <?=h($p['tutor_telefono'])?></div><?php endif; ?></td>
      <?php if(!$es_historica): ?>
      <td><button class="btn btn-sm <?=$p['pagado']?'btn-secondary':'btn-primary'?>"
        onclick="togglePago(<?=$p['id']?>,<?=$p['pagado']?0:1?>,'<?=h($p['nombre'].' '.$p['apellidos'])?>')"><?=$p['pagado']?'Desmarcar':'✓ Cobrado'?></button></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($pagos)): ?>
    <tr><td colspan="8" class="text-center text-gray" style="padding:32px">
      <?=!$tid?'No existe la temporada '.getTemporadaLabel($TEMPORADA_ACTIVA).' en la base de datos.':'Sin registros con estos filtros.'?>
    </td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
</div>
</div></div></div>

<div class="modal-overlay" id="modal-pago">
  <div class="modal">
    <div class="modal-header"><h3 id="modal-title">Registrar pago</h3>
      <button onclick="document.getElementById('modal-pago').classList.remove('open')" style="background:none;border:none;font-size:1.3rem;cursor:pointer">✕</button></div>
    <form method="POST">
      <input type="hidden" name="toggle_pago" value="1">
      <input type="hidden" name="pago_id" id="modal-pago-id">
      <input type="hidden" name="nuevo_estado" id="modal-nuevo-estado">
      <div class="modal-body">
        <div id="modal-body-pagar"><div class="form-group"><label>Método de pago</label>
          <select name="metodo" class="form-control">
            <option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option>
            <option value="domiciliacion">Domiciliación</option><option value="otro">Otro</option>
          </select></div></div>
        <div id="modal-body-desmarcar" class="hidden"><div class="alert alert-warn">¿Marcar como pendiente?</div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-pago').classList.remove('open')">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="modal-btn-ok">Confirmar</button>
      </div>
    </form>
  </div>
</div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}
function togglePago(pid,nuevo,n){
  document.getElementById('modal-pago-id').value=pid;
  document.getElementById('modal-nuevo-estado').value=nuevo;
  document.getElementById('modal-title').textContent=nuevo===1?'Cobrar — '+n:'Desmarcar — '+n;
  document.getElementById('modal-body-pagar').classList.toggle('hidden',nuevo!==1);
  document.getElementById('modal-body-desmarcar').classList.toggle('hidden',nuevo!==0);
  document.getElementById('modal-btn-ok').textContent=nuevo===1?'✓ Marcar pagado':'Desmarcar';
  document.getElementById('modal-pago').classList.add('open');
}
document.getElementById('modal-pago').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
</script>
</body></html>
