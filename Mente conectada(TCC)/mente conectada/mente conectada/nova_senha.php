<?php
session_start();
$erro = $mensagem = "";
$token = $_GET['token'] ?? "";

if(!$token){
    die("Token inválido!");
}

$conn = new mysqli("localhost","root","","mente_conectada");
if($conn->connect_error) die("Erro: ".$conn->connect_error);

// Verificar token válido
$stmt = $conn->prepare("SELECT paciente_id, expiracao, usado FROM redefinir_senha WHERE token=?");
$stmt->bind_param("s",$token);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows!==1){ die("Token inválido ou expirado!"); }
$stmt->bind_result($paciente_id,$expiracao,$usado);
$stmt->fetch();
if($usado || strtotime($expiracao)<time()){ die("Token inválido ou expirado!"); }
$stmt->close();

// Processar nova senha
if($_SERVER['REQUEST_METHOD']==='POST'){
    $senha = $_POST['senha'] ?? "";
    $senha2 = $_POST['senha2'] ?? "";

    if($senha === "" || $senha2 === ""){
        $erro = "Preencha os campos!";
    } elseif($senha !== $senha2){
        $erro = "As senhas não coincidem!";
    } else {
        $hash = password_hash($senha,PASSWORD_DEFAULT);
        $stmt2 = $conn->prepare("UPDATE pacientes SET senha=? WHERE id=?");
        $stmt2->bind_param("si",$hash,$paciente_id);
        $stmt2->execute();
        $stmt2->close();

        // Marcar token como usado
        $stmt3 = $conn->prepare("UPDATE redefinir_senha SET usado=1 WHERE token=?");
        $stmt3->bind_param("s",$token);
        $stmt3->execute();
        $stmt3->close();

        $mensagem = "Senha redefinida com sucesso! <a href='login.php'>Faça login</a>";
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Nova senha</title>
<style>
body{font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; background:#f4f4f9;}
.container{background:white; padding:30px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1);}
input, button{padding:12px; width:100%; margin-top:10px; border-radius:6px; border:1px solid #ccc;}
button{background:#8e44ad; color:white; border:none; cursor:pointer;}
button:hover{background:#732d91;}
.error{color:red; margin-top:10px;}
.success{color:green; margin-top:10px;}
</style>
</head>
<body>
<div class="container">
<h2>Nova senha</h2>
<?php if($erro!=="") echo "<div class='error'>$erro</div>"; ?>
<?php if($mensagem!==""): echo "<div class='success'>$mensagem</div>"; else: ?>
<form method="POST">
<input type="password" name="senha" placeholder="Nova senha" required>
<input type="password" name="senha2" placeholder="Confirme a senha" required>
<button type="submit">Redefinir senha</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
