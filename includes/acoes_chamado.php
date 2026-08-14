<?php
/**
 * Processa as ações POST do painel de detalhe do chamado.
 * Espera: $agente (agente logado) e $paginaArquivo (para onde redirecionar).
 * Define: $mensagemErro.
 *
 * Em caso de sucesso, redireciona e encerra o script (padrão POST-Redirect-GET,
 * que evita reenvio da ação ao atualizar a página).
 */
declare(strict_types=1);

$mensagemErro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $mensagemErro = 'Sessão expirada. Entre novamente e repita a ação.';
    } else {
        $acao      = (string) ($_POST['acao'] ?? '');
        $chamadoId = (int) ($_POST['chamado_id'] ?? 0);

        try {
            $pdo = getConexao();

            switch ($acao) {
                case 'atribuir':
                    $destinoId = (int) ($_POST['agente_destino'] ?? $agente['id']);
                    $erro = atribuirChamado($pdo, $chamadoId, $destinoId, $agente['id']);
                    if ($erro === null) {
                        header('Location: ' . $paginaArquivo . '?feito=atribuido');
                        exit;
                    }
                    $mensagemErro = $erro;
                    break;

                case 'status':
                    $novoStatus = (string) ($_POST['novo_status'] ?? '');
                    $extra = [];
                    if ($novoStatus === 'Resolvido') {
                        $extra['solucao'] = (string) ($_POST['solucao'] ?? '');
                    }
                    if ($novoStatus === 'Concluido') {
                        $extra['numero_servicenow'] = (string) ($_POST['numero_servicenow'] ?? '');
                    }
                    $erro = mudarStatusChamado($pdo, $chamadoId, $novoStatus, $agente['id'], $extra);
                    if ($erro === null) {
                        $marcador = match ($novoStatus) {
                            'Resolvido'    => 'resolvido',
                            'Concluido'    => 'concluido',
                            'Em tratativa' => 'reaberto',
                            default        => 'atualizado',
                        };
                        header('Location: ' . $paginaArquivo . '?feito=' . $marcador);
                        exit;
                    }
                    $mensagemErro = $erro;
                    break;

                case 'prioridade':
                    $prioridade = (string) ($_POST['prioridade_suporte'] ?? '');
                    $erro = definirPrioridadeSuporte($pdo, $chamadoId, $prioridade, $agente['id']);
                    if ($erro === null) {
                        header('Location: ' . $paginaArquivo . '?feito=prioridade&ver=' . $chamadoId);
                        exit;
                    }
                    $mensagemErro = $erro;
                    break;

                default:
                    $mensagemErro = 'Ação não reconhecida.';
            }
        } catch (Throwable $e) {
            $mensagemErro = 'Não foi possível concluir a ação agora. Tente novamente.';
        }
    }
}

$mensagensSucesso = [
    'atribuido'  => 'Chamado atribuído e movido para Em tratativa.',
    'resolvido'  => 'Chamado marcado como pendente de lançamento no ServiceNow.',
    'concluido'  => 'Chamado marcado como lançado no ServiceNow e concluído.',
    'reaberto'   => 'Chamado devolvido para tratativa.',
    'prioridade' => 'Prioridade do suporte atualizada.',
    'atualizado' => 'Chamado atualizado.',
];

$mensagemSucesso = $mensagensSucesso[$_GET['feito'] ?? ''] ?? null;
