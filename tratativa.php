<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

exigirLogin();
$agente = agenteLogado();

$paginaAtual   = 'tratativa';
$paginaArquivo = 'tratativa.php';
$tituloPagina  = 'Em tratativa';

require __DIR__ . '/includes/acoes_chamado.php';

$emTratativa   = [];
$pendentes     = [];
$agentesAtivos = [];
$detalhe       = null;
$historico     = [];
$erroBanco     = null;

// Filtro "somente meus chamados"
$soMeus = isset($_GET['meus']);

try {
    $pdo           = getConexao();
    $emTratativa   = listarChamadosPorStatus($pdo, ['Em tratativa']);
    $pendentes     = listarChamadosPorStatus($pdo, ['Resolvido']);
    $agentesAtivos = listarAgentesAtivos($pdo);

    if ($soMeus) {
        $filtro = static fn(array $c): bool => (int) $c['agente_atribuido_id'] === $agente['id'];
        $emTratativa = array_values(array_filter($emTratativa, $filtro));
        $pendentes   = array_values(array_filter($pendentes, $filtro));
    }

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

/**
 * Renderiza uma tabela de chamados desta tela.
 *
 * @param array<int,array<string,mixed>> $itens
 * @param array<string,string> $rotuloTipo
 * @param array<string,string> $rotuloPrioridade
 * @param array<string,string> $classePrioridade
 */
function tabelaChamados(
    array $itens,
    array $rotuloTipo,
    array $rotuloPrioridade,
    array $classePrioridade,
    string $vazioTitulo,
    string $vazioDica
): void {
    if (empty($itens)) {
        echo '<div class="vazio"><p>' . htmlspecialchars($vazioTitulo) . '</p>'
           . '<p class="vazio-dica">' . htmlspecialchars($vazioDica) . '</p></div>';
        return;
    }
    ?>
    <div class="tabela-wrap">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Chamado</th>
                    <th>Solicitante</th>
                    <th>Tipo</th>
                    <th>Prioridade</th>
                    <th>Responsável</th>
                    <th class="ocultar-mobile">Atualizado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $c): ?>
                    <?php $prio = $c['prioridade_suporte'] ?: $c['prioridade_usuario']; ?>
                    <tr class="linha-clicavel" onclick="window.location='tratativa.php?ver=<?= (int) $c['id'] ?>'">
                        <td><span class="num-chamado"><?= htmlspecialchars($c['numero_chamado']) ?></span></td>
                        <td><?= htmlspecialchars($c['nome_solicitante']) ?></td>
                        <td><?= htmlspecialchars($rotuloTipo[$c['tipo']] ?? $c['tipo']) ?></td>
                        <td>
                            <span class="tag <?= $classePrioridade[$prio] ?? '' ?>">
                                <?= htmlspecialchars($rotuloPrioridade[$prio] ?? $prio) ?>
                            </span>
                            <?php if (!$c['prioridade_suporte']): ?>
                                <span class="marcador-pendente" title="Prioridade ainda não avaliada pelo suporte">?</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $c['agente_nome'] ? htmlspecialchars($c['agente_nome']) : '<span class="sem-dado">—</span>' ?></td>
                        <td class="ocultar-mobile col-data"><?= formatarData($c['atualizado_em']) ?></td>
                        <td class="col-acao"><a class="btn-linha" href="tratativa.php?ver=<?= (int) $c['id'] ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>

<div class="filtro-barra">
    <a class="chip <?= $soMeus ? '' : 'chip-ativo' ?>" href="tratativa.php">Todos</a>
    <a class="chip <?= $soMeus ? 'chip-ativo' : '' ?>" href="tratativa.php?meus=1">Somente meus</a>
</div>

<section class="bloco">
    <div class="bloco-cabecalho">
        <h2>Em atendimento</h2>
        <span class="bloco-nota"><?= count($emTratativa) ?> chamado(s)</span>
    </div>
    <?php tabelaChamados(
        $emTratativa,
        $rotuloTipo,
        $rotuloPrioridade,
        $classePrioridade,
        'Nenhum chamado em atendimento.',
        $soMeus ? 'Você não tem chamados em tratativa no momento.' : 'Atribua um chamado na aba Chamados para começar.'
    ); ?>
</section>

<section class="bloco">
    <div class="bloco-cabecalho">
        <h2>Pendentes de lançamento no ServiceNow</h2>
        <span class="bloco-nota"><?= count($pendentes) ?> chamado(s)</span>
    </div>
    <?php tabelaChamados(
        $pendentes,
        $rotuloTipo,
        $rotuloPrioridade,
        $classePrioridade,
        'Nenhum chamado pendente de lançamento.',
        'Chamados resolvidos aguardando o registro no ServiceNow aparecem aqui.'
    ); ?>
</section>

<?php
if ($detalhe !== null) {
    require __DIR__ . '/includes/detalhe_chamado.php';
}
require __DIR__ . '/includes/layout_rodape.php';
