<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$tiposValidos = [
    'Duvida'     => ['label' => 'Dúvida', 'desc' => 'Tirar uma dúvida ou entender um processo'],
    'Incidente'  => ['label' => 'Incidente', 'desc' => 'Algo parou de funcionar'],
    'Requisicao' => ['label' => 'Requisição', 'desc' => 'Pedir algo novo'],
];

$prioridadesValidas = [
    'Baixa'   => 'Baixa',
    'Media'   => 'Média',
    'Alta'    => 'Alta',
    'Critica' => 'Crítica',
];

$erros   = [];
$valores = ['nome' => '', 'setor' => '', 'login' => '', 'email' => '', 'tipo' => '', 'prioridade' => '', 'descricao' => ''];

// ------------------------------------------------------------
// Tela de confirmação (chega aqui via redirecionamento pós-envio)
// ------------------------------------------------------------
if (isset($_GET['ok'], $_GET['numero'])) {
    $numeroConfirmado = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $_GET['numero']));
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <title>Chamado registrado — Central de Chamados TI</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <div class="top-stripe"></div>
        <main class="page">
            <div class="card">
                <div class="confirmacao">
                    <div class="check">&#10003;</div>
                    <h1>Chamado registrado</h1>
                    <div class="stub"><?= htmlspecialchars($numeroConfirmado) ?></div>
                    <p class="msg">Obrigado por informar sua solicitação. Em breve entraremos em contato.</p>
                    <a class="btn-novo" href="index.php">Abrir novo chamado</a>
                </div>
            </div>
        </main>
        <script>
            setTimeout(function () { window.location.href = 'index.php'; }, 8000);
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ------------------------------------------------------------
// Processamento do envio
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores['nome']       = trim((string) ($_POST['nome'] ?? ''));
    $valores['setor']      = trim((string) ($_POST['setor'] ?? ''));
    $valores['login']      = trim((string) ($_POST['login'] ?? ''));
    $valores['email']      = trim((string) ($_POST['email'] ?? ''));
    $valores['tipo']       = (string) ($_POST['tipo'] ?? '');
    $valores['prioridade'] = (string) ($_POST['prioridade'] ?? '');
    $valores['descricao']  = trim((string) ($_POST['descricao'] ?? ''));

    if ($valores['nome'] === '') {
        $erros['nome'] = 'Informe o nome completo.';
    }
    if ($valores['setor'] === '') {
        $erros['setor'] = 'Informe o setor.';
    }
    if ($valores['login'] === '') {
        $erros['login'] = 'Informe o login.';
    }
    if ($valores['email'] === '' || !validarEmailFormato($valores['email'])) {
        $erros['email'] = 'Informe um e-mail em formato válido.';
    }
    if (!array_key_exists($valores['tipo'], $tiposValidos)) {
        $erros['tipo'] = 'Selecione o tipo de solicitação.';
    }
    if (!array_key_exists($valores['prioridade'], $prioridadesValidas)) {
        $erros['prioridade'] = 'Selecione a prioridade.';
    }
    if ($valores['descricao'] === '') {
        $erros['descricao'] = 'Descreva o problema ou a solicitação.';
    } elseif (mb_strlen($valores['descricao']) > 500) {
        $erros['descricao'] = 'A descrição não pode passar de 500 caracteres.';
    }

    if (empty($erros)) {
        try {
            $pdo    = getConexao();
            $numero = criarChamado($pdo, $valores);
            header('Location: index.php?ok=1&numero=' . urlencode($numero));
            exit;
        } catch (Throwable $e) {
            $erros['geral'] = 'Não foi possível salvar o chamado agora. Tente novamente em instantes.';
            // Em produção: registrar $e->getMessage() em log próprio, nunca exibir na tela.
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Central de Chamados TI</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="top-stripe"></div>
    <main class="page">
        <div class="card">
            <header class="masthead">
                <p class="eyebrow">TI · Field Service</p>
                <h1>Central de Chamados</h1>
                <p class="subtitle">Preencha os campos abaixo para registrar sua dúvida, incidente ou requisição.</p>
            </header>

            <?php if (!empty($erros['geral'])): ?>
                <div class="section">
                    <p class="error-text"><?= htmlspecialchars($erros['geral']) ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php" novalidate>
                <div class="section">
                    <p class="eyebrow">Dados do solicitante</p>

                    <div class="field <?= isset($erros['nome']) ? 'has-error' : '' ?>">
                        <label class="field-label" for="nome">Nome completo</label>
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($valores['nome']) ?>" required>
                        <?php if (isset($erros['nome'])): ?><p class="error-text"><?= htmlspecialchars($erros['nome']) ?></p><?php endif; ?>
                    </div>

                    <div class="field <?= isset($erros['setor']) ? 'has-error' : '' ?>">
                        <label class="field-label" for="setor">Setor</label>
                        <input type="text" id="setor" name="setor" value="<?= htmlspecialchars($valores['setor']) ?>" required>
                        <?php if (isset($erros['setor'])): ?><p class="error-text"><?= htmlspecialchars($erros['setor']) ?></p><?php endif; ?>
                    </div>

                    <div class="field <?= isset($erros['login']) ? 'has-error' : '' ?>">
                        <label class="field-label" for="login">Login</label>
                        <input type="text" id="login" name="login" value="<?= htmlspecialchars($valores['login']) ?>" required>
                        <?php if (isset($erros['login'])): ?><p class="error-text"><?= htmlspecialchars($erros['login']) ?></p><?php endif; ?>
                    </div>

                    <div class="field <?= isset($erros['email']) ? 'has-error' : '' ?>">
                        <label class="field-label" for="email">E-mail Grupo Boticário</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($valores['email']) ?>" required>
                        <?php if (isset($erros['email'])): ?><p class="error-text"><?= htmlspecialchars($erros['email']) ?></p><?php endif; ?>
                    </div>
                </div>

                <div class="section">
                    <p class="eyebrow">Tipo de solicitação</p>
                    <div class="tipo-opcoes">
                        <?php foreach ($tiposValidos as $valor => $info): ?>
                            <div>
                                <input type="radio" id="tipo_<?= $valor ?>" name="tipo" value="<?= $valor ?>" <?= $valores['tipo'] === $valor ? 'checked' : '' ?>>
                                <label for="tipo_<?= $valor ?>" class="tipo-card <?= strtolower($valor) ?>">
                                    <span class="titulo"><?= htmlspecialchars($info['label']) ?></span>
                                    <span class="desc"><?= htmlspecialchars($info['desc']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($erros['tipo'])): ?><p class="error-text"><?= htmlspecialchars($erros['tipo']) ?></p><?php endif; ?>
                </div>

                <div class="section">
                    <p class="eyebrow">Prioridade</p>
                    <div class="field <?= isset($erros['prioridade']) ? 'has-error' : '' ?>">
                        <label class="field-label" for="prioridade">Como você avalia a urgência?</label>
                        <select id="prioridade" name="prioridade" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($prioridadesValidas as $valor => $label): ?>
                                <option value="<?= $valor ?>" <?= $valores['prioridade'] === $valor ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($erros['prioridade'])): ?><p class="error-text"><?= htmlspecialchars($erros['prioridade']) ?></p><?php endif; ?>
                    </div>
                </div>

                <div class="section">
                    <p class="eyebrow">Descrição</p>
                    <div class="field <?= isset($erros['descricao']) ? 'has-error' : '' ?>">
                        <label class="field-label" for="descricao">Descreva o problema, dúvida ou pedido</label>
                        <textarea id="descricao" name="descricao" maxlength="500" required><?= htmlspecialchars($valores['descricao']) ?></textarea>
                        <div class="contador" id="contador">0/500</div>
                        <?php if (isset($erros['descricao'])): ?><p class="error-text"><?= htmlspecialchars($erros['descricao']) ?></p><?php endif; ?>
                    </div>
                </div>

                <div class="section">
                    <button type="submit" class="btn-salvar">Salvar chamado</button>
                </div>
            </form>

            <p class="footer-note">
                Seus dados são usados apenas para retorno do suporte de TI.
                <a class="link-suporte" href="login.php">Área do Suporte</a>
            </p>
        </div>
    </main>

    <script>
        (function () {
            var textarea = document.getElementById('descricao');
            var contador = document.getElementById('contador');
            function atualizar() {
                var qtd = textarea.value.length;
                contador.textContent = qtd + '/500';
                contador.classList.toggle('aviso', qtd > 450 && qtd <= 500);
                contador.classList.toggle('limite', qtd >= 500);
            }
            textarea.addEventListener('input', atualizar);
            atualizar();
        })();
    </script>
</body>
</html>
