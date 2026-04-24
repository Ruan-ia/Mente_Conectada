<?php
session_start();

// Verifica se o psicólogo está logado
if (!isset($_SESSION['psicologo_id'])) {
    echo "<script>alert('Você precisa estar logado para cadastrar uma sessão.'); window.location.href='login_psicologo.html';</script>";
    exit();
}

// Conexão com o banco
$servidor = "localhost";
$usuario = "root";
$senha = "Home@spSENAI2025!";
$banco = "mente_conectada";

$conn = new mysqli($servidor, $usuario, $senha, $banco);
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Captura os dados do formulário
$paciente_nome = $_POST['paciente_nome'] ?? '';
$data_sessao = $_POST['data_sessao'] ?? '';
$hora = $_POST['hora'] ?? '';
$tipo = $_POST['tipo'] ?? 'Online';
$status = $_POST['status'] ?? 'Pendente';
$observacoes = $_POST['observacoes'] ?? '';

$psicologo_id = $_SESSION['psicologo_id'];

// Validação simples
if (empty($paciente_nome) || empty($data_sessao) || empty($hora)) {
    echo "<script>alert('Preencha todos os campos obrigatórios!'); history.back();</script>";
    exit();
}

// Inserir no banco
$stmt = $conn->prepare("INSERT INTO sessoes (paciente_nome, psicologo_id, data_sessao, hora, tipo, status, observacoes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sisssss", $paciente_nome, $psicologo_id, $data_sessao, $hora, $tipo, $status, $observacoes);

if ($stmt->execute()) {
    echo "<script>alert('Sessão cadastrada com sucesso!'); window.location.href='dashboard.html';</script>";
} else {
    echo "<script>alert('Erro ao salvar sessão: " . $stmt->error . "'); history.back();</script>";
}

$stmt->close();
$conn->close();
?>
