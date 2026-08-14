<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (estaLogado()) {
    header('Location: dashboard.php');
    exit;
}

$erro     = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $senha    = (string) ($_POST['senha'] ?? '');

    try {
        $pdo  = getConexao();
        $erro = tentarLogin($pdo, $username, $senha);

        if ($erro === null) {
            header('Location: dashboard.php');
            exit;
        }
    } catch (Throwable $e) {
        $erro = 'Não foi possível validar o acesso agora. Tente novamente em instantes.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Área do Suporte — Central de Chamados TI</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="top-stripe"></div>
    <main class="page">
        <div class="card card-login">
            <header class="masthead">
                <p class="eyebrow">TI · Field Service</p>
                <h1>Área do Suporte</h1>
                <p class="subtitle">Acesso restrito à equipe de Field Service.</p>
            </header>

            <form method="post" action="login.php" novalidate>
                <div class="section">
                    <?php if ($erro !== null): ?>
                        <div class="alerta-erro"><?= htmlspecialchars($erro) ?></div>
                    <?php endif; ?>

                    <div class="field">
                        <label class="field-label" for="username">Usuário</label>
                        <input type="text" id="username" name="username"
                               value="<?= htmlspecialchars($username) ?>"
                               placeholder="primeiro.ultimo" autocomplete="username" required>
                    </div>

                    <div class="field">
                        <label class="field-label" for="senha">Senha</label>
                        <input type="password" id="senha" name="senha"
                               autocomplete="current-password" required>
                    </div>

                    <button type="submit" class="btn-salvar">Entrar</button>
                </div>
            </form>

            <p class="footer-note">
                <a class="link-voltar" href="index.php">&larr; Voltar para abertura de chamado</a>
            </p>
        </div>
    </main>
</body>
</html>
