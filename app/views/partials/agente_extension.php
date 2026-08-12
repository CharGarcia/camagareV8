<?php

/**
 * Auto-conexión de la extensión CaMaGaRe (Descarga SRI). Se incluye desde el layout
 * principal (partials/scripts.php), es decir en TODA página autenticada — no solo en
 * Descargas SRI — para que la extensión quede conectada sola apenas el usuario entra
 * al sistema, sin que tenga que visitar esa pantalla ni pegar el token a mano.
 *
 * La extensión (extension/content_sistema.js) lee este elemento #cmg-config al cargar
 * cualquier página del dominio del sistema. Token idempotente: asegurarAgenteToken()
 * hace un SELECT por PK y solo genera uno nuevo si el usuario aún no tiene.
 */

$idUsuarioAgente = (int) ($_SESSION['id_usuario'] ?? 0);
if ($idUsuarioAgente > 0):
    $agenteTokenGlobal = (new \App\models\Usuario())->asegurarAgenteToken($idUsuarioAgente);
    $agenteBaseGlobal  = rtrim(BASE_URL ?? '', '/');
?>
<div id="cmg-config"
     data-token="<?= htmlspecialchars($agenteTokenGlobal, ENT_QUOTES) ?>"
     data-base="<?= htmlspecialchars($agenteBaseGlobal, ENT_QUOTES) ?>"
     hidden></div>
<?php endif; ?>
