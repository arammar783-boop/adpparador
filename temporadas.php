<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/layout.php';
requireLogin();

$temporadas = $pdo->query("SELECT * FROM temporadas ORDER BY anio_inicio DESC")->fetchAll();
$anio_real  = calcularTemporadaActual();
$siguiente  = $anio_real + 1;
$errors     = [];

// ─── Crear nueva temporada ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='nueva_temporada') {
    $anio_nuevo   = (int)$_POST['anio_nuevo'];
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? ($anio_nuevo.'-08-01'));
    $fecha_fin    = trim($_POST['fecha_fin']    ?? (($anio_nuevo+1).'-06-30'));
    $notas        = trim($_POST['notas'] ?? '');

    $existe = $pdo->prepare("SELECT id FROM temporadas WHERE anio_inicio=?");
    $existe->execute([$anio_nuevo]);
    if ($existe->fetch()) {
        $errors[] = "La temporada {$anio_nuevo}/".($anio_nuevo+1)." ya existe.";
    } elseif ($anio_nuevo < 2020 || $anio_nuevo > 2100) {
        $errors[] = "Año de inicio inválido.";
    } elseif (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
        $errors[] = "Las fechas introducidas no son válidas.";
    } elseif (strtotime($fecha_fin) <= strtotime($fecha_inicio)) {
        $errors[] = "La fecha de fin debe ser posterior a la de inicio.";
    } else {
        $nombre_nuevo = $anio_nuevo.'/'.($anio_nuevo+1);
        $pdo->prepare("
            INSERT INTO temporadas (anio_inicio, nombre, activa, fecha_inicio, fecha_fin, notas)
            VALUES (?,?,0,?,?,?)
        ")->execute([$anio_nuevo, $nombre_nuevo, $fecha_inicio, $fecha_fin, $notas ?: null]);
        $nueva_id = $pdo->lastInsertId();

        // Copiar jugadores de la temporada origen
        $copiados = 0;
        if (!empty($_POST['copiar_jugadores']) && !empty($_POST['copiar_de'])) {
            $anio_origen = (int)$_POST['copiar_de'];
            $origen_id   = getTemporadaId($pdo, $anio_origen);
            if ($origen_id) {
                $activos = $pdo->prepare("
                    SELECT i.*, j.fecha_nacimiento
                    FROM inscripciones i JOIN jugadores j ON j.id=i.jugador_id
                    WHERE i.temporada_id=? AND i.activo=1
                ");
                $activos->execute([$origen_id]);
                foreach ($activos->fetchAll() as $insc) {
                    $nueva_cat = getCategoria($insc['fecha_nacimiento'], $anio_nuevo);
                    $cuota     = CUOTAS[$nueva_cat] ?? 40;
                    $pdo->prepare("
                        INSERT IGNORE INTO inscripciones
                        (jugador_id, temporada_id, anio_inicio, categoria, equipo, activo)
                        VALUES (?,?,?,?,?,1)
                    ")->execute([$insc['jugador_id'], $nueva_id, $anio_nuevo, $nueva_cat, $insc['equipo']]);
                    foreach (MESES_TEMPORADA as $mes) {
                        $pdo->prepare("
                            INSERT IGNORE INTO pagos
                            (jugador_id, temporada_id, anio_inicio, mes, cuota)
                            VALUES (?,?,?,?,?)
                        ")->execute([$insc['jugador_id'], $nueva_id, $anio_nuevo, $mes, $cuota]);
                    }
                    $copiados++;
                }
                flash("Temporada {$nombre_nuevo} creada. {$copiados} jugadores copiados de {$anio_origen}/".($anio_origen+1).".", 'success');
            }
        } else {
            flash("Temporada {$nombre_nuevo} creada sin jugadores. Añádelos manualmente.", 'success');
        }
        // Recargar lista
        redirect(BASE_URL.'/temporadas.php');
    }
}

// ─── Editar fechas de una temporada ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='editar_fechas') {
    $tid_ed       = (int)$_POST['tid_editar'];
    $fecha_inicio = trim($_POST['fecha_inicio_ed'] ?? '');
    $fecha_fin    = trim($_POST['fecha_fin_ed']    ?? '');
    $notas_ed     = trim($_POST['notas_ed'] ?? '');

    if (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
        flash('Las fechas introducidas no son válidas.', 'error');
    } elseif (strtotime($fecha_fin) <= strtotime($fecha_inicio)) {
        flash('La fecha de fin debe ser posterior a la de inicio.', 'error');
    } else {
        $pdo->prepare("UPDATE temporadas SET fecha_inicio=?, fecha_fin=?, notas=? WHERE id=?")
            ->execute([$fecha_inicio, $fecha_fin, $notas_ed ?: null, $tid_ed]);
        flash('Fechas de la temporada actualizadas correctamente.', 'success');
    }
    redirect(BASE_URL.'/temporadas.php');
}

// ─── Activar temporada ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='activar') {
    $anio_act = (int)$_POST['anio_activar'];
    $pdo->exec("UPDATE temporadas SET activa=0");
    $pdo->prepare("UPDATE temporadas SET activa=1 WHERE anio_inicio=?")->execute([$anio_act]);
    $_SESSION['temporada_vista'] = $anio_act;
    flash("Temporada ".getTemporadaLabel($anio_act)." activada.", 'success');
    redirect(BASE_URL.'/temporadas.php');
}

// Recargar lista actualizada
$temporadas = $pdo->query("SELECT * FROM temporadas ORDER BY anio_inicio DESC")->fetchAll();

// Stats por temporada
$stats = [];
foreach ($temporadas as $t) {
    $s = $pdo->prepare("
        SELECT COUNT(DISTINCT i.jugador_id) as jugadores,
               SUM(p.pagado) as pagos_cobrados,
               COUNT(p.id) as pagos_total,
               COALESCE(SUM(CASE WHEN p.pagado=1 THEN p.cuota ELSE 0 END),0) as ingresos
        FROM inscripciones i
        LEFT JOIN pagos p ON p.jugador_id=i.jugador_id AND p.temporada_id=i.temporada_id
        WHERE i.temporada_id=?
    ");
    $s->execute([$t['id']]);
    $stats[$t['anio_inicio']] = $s->fetch();
}

renderHead('Temporadas');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar('temporadas') ?>
<div class="main-content">
<?php renderTopHeader('Gestión de temporadas') ?>
<div class="page-content">
<?php renderFlash() ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start">

  <!-- Lista de temporadas -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📅 Historial de temporadas</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Temporada</th><th>Fechas</th><th>Estado</th><th>Jugadores</th><th>Ingresos</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($temporadas as $t):
          $st = $stats[$t['anio_inicio']] ?? [];
          $cerrada = $t['fecha_fin'] && strtotime($t['fecha_fin']) < time();
        ?>
        <tr>
          <td>
            <strong><?= h($t['nombre']) ?></strong>
            <?php if ($t['activa']): ?><span class="badge badge-green" style="margin-left:6px">★ Activa</span><?php endif; ?>
            <?php if ($t['notas']): ?><div class="text-xs text-gray" style="margin-top:2px"><?= h($t['notas']) ?></div><?php endif; ?>
          </td>
          <td class="text-sm text-gray">
            <?= date('d/m/Y', strtotime($t['fecha_inicio'])) ?>
            →
            <?= $t['fecha_fin'] ? date('d/m/Y', strtotime($t['fecha_fin'])) : 'en curso' ?>
            <button class="btn btn-secondary btn-sm" style="margin-top:4px;display:block"
              onclick="abrirEditar(<?= $t['id'] ?>,'<?= $t['fecha_inicio'] ?>','<?= h($t['fecha_fin']??'') ?>','<?= h($t['notas']??'') ?>')">
              ✏ Editar fechas
            </button>
          </td>
          <td>
            <?php if ($t['activa']): ?>
              <span class="badge badge-green">En curso</span>
            <?php elseif ($cerrada): ?>
              <span class="badge badge-gray">Cerrada</span>
            <?php else: ?>
              <span class="badge badge-yellow">Histórica</span>
            <?php endif; ?>
          </td>
          <td>
            <?= (int)($st['jugadores'] ?? 0) ?>
            <div class="text-xs text-gray"><?= (int)($st['pagos_cobrados']??0) ?>/<?= (int)($st['pagos_total']??0) ?> pagos</div>
          </td>
          <td class="fw-600 text-verde"><?= number_format($st['ingresos'] ?? 0, 0, ',', '.') ?>€</td>
          <td>
            <div class="d-flex gap-8" style="flex-wrap:wrap">
              <a href="<?= BASE_URL ?>/cambiar_temporada.php?temporada=<?= $t['anio_inicio'] ?>&redirect=<?= urlencode(BASE_URL.'/dashboard.php') ?>"
                 class="btn btn-secondary btn-sm">Ver</a>
              <?php if (!$t['activa']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="accion" value="activar">
                <input type="hidden" name="anio_activar" value="<?= $t['anio_inicio'] ?>">
                <button type="submit" class="btn btn-outline btn-sm"
                  onclick="return confirm('¿Activar temporada <?= h($t['nombre']) ?>?')">
                  ★ Activar
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($temporadas)): ?>
        <tr><td colspan="6" class="text-center text-gray" style="padding:24px">Sin temporadas creadas aún.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Crear nueva temporada -->
  <div class="card">
    <div class="card-header"><div class="card-title">➕ Nueva temporada</div></div>
    <div class="card-body">
      <div class="alert alert-info">
        Las fechas se rellenan automáticamente (1 agosto → 30 junio) pero puedes modificarlas. Si copias jugadores, sus categorías se recalculan.
      </div>
      <form method="POST" id="form-nueva">
        <input type="hidden" name="accion" value="nueva_temporada">

        <div class="form-group">
          <label>Año de inicio <span class="required">*</span></label>
          <input type="number" name="anio_nuevo" id="inp-anio" class="form-control"
            value="<?= $siguiente ?>" min="2020" max="2100" required
            oninput="actualizarFechas(this.value)">
          <div class="form-hint">La temporada se llamará [año]/[año+1]</div>
        </div>

        <div class="form-group">
          <label>Fecha de inicio</label>
          <input type="date" name="fecha_inicio" id="inp-fi" class="form-control"
            value="<?= $siguiente ?>-08-01">
        </div>

        <div class="form-group">
          <label>Fecha de fin</label>
          <input type="date" name="fecha_fin" id="inp-ff" class="form-control"
            value="<?= $siguiente+1 ?>-06-30">
        </div>

        <div class="form-group">
          <label>
            <input type="checkbox" name="copiar_jugadores" value="1" checked style="margin-right:6px">
            Copiar jugadores activos de la temporada anterior
          </label>
        </div>

        <div class="form-group">
          <label>Copiar desde temporada</label>
          <select name="copiar_de" class="form-control">
            <?php foreach ($temporadas as $t): ?>
            <option value="<?= $t['anio_inicio'] ?>" <?= $t['activa']?'selected':'' ?>>
              <?= h($t['nombre']) ?><?= $t['activa'] ? ' (activa)' : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Notas <span class="optional">(opcional)</span></label>
          <textarea name="notas" class="form-control" rows="2" placeholder="Observaciones..."></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-100"
          onclick="return confirm('¿Crear nueva temporada?')">
          📅 Crear temporada
        </button>
      </form>
    </div>
  </div>

</div><!-- /grid -->
</div><!-- /page-content -->
</div>
</div>

<!-- Modal editar fechas -->
<div class="modal-overlay" id="modal-editar">
  <div class="modal">
    <div class="modal-header">
      <h3>✏ Editar fechas de temporada</h3>
      <button onclick="document.getElementById('modal-editar').classList.remove('open')"
        style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--gris-5)">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="accion" value="editar_fechas">
      <input type="hidden" name="tid_editar" id="ed-tid">
      <div class="modal-body">
        <div class="form-group">
          <label>Fecha de inicio</label>
          <input type="date" name="fecha_inicio_ed" id="ed-fi" class="form-control">
        </div>
        <div class="form-group">
          <label>Fecha de fin</label>
          <input type="date" name="fecha_fin_ed" id="ed-ff" class="form-control">
        </div>
        <div class="form-group">
          <label>Notas <span class="optional">(opcional)</span></label>
          <textarea name="notas_ed" id="ed-notas" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary"
          onclick="document.getElementById('modal-editar').classList.remove('open')">Cancelar</button>
        <button type="submit" class="btn btn-primary">💾 Guardar fechas</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('open'); }

function actualizarFechas(anio) {
    const a = parseInt(anio);
    if (a >= 2020 && a <= 2100) {
        document.getElementById('inp-fi').value = a + '-08-01';
        document.getElementById('inp-ff').value = (a+1) + '-06-30';
    }
}

function abrirEditar(tid, fi, ff, notas) {
    document.getElementById('ed-tid').value   = tid;
    document.getElementById('ed-fi').value    = fi;
    document.getElementById('ed-ff').value    = ff;
    document.getElementById('ed-notas').value = notas;
    document.getElementById('modal-editar').classList.add('open');
}

document.getElementById('modal-editar').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>
</body>
</html>
