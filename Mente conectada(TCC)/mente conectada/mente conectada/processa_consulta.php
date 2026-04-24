<?php
session_start();
// Garante que apenas psicólogos logados possam acessar
if(!isset($_SESSION['psicologo_id'])){
    header("Location: login_psicologo.php");
    exit();
}

// Configurações de conexão (use as mesmas do seu dashboard.php)
$servidor = "localhost";
$usuario = "root";
$senha = "Home@spSENAI2025!";
$banco = "mente_conectada";

$conn = new mysqli($servidor, $usuario, $senha, $banco);
if ($conn->connect_error) die("Conexão falhou: ".$conn->connect_error);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    
    $consulta_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'cancelar') {
        
        // Query para atualizar o status da consulta
        $sql = "UPDATE consultas SET status = 'Cancelada' WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $consulta_id);

        if ($stmt->execute()) {
            // Sucesso: Redireciona de volta para o dashboard com uma mensagem
            echo "<script>alert('Consulta desmarcada (cancelada) com sucesso!'); window.location.href='dashboard.php';</script>";
        } else {
            // Erro: Redireciona com uma mensagem de falha
            echo "<script>alert('Erro ao desmarcar a consulta: " . $conn->error . "'); window.location.href='dashboard.php';</script>";
        }
        
        $stmt->close();
    }
    // Você pode adicionar outras ações aqui, se necessário
} else {
    // Se o acesso for inválido
    header("Location: dashboard.php");
    exit();
}

$conn->close();
?>