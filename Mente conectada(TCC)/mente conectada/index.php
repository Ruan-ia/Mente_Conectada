<?php
session_start();
$erro = $mensagem = "";
$acao = $_POST['acao'] ?? '';

// Configurações do banco
$servidor = "localhost";
$usuario = "root";
$senha = "Home@spSENAI2025!";
$banco = "mente_conectada";

// Conexão segura com tratamento de erros
$conn = new mysqli($servidor, $usuario, $senha, $banco);
if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

// Função para limpar dados de entrada
function limpar($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// --- LOGIN ---
if ($acao === 'login') {
    $email = limpar($_POST['email'] ?? '');
    $senhaInput = $_POST['senha'] ?? '';

    if ($email === '' || $senhaInput === '') {
        $erro = "Preencha todos os campos!";
    } else {
        $stmt = $conn->prepare("SELECT id, nome, senha FROM pacientes WHERE email=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $nome, $hashSenha);
                $stmt->fetch();
                if (password_verify($senhaInput, $hashSenha)) {
                    // Login bem-sucedido
                    $_SESSION['paciente'] = $nome;
                    $_SESSION['paciente_id'] = $id;
                    header("Location: paciente.php");
                    exit();
                } else {
                    $erro = "Senha incorreta!";
                }
            } else {
                $erro = "E-mail não cadastrado!";
            }
            $stmt->close();
        } else {
            $erro = "Erro na consulta: " . $conn->error;
        }
    }
}

// --- CADASTRO ---
if ($acao === 'cadastro') {
    $nome = limpar($_POST['nome'] ?? '');
    $email = limpar($_POST['email'] ?? '');
    $senhaInput = $_POST['senha'] ?? '';
    $telefone = limpar($_POST['telefone'] ?? null);
    $idade = intval($_POST['idade'] ?? 0);
    // CORRIGIDO: Agora pega o valor de 'psicologo_id'
    $psicologo_id = intval($_POST['psicologo_id'] ?? 0); 

    if ($nome === '' || $email === '' || $senhaInput === '') {
        $erro = "Preencha todos os campos obrigatórios!";
    } else {
        // Verifica se o e-mail já está cadastrado
        $stmtCheck = $conn->prepare("SELECT id FROM pacientes WHERE email=? LIMIT 1");
        $stmtCheck->bind_param("s", $email);
        $stmtCheck->execute();
        $stmtCheck->store_result();
        if ($stmtCheck->num_rows > 0) {
            $erro = "E-mail já cadastrado!";
        } else {
            $senhaHash = password_hash($senhaInput, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO pacientes (nome, email, senha, telefone, idade, psicologo_id) VALUES (?,?,?,?,?,?)");
            if ($stmt) {
                $stmt->bind_param("ssssii", $nome, $email, $senhaHash, $telefone, $idade, $psicologo_id);
                if ($stmt->execute()) {
                    $mensagem = "Cadastro realizado com sucesso! Faça login.";
                    header("Location: login.html");
                    exit();
                } else {
                    $erro = "Erro ao cadastrar: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $erro = "Erro na consulta: " . $conn->error;
            }
        }
        $stmtCheck->close();
    }
}

// HTML básico de feedback
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Mente Conectada</title>
</head>
<body>
<?php if ($erro) echo "<p style='color:red;'>$erro</p>"; ?>
<?php if ($mensagem) echo "<p style='color:green;'>$mensagem</p>"; ?>
<a href="login.html">Voltar para Login</a>
</body>
</html>