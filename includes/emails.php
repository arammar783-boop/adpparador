<?php
function emailLayout(string $contenido, string $preheader=''): string {
    $año=date('Y'); $v='#2d7a3a'; $vo='#1e5228';
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>A.D. Parador</title>
<style>*{box-sizing:border-box;margin:0;padding:0;}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;background:#f4f7f4;color:#1a1f1a;line-height:1.6;}
.w{max-width:600px;margin:0 auto;padding:24px 16px;}.c{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);}
.h{background:linear-gradient(135deg,'.$vo.' 0%,'.$v.' 100%);padding:32px 36px;text-align:center;}
.h h1{color:#fff;font-size:22px;font-weight:700;margin:0;}.h p{color:rgba(255,255,255,.78);font-size:13px;margin-top:4px;}
.b{padding:32px 36px;}.b h2{font-size:18px;color:'.$vo.';margin-bottom:12px;}.b p{font-size:14px;color:#374137;margin-bottom:14px;}
.btn{display:inline-block;background:'.$v.';color:#fff!important;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:600;}
.info{background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 20px;margin:18px 0;}
.info p{margin:0;font-size:13px;color:#166534;}
.warn{background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:16px 20px;margin:18px 0;}
.warn p{margin:0;font-size:13px;color:#92400e;}
.dr{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f4f0;font-size:13px;}
.dr:last-child{border-bottom:none;}.dl{color:#6b7a6b;font-weight:500;}.dv{color:#1a1f1a;font-weight:600;text-align:right;}
.ft{padding:20px 36px;text-align:center;}.ft p{font-size:11px;color:#8a9a8a;margin:0;line-height:1.8;}.ft a{color:'.$v.';text-decoration:none;}
@media(max-width:480px){.b,.ft{padding:24px 20px;}.h{padding:24px 20px;}}</style></head>
<body>'.($preheader?'<div style="display:none;max-height:0;overflow:hidden;font-size:1px">'.$preheader.'</div>':'').'
<div class="w"><div class="c">
<div class="h"><div style="font-size:40px;margin-bottom:10px">⚽</div>
  <h1>A.D. Parador</h1><p>Formando futbolistas con valores desde 1965</p></div>
<div class="b">'.$contenido.'</div>
<div class="ft"><p><strong>A.D. Parador</strong><br><a href="'.BASE_URL.'">adpparador.com</a> · <a href="mailto:'.EMAIL_FROM.'">'.EMAIL_FROM.'</a></p>
<p style="margin-top:8px">© '.$año.' A.D. Parador.</p></div>
</div></div></body></html>';
}

function emailEnlaceRegistro(string $nombre, string $enlace, string $caduca): array {
    $cf=date('d/m/Y \a \l\a\s H:i',strtotime($caduca));
    $c='<h2>👋 Bienvenido/a a A.D. Parador</h2>
<p>Hola <strong>'.h($nombre).'</strong>,</p>
<p>El club ha generado un enlace para que puedas <strong>registrar los datos de tu hijo/a</strong> como jugador/a.</p>
<div style="text-align:center;margin:24px 0"><a href="'.h($enlace).'" class="btn">📝 Completar registro</a></div>
<div class="warn"><p>⏰ Caduca el <strong>'.$cf.'</strong>. Enlace de un solo uso.</p></div>
<p style="font-size:13px;color:#6b7a6b">Si el botón no funciona:<br><span style="font-size:12px;color:#2d7a3a;word-break:break-all">'.h($enlace).'</span></p>';
    return ['asunto'=>'Registro en A.D. Parador — Completa los datos de tu jugador/a','html'=>emailLayout($c,'Completa el registro en A.D. Parador')];
}

function emailBienvenida(string $jug, string $tutor, string $cat, float $cuota): array {
    $cl=getCategoriaLabel($cat);
    $c='<h2>🎉 ¡Bienvenido/a al club!</h2>
<p>Hola <strong>'.h($tutor).'</strong>,</p>
<p>El alta de <strong>'.h($jug).'</strong> se ha completado. ¡Nos alegra teneros!</p>
<div style="margin:16px 0">
  <div class="dr"><span class="dl">Jugador/a</span><span class="dv">'.h($jug).'</span></div>
  <div class="dr"><span class="dl">Categoría</span><span class="dv">'.h($cl).'</span></div>
  <div class="dr"><span class="dl">Cuota mensual</span><span class="dv">'.number_format($cuota,2,',','.').' €/mes</span></div>
</div>
<p>Si tienes alguna duda, contáctanos directamente. ⚽</p>';
    return ['asunto'=>'¡Bienvenido/a a A.D. Parador! — '.h($jug),'html'=>emailLayout($c,'Registro completado')];
}

function emailRecordatorioPago(string $tutor, string $jug, string $mes, float $cuota, int $pend=1): array {
    $c='<h2>💰 Recordatorio de pago</h2>
<p>Hola <strong>'.h($tutor).'</strong>,</p>
<p>Tienes pendiente la cuota de <strong>'.h($mes).'</strong> de <strong>'.h($jug).'</strong>.</p>
<div style="margin:16px 0">
  <div class="dr"><span class="dl">Mes</span><span class="dv">'.h($mes).'</span></div>
  <div class="dr"><span class="dl">Importe</span><span class="dv"><strong>'.number_format($cuota,2,',','.').' €</strong></span></div>
</div>
'.($pend>1?'<p>Tienes <strong>'.$pend.' mensualidades</strong> pendientes esta temporada.</p>':'').'
<div class="info"><p>💡 Puedes pagar en efectivo, transferencia o domiciliación. Consulta con el club.</p></div>';
    return ['asunto'=>'Recordatorio de pago — '.h($mes).' · A.D. Parador','html'=>emailLayout($c,'Tienes un pago pendiente')];
}

function emailConfirmacionPago(string $tutor, string $jug, string $mes, float $cuota, string $metodo): array {
    $c='<h2>✅ Pago recibido</h2>
<p>Hola <strong>'.h($tutor).'</strong>,</p>
<p>Confirmamos el pago de <strong>'.h($jug).'</strong> — <strong>'.h($mes).'</strong>.</p>
<div style="margin:16px 0">
  <div class="dr"><span class="dl">Importe</span><span class="dv">'.number_format($cuota,2,',','.').' €</span></div>
  <div class="dr"><span class="dl">Método</span><span class="dv">'.ucfirst(h($metodo)).'</span></div>
  <div class="dr"><span class="dl">Fecha</span><span class="dv">'.date('d/m/Y').'</span></div>
</div>
<div class="info"><p>✓ Este mensaje sirve como comprobante.</p></div>';
    return ['asunto'=>'Pago confirmado — '.h($mes).' · A.D. Parador','html'=>emailLayout($c,'Hemos recibido tu pago')];
}

function emailComunicado(string $dest, string $asunto, string $msg, string $cat=''): array {
    $ci=$cat?'<div class="info"><p>📢 Comunicado para <strong>'.getCategoriaLabel($cat).'</strong></p></div>':'<div class="info"><p>📢 Comunicado general del club</p></div>';
    $c='<h2>📣 Comunicado de A.D. Parador</h2>'.($dest?'<p>Hola <strong>'.h($dest).'</strong>,</p>':'').$ci.'<div style="margin:20px 0;padding:20px;background:#f8faf8;border-radius:8px;border-left:4px solid #2d7a3a">'.$msg.'</div>';
    return ['asunto'=>h($asunto).' — A.D. Parador','html'=>emailLayout($c)];
}

function emailCambioPassword(string $nombre): array {
    $c='<h2>🔐 Contraseña actualizada</h2>
<p>Hola <strong>'.h($nombre).'</strong>,</p>
<p>Tu contraseña del portal de A.D. Parador ha sido <strong>cambiada correctamente</strong>.</p>
<div class="warn"><p>⚠️ Si no realizaste este cambio, contacta inmediatamente con el club.</p></div>
<p>Fecha: <strong>'.date('d/m/Y \a \l\a\s H:i').'</strong></p>';
    return ['asunto'=>'Contraseña actualizada — Portal A.D. Parador','html'=>emailLayout($c)];
}
