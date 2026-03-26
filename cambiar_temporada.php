<?php
require_once __DIR__.'/config.php'; requireLogin();
$a=(int)($_POST['temporada']??$_GET['temporada']??0);
if($a>2000&&$a<2100) $_SESSION['temporada_vista']=$a;
$r=$_POST['redirect']??$_GET['redirect']??BASE_URL.'/dashboard.php';
if(!str_starts_with($r,'/')&&!str_starts_with($r,BASE_URL)) $r=BASE_URL.'/dashboard.php';
redirect($r);
