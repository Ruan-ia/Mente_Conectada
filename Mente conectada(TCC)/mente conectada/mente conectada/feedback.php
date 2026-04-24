<?php
// Conexão com o banco de dados
$mysqli = new mysqli("localhost", "root", "Home@spSENAI2025!", "mente_conectada");
if ($mysqli->connect_error) {
    die("Erro na conexão: " . $mysqli->connect_error);
}

// Controle de mensagens
$mensagem_enviada = false;
$erro_envio = '';

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');

    if (empty($nome) || empty($mensagem)) {
        $erro_envio = "Preencha todos os campos obrigatórios.";
    } elseif (strlen($nome) > 100 || strlen($email) > 100 || strlen($mensagem) > 1000) {
        $erro_envio = "Um dos campos excedeu o limite de caracteres.";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO comentarios (nome, email, mensagem) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $nome, $email, $mensagem);
            $stmt->execute();
            $stmt->close();
            $mensagem_enviada = true;
        } else {
            $erro_envio = "Erro ao salvar comentário.";
        }
    }
}

// Buscar comentários
$comentarios = [];
$result = $mysqli->query("SELECT nome, mensagem FROM comentarios ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $comentarios[] = $row;
    }
    $result->free();
}
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mente Conectada - Feedback</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
         .login-group {
      display:flex;
      align-items:center;
      gap:20px;
      font-size:16px;
      font-weight:500;
    }

    .login-group a {
      color:#555;
      transition:color 0.3s;
    }

    .login-group a:hover {color:#6a0dad;}

    .btn-psicologo {
      color:#4a2c6e !important;
      font-weight:600;
    }

    .btn-psicologo:hover {color:#8b2ddb !important;}
    </style>
</head>
<body>
<div class="container">
    <aside class="sidebar">
      <img src="img/logo.png.png" alt="Logo Mente Conectada">
      <nav>
        <a href="page1.html" class="active"><span>🏠</span>Início</a>
        <a href="especialidades.html"><span>📖</span>Especialidades</a>
        <a href="blog.html"><span>📰</span>Blog</a>
        <a href="autoajuda.html"><span>💜</span>Autoajuda</a>
        <a href="feedback.php"><span>💬</span>Feedback</a>
        <a href="agendamento.html"><span>📅</span>Agendamento</a>
      </nav>
      <div class="sidebar-indicator"></div>

        <button id="acessibilidade-btn" style="
        margin:20px; 
        padding:12px 18px; 
        border-radius:10px; 
        border:none; 
        width:90%; 
        font-size:15px; 
        font-weight:600; 
        color:#fff; 
        background: linear-gradient(90deg, #b06ab3);
        box-shadow:0 4px 10px rgba(0,0,0,0.15); 
        cursor:pointer;
        transition: transform 0.2s ease, opacity 0.3s;
      ">
        <i class="fa-solid fa-universal-access"></i> Modo Acessível
      </button>
    <script>
        document.getElementById('acessibilidade-btn').onclick = function() {
            document.body.classList.toggle('preto-branco');
        };
    </script>

    </aside>

    <main class="main">
      <div class="top-bar">
        <div class="top-menu">
          <a href="page1.html" class="active">Início</a>
          <a href="especialidades.html">Especialidades</a>
          <a href="blog.html">Blog</a>
          <a href="autoajuda.html">Autoajuda</a>
          <a href="feedback.php">Feedback</a>
          <a href="agendamento.html">Agendamento</a>
          <div class="top-indicator"></div>
        </div>
         <div class="login-group">
          <a href="login_psicologo.html" class="btn-psicologo">Área do Psicólogo</a>
          <a href="login.html" class="login-link">Login/Cadastrar</a>
        </div>
      </div>

      <h1 class="page-title">Feedback dos Usuários</h1>

      <section class="comments-section">
        <?php if (count($comentarios)): ?>
            <?php foreach ($comentarios as $comentario): ?>
                <div class="comment">
                    <div class="comment-content">
                        <h4><?= htmlspecialchars($comentario['nome']) ?></h4>
                        <p><?= nl2br(htmlspecialchars($comentario['mensagem'])) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div>Nenhum comentário ainda.</div>
        <?php endif; ?>
      </section>

      <section class="feedback-form">
        <h2>Deixe seu comentário</h2>
        <?php if ($mensagem_enviada): ?>
            <div class="success-msg">Seu comentário foi enviado com sucesso!</div>
        <?php elseif ($erro_envio): ?>
            <div class="error-msg"><?= htmlspecialchars($erro_envio) ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <label for="nome">Nome</label>
            <input type="text" id="nome" name="nome" maxlength="100" required placeholder="Seu nome">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" maxlength="100" placeholder="Seu e-mail (opcional)">
            <label for="mensagem">Mensagem</label>
            <textarea id="mensagem" name="mensagem" rows="6" maxlength="1000" required placeholder="Escreva sua mensagem aqui"></textarea>
            <button type="submit">Enviar</button>
        </form>
      </section>
    </main>
</div>
</body>
</html>
