<?php
/**
 * Utilitário para gerar o hash de senha de um agente de suporte.
 *
 * Uso: abra pelo navegador -> gerar_senha.php?senha=SUASENHA
 * Copie o hash gerado e cole na coluna senha_hash da tabela
 * agentes_suporte (via phpMyAdmin), no lugar de SUBSTITUIR_PELO_HASH_REAL.
 *
 * IMPORTANTE: apague este arquivo do servidor (ou proteja o acesso a ele)
 * depois de gerar as senhas iniciais. Deixá-lo acessível publicamente em
 * produção é um risco de segurança desnecessário.
 */

$senha = $_GET['senha'] ?? null;

if (!$senha) {
    echo '<p>Use assim: gerar_senha.php?senha=SUASENHA</p>';
    exit;
}

echo '<p>Hash gerado para a senha informada:</p>';
echo '<pre>' . htmlspecialchars(password_hash($senha, PASSWORD_BCRYPT)) . '</pre>';
