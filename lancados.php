<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

exigirLogin();
$agente = agenteLogado();

$paginaAtual   = 'lancados';
$paginaArquivo = 'lancados.php';
$tituloPagina  = 'Lançados';

require __DIR__ . '/includes/acoes_chamado.php';

$lista         = [];
$agentesAtivos = [];
$detalhe       = null;
$historico     = [];
$erroBanco     = null;
$totalGeral    = 0;

// Filtro de período: mês atual (padrão) ou tudo
$periodo = ($_GET['periodo'] ?? 'mes') === 'tudo' ? 'tudo' : 'mes';

try {
    $pdo           = getConexao();
    $agentesAtivos = listarAgentesAtivos($pdo);
    $todos         = listarChamadosPorStatus($pdo, ['Concluido']);
    $totalGeral    = count($todos);

    if ($periodo === 'mes') {
        $inicioMes = date('Y-m-01 00:00:00');
        $todos = array_values(array_filter(
            $todos,
            static fn(array $c): bool => (string) $c['atualizado_em'] >= $inicioMes
        ));
    }

    // Mais recentes primeiro nesta tela (o histórico interessa do fim para o começo)
    usort($todos, static fn(array $a, array $b): int => strcmp((string) $b['atualizado_em'], (string) $a['atualizado_em']));
    $lista = $todos;

    if (isset($_GET['ver'])) {
        $detalhe = buscarChamado($pdo, (int) $_GET['ver']);
        if ($detalhe !== null) {
            $historico = historicoDoChamado($pdo, (int) $detalhe['id']);
        }
    }
} catch (Throwable $e) {
    $erroBanco = 'Não foi possível carregar os chamados agora. Tente atualizar a página.';
}

require __DIR__ . '/includes/rotulos.php';
require __DIR__ . '/includes/layout_topo.php';
require __DIR__ . '/includes/alertas.php';
?>

<div class="filtro-barra">
    <a class="chip <?= $periodo === 'mes' ? 'chip-ativo' : '' ?>" href="lancados.php">Mês atual</a>
    <a class="chip <?= $periodo === 'tudo' ? 'chip-ativo' : '' ?>" href="lancados.php?periodo=tudo">Todo o histórico</a>
</div>

<section class="bloco">
    <div class="bloco-cabecalho">
        <h2>Lançados no ServiceNow</h2>
        <span class="bloco-nota">
            <?= count($lista) ?> <?= $periodo === 'mes' ? 'neste mês' : 'no total' ?>
            <?php if ($periodo === 'mes' && $totalGeral > count($lista)): ?>
                · <?= $totalGeral ?> no histórico completo
            <?php endif; ?>
        </span>
    </div>

    <?php if (empty($lista)): ?>
        <div class="vazio">
            <p>Nenhum chamado lançado <?= $periodo === 'mes' ? 'neste mês' : 'até agora' ?>.</p>
            <p class="vazio-dica">Chamados marcados como lançados no ServiceNow aparecem aqui.</p>
        </div>
    <?php else: ?>
        <div class="tabela-wrap">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Chamado</th>
                        <th>Solicitante</th>
                        <th class="ocultar-mobile">Setor</th>
                        <th>Tipo</th>
                        <th>Prioridade</th>
                        <th>Responsável</th>
                        <th class="ocultar-mobile">Nº ServiceNow</th>
                        <th class="ocultar-mobile">Concluído em</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $c): ?>
                        <?php $prio = $c['prioridade_suporte'] ?: $c['prioridade_usuario']; ?>
                        <tr class="linha-clicavel" onclick="window.location='lancados.php?ver=<?= (int) $c['id'] ?>'">
                            <td><span class="num-chamado"><?= htmlspecialchars($c['numero_chamado']) ?></span></td>
                            <td><?= htmlspecialchars($c['nome_solicitante']) ?></td>
                            <td class="ocultar-mobile"><?= htmlspecialchars($c['setor']) ?></td>
                            <td><?= htmlspecialchars($rotuloTipo[$c['tipo']] ?? $c['tipo']) ?></td>
                            <td>
                                <span class="tag <?= $classePrioridade[$prio] ?? '' ?>">
                                    <?= htmlspecialchars($rotuloPrioridade[$prio] ?? $prio) ?>
                                </span>
                            </td>
                            <td><?= $c['agente_nome'] ? htmlspecialchars($c['agente_nome']) : '<span class="sem-dado">—</span>' ?></td>
                            <td class="ocultar-mobile"><span class="num-chamado"><?= htmlspecialchars($c['numero_servicenow'] ?? '—') ?></span></td>
                            <td class="ocultar-mobile col-data"><?= formatarData($c['atualizado_em']) ?></td>
                            <td class="col-acao"><a class="btn-linha" href="lancados.php?ver=<?= (int) $c['id'] ?>">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
if ($detalhe !== null) {
    require __DIR__ . '/includes/detalhe_chamado.php';
}
require __DIR__ . '/includes/layout_rodape.php';
