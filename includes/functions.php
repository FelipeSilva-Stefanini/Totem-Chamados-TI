<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Validação leve de formato de e-mail (sem travar domínio).
 */
function validarEmailFormato(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Gera o próximo número de chamado no formato CAM00001 e grava o chamado
 * + o evento de abertura na trilha de auditoria, dentro de uma transação.
 *
 * Observação: o bloqueio (FOR UPDATE) evita duplicidade de número quando
 * dois chamados são salvos quase ao mesmo tempo. Para um único totem com
 * volume normal de uso isso é suficiente; se no futuro forem vários totens
 * simultâneos com volume alto, vale migrar para uma tabela de sequência
 * dedicada.
 *
 * @param array<string,string> $dados
 */
function criarChamado(PDO $pdo, array $dados): string
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->query(
            'SELECT numero_chamado FROM chamados ORDER BY id DESC LIMIT 1 FOR UPDATE'
        );
        $ultimo = $stmt->fetchColumn();

        $proximo = 1;
        if ($ultimo) {
            $proximo = ((int) substr((string) $ultimo, 3)) + 1;
        }
        $numero = 'CAM' . str_pad((string) $proximo, 5, '0', STR_PAD_LEFT);

        $ins = $pdo->prepare(
            'INSERT INTO chamados
                (numero_chamado, nome_solicitante, setor, login_solicitante, email_solicitante,
                 tipo, descricao, prioridade_usuario, status, criado_em, atualizado_em)
             VALUES
                (:numero, :nome, :setor, :login, :email, :tipo, :descricao, :prioridade, \'Aberto\', NOW(), NOW())'
        );
        $ins->execute([
            ':numero'     => $numero,
            ':nome'       => $dados['nome'],
            ':setor'      => $dados['setor'],
            ':login'      => $dados['login'],
            ':email'      => $dados['email'],
            ':tipo'       => $dados['tipo'],
            ':descricao'  => $dados['descricao'],
            ':prioridade' => $dados['prioridade'],
        ]);

        $chamadoId = (int) $pdo->lastInsertId();

        $hist = $pdo->prepare(
            'INSERT INTO chamados_historico
                (chamado_id, agente_id, tipo_evento, valor_anterior, valor_novo, criado_em)
             VALUES
                (:chamado_id, NULL, \'Abertura\', NULL, \'Aberto\', NOW())'
        );
        $hist->execute([':chamado_id' => $chamadoId]);

        $pdo->commit();

        return $numero;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Grava um evento na trilha de auditoria.
 */
function registrarHistorico(
    PDO $pdo,
    int $chamadoId,
    ?int $agenteId,
    string $tipoEvento,
    ?string $valorAnterior,
    ?string $valorNovo
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO chamados_historico
            (chamado_id, agente_id, tipo_evento, valor_anterior, valor_novo, criado_em)
         VALUES (:c, :a, :t, :va, :vn, NOW())'
    );
    $stmt->execute([
        ':c'  => $chamadoId,
        ':a'  => $agenteId,
        ':t'  => $tipoEvento,
        ':va' => $valorAnterior,
        ':vn' => $valorNovo,
    ]);
}

/**
 * Lista chamados filtrando por um ou mais status.
 *
 * @param array<int,string> $status
 * @return array<int,array<string,mixed>>
 */
function listarChamadosPorStatus(PDO $pdo, array $status): array
{
    if (empty($status)) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($status), '?'));
    $sql = "SELECT c.*, a.nome_completo AS agente_nome
            FROM chamados c
            LEFT JOIN agentes_suporte a ON a.id = c.agente_atribuido_id
            WHERE c.status IN ($marcadores)
            ORDER BY c.criado_em ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($status);

    return $stmt->fetchAll();
}

/**
 * Busca um chamado pelo id, já com o nome do responsável.
 *
 * @return array<string,mixed>|null
 */
function buscarChamado(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT c.*, a.nome_completo AS agente_nome
         FROM chamados c
         LEFT JOIN agentes_suporte a ON a.id = c.agente_atribuido_id
         WHERE c.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $chamado = $stmt->fetch();

    return $chamado ?: null;
}

/**
 * Histórico completo de um chamado, do mais antigo ao mais recente.
 *
 * @return array<int,array<string,mixed>>
 */
function historicoDoChamado(PDO $pdo, int $chamadoId): array
{
    $stmt = $pdo->prepare(
        'SELECT h.*, a.nome_completo AS agente_nome
         FROM chamados_historico h
         LEFT JOIN agentes_suporte a ON a.id = h.agente_id
         WHERE h.chamado_id = :c
         ORDER BY h.criado_em ASC, h.id ASC'
    );
    $stmt->execute([':c' => $chamadoId]);

    return $stmt->fetchAll();
}

/**
 * Atribui (ou transfere) um chamado a um agente e move para "Em tratativa".
 * Retorna null em caso de sucesso ou a mensagem de erro.
 */
function atribuirChamado(PDO $pdo, int $chamadoId, int $agenteDestinoId, int $agenteExecutorId): ?string
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT * FROM chamados WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $chamadoId]);
        $chamado = $stmt->fetch();

        if (!$chamado) {
            $pdo->rollBack();
            return 'Chamado não encontrado.';
        }

        if ($chamado['status'] === 'Concluido') {
            $pdo->rollBack();
            return 'Este chamado já foi concluído e não pode ser reatribuído.';
        }

        $anteriorId = $chamado['agente_atribuido_id'] !== null ? (int) $chamado['agente_atribuido_id'] : null;

        if ($anteriorId === $agenteDestinoId && $chamado['status'] === 'Em tratativa') {
            $pdo->rollBack();
            return 'Este chamado já está atribuído a esse agente.';
        }

        $nomeDestino = nomeDoAgente($pdo, $agenteDestinoId);
        $nomeAnterior = $anteriorId !== null ? nomeDoAgente($pdo, $anteriorId) : null;

        $upd = $pdo->prepare(
            "UPDATE chamados
             SET agente_atribuido_id = :a, status = 'Em tratativa', atualizado_em = NOW()
             WHERE id = :id"
        );
        $upd->execute([':a' => $agenteDestinoId, ':id' => $chamadoId]);

        $evento = $anteriorId === null ? 'Atribuicao' : 'Transferencia';
        registrarHistorico($pdo, $chamadoId, $agenteExecutorId, $evento, $nomeAnterior, $nomeDestino);

        if ($chamado['status'] !== 'Em tratativa') {
            registrarHistorico(
                $pdo,
                $chamadoId,
                $agenteExecutorId,
                'Mudanca_Status',
                (string) $chamado['status'],
                'Em tratativa'
            );
        }

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return 'Não foi possível atribuir o chamado agora. Tente novamente.';
    }
}

function nomeDoAgente(PDO $pdo, int $agenteId): ?string
{
    $stmt = $pdo->prepare('SELECT nome_completo FROM agentes_suporte WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $agenteId]);
    $nome = $stmt->fetchColumn();

    return $nome !== false ? (string) $nome : null;
}

/**
 * Agentes ativos, para o seletor de transferência.
 *
 * @return array<int,array<string,mixed>>
 */
function listarAgentesAtivos(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, username, nome_completo FROM agentes_suporte WHERE ativo = 1 ORDER BY nome_completo'
    )->fetchAll();
}

/**
 * Todos os agentes (ativos e inativos), para a tela de equipe.
 *
 * @return array<int,array<string,mixed>>
 */
function listarTodosAgentes(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, username, nome_completo, ativo, criado_em FROM agentes_suporte ORDER BY nome_completo'
    )->fetchAll();
}

/**
 * Cadastra um novo agente de suporte.
 * Retorna null em caso de sucesso ou a mensagem de erro.
 */
function criarAgente(PDO $pdo, string $username, string $nomeCompleto, string $senha): ?string
{
    $username     = strtolower(trim($username));
    $nomeCompleto = trim($nomeCompleto);

    if ($username === '' || $nomeCompleto === '' || $senha === '') {
        return 'Preencha usuário, nome completo e senha.';
    }

    if (!preg_match('/^[a-z]+\.[a-z]+$/', $username)) {
        return 'O usuário deve seguir o formato primeiro.ultimo, apenas letras minúsculas sem acento.';
    }

    if (mb_strlen($senha) < 8) {
        return 'A senha deve ter pelo menos 8 caracteres.';
    }

    $existe = $pdo->prepare('SELECT COUNT(*) FROM agentes_suporte WHERE username = :u');
    $existe->execute([':u' => $username]);

    if ((int) $existe->fetchColumn() > 0) {
        return 'Já existe um agente com esse usuário.';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO agentes_suporte (username, nome_completo, senha_hash, ativo, criado_em)
         VALUES (:u, :n, :h, 1, NOW())'
    );
    $stmt->execute([
        ':u' => $username,
        ':n' => $nomeCompleto,
        ':h' => password_hash($senha, PASSWORD_BCRYPT),
    ]);

    return null;
}

/**
 * Ativa ou desativa um agente. Um agente não pode desativar a si mesmo,
 * e o sistema não permite ficar sem nenhum agente ativo.
 */
function alternarAtivoAgente(PDO $pdo, int $agenteId, int $agenteLogadoId): ?string
{
    if ($agenteId === $agenteLogadoId) {
        return 'Você não pode desativar o seu próprio acesso.';
    }

    $stmt = $pdo->prepare('SELECT ativo FROM agentes_suporte WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $agenteId]);
    $atual = $stmt->fetchColumn();

    if ($atual === false) {
        return 'Agente não encontrado.';
    }

    $novo = ((int) $atual === 1) ? 0 : 1;

    if ($novo === 0) {
        $ativos = (int) $pdo->query('SELECT COUNT(*) FROM agentes_suporte WHERE ativo = 1')->fetchColumn();
        if ($ativos <= 1) {
            return 'Não é possível desativar o último agente ativo.';
        }
    }

    $upd = $pdo->prepare('UPDATE agentes_suporte SET ativo = :a WHERE id = :id');
    $upd->execute([':a' => $novo, ':id' => $agenteId]);

    return null;
}

/**
 * Converte o valor de status gravado no banco (sem acento) para exibição.
 */
function rotularStatus(string $status): string
{
    $mapa = [
        'Aberto'       => 'Aberto',
        'Em tratativa' => 'Em tratativa',
        'Resolvido'    => 'Resolvido',
        'Concluido'    => 'Concluído',
    ];

    return $mapa[$status] ?? $status;
}

/**
 * Transições de status permitidas. Bloquear no código evita que um clique
 * fora de ordem (ou um POST forjado) coloque o chamado num estado inválido.
 *
 * @var array<string,array<int,string>>
 */
const TRANSICOES_PERMITIDAS = [
    'Aberto'       => ['Em tratativa'],
    'Em tratativa' => ['Resolvido'],
    'Resolvido'    => ['Concluido', 'Em tratativa'],
    'Concluido'    => [],
];

/**
 * Muda o status de um chamado respeitando as transições permitidas.
 *
 * $extra aceita:
 *   'solucao'           => obrigatório ao mudar para Resolvido (o que foi feito)
 *   'numero_servicenow' => obrigatório ao mudar para Concluido (nº do chamado espelhado no SN)
 *
 * Retorna null em caso de sucesso ou a mensagem de erro.
 *
 * @param array{solucao?:string,numero_servicenow?:string} $extra
 */
function mudarStatusChamado(PDO $pdo, int $chamadoId, string $novoStatus, int $agenteId, array $extra = []): ?string
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT * FROM chamados WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $chamadoId]);
        $chamado = $stmt->fetch();

        if (!$chamado) {
            $pdo->rollBack();
            return 'Chamado não encontrado.';
        }

        $statusAtual = (string) $chamado['status'];
        $permitidos  = TRANSICOES_PERMITIDAS[$statusAtual] ?? [];

        if (!in_array($novoStatus, $permitidos, true)) {
            $pdo->rollBack();
            return 'Não é possível mudar de "' . rotularStatus($statusAtual)
                 . '" para "' . rotularStatus($novoStatus) . '".';
        }

        if ($chamado['agente_atribuido_id'] === null) {
            $pdo->rollBack();
            return 'Atribua o chamado a alguém antes de mudar o status.';
        }

        $sets   = ['status = :s', 'atualizado_em = NOW()'];
        $params = [':s' => $novoStatus, ':id' => $chamadoId];

        if ($novoStatus === 'Resolvido') {
            $solucao = trim((string) ($extra['solucao'] ?? ''));
            if ($solucao === '') {
                $pdo->rollBack();
                return 'Descreva o que foi feito antes de marcar como resolvido.';
            }
            if (mb_strlen($solucao) > 1000) {
                $pdo->rollBack();
                return 'A descrição da solução não pode passar de 1000 caracteres.';
            }
            $sets[]          = 'solucao = :sol';
            $params[':sol']  = $solucao;
        }

        if ($novoStatus === 'Concluido') {
            $numeroSN = trim((string) ($extra['numero_servicenow'] ?? ''));
            if ($numeroSN === '') {
                $pdo->rollBack();
                return 'Informe o número do chamado no ServiceNow antes de concluir.';
            }
            if (mb_strlen($numeroSN) > 50) {
                $pdo->rollBack();
                return 'O número do ServiceNow não pode passar de 50 caracteres.';
            }
            $sets[]         = 'numero_servicenow = :sn';
            $params[':sn']  = $numeroSN;
        }

        $sql = 'UPDATE chamados SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        registrarHistorico($pdo, $chamadoId, $agenteId, 'Mudanca_Status', $statusAtual, $novoStatus);

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return 'Não foi possível alterar o status agora. Tente novamente.';
    }
}

/**
 * Define (ou corrige) a prioridade avaliada pelo suporte.
 * A prioridade informada pelo usuário nunca é sobrescrita.
 */
function definirPrioridadeSuporte(PDO $pdo, int $chamadoId, string $prioridade, int $agenteId): ?string
{
    $validas = ['Baixa', 'Media', 'Alta', 'Critica'];

    if (!in_array($prioridade, $validas, true)) {
        return 'Prioridade inválida.';
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT prioridade_suporte FROM chamados WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $chamadoId]);
        $atual = $stmt->fetchColumn();

        if ($atual === false) {
            $pdo->rollBack();
            return 'Chamado não encontrado.';
        }

        if ((string) $atual === $prioridade) {
            $pdo->rollBack();
            return null; // nada a fazer, não é erro
        }

        $upd = $pdo->prepare(
            'UPDATE chamados SET prioridade_suporte = :p, atualizado_em = NOW() WHERE id = :id'
        );
        $upd->execute([':p' => $prioridade, ':id' => $chamadoId]);

        registrarHistorico(
            $pdo,
            $chamadoId,
            $agenteId,
            'Alteracao_Prioridade',
            $atual !== null ? (string) $atual : null,
            $prioridade
        );

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return 'Não foi possível alterar a prioridade agora. Tente novamente.';
    }
}

/**
 * Contagem de chamados atribuídos a cada agente, por status.
 * Usado para avisar antes de desativar alguém com chamados em aberto.
 */
function chamadosAbertosDoAgente(PDO $pdo, int $agenteId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM chamados
         WHERE agente_atribuido_id = :id AND status IN ('Em tratativa','Resolvido')"
    );
    $stmt->execute([':id' => $agenteId]);

    return (int) $stmt->fetchColumn();
}
