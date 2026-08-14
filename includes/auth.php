<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const MAX_TENTATIVAS       = 5;   // tentativas falhas permitidas
const JANELA_BLOQUEIO_MIN  = 15;  // minutos de bloqueio após estourar o limite

/**
 * Inicia a sessão apenas uma vez, com parâmetros de cookie mais seguros.
 */
function iniciarSessao(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function estaLogado(): bool
{
    iniciarSessao();
    return isset($_SESSION['agente_id']);
}

/**
 * Protege páginas restritas. Redireciona pro login se não houver sessão.
 */
function exigirLogin(): void
{
    if (!estaLogado()) {
        header('Location: login.php');
        exit;
    }
}

function agenteLogado(): array
{
    iniciarSessao();
    return [
        'id'       => (int) ($_SESSION['agente_id'] ?? 0),
        'username' => (string) ($_SESSION['agente_username'] ?? ''),
        'nome'     => (string) ($_SESSION['agente_nome'] ?? ''),
    ];
}

function ipDoCliente(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Conta tentativas falhas recentes para um usuário dentro da janela de bloqueio.
 */
function tentativasRecentes(PDO $pdo, string $username): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_tentativas
         WHERE username = :u AND tentativa_em > (NOW() - INTERVAL :min MINUTE)'
    );
    $stmt->bindValue(':u', $username);
    $stmt->bindValue(':min', JANELA_BLOQUEIO_MIN, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function registrarTentativaFalha(PDO $pdo, string $username): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO login_tentativas (username, ip, tentativa_em) VALUES (:u, :ip, NOW())'
    );
    $stmt->execute([':u' => $username, ':ip' => ipDoCliente()]);
}

function limparTentativas(PDO $pdo, string $username): void
{
    $stmt = $pdo->prepare('DELETE FROM login_tentativas WHERE username = :u');
    $stmt->execute([':u' => $username]);
}

/**
 * Tenta autenticar. Retorna null em caso de sucesso, ou a mensagem de erro.
 *
 * A mensagem de credencial inválida é propositalmente genérica: não revela
 * se o usuário existe ou se apenas a senha está errada.
 */
function tentarLogin(PDO $pdo, string $username, string $senha): ?string
{
    $username = trim($username);

    if ($username === '' || $senha === '') {
        return 'Informe usuário e senha.';
    }

    if (tentativasRecentes($pdo, $username) >= MAX_TENTATIVAS) {
        return 'Muitas tentativas seguidas. Aguarde ' . JANELA_BLOQUEIO_MIN . ' minutos e tente de novo.';
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, nome_completo, senha_hash, ativo
         FROM agentes_suporte WHERE username = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $agente = $stmt->fetch();

    if (!$agente || (int) $agente['ativo'] !== 1 || !password_verify($senha, (string) $agente['senha_hash'])) {
        registrarTentativaFalha($pdo, $username);
        return 'Usuário ou senha inválidos.';
    }

    limparTentativas($pdo, $username);

    iniciarSessao();
    session_regenerate_id(true);
    $_SESSION['agente_id']       = (int) $agente['id'];
    $_SESSION['agente_username'] = (string) $agente['username'];
    $_SESSION['agente_nome']     = (string) $agente['nome_completo'];

    return null;
}

function fazerLogout(): void
{
    iniciarSessao();
    $_SESSION = [];
    session_destroy();
}

/**
 * Contagem de chamados por status, para os KPIs do dashboard.
 *
 * @return array<string,int>
 */
function contarPorStatus(PDO $pdo): array
{
    $base = ['Aberto' => 0, 'Em tratativa' => 0, 'Resolvido' => 0, 'Concluido' => 0];

    $stmt = $pdo->query('SELECT status, COUNT(*) AS total FROM chamados GROUP BY status');
    foreach ($stmt->fetchAll() as $linha) {
        $base[$linha['status']] = (int) $linha['total'];
    }

    return $base;
}

/**
 * Chamados mais recentes, para a lista de atividade do dashboard.
 *
 * @return array<int,array<string,mixed>>
 */
function chamadosRecentes(PDO $pdo, int $limite = 8): array
{
    $stmt = $pdo->prepare(
        'SELECT c.numero_chamado, c.nome_solicitante, c.tipo, c.status,
                c.prioridade_usuario, c.prioridade_suporte, c.criado_em,
                a.nome_completo AS agente_nome
         FROM chamados c
         LEFT JOIN agentes_suporte a ON a.id = c.agente_atribuido_id
         ORDER BY c.criado_em DESC
         LIMIT :lim'
    );
    $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Quantos chamados estão atribuídos ao agente logado e ainda em tratativa.
 */
function contarMeusEmTratativa(PDO $pdo, int $agenteId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM chamados
         WHERE agente_atribuido_id = :id AND status = 'Em tratativa'"
    );
    $stmt->execute([':id' => $agenteId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Gera (uma vez por sessão) o token anti-CSRF usado nos formulários internos.
 */
function tokenCsrf(): string
{
    iniciarSessao();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

/**
 * Valida o token recebido num POST. Retorna false se não conferir.
 */
function csrfValido(?string $token): bool
{
    iniciarSessao();
    $esperado = (string) ($_SESSION['csrf_token'] ?? '');
    return $esperado !== '' && is_string($token) && hash_equals($esperado, $token);
}
