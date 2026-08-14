<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

exigirLogin();

$agente = agenteLogado();
$erroBanco = null;
$kpis = ['Aberto' => 0, 'Em tratativa' => 0, 'Resolvido' => 0, 'Concluido' => 0];
$recentes = [];
$meusEmTratativa = 0;

try {
    $pdo             = getConexao();
    $kpis            = contarPorStatus($pdo);
    $recentes        = chamadosRecentes($pdo);
    $meusEmTratativa = contarMeusEmTratativa($pdo, $agente['id']);
} catch (Throwable $e) {
    $erroBanco = 'Não foi possível carregar os dados agora. Tente atualizar a página.';
}

$totalAtivos = $kpis['Aberto'] + $kpis['Em tratativa'] + $kpis['Resolvido'];

$paginaAtual  = 'dashboard';
$tituloPagina = 'Dashboard';

require __DIR__ . '/includes/rotulos.php';
require __DIR__ . '/includes/layout_topo.php';
?>
        <?php if ($erroBanco !== null): ?>
            <div class="alerta-erro"><?= htmlspecialchars($erroBanco) ?></div>
        <?php endif; ?>

        <section class="bloco">
            <div class="bloco-cabecalho">
                <h2>Visão geral</h2>
                <span class="bloco-nota"><?= $totalAtivos ?> chamado(s) em aberto no total</span>
            </div>

            <div class="kpi-grid">
                <a class="kpi kpi-aberto" href="chamados.php">
                    <span class="kpi-rotulo">Aberto</span>
                    <span class="kpi-valor"><?= $kpis['Aberto'] ?></span>
                    <span class="kpi-desc">Aguardando atribuição</span>
                </a>
                <a class="kpi kpi-tratativa" href="tratativa.php">
                    <span class="kpi-rotulo">Em tratativa</span>
                    <span class="kpi-valor"><?= $kpis['Em tratativa'] ?></span>
                    <span class="kpi-desc">Suporte atuando</span>
                </a>
                <a class="kpi kpi-resolvido" href="tratativa.php">
                    <span class="kpi-rotulo">Resolvido</span>
                    <span class="kpi-valor"><?= $kpis['Resolvido'] ?></span>
                    <span class="kpi-desc">Pendente de lançamento no SN</span>
                </a>
                <a class="kpi kpi-concluido" href="lancados.php">
                    <span class="kpi-rotulo">Concluído</span>
                    <span class="kpi-valor"><?= $kpis['Concluido'] ?></span>
                    <span class="kpi-desc">Lançado no ServiceNow</span>
                </a>
            </div>

            <p class="minha-carga">
                <?php if ($meusEmTratativa > 0): ?>
                    Você tem <strong><?= $meusEmTratativa ?></strong> chamado(s) em tratativa no seu nome.
                <?php else: ?>
                    Você não tem chamados em tratativa no momento.
                <?php endif; ?>
            </p>
        </section>

        <section class="bloco">
            <div class="bloco-cabecalho">
                <h2>Atividade recente</h2>
                <a class="link-ver-todos" href="chamados.php">Ver todos</a>
            </div>

            <?php if (empty($recentes)): ?>
                <div class="vazio">
                    <p>Nenhum chamado registrado ainda.</p>
                    <p class="vazio-dica">Assim que alguém abrir um chamado no tablet, ele aparece aqui.</p>
                </div>
            <?php else: ?>
                <div class="tabela-wrap">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th>Chamado</th>
                                <th>Solicitante</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Responsável</th>
                                <th>Aberto em</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentes as $c): ?>
                                <tr>
                                    <td><span class="num-chamado"><?= htmlspecialchars($c['numero_chamado']) ?></span></td>
                                    <td><?= htmlspecialchars($c['nome_solicitante']) ?></td>
                                    <td><?= htmlspecialchars($rotuloTipo[$c['tipo']] ?? $c['tipo']) ?></td>
                                    <td>
                                        <span class="tag <?= $classeStatus[$c['status']] ?? '' ?>">
                                            <?= htmlspecialchars($rotuloStatus[$c['status']] ?? $c['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $c['agente_nome'] ? htmlspecialchars($c['agente_nome']) : '<span class="sem-dado">—</span>' ?></td>
                                    <td class="col-data"><?= formatarData($c['criado_em']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
<?php require __DIR__ . '/includes/layout_rodape.php'; ?>
