<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/mailer.php';
require_once __DIR__.'/includes/emails.php';
requireLogin();

$tid = getTemporadaId($pdo, $TEMPORADA_ACTIVA);
$resultado = null;

// Recordatorio masivo de pagos
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['tipo']??'') === 'recordatorio_pagos') {
    $mes = $_POST['mes'] ?? date('m');
    $cat = $_POST['categoria'] ?? '';
    if (!$tid) { flash('No existe la temporada en BD.','error'); redirect(BASE_URL.'/notificaciones.php'); }
    $params = [$tid, $mes, 0]; $where = ['p.temporada_id=?','p.mes=?','p.pagado=?'];
    if ($cat) { $where[] = 'i.categoria=?'; $params[] = $cat; }
    $pendientes = $pdo->prepare("
        SELECT j.tutor_nombre,j.tutor_apellidos,j.tutor_email,j.nombre as jn,j.apellidos as ja,i.categoria,p.cuota,
               (SELECT COUNT(*) FROM pagos p2 WHERE p2.jugador_id=j.id AND p2.temporada_id=p.temporada_id AND p2.pagado=0) as total_pend
        FROM pagos p JOIN jugadores j ON j.id=p.jugador_id
        JOIN inscripciones i ON i.jugador_id=j.id AND i.temporada_id=p.temporada_id
        WHERE ".implode(' AND ',$where)." AND i.activo=1");
    $pendientes->execute($params); $lista=$pendientes->fetchAll();
    $ok=0;$err=0;$dest=[];
    foreach ($lista as $r) {
        $jn=$r['jn'].' '.$r['ja']; $ml=NOMBRES_MESES[$mes]??$mes;
        [$as,$ht]=array_values(emailRecordatorioPago($r['tutor_nombre'],$jn,$ml,$r['cuota'],(int)$r['total_pend']));
        if ($mailer->enviar($r['tutor_email'],$as,$ht)){$ok++;$dest[]=['email'=>$r['tutor_email'],'jugador'=>$jn,'ok'=>true];}
        else{$err++;$dest[]=['email'=>$r['tutor_email'],'jugador'=>$jn,'ok'=>false];}
    }
    $resultado=['tipo'=>'recordatorio','ok'=>$ok,'error'=>$err,'destinatarios'=>$dest];
}

// Comunicado libre
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['tipo']??'') === 'comunicado') {
    $asunto_c=trim($_POST['asunto_com']??''); $msg_raw=trim($_POST['mensaje']??'');
    $cat_c=$_POST['categoria_com']??''; $jids=$_POST['jugadores_sel']??[];
    if (!$asunto_c||!$msg_raw) { $resultado=['tipo'=>'comunicado','error_form'=>'Asunto y mensaje son obligatorios.']; }
    else {
        $msg_html=implode('',array_map(fn($p)=>'<p>'.nl2br(h(trim($p))).'</p>',array_filter(explode("\n\n",$msg_raw))));
        if (!$msg_html) $msg_html='<p>'.nl2br(h($msg_raw)).'</p>';
        if (!empty($jids)){
            $in=implode(',',array_map('intval',$jids));
            $lista=$pdo->query("SELECT tutor_nombre,tutor_apellidos,tutor_email,nombre,apellidos FROM jugadores WHERE id IN($in)")->fetchAll();
        } elseif ($cat_c&&$tid) {
            $s=$pdo->prepare("SELECT j.tutor_nombre,j.tutor_apellidos,j.tutor_email,j.nombre,j.apellidos FROM jugadores j JOIN inscripciones i ON i.jugador_id=j.id WHERE i.temporada_id=? AND i.categoria=? AND i.activo=1");
            $s->execute([$tid,$cat_c]); $lista=$s->fetchAll();
        } else {
            $s=$tid?$pdo->prepare("SELECT j.tutor_nombre,j.tutor_apellidos,j.tutor_email,j.nombre,j.apellidos FROM jugadores j JOIN inscripciones i ON i.jugador_id=j.id WHERE i.temporada_id=? AND i.activo=1"):null;
            if($s){$s->execute([$tid]);$lista=$s->fetchAll();}else $lista=[];
        }
        $vistos=[];$ok=0;$err=0;$dest=[];
        foreach ($lista as $r) {
            if (in_array($r['tutor_email'],$vistos)) continue; $vistos[]=$r['tutor_email'];
            [$as,$ht]=array_values(emailComunicado($r['tutor_nombre'],$asunto_c,$msg_html,$cat_c));
            if($mailer->enviar($r['tutor_email'],$as,$ht)){$ok++;$dest[]=['email'=>$r['tutor_email'],'jugador'=>$r['nombre'].' '.$r['apellidos'],'ok'=>true];}
            else{$err++;$dest[]=['email'=>$r['tutor_email'],'jugador'=>$r['nombre'].' '.$r['apellidos'],'ok'=>false];}
        }
        $resultado=['tipo'=>'comunicado','ok'=>$ok,'error'=>$err,'destinatarios'=>$dest];
    }
}

// Confirmaciones de pago
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['tipo']??'') === 'confirmacion_pagos') {
    $mes_cf=$_POST['mes_cf']??date('m'); $cat_cf=$_POST['cat_cf']??'';
    if (!$tid) { flash('No existe la temporada en BD.','error'); redirect(BASE_URL.'/notificaciones.php'); }
    $params=[$tid,$mes_cf,1]; $where=['p.temporada_id=?','p.mes=?','p.pagado=?'];
    if ($cat_cf){$where[]='i.categoria=?';$params[]=$cat_cf;}
    $s=$pdo->prepare("SELECT j.tutor_nombre,j.tutor_email,j.nombre,j.apellidos,p.cuota,p.metodo_pago FROM pagos p JOIN jugadores j ON j.id=p.jugador_id JOIN inscripciones i ON i.jugador_id=j.id AND i.temporada_id=p.temporada_id WHERE ".implode(' AND ',$where)." AND i.activo=1");
    $s->execute($params); $lista=$s->fetchAll();
    $ok=0;$err=0;$dest=[];
    foreach($lista as $r){
        [$as,$ht]=array_values(emailConfirmacionPago($r['tutor_nombre'],$r['nombre'].' '.$r['apellidos'],NOMBRES_MESES[$mes_cf]??$mes_cf,$r['cuota'],$r['metodo_pago']??'efectivo'));
        if($mailer->enviar($r['tutor_email'],$as,$ht)){$ok++;$dest[]=['email'=>$r['tutor_email'],'jugador'=>$r['nombre'].' '.$r['apellidos'],'ok'=>true];}
        else{$err++;$dest[]=['email'=>$r['tutor_email'],'jugador'=>$r['nombre'].' '.$r['apellidos'],'ok'=>false];}
    }
    $resultado=['tipo'=>'confirmacion','ok'=>$ok,'error'=>$err,'destinatarios'=>$dest];
}

$todos_jugadores = $tid ? $pdo->prepare("SELECT j.id,j.nombre,j.apellidos,i.categoria FROM jugadores j JOIN inscripciones i ON i.jugador_id=j.id WHERE i.temporada_id=? AND i.activo=1 ORDER BY j.apellidos,j.nombre") : null;
$lista_jug=[];
if($todos_jugadores){$todos_jugadores->execute([$tid]);$lista_jug=$todos_jugadores->fetchAll();}

renderHead('Notificaciones');
?>
<body><div class="app-wrapper">
<?php renderSidebar('notificaciones') ?>
<div class="main-content">
<?php renderTopHeader('Notificaciones','Comunicados por email — '.getTemporadaLabel($TEMPORADA_ACTIVA)) ?>
<div class="page-content">
<?php renderFlash() ?>

<?php if($resultado&&!isset($resultado['error_form'])): ?>
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><div class="card-title">📨 Resultado del envío</div></div>
  <div class="card-body">
    <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap">
      <div class="stat-card" style="flex:1;min-width:140px"><div class="stat-card-value text-verde"><?=$resultado['ok']?></div><div class="stat-card-label">Enviados</div></div>
      <div class="stat-card" style="flex:1;min-width:140px"><div class="stat-card-value" style="color:var(--rojo)"><?=$resultado['error']?></div><div class="stat-card-label">Errores</div></div>
    </div>
    <?php if(!empty($resultado['destinatarios'])): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Jugador</th><th>Email</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach($resultado['destinatarios'] as $d): ?>
      <tr><td><?=h($d['jugador'])?></td><td class="text-sm"><?=h($d['email'])?></td>
        <td><?=$d['ok']?'<span class="badge badge-green">✓ Enviado</span>':'<span class="badge badge-red">✗ Error</span>'?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div><?php endif; ?>
  </div>
</div><?php endif; ?>
<?php if(isset($resultado['error_form'])): ?><div class="alert alert-error"><?=h($resultado['error_form'])?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">
  <div class="card"><div class="card-header"><div class="card-title">⏰ Recordatorio de pagos pendientes</div></div>
    <div class="card-body">
      <div class="alert alert-info">Envía recordatorios a todos los tutores con cuotas pendientes del mes seleccionado.</div>
      <form method="POST" onsubmit="return confirm('¿Enviar recordatorios?')">
        <input type="hidden" name="tipo" value="recordatorio_pagos">
        <div class="form-grid" style="grid-template-columns:1fr 1fr">
          <div class="form-group"><label>Mes</label><select name="mes" class="form-control">
            <?php foreach(MESES_TEMPORADA as $m): ?>
            <option value="<?=$m?>" <?=date('m')===$m?'selected':''?>><?=NOMBRES_MESES[$m]?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="form-group"><label>Categoría</label><select name="categoria" class="form-control">
            <option value="">Todas</option>
            <?php foreach(['prebenjamin','benjamin','alevin','infantil','cadete','juvenil'] as $c): ?>
            <option value="<?=$c?>"><?=getCategoriaLabel($c)?></option>
            <?php endforeach; ?>
          </select></div>
        </div>
        <button type="submit" class="btn btn-primary">📧 Enviar recordatorios</button>
      </form>
    </div></div>

  <div class="card"><div class="card-header"><div class="card-title">✅ Confirmaciones de pago</div></div>
    <div class="card-body">
      <div class="alert alert-info">Envía comprobantes a los tutores cuyos recibos ya están cobrados.</div>
      <form method="POST" onsubmit="return confirm('¿Enviar confirmaciones?')">
        <input type="hidden" name="tipo" value="confirmacion_pagos">
        <div class="form-grid" style="grid-template-columns:1fr 1fr">
          <div class="form-group"><label>Mes</label><select name="mes_cf" class="form-control">
            <?php foreach(MESES_TEMPORADA as $m): ?>
            <option value="<?=$m?>" <?=date('m')===$m?'selected':''?>><?=NOMBRES_MESES[$m]?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="form-group"><label>Categoría</label><select name="cat_cf" class="form-control">
            <option value="">Todas</option>
            <?php foreach(['prebenjamin','benjamin','alevin','infantil','cadete','juvenil'] as $c): ?>
            <option value="<?=$c?>"><?=getCategoriaLabel($c)?></option>
            <?php endforeach; ?>
          </select></div>
        </div>
        <button type="submit" class="btn btn-primary">📧 Enviar confirmaciones</button>
      </form>
    </div></div>
</div>

<div class="card" style="margin-top:24px">
  <div class="card-header"><div class="card-title">📣 Comunicado libre</div></div>
  <div class="card-body">
    <form method="POST" onsubmit="return confirm('¿Enviar este comunicado?')">
      <input type="hidden" name="tipo" value="comunicado">
      <div class="form-grid" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label>Destinatarios</label>
          <select name="categoria_com" id="sel-cat" class="form-control" onchange="toggleJugadores(this.value)">
            <option value="">📋 Todo el club (temporada actual)</option>
            <?php foreach(['prebenjamin','benjamin','alevin','infantil','cadete','juvenil'] as $c): ?>
            <option value="<?=$c?>"><?=getCategoriaLabel($c)?></option>
            <?php endforeach; ?>
            <option value="_individual">👤 Jugadores específicos…</option>
          </select></div>
        <div class="form-group" id="grupo-jugadores-sel" style="display:none"><label>Seleccionar jugadores</label>
          <div style="border:1.5px solid var(--gris-3);border-radius:var(--radio);max-height:160px;overflow-y:auto;padding:8px">
            <?php foreach($lista_jug as $jg): ?>
            <label style="display:flex;align-items:center;gap:8px;padding:4px;cursor:pointer;font-weight:400;font-size:.85rem">
              <input type="checkbox" name="jugadores_sel[]" value="<?=$jg['id']?>">
              <?=h($jg['nombre'].' '.$jg['apellidos'])?>
              <span class="badge badge-gray" style="font-size:.65rem"><?=getCategoriaLabel($jg['categoria'])?></span>
            </label>
            <?php endforeach; ?>
          </div></div>
      </div>
      <div class="form-group"><label>Asunto *</label>
        <input type="text" name="asunto_com" class="form-control" placeholder="Ej: Convocatoria entrenamiento del sábado" value="<?=h($_POST['asunto_com']??'')?>" required oninput="document.getElementById('prev-asunto').textContent=this.value?this.value+' — A.D. Parador':'El asunto aparecerá aquí...'"></div>
      <div class="form-group"><label>Mensaje *</label>
        <textarea name="mensaje" class="form-control" rows="8" placeholder="Escribe el mensaje. Separa párrafos con línea en blanco." required><?=h($_POST['mensaje']??'')?></textarea>
        <div class="form-hint">Separa párrafos con una línea en blanco.</div></div>
      <div style="margin-bottom:18px">
        <div class="text-xs text-gray" style="margin-bottom:6px;font-weight:600;letter-spacing:1px;text-transform:uppercase">Vista previa del asunto</div>
        <div id="prev-asunto" style="padding:10px 14px;background:var(--gris-2);border-radius:var(--radio);font-size:.9rem;color:var(--gris-5)">El asunto aparecerá aquí...</div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg">📧 Enviar comunicado</button>
    </form>
  </div>
</div>
</div></div></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}
function toggleJugadores(v){document.getElementById('grupo-jugadores-sel').style.display=v==='_individual'?'':'none';}
</script></body></html>
