<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

exigirLogin();
$agente = agenteLogado();

$paginaAtual  = 'agentes';
$tituloPagina = 'Equipe';

$mensagemErro    = null;
$mensagemSucesso = null;
$valores         = ['username' => '', 'nome_completo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $mensagemErro = 'Sessão expirada. Entre novamente e repita a ação.';
    } else {
        $acao = (string) ($_POST['acao'] ?? '');

        try {
            $pdo = getConexao();

            if ($acao === 'criar') {
                $valores['username']      = (string) ($_POST['username'] ?? '');
                $valores['nome_completo'] = (string) ($_POST['nome_completo'] ?? '');
                $senha                    = (string) ($_POST['senha'] ?? '');

                $erro = criarAgente($pdo, $valores['username'], $valores['nome_completo'], $senha);

                if ($erro === null) {
                    header('Location: agentes.php?criado=1');
                    exit;
                }
                $mensagemErro = $erro;
            } elseif ($acao === 'alternar') {
                $erro = alternarAtivoAgente($pdo, (int) ($_POST['agente_id'] ?? 0), $agente['id']);

                if ($erro === null) {
                    header('Location: agentes.php?atualizado=1');
                    exit;
                }
                $mensagemErro = $erro;
            }
        } catch (Throwable $e) {
            $mensagemErro = 'Não foi possível concluir a ação agora. Tente novamente.';
        }
    }
}

if (isset($_GET['criado'])) {
    $mensagemSucesso = 'Agente cadastrado. Ele já pode entrar com o usuário e a senha definidos.';
}
if (isset($_GET['atualizado'])) {
    $mensagemSucesso = 'Acesso do agente atualizado.';
}

$agentes   = [];
$cargaPorAgente = [];
$erroBanco = null;

try {
    $pdo     = getConexao();
    $agentes = listarTodosAgentes($pdo);
    foreach ($agentes as $a) {
        $cargaPorAgente[(int) $a['id']] = chamadosAbertosDoAgente($pdo, (int) $a['id']);
    }
} catch (Throwable $e) {
    $erroBanco = 'Não foi possível carregar a equipe agora. Tente atualizar a página.';
}

require __DIR__ . '/includes/rotulos.php';
require __DIR__ . '/includes/layout_topo.php';
?>

<?php if ($erroBanco !== null): ?>
    <div class="alerta-erro"><?= htmlspecialchars($erroBanco) ?></div>
<?php endif; ?>
<?php if ($mensagemErro !== null): ?>
    <div class="alerta-erro"><?= htmlspecialchars($mensagemErro) ?></div>
<?php endif; ?>
<?php if ($mensagemSucesso !== null): ?>
    <div class="alerta-ok"><?= htmlspecialchars($mensagemSucesso) ?></div>
<?php endif; ?>

<section class="bloco">
    <div class="bloco-cabecalho">
        <h2>Agentes de suporte</h2>
        <span class="bloco-nota"><?= count($agentes) ?> cadastrado(s)</span>
    </div>

    <div class="tabela-wrap">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Usuário</th>
                    <th>Acesso</th>
                    <th class="ocultar-mobile">Em aberto</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agentes as $a): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($a['nome_completo']) ?>
                            <?php if ((int) $a['id'] === $agente['id']): ?>
                                <span class="marcador-eu">você</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="num-chamado"><?= htmlspecialchars($a['username']) ?></span></td>
                        <td>
                            <span class="tag <?= (int) $a['ativo'] === 1 ? 'st-concluido' : 'st-aberto' ?>">
                                <?= (int) $a['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="ocultar-mobile"><?= ($cargaPorAgente[(int) $a['id']] ?? 0) > 0 ? (int) $cargaPorAgente[(int) $a['id']] . ' chamado(s)' : '<span class="sem-dado">—</span>' ?></td>
                        <td class="col-acao">
                            <?php if ((int) $a['id'] !== $agente['id']): ?>
                                <form method="post" action="agentes.php" class="form-inline">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
                                    <input type="hidden" name="acao" value="alternar">
                                    <input type="hidden" name="agente_id" value="<?= (int) $a['id'] ?>">
                                    <?php $carga = $cargaPorAgente[(int) $a['id']] ?? 0; ?>
                                    <button type="submit" class="btn-linha"
                                        <?php if ((int) $a['ativo'] === 1 && $carga > 0): ?>
                                            onclick="return confirm('<?= $carga ?> chamado(s) seguem no nome desta pessoa. Ela perde o acesso, mas os chamados continuam atribuídos a ela ate serem transferidos. Desativar mesmo assim?')"
                                        <?php endif; ?>>
                                        <?= (int) $a['ativo'] === 1 ? 'Desativar' : 'Reativar' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="sem-dado">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="bloco">
    <div class="bloco-cabecalho">
        <h2>Cadastrar novo agente</h2>
    </div>

    <div class="painel">
        <form method="post" action="agentes.php" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(tokenCsrf()) ?>">
            <input type="hidden" name="acao" value="criar">

            <div class="grid-form">
                <div class="field">
                    <label class="field-label" for="nome_completo">Nome completo</label>
                    <input type="text" id="nome_completo" name="nome_completo"
                           value="<?= htmlspecialchars($valores['nome_completo']) ?>" required>
                </div>

                <div class="field">
                    <label class="field-label" for="username">Usuário</label>
                    <input type="text" id="username" name="username"
                           value="<?= htmlspecialchars($valores['username']) ?>"
                           placeholder="primeiro.ultimo" autocomplete="off" required>
                    <p class="ajuda">Formato primeiro.ultimo, sem acento e sem maiúsculas.</p>
                </div>

                <div class="field">
                    <label class="field-label" for="senha">Senha inicial</label>
                    <input type="password" id="senha" name="senha" autocomplete="new-password" required>
                    <p class="ajuda">Mínimo de 8 caracteres. Combine a senha com a pessoa antes de salvar.</p>
                </div>
            </div>

            <button type="submit" class="btn-salvar btn-compacto">Cadastrar agente</button>
        </form>
    </div>

    <p class="nota-rodape">
        Não existe recuperação de senha automática. Se alguém esquecer a senha, gere uma nova
        cadastrando o acesso novamente ou ajuste o hash pelo phpMyAdmin.
    </p>
</section>

<?php require __DIR__ . '/includes/layout_rodape.php'; ?>
