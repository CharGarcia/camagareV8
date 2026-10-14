<?php
/**
 * Candado junto a la fecha de un movimiento que NO se puede editar desde Inventario (lo
 * generó un documento, su período contable está cerrado o el usuario no tiene la bodega).
 * El motivo va en el title. Los anulados no lo llevan: ya tienen su propia marca.
 * Lo usan index.php y InventarioController::searchAjax().
 *
 * @var array $row Fila marcada por InventarioService::marcarEdicion().
 */
if (array_key_exists('editable', $row) && !$row['editable'] && !\App\Helpers\Booleano::es($row['eliminado'] ?? false)):
?>
<i class="bi bi-lock-fill text-secondary ms-1 inv-candado" title="<?= htmlspecialchars((string) ($row['motivo_bloqueo'] ?? 'No se puede editar desde Inventario')) ?>"></i>
<?php endif;
