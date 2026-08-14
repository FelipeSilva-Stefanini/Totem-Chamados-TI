<?php
/**
 * Cabeçalho compartilhado da Área do Suporte.
 * Espera: $agente (array), $paginaAtual (string), $tituloPagina (string).
 */
declare(strict_types=1);

$abas = [
    'dashboard' => ['rotulo' => 'Dashboard',    'arquivo' => 'dashboard.php'],
    'chamados'  => ['rotulo' => 'Chamados',     'arquivo' => 'chamados.php'],
    'tratativa' => ['rotulo' => 'Em tratativa', 'arquivo' => 'tratativa.php'],
    'lancados'  => ['rotulo' => 'Lançados',     'arquivo' => 'lancados.php'],
    'agentes'   => ['rotulo' => 'Equipe',       'arquivo' => 'agentes.php'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($tituloPagina) ?> — Área do Suporte</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app">
    <div class="top-stripe"></div>

    <header class="app-bar">
        <div class="app-bar-inner">
            <div class="app-brand">
                <p class="eyebrow">TI · Field Service</p>
                <strong>Central de Chamados</strong>
            </div>
            <div class="app-user">
                <span class="user-nome"><?= htmlspecialchars($agente['nome']) ?></span>
                <a class="btn-sair" href="logout.php">Sair</a>
            </div>
        </div>
        <nav class="app-nav">
            <?php foreach ($abas as $chave => $aba): ?>
                <a class="nav-item <?= $paginaAtual === $chave ? 'ativo' : '' ?>"
                   href="<?= $aba['arquivo'] ?>"><?= htmlspecialchars($aba['rotulo']) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main class="app-main">
