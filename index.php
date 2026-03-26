<?php
require_once __DIR__.'/config.php';
if(!empty($_SESSION['admin_id'])) redirect(BASE_URL.'/dashboard.php');
else redirect(BASE_URL.'/login.php');
