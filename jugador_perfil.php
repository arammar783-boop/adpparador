<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/layout.php';
requireLogin();

$id=(int)($_GET['id']??0); if(!$id) redirect(BASE_URL.'/jugadores.php');
$jq=$pdo->prepare("SELECT * FROM jugadores WHERE id=?"); $jq->execute([$id]); $j=$jq->fetch();
if(!$j) redirect(BASE_URL.'/jugadores.php');

$tid=getTemporadaId($pdo,$TEMPORADA_ACTIVA);
$insc=null;
if($tid){
    $s=$pdo->prepare("SELECT * FROM inscripciones WHERE jugador_id=? AND temporada_id=?");
    $s->execute([$id,$tid]); $insc=$s->fetch();
}

// Cargar pagos de la temporada vista
$pagos=[];
if($tid){
    $s=$pdo->prepare("SELECT * FROM pagos WHERE jugador_id=? AND temporada_id=? ORDER BY mes");
    $s->execute([$id,$tid]);
    foreach($s->fetchAll() as $p) $pagos[$p['mes']]=$p;
}

// Historial completo de inscripciones
$hist=$pdo->prepare("SELECT i.*,t.nombre as temp_nombre FROM inscripciones i JOIN temporadas t ON t.id=i.temporada_id WHERE i.jugador_id=? ORDER BY i.anio_inicio DESC");
$hist->execute([$id]); $historial=$hist->fetchAll();

// Toggle pago
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['toggle_pago'])&&$tid){
    $pid=(int)$_POST['pago_id']; $nuevo=(int)$_POST['nuevo_estado']; $met=$_POST['metodo']??'efectivo';
    if($nuevo===1) $pdo->prepare("UPDATE pagos SET pagado=1,fecha_pago=CURDATE(),metodo_pago=? WHERE id=? AND jugador_id=?")->execute([$met,$pid,$id]);
    else $pdo->prepare("UPDATE pagos SET pagado=0,fecha_pago=NULL,metodo_pago=NULL WHERE id=? AND jugador_id=?")->execute([$pid,$id]);
    flash('Pago actualizado','success');
    redirect(BASE_URL.'/jugador_perfil.php?id='.$id.'#pagos');
}

// Registrar baja
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['registrar_baja'])&&$insc&&$tid){
    $motivo=$_POST['motivo_baja']??'otro'; $club=trim($_POST['club_destino']??'');
    $pdo->prepare("UPDATE inscripciones SET activo=0,motivo_baja=?,club_destino=?,fecha_baja=CURDATE() WHERE jugador_id=? AND temporada_id=?")
        ->execute([$motivo,$club?:null,$id,$tid]);
    flash('Baja registrada. El jugador y su historial quedan guardados.','success');
    redirect(BASE_URL.'/jugador_perfil.php?id='.$id);
}

// Reactivar
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['reactivar'])&&$insc&&$tid){
    $pdo->prepare("UPDATE inscripciones SET activo=1,motivo_baja=NULL,club_destino=NULL,fecha_baja=NULL WHERE jugador_id=? AND temporada_id=?")
        ->execute([$id,$tid]);
    flash('Jugador reactivado.','success');
    redirect(BASE_URL.'/jugador_perfil.php?id='.$id);
}

$cat=$insc?$insc['categoria']:getCategoria($j['fecha_nacimiento'],$TEMPORADA_ACTIVA);
$total_cobrado=array_sum(array_column(array_filter($pagos,fn($p)=>$p['pagado']),'cuota'));
$total_pendiente=array_sum(array_column(array_filter($pagos,fn($p)=>!$p['pagado']),'cuota'));
$pagos_cobrados=count(array_filter($pagos,fn($p)=>$p['pagado']));
$es_historica=$TEMPORADA_ACTIVA!==calcularTemporadaActual();

renderHead($j['nombre'].' '.$j['apellidos']);
?>
<body><div class="app-wrapper">
<?php renderSidebar('jugadores') ?>
<div class="main-content">
<?php renderTopHeader($j['nombre'].' '.$j['apellidos'],'Perfil') ?>
<div class="page-content">
<?php renderFlash() ?>

<!-- Cabecera -->
<div class="card" style="margin-bottom:20px"><div class="card-body">
  <div class="perfil-header">
    <?php renderFoto($j['foto'],($j['nombre'][0]??'').($j['apellidos'][0]??''),'perfil-foto') ?>
    <div class="perfil-info">
      <div class="perfil-nombre"><?=h($j['nombre'].' '.$j['apellidos'])?></div>
      <div class="perfil-meta">
        <span class="cat-tag badge" style="background:<?=getCategoriaColor($cat)?>22;color:<?=getCategoriaColor($cat)?>"><?=getCategoriaLabel($cat)?></span>
        <?php if($insc): ?>
          <?php if($insc['activo']): ?><span class="badge badge-green">Activo</span>
          <?php else: ?><span class="badge badge-red">Baja<?=$insc['club_destino']?' → '.h($insc['club_destino']):''?></span><?php endif; ?>
        <?php else: ?><span class="badge badge-gray">Sin inscripción esta temporada</span><?php endif; ?>
        <span class="text-sm text-gray">📅 <?=date('d/m/Y',strtotime($j['fecha_nacimiento']))?> (<?=edadActual($j['fecha_nacimiento'])?> años)</span>
        <?php if($insc&&$insc['equipo']): ?><span class="text-sm text-gray">⚽ <?=h($insc['equipo'])?></span><?php endif; ?>
      </div>
      <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:20px">
        <div><div class="text-xs text-gray">TUTOR LEGAL</div>
          <div class="text-sm fw-600"><?=h($j['tutor_nombre'].' '.$j['tutor_apellidos'])?></div>
          <div class="text-sm"><a href="mailto:<?=h($j['tutor_email'])?>"><?=h($j['tutor_email'])?></a></div>
          <?php if($j['tutor_telefono']): ?><div class="text-sm">📞 <?=h($j['tutor_telefono'])?></div><?php endif; ?></div>
        <?php if($j['jugador_email']): ?>
        <div><div class="text-xs text-gray">EMAIL JUGADOR</div>
          <div class="text-sm"><a href="mailto:<?=h($j['jugador_email'])?>"><?=h($j['jugador_email'])?></a></div></div>
        <?php endif; ?>
        <?php if($j['notas']): ?>
        <div><div class="text-xs text-gray">NOTAS</div><div class="text-sm"><?=nl2br(h($j['notas']))?></div></div>
        <?php endif; ?>
      </div>
    </div>
    <?php if(!$es_historica): ?>
    <div class="d-flex gap-8" style="align-self:flex-start;flex-wrap:wrap">
      <a href="<?=BASE_URL?>/jugador_editar.php?id=<?=$j['id']?>" class="btn btn-outline btn-sm">✏ Editar</a>
      <?php if($insc&&$insc['activo']): ?>
      <button class="btn btn-danger btn-sm" onclick="document.getElementById('modal-baja').classList.add('open')">📤 Dar de baja</button>
      <?php elseif($insc&&!$insc['activo']): ?>
      <form method="POST" style="display:inline">
        <input type="hidden" name="reactivar" value="1">
        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('¿Reactivar jugador?')">↩ Reactivar</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div></div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab-btn active" onclick="showTab('pagos',this)">💰 Pagos <?=getTemporadaLabel($TEMPORADA_ACTIVA)?></button>
  <button class="tab-btn" onclick="showTab('historial',this)">📋 Historial temporadas</button>
</div>

<!-- Tab pagos -->
<div class="tab-panel active" id="tab-pagos">
  <?php if(!$insc): ?>
  <div class="alert alert-warn">Este jugador no tiene inscripción en la temporada <?=getTemporadaLabel($TEMPORADA_ACTIVA)?>.</div>
  <?php elseif(empty($pagos)): ?>
  <div class="alert alert-warn">No hay pagos registrados para esta temporada.</div>
  <?php else: ?>
  <div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card"><div class="stat-card-value text-verde"><?=number_format($total_cobrado,2,',','.')?>€</div><div class="stat-card-label">Cobrado</div></div>
    <div class="stat-card"><div class="stat-card-value" style="color:var(--rojo)"><?=number_format($total_pendiente,2,',','.')?>€</div><div class="stat-card-label">Pendiente</div></div>
    <div class="stat-card"><div class="stat-card-value"><?=$pagos_cobrados?>/<?=count($pagos)?></div><div class="stat-card-label">Meses pagados</div></div>
  </div>
  <div class="card" id="pagos">
    <div class="card-header"><div class="card-title">Control mensual — <?=getTemporadaLabel($TEMPORADA_ACTIVA)?></div>
      <?php if(!$es_historica): ?><div class="text-sm text-gray">Clic en cada mes para marcar pagado/pendiente</div><?php endif; ?></div>
    <div class="card-body">
      <div class="pagos-grid">
        <?php foreach(MESES_TEMPORADA as $mes): $p=$pagos[$mes]??null; if(!$p)continue;
          $lbl=NOMBRES_MESES[$mes]??$mes; $estado=$p['pagado']?'pagado':'pendiente';
          $nuevo=$p['pagado']?0:1; ?>
        <div class="pago-mes">
          <div class="pago-mes-label"><?=mb_substr($lbl,0,3)?></div>
          <div class="pago-dot <?=$estado?>" title="<?=$p['pagado']?'Pagado':'Pendiente — '.$p['cuota'].'€'?>"
            <?php if(!$es_historica): ?>onclick="togglePago(<?=$p['id']?>,<?=$nuevo?>,'<?=h($lbl)?>')"<?php endif; ?>>
            <?=$p['pagado']?'✓':'€'?>
          </div>
          <div class="pago-mes-label"><?=number_format($p['cuota'],0)?>€</div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:24px"><table>
        <thead><tr><th>Mes</th><th>Cuota</th><th>Estado</th><th>Fecha</th><th>Método</th><?php if(!$es_historica): ?><th>Acción</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach(MESES_TEMPORADA as $mes): $p=$pagos[$mes]??null; if(!$p)continue; ?>
        <tr><td class="fw-600"><?=NOMBRES_MESES[$mes]?></td>
          <td><?=number_format($p['cuota'],2,',','.')?>€</td>
          <td><?=$p['pagado']?'<span class="badge badge-green">✓ Pagado</span>':'<span class="badge badge-red">⏳ Pendiente</span>'?></td>
          <td class="text-sm text-gray"><?=$p['fecha_pago']?date('d/m/Y',strtotime($p['fecha_pago'])):'—'?></td>
          <td class="text-sm"><?=$p['metodo_pago']?ucfirst($p['metodo_pago']):'—'?></td>
          <?php if(!$es_historica): ?>
          <td><button class="btn btn-sm <?=$p['pagado']?'btn-secondary':'btn-primary'?>"
            onclick="togglePago(<?=$p['id']?>,<?=$p['pagado']?0:1?>,'<?=NOMBRES_MESES[$mes]?>')">
            <?=$p['pagado']?'Desmarcar':'Marcar pagado'?></button></td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Tab historial -->
<div class="tab-panel" id="tab-historial">
  <div class="card"><div class="card-header"><div class="card-title">Historial completo del jugador</div>
    <div class="text-sm text-gray">Se conserva aunque cambie de club o categoría</div></div>
    <div class="card-body" style="padding:0"><table>
      <thead><tr><th>Temporada</th><th>Categoría</th><th>Equipo</th><th>Estado</th><th>Observaciones</th></tr></thead>
      <tbody>
      <?php foreach($historial as $hr): ?>
      <tr>
        <td><?=h($hr['temp_nombre'])?></td>
        <td><span class="cat-tag badge" style="background:<?=getCategoriaColor($hr['categoria'])?>22;color:<?=getCategoriaColor($hr['categoria'])?>">
          <?=getCategoriaLabel($hr['categoria'])?></span></td>
        <td class="text-sm"><?=h($hr['equipo']??'—')?></td>
        <td><?=$hr['activo']?'<span class="badge badge-green">Activo</span>':'<span class="badge badge-red">Baja</span>'?></td>
        <td class="text-sm text-gray">
          <?php if(!$hr['activo']): ?>
            <?=$hr['motivo_baja']?ucfirst($hr['motivo_baja']):'—'?>
            <?=$hr['club_destino']?' → <strong>'.h($hr['club_destino']).'</strong>':''?>
            <?=$hr['fecha_baja']?' ('.date('d/m/Y',strtotime($hr['fecha_baja'])).')':''?>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($historial)): ?><tr><td colspan="5" class="text-center text-gray" style="padding:24px">Sin historial</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
</div></div></div>

<!-- Modal pago -->
<div class="modal-overlay" id="modal-pago">
  <div class="modal">
    <div class="modal-header"><h3 id="modal-title">Registrar pago</h3>
      <button onclick="cerrarModal('modal-pago')" style="background:none;border:none;font-size:1.3rem;cursor:pointer">✕</button></div>
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
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modal-pago')">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="modal-btn-ok">Confirmar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal baja -->
<div class="modal-overlay" id="modal-baja">
  <div class="modal">
    <div class="modal-header"><h3>📤 Registrar baja del jugador</h3>
      <button onclick="cerrarModal('modal-baja')" style="background:none;border:none;font-size:1.3rem;cursor:pointer">✕</button></div>
    <form method="POST">
      <input type="hidden" name="registrar_baja" value="1">
      <div class="modal-body">
        <div class="alert alert-warn">El jugador quedará como baja en esta temporada pero su historial se conserva. Podrá volver al club en una temporada futura.</div>
        <div class="form-group" style="margin-top:16px"><label>Motivo de la baja</label>
          <select name="motivo_baja" class="form-control">
            <option value="otro_club">Va a otro club</option>
            <option value="personal">Motivo personal</option>
            <option value="lesion">Lesión</option>
            <option value="otro">Otro</option>
          </select></div>
        <div class="form-group"><label>Club de destino <span class="optional">(si va a otro club)</span></label>
          <input type="text" name="club_destino" class="form-control" placeholder="Nombre del club"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modal-baja')">Cancelar</button>
        <button type="submit" class="btn btn-danger">Confirmar baja</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}
function showTab(id,btn){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active'); btn.classList.add('active');
}
function togglePago(pid,nuevo,lbl){
  document.getElementById('modal-pago-id').value=pid;
  document.getElementById('modal-nuevo-estado').value=nuevo;
  document.getElementById('modal-title').textContent=nuevo===1?'Registrar pago — '+lbl:'Desmarcar — '+lbl;
  document.getElementById('modal-body-pagar').classList.toggle('hidden',nuevo!==1);
  document.getElementById('modal-body-desmarcar').classList.toggle('hidden',nuevo!==0);
  document.getElementById('modal-btn-ok').textContent=nuevo===1?'✓ Marcar pagado':'Desmarcar';
  document.getElementById('modal-pago').classList.add('open');
}
function cerrarModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');}));
</script>
</body></html>
