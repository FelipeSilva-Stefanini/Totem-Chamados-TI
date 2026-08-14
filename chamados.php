<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

exigirLogin();
$agente = agenteLogado();

$paginaAtual   = 'chamados';
$paginaArquivo = 'chamados.php';
$tituloPagina  = 'Chamados';

require __DIR__ . '/includes/acoes_chamado.php';

$lista         = [];
$agentesAtivos = [];
$detalhe       = null;
$historico     = [];
$erroBanco     = null;

try {
    $pdo           = getConexao();
    $lista         = listarChamadosPorStatus($pdo, ['Aberto']);
    $agentesAtivos = listarAgentesAtivos($pdo);

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

<section class="bloco">
    <div class="bloco-cabecalho">
        <h2>Chamados abertos</h2>
        <span class="bloco-nota"><?= count($lista) ?> aguardando atribuição</span>
    </div>

    <?php if (empty($lista)): ?>
        <div class="vazio">
            <p>Nenhum chamado aguardando atribuição.</p>
            <p class="vazio-dica">Os chamados abertos no tablet aparecem aqui.</p>
        </div>
    <?php else: ?>
        <div class="tabela-wrap">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Chamado</th>
                        <th>Solicitante</th>
                        <th class="ocultar-mobile">E-mail</th>
                        <th>Tipo</th>
                        <th>Prioridade</th>
                        <th class="ocultar-mobile">Aberto em</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $c): ?>
                        <tr class="linha-clicavel" onclick="window.location='chamados.php?ver=<?= (int) $c['id'] ?>'">
                            <td><span class="num-chamado"><?= htmlspecialchars($c['numero_chamado']) ?></span></td>
                            <td><?= htmlspecialchars($c['nome_solicitante']) ?></td>
                            <td class="ocultar-mobile celula-email"><?= htmlspecialchars($c['email_solicitante']) ?></td>
                            <td><?= htmlspecialchars($rotuloTipo[$c['tipo']] ?? $c['tipo']) ?></td>
                            <td>
                                <span class="tag <?= $classePrioridade[$c['prioridade_usuario']] ?? '' ?>">
                                    <?= htmlspecialchars($rotuloPrioridade[$c['prioridade_usuario']] ?? $c['prioridade_usuario']) ?>
                                </span>
                            </td>
                            <td class="ocultar-mobile col-data"><?= formatarData($c['criado_em']) ?></td>
                            <td class="col-acao"><a class="btn-linha" href="chamados.php?ver=<?= (int) $c['id'] ?>">Abrir</a></td>
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
