<?php
session_start();
// Garante que apenas psicólogos logados possam acessar
if(!isset($_SESSION['psicologo_id'])){
    header("Location: login_psicologo.php");
    exit();
}

$servidor = "localhost";
$usuario = "root";
$senha = "Home@spSENAI2025!";
$banco = "mente_conectada";

$conn = new mysqli($servidor, $usuario, $senha, $banco);
if ($conn->connect_error) die("Conexão falhou: ".$conn->connect_error);

$consulta_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$consulta_antiga = null;
$psicologo_id = $_SESSION['psicologo_id'];

// 1. BUSCA OS DADOS DA CONSULTA ORIGINAL (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $consulta_id > 0) {
    $sql = "SELECT c.data, c.hora, p.nome FROM consultas c JOIN pacientes p ON c.paciente_id = p.id WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $consulta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $consulta_antiga = $result->fetch_assoc();
    $stmt->close();

    if (!$consulta_antiga) {
        // Consulta não encontrada, redireciona
        echo "<script>alert('Consulta não encontrada.'); window.location.href='dashboard.php';</script>";
        exit;
    }
}

// 2. PROCESSA O REAGENDAMENTO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $consulta_id > 0) {
    $nova_data = $_POST['data'];
    $nova_hora = $_POST['hora'];
    
    // Atualiza a consulta
    $sql = "UPDATE consultas SET data = ?, hora = ?, status = 'Reagendada' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    // Bindamos os parâmetros: s=string (nova data), s=string (nova hora), i=integer (ID da consulta)
    $stmt->bind_param("ssi", $nova_data, $nova_hora, $consulta_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Consulta reagendada com sucesso para " . date('d/m/Y', strtotime($nova_data)) . " às " . $nova_hora . "!'); window.location.href='dashboard.php';</script>";
    } else {
        echo "<script>alert('Erro ao reagendar a consulta: " . $conn->error . "'); window.location.href='reagendar.php?id=" . $consulta_id . "';</script>";
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

// 3. EXIBE O FORMULÁRIO (HTML)
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reagendar Consulta</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #d6a4f4, #8e44ad);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background: #fff;
            padding: 30px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
        }
        h1 {
            color: #4a2c6e;
            text-align: center;
            margin-bottom: 25px;
            font-size: 24px;
        }
        .info-box {
            background: #f3e8fa;
            border-left: 5px solid #8e44ad;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 16px;
            color: #333;
        }
        .info-box strong {
            color: #4a2c6e;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        input[type="date"], input[type="time"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #8e44ad;
            color: white;
            font-size: 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-weight: 500;
        }
        .btn-submit:hover {
            background: #732d91;
        }
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #8e44ad;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        .btn-back:hover {
            color: #732d91;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($consulta_antiga): ?>
            <h1><i class="fa-solid fa-calendar-day"></i> Reagendar Consulta</h1>

            <div class="info-box">
                Paciente: <strong><?php echo htmlspecialchars($consulta_antiga['nome']); ?></strong><br>
                Data Atual: <strong><?php echo date('d/m/Y', strtotime($consulta_antiga['data'])); ?></strong><br>
                Hora Atual: <strong><?php echo date('H:i', strtotime($consulta_antiga['hora'])); ?></strong>
            </div>

            <form method="POST" action="reagendar.php">
                <input type="hidden" name="id" value="<?php echo $consulta_id; ?>">
                
                <div class="form-group">
                    <label for="data">Nova Data:</label>
                    <input type="date" id="data" name="data" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="hora">Nova Hora:</label>
                    <input type="time" id="hora" name="hora" required>
                </div>

                <button type="submit" class="btn-submit">Confirmar Reagendamento</button>
            </form>
            <a href="dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Voltar ao Dashboard</a>
        <?php else: ?>
            <p>ID de consulta inválido.</p>
            <a href="dashboard.php" class="btn-back">Voltar ao Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>