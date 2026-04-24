<?php
// 1. Configurações de Erro (Mantenha para desenvolvimento)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Inicia a sessão (Apenas uma vez, e no topo!)
session_start(); 

// 3. Inclui a conexão (Apenas uma vez!)
include(__DIR__ . '/conexao.php'); // Certifique-se de que conexao.php não tem output/espaços em branco

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    if (!empty($email) && !empty($senha)) {

        $sql = "SELECT * FROM psicologos WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $psicologo = $result->fetch_assoc();

            // Verifica a senha (Hashed ou simples)
            if ($senha === $psicologo['senha'] || password_verify($senha, $psicologo['senha'])) {

                $_SESSION['psicologo_id'] = $psicologo['id'];
                $_SESSION['psicologo_nome'] = $psicologo['nome'];

                // *REMOVIDOS* todos os "echo" de teste daqui

                // Redirecionamento CORRETO: deve ser a primeira coisa enviada
                header("Location: dashboard.php");
                exit; // O 'exit' garante que o script para de executar imediatamente
            } else {
                // Senha incorreta
                echo "<script>alert('Senha incorreta!'); window.location.href='login_psicologo.html';</script>";
                exit;
            }
        } else {
            // E-mail não encontrado
            echo "<script>alert('E-mail não encontrado!'); window.location.href='login_psicologo.html';</script>";
            exit;
        }

    } else {
        // Campos vazios
        echo "<script>alert('Preencha todos os campos.'); window.location.href='login_psicologo.html';</script>";
        exit;
    }
} else {
    // Se a página for acessada via GET
    header("Location: login_psicologo.html");
    exit;
}
?>