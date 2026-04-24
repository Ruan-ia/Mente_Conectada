<?php
// Configurações de Conexão (Adaptadas do seu dashboard.php)
$servidor = "localhost";
$usuario = "root";
$senha = "Home@spSENAI2025!";
$banco = "mente_conectada";

$conn = new mysqli($servidor, $usuario, $senha, $banco);
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// 1. Query para buscar todos os psicólogos, ID, nome e valor da consulta
$sqlPsicologos = "SELECT id, nome, valor_consulta FROM psicologos ORDER BY nome";
$resultPsicologos = $conn->query($sqlPsicologos);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mente Conectada - Cadastro</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Roboto', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background: linear-gradient(135deg, #d6a4f4, #8e44ad);
      padding: 20px;
    }
    .cadastro-container {
      background: #fff;
      padding: 30px;
      border-radius: 16px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
      max-width: 400px;
      width: 100%;
    }
    h1 {
      font-family: 'Playfair Display', serif;
      font-size: 1.8rem;
      margin-bottom: 15px;
      text-align: center;
    }
    .input-field {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 1rem;
      outline: none;
      transition: border 0.3s ease;
    }
    .input-field:focus { border-color: #8e44ad; }
    .btn {
      width: 100%;
      padding: 12px;
      background: #8e44ad;
      color: white;
      font-size: 1rem;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 10px;
    }
    .btn:hover { background: #732d91; }
    .link { display: block; text-align: center; margin-top: 15px; color: #8e44ad; text-decoration: none; }
  </style>
</head>
<body>
  <div class="cadastro-container">
    <h1>Criar Conta</h1>
    <form action="index.php" method="POST">
      <input type="hidden" name="acao" value="cadastro">
      <input type="text" name="nome" placeholder="Nome completo" class="input-field" required>
      <input type="email" name="email" placeholder="E-mail" class="input-field" required>
      <input type="password" name="senha" placeholder="Senha" class="input-field" required>
      <input type="text" name="telefone" placeholder="Telefone" class="input-field">
      <input type="number" name="idade" placeholder="Idade" class="input-field">
      
      <select name="tipo_atendimento" class="input-field" required>
        <option value="">Escolha o Tipo de Atendimento</option>
        <option value="Presencial">Presencial</option>
        <option value="Online">Online</option>
        <option value="Grupo">Grupo</option>
      </select>
      
      <select name="psicologo_id" class="input-field" required>
        <option value="">Escolha um psicólogo (Nome e Valor)</option>
        
        <?php
        if ($resultPsicologos->num_rows > 0) {
            while($psicologo = $resultPsicologos->fetch_assoc()) {
                // Formata o valor para exibição (ex: 150.00 -> 150,00)
                $valorFormatado = number_format($psicologo['valor_consulta'], 2, ',', '.');
                
                echo '<option value="' . htmlspecialchars($psicologo['id']) . '">';
                echo htmlspecialchars($psicologo['nome']) . ' (R$ ' . $valorFormatado . ')';
                echo '</option>';
            }
        } else {
            echo '<option value="" disabled>Nenhum psicólogo encontrado no sistema.</option>';
        }
        ?>
        
      </select>
      
      <button type="submit" class="btn">Cadastrar</button>
    </form>
    <a href="login.html" class="link">Já tem conta? Faça login</a>
  </div>
</body>
</html>