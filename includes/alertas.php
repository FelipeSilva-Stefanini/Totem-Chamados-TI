<?php
/**
 * Exibe os alertas de erro e sucesso das telas internas.
 * Espera (opcionalmente): $erroBanco, $mensagemErro, $mensagemSucesso.
 */
declare(strict_types=1);
?>
<?php if (!empty($erroBanco)): ?>
    <div class="alerta-erro"><?= htmlspecialchars($erroBanco) ?></div>
<?php endif; ?>
<?php if (!empty($mensagemErro)): ?>
    <div class="alerta-erro"><?= htmlspecialchars($mensagemErro) ?></div>
<?php endif; ?>
<?php if (!empty($mensagemSucesso)): ?>
    <div class="alerta-ok"><?= htmlspecialchars($mensagemSucesso) ?></div>
<?php endif; ?>
