<?php
/**
 * Painel de detalhe do chamado, usado nas abas Chamados, Em tratativa e Lançados.
 * Espera: $detalhe, $historico, $agentesAtivos, $agente, $paginaArquivo
 * e os rótulos vindos de rotulos.php.
 *
 * As ações disponíveis mudam conforme o status, seguindo as mesmas regras
 * validadas no backend (TRANSICOES_PERMITIDAS).
 */
declare(strict_types=1);

$status = (string) $detalhe['status'];
$csrf   = tokenCsrf();
?>
<div class="modal-fundo">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="tituloDetalhe">
        <header class="modal-topo">
            <div>
                <span class="num-chamado num-destaque"><?= htmlspecialchars($detalhe['numero_chamado']) ?></span>
                <span class="tag <?= $classeStatus[$status] ?? '' ?>">
                    <?= htmlspecialchars($rotuloStatus[$status] ?? $status) ?>
                </span>
            </div>
            <a class="modal-fechar" href="<?= $paginaArquivo ?>" aria-label="Fechar detalhe">&times;</a>
        </header>

        <div class="modal-corpo">
            <h3 id="tituloDetalhe" class="modal-secao">Dados do solicitante</h3>
            <dl class="lista-dados">
                <div><dt>Nome</dt><dd><?= htmlspecialchars($detalhe['nome_solicitante']) ?></dd></div>
                <div><dt>Setor</dt><dd><?= htmlspecialchars($detalhe['setor']) ?></dd></div>
                <div><dt>Login</dt><dd><?= htmlspecialchars($detalhe['login_solicitante']) ?></dd></div>
                <div><dt>E-mail</dt><dd class="celula-email"><?= htmlspecialchars($detalhe['email_solicitante']) ?></dd></div>
            </dl>

            <h3 class="modal-secao">Solicitação</h3>
            <dl class="lista-dados">
                <div><dt>Tipo</dt><dd><?= htmlspecialchars($rotuloTipo[$detalhe['tipo']] ?? $detalhe['tipo']) ?></dd></div>
                <div>
                    <dt>Prioridade informada</dt>
                    <dd>
                        <span class="tag <?= $classePrioridade[$detalhe['prioridade_usuario']] ?? '' ?>">
                            <?= htmlspecialchars($rotuloPrioridade[$detalhe['prioridade_usuario']] ?? $detalhe['prioridade_usuario']) ?>
                        </span>
                    </dd>
                </div>
                <div>
                    <dt>Prioridade do suporte</dt>
                    <dd>
                        <?php if ($detalhe['prioridade_suporte']): ?>
                            <span class="tag <?= $classePrioridade[$detalhe['prioridade_suporte']] ?? '' ?>">
                                <?= htmlspecialchars($rotuloPrioridade[$detalhe['prioridade_suporte']] ?? $detalhe['prioridade_suporte']) ?>
                            </span>
                        <?php else: ?>
                            <span class="sem-dado">Ainda não definida</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div><dt>Aberto em</dt><dd><?= formatarData($detalhe['criado_em'], true) ?></dd></div>
                <div>
                    <dt>Responsável</dt>
                    <dd><?= $detalhe['agente_nome'] ? htmlspecialchars($detalhe['agente_nome']) : '<span class="sem-dado">Sem responsável</span>' ?></dd>
                </div>
                <div><dt>Última atualização</dt><dd><?= formatarData($detalhe['atualizado_em'], true) ?></dd></div>
            </dl>

            <h3 class="modal-secao">Descrição</h3>
            <p class="descricao-box"><?= nl2br(htmlspecialchars($detalhe['descricao'])) ?></p>

            <?php if (!empty($detalhe['solucao'])): ?>
                <h3 class="modal-secao">Solução registrada</h3>
                <p class="descricao-box descricao-solucao"><?= nl2br(htmlspecialchars($detalhe['solucao'])) ?></p>
            <?php endif; ?>

            <?php if (!empty($detalhe['numero_servicenow'])): ?>
                <h3 class="modal-secao">ServiceNow</h3>
                <dl class="lista-dados">
                    <div><dt>Nº do chamado no ServiceNow</dt><dd><span class="num-chamado"><?= htmlspecialchars($detalhe['numero_servicenow']) ?></span></dd></div>
                </dl>
            <?php endif; ?>

            <h3 class="modal-secao">Histórico</h3>
            <?php if (empty($historico)): ?>
                <p class="sem-dado">Sem eventos registrados.</p>
            <?php else: ?>
                <ul class="timeline">
                    <?php foreach ($historico as $h): ?>
                        <li>
                            <span class="timeline-data"><?= formatarData($h['criado_em'], true) ?></span>
                            <span class="timeline-texto">
                                <strong><?= htmlspecialchars($rotuloEvento[$h['tipo_evento']] ?? $h['tipo_evento']) ?></strong>
                                <?php
                                $de   = $h['valor_anterior'];
                                $para = $h['valor_novo'];
                                $deEx   = $de   ? ($rotuloStatus[$de]   ?? $rotuloPrioridade[$de]   ?? $de)   : null;
                                $paraEx = $para ? ($rotuloStatus[$para] ?? $rotuloPrioridade[$para] ?? $para) : null;
                                ?>
                                <?php if ($deEx && $paraEx): ?>
                                    — de <?= htmlspecialchars($deEx) ?> para <?= htmlspecialchars($paraEx) ?>
                                <?php elseif ($paraEx): ?>
                                    — <?= htmlspecialchars($paraEx) ?>
                                <?php endif; ?>
                                <span class="timeline-autor">
                                    <?= $h['agente_nome'] ? 'por ' . htmlspecialchars($h['agente_nome']) : 'pelo solicitante' ?>
                                </span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if ($status !== 'Concluido'): ?>
            <footer class="modal-rodape">

                <?php if ($status === 'Em tratativa'): ?>
                    <form method="post" action="<?= $paginaArquivo ?>" class="bloco-acao">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="acao" value="prioridade">
                        <input type="hidden" name="chamado_id" value="<?= (int) $detalhe['id'] ?>">
                        <label class="field-label" for="prioridade_suporte">Prioridade avaliada pelo suporte</label>
                        <div class="linha-acao">
                            <select id="prioridade_suporte" name="prioridade_suporte">
                                <?php foreach ($rotuloPrioridade as $valor => $label): ?>
                                    <option value="<?= $valor ?>"
                                        <?= ($detalhe['prioridade_suporte'] ?? $detalhe['prioridade_usuario']) === $valor ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-linha btn-alto">Salvar prioridade</button>
                        </div>
                    </form>
                <?php endif; ?>

                <form method="post" action="<?= $paginaArquivo ?>" class="bloco-acao">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="acao" value="atribuir">
                    <input type="hidden" name="chamado_id" value="<?= (int) $detalhe['id'] ?>">
                    <label class="field-label" for="agente_destino">
                        <?= $detalhe['agente_atribuido_id'] ? 'Transferir para' : 'Atribuir para' ?>
                    </label>
                    <div class="linha-acao">
                        <select id="agente_destino" name="agente_destino">
                            <?php foreach ($agentesAtivos as $a): ?>
                                <option value="<?= (int) $a['id'] ?>"
                                    <?= (int) $a['id'] === $agente['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['nome_completo']) ?><?= (int) $a['id'] === $agente['id'] ? ' (eu)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-linha btn-alto">
                            <?= $detalhe['agente_atribuido_id'] ? 'Transferir' : 'Atribuir' ?>
                        </button>
                    </div>
                </form>

                <?php if ($status === 'Em tratativa'): ?>
                    <form method="post" action="<?= $paginaArquivo ?>" class="bloco-acao">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="acao" value="status">
                        <input type="hidden" name="chamado_id" value="<?= (int) $detalhe['id'] ?>">
                        <input type="hidden" name="novo_status" value="Resolvido">
                        <label class="field-label" for="solucao">O que foi feito?</label>
                        <textarea id="solucao" name="solucao" maxlength="1000" required
                            placeholder="Descreva a causa e o que foi feito para resolver."><?= htmlspecialchars($detalhe['solucao'] ?? '') ?></textarea>
                        <button type="submit" class="btn-salvar btn-compacto">Marcar como pendente de lançamento</button>
                        <p class="ajuda">O chamado sai da fila de atendimento e passa a aguardar o lançamento no ServiceNow.</p>
                    </form>
                <?php endif; ?>

                <?php if ($status === 'Resolvido'): ?>
                    <form method="post" action="<?= $paginaArquivo ?>" class="bloco-acao">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="acao" value="status">
                        <input type="hidden" name="chamado_id" value="<?= (int) $detalhe['id'] ?>">
                        <input type="hidden" name="novo_status" value="Concluido">
                        <label class="field-label" for="numero_servicenow">Nº do chamado no ServiceNow</label>
                        <div class="linha-acao">
                            <input type="text" id="numero_servicenow" name="numero_servicenow" maxlength="50"
                                placeholder="ex: INC0012345" required>
                            <button type="submit" class="btn-salvar btn-compacto">Marcar como lançado</button>
                        </div>
                        <p class="ajuda">Use depois de abrir e fechar o chamado no ServiceNow. Isso conclui o chamado aqui.</p>
                    </form>

                    <form method="post" action="<?= $paginaArquivo ?>" class="bloco-acao">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="acao" value="status">
                        <input type="hidden" name="chamado_id" value="<?= (int) $detalhe['id'] ?>">
                        <input type="hidden" name="novo_status" value="Em tratativa">
                        <button type="submit" class="btn-linha">Voltar para tratativa</button>
                        <p class="ajuda">Use se o problema voltou ou o atendimento não estava concluído.</p>
                    </form>
                <?php endif; ?>

            </footer>
        <?php endif; ?>
    </div>
</div>
