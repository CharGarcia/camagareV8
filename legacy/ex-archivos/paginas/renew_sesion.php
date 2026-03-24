<?php
// Archivo renew_session.php
// Inicia la sesión
session_start();
// Actualiza la última actividad del usuario
$_SESSION['last_activity'] = time();
// Puedes realizar otras acciones según tus necesidades
// Envía una respuesta al cliente (puede no ser necesaria)
echo "Sesión renovada exitosamente";
