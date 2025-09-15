<?php
declare(strict_types=1);
require_once __DIR__ . '/_util.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config/db.php';

try {
  require_auth();
  require_csrf_header();

  $input = json_decode(file_get_contents('php://input'), true) ?: [];
  $direction = isset($input['direction']) ? (string)$input['direction'] : '';
  $ids = ints_from_array($input['ticket_ids'] ?? [], 200);
  if (!in_array($direction, ['to_espejo','to_operativo'], true)) {
    json_error('direction inválida');
  }
  if (empty($ids)) {
    json_error('ticket_ids vacío');
  }

  $result = [ 'succeed' => [], 'failed' => [] ];

  foreach ($ids as $id) {
    try {
      if ($direction === 'to_espejo') {
        $stmt = $pdoOp->prepare('CALL sp_archivar_transaccion(:id)');
        $stmt->execute([':id' => $id]);
        $stmt->closeCursor();
      } else { // to_operativo
        $stmt = $pdoEsp->prepare('CALL sp_desarchivar_transaccion(:id)');
        $stmt->execute([':id' => $id]);
        $stmt->closeCursor();
      }
      $result['succeed'][] = $id;
    } catch (Throwable $e) {
      $result['failed'][] = [ 'ticket_id' => $id, 'error' => $e->getMessage() ];
      // Continue with next id
    }
  }

  json_ok($result);

} catch (Throwable $e) {
  json_error('Error: ' . $e->getMessage(), 500);
}

?>
