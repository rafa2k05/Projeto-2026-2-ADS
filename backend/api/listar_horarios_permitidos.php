<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers/funcoes.php';
iniciar_api('GET');
responder(200, ['horarios' => HORARIOS_FIXOS]);
?>
