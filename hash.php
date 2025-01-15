<?php
$clave_plana = '123456';
$clave_encriptada = password_hash($clave_plana, PASSWORD_BCRYPT);
echo $clave_encriptada;
?>
