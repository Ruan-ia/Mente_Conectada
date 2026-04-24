<?php
session_start();
if(!isset($_SESSION['paciente_id'])){
    header("Location: login.html");
    exit();
}

$servidor = "localhost";
$usuario = "root";
$senha = "Home@spSENAI2025!";
$banco = "mente_conectada";

$conn = new mysqli($servidor, $usuario, $senha, $banco);
if ($conn->connect_error) die("Conexão falhou: ".$conn->connect_error);

$paciente_id = $_SESSION['paciente_id'];
$paciente_nome = $_SESSION['paciente'];

// Buscar psicologo do paciente
$stmt = $conn->prepare("SELECT psicologo_id FROM pacientes WHERE id=?");
$stmt->bind_param("i",$paciente_id);
$stmt->execute();
$stmt->bind_result($psicologo_id);
$stmt->fetch();
$stmt->close();

// --- Diário ---
if(isset($_POST['acao']) && $_POST['acao']==='adicionar'){
    $texto = trim($_POST['texto']);
    if($texto!==''){
        $stmt = $conn->prepare("INSERT INTO diario (paciente_id,texto,data) VALUES (?,?,NOW())");
        $stmt->bind_param("is",$paciente_id,$texto);
        $stmt->execute();
        $stmt->close();
    }
}
if(isset($_POST['acao']) && $_POST['acao']==='editar'){
    $id_edit = intval($_POST['id']);
    $texto_edit = trim($_POST['texto']);
    if($texto_edit!==''){
        $stmt = $conn->prepare("UPDATE diario SET texto=? WHERE id=? AND paciente_id=?");
        $stmt->bind_param("sii",$texto_edit,$id_edit,$paciente_id);
        $stmt->execute();
        $stmt->close();
    }
}
if(isset($_GET['deletar'])){
    $id_deletar = intval($_GET['deletar']);
    $stmt = $conn->prepare("DELETE FROM diario WHERE id=? AND paciente_id=?");
    $stmt->bind_param("ii",$id_deletar,$paciente_id);
    $stmt->execute();
    $stmt->close();
}

// Buscar entradas do mês atual
$mes_atual = date("m");
$ano_atual = date("Y");
$stmt = $conn->prepare("SELECT id,texto,data FROM diario WHERE paciente_id=? AND MONTH(data)=? AND YEAR(data)=? ORDER BY data ASC");
$stmt->bind_param("iii",$paciente_id,$mes_atual,$ano_atual);
$stmt->execute();
$result = $stmt->get_result();
$entradasPorDia = [];
while($row = $result->fetch_assoc()){
    $dia = date("j", strtotime($row['data']));
    if(!isset($entradasPorDia[$dia])) $entradasPorDia[$dia] = [];
    $entradasPorDia[$dia][] = $row;
}
$stmt->close();


// ===============================================
// --- LÓGICA DE AGENDAMENTO E CANCELAMENTO ---
// ===============================================

// --- CANCELAMENTO (Desmarcar pelo Paciente) ---
if(isset($_GET['acao']) && $_GET['acao']==='cancelar_consulta'){
    $id_cancelar = intval($_GET['id']);
    // Atualiza o status para 'Cancelada', garantindo que a consulta é deste paciente
    $stmt = $conn->prepare("UPDATE consultas SET status = 'Cancelada' WHERE id=? AND paciente_id=? AND status != 'Cancelada'");
    $stmt->bind_param("ii",$id_cancelar,$paciente_id);
    if($stmt->execute()){
        $mensagem_agenda = "Consulta ID $id_cancelar desmarcada (cancelada) com sucesso!";
    } else {
         $erro_agenda = "Erro ao desmarcar a consulta: " . $conn->error;
    }
    $stmt->close();
    // Redireciona para evitar reenvio do formulário/link
    header("Location: paciente.php?msg=" . urlencode($mensagem_agenda ?? $erro_agenda));
    exit();
}


// --- AGENDAMENTO (Novo) ---
if(isset($_POST['acao']) && $_POST['acao']==='agendar'){
    $data = $_POST['data'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $tipo_atendimento = $_POST['tipo_atendimento'] ?? ''; // Novo campo
    $status_inicial = 'Agendada';
    
    if($data && $hora && $tipo_atendimento && $psicologo_id){
        // Adicionando 'tipo_atendimento' e 'status'
        $stmt = $conn->prepare("INSERT INTO consultas (paciente_id, psicologo_id, data, hora, tipo_atendimento, status) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("iissss", $paciente_id, $psicologo_id, $data, $hora, $tipo_atendimento, $status_inicial);
        
        if ($stmt->execute()) {
            $mensagem_agenda = "Consulta agendada com sucesso! Tipo: " . htmlspecialchars($tipo_atendimento);
        } else {
            $erro_agenda = "Erro ao agendar: " . $conn->error;
        }
        $stmt->close();
    } else {
        $erro_agenda = "Preencha todos os campos e certifique-se de que há um psicólogo atribuído.";
    }
}

// Verifica mensagens de sucesso/erro após redirecionamento
if (isset($_GET['msg'])) {
    $mensagem_agenda = urldecode($_GET['msg']);
}


// --- BUSCA DAS CONSULTAS (Atualizada) ---
// Adicionando c.status e c.tipo_atendimento ao SELECT
$stmt = $conn->prepare("SELECT c.id, c.data, c.hora, c.status, c.tipo_atendimento, p.nome AS psicologo_nome 
                        FROM consultas c 
                        JOIN psicologos p ON c.psicologo_id = p.id
                        WHERE c.paciente_id=? 
                        ORDER BY c.data, c.hora");
$stmt->bind_param("i",$paciente_id);
$stmt->execute();
$result_consultas = $stmt->get_result();
$consultas = [];
$diasComConsulta = [];
while($row = $result_consultas->fetch_assoc()){
    $consultas[] = $row;
    $dia = date("j", strtotime($row['data']));
    $diasComConsulta[$dia] = true;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Área do Paciente - Mente Conectada</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Roboto',sans-serif;background:#f9f9fb;color:#333;}
a{text-decoration:none;}

.container{display:flex; height:100vh; overflow:hidden;}
.sidebar{
  background: linear-gradient(180deg,#d8b4e2,#bfa0d6);
  width:230px; padding:30px 20px;
  display:flex; flex-direction:column; align-items:center;
  box-shadow:2px 0 8px rgba(0,0,0,0.1); position:relative;
}
.sidebar img{width:120px; margin-bottom:40px;}
.sidebar nav{display:flex; flex-direction:column; gap:12px; width:100%;}
.sidebar-indicator{
  position:absolute; left:0; width:8px; height:40px;
  background: linear-gradient(180deg,#fff,#ffccff); border-radius:4px;
  box-shadow:0 0 12px rgba(255,255,255,0.9); transition:top 0.3s,height 0.3s,box-shadow 0.3s;
  animation:pulse 2s infinite;
}
@keyframes pulse {
  0% {box-shadow:0 0 8px rgba(255,255,255,0.7);}
  50% {box-shadow:0 0 16px rgba(255,255,255,1);}
  100% {box-shadow:0 0 8px rgba(255,255,255,0.7);}
}
.sidebar a{
  display:flex; align-items:center; gap:10px; padding:10px 15px; border-radius:8px;
  color:#fff; font-weight:500; font-size:16px; transition:0.3s; position:relative; z-index:1;
}
.sidebar a:hover, .sidebar a.active{background: rgba(255,255,255,0.25);}

.main{flex:1; display:flex; flex-direction:column; background:#fff; overflow-y:auto;}
.top-bar{display:flex; justify-content:space-between; align-items:center; padding:20px 40px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.05);}
.top-menu{display:flex; gap:20px; position:relative;}
.top-menu a{padding:8px 12px; color:#555; font-weight:500; border-radius:6px; position:relative; transition:all 0.3s;}
.top-menu a.active, .top-menu a:hover{color:#74275f;}
.top-indicator{position:absolute; bottom:0; height:4px; width:60px; background:linear-gradient(90deg,#ff99ff,#cc66ff); border-radius:2px; transition:left 0.3s,width 0.3s; animation:pulseTop 2s infinite;}
@keyframes pulseTop{
  0%{box-shadow:0 0 6px rgba(255,0,255,0.6);}
  50%{box-shadow:0 0 14px rgba(255,0,255,0.9);}
  100%{box-shadow:0 0 6px rgba(255,0,255,0.6);}
}
.login{display:flex;align-items:center; font-size:14px; font-weight:500; color:#555; cursor:pointer; transition: color 0.3s;}
.login img{width:32px; height:32px; border-radius:50%; margin-right:8px;}
.login:hover{color:#6a0dad;}

.content{padding:40px 60px; display:flex; flex-direction:column; gap:40px;}
.content h1{font-family:'Playfair Display', serif; font-size:32px; color:#4a2c6e;}
.content h2{font-size:20px; margin-bottom:15px; color:#333;}
.content p{font-size:16px; color:#555; margin-bottom:12px;}

.diario, .agendamento, .calendario{background:#fff; border-radius:16px; padding:25px; box-shadow:0 6px 20px rgba(0,0,0,0.05);}
/* Estilo para inputs e select */
.diario textarea, 
.diario input, 
.agendamento input,
.agendamento select {
    width:100%; 
    padding:12px; 
    margin-bottom:12px; 
    border-radius:8px; 
    border:1px solid #ccc;
}

.diario button, .agendamento button{padding:12px 20px; border:none; border-radius:8px; background:#74275f; color:white; cursor:pointer; transition:0.3s;}
.diario button:hover, .agendamento button:hover{background:#6a0dad;}
.entrada{border-bottom:1px solid #eee; padding:12px; margin-bottom:10px; position:relative; border-radius:8px; background:#f9f9fb;}
.entrada span.data{font-size:12px; color:#666; display:block; margin-bottom:5px;}
.entrada button{position:absolute; right:10px; top:10px; background:#e0e0e0; border:none; padding:6px 8px; border-radius:6px; cursor:pointer;}
.entrada button:hover{background:#d5d5d5;}

ul{list-style:none; margin-top:10px;}
/* Estilizando a lista de consultas */
ul li{
    background:#f0f0f0; 
    padding:10px; 
    margin-bottom:8px; 
    border-radius:8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
ul li span {
    margin-right: 10px;
}
ul li a {
    white-space: nowrap; /* Não quebrar linha no botão */
}


footer{background:#bfa0d6;color:white;text-align:center;padding:1.5rem;font-weight:600;box-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.1);}
</style>
</head>
<body>
<div class="container">
<aside class="sidebar">
  <img src="img/logo.png.png" alt="Logo Mente Conectada">
  <nav>
    <a href="#diario" class="active"><span>📝</span>Diário</a>
    <a href="#calendario"><span>📅</span>Calendário</a>
    <a href="#agendamento"><span>📌</span>Agendamento</a>
    <a href="logout.php"><span>🚪</span>Sair</a>
  </nav>
  <div class="sidebar-indicator"></div>
</aside>

<main class="main">
<div class="top-bar">
  <div class="top-menu">
    <a href="#diario" class="active">Diário</a>
    <a href="#calendario">Calendário</a>
    <a href="#agendamento">Agendamento</a>
    <div class="top-indicator"></div>
  </div>
  <div class="login">
    <img src="img/login.jpg" alt="Usuário">
    <span><?php echo htmlspecialchars($paciente_nome); ?></span>
  </div>
</div>

<div class="content">
  <section class="diario" id="diario">
    <h1>Diário Emocional</h1>
    <form method="POST">
      <textarea name="texto" placeholder="Escreva como se sente hoje..." required></textarea>
      <input type="hidden" name="acao" value="adicionar">
      <button type="submit">Adicionar Entrada</button>
    </form>

    <h2>Entradas</h2>
    <?php foreach($entradasPorDia as $dia => $entradasDia): ?>
      <?php foreach($entradasDia as $e): ?>
        <div class="entrada">
          <span class="data"><?php echo date("d/m/Y H:i", strtotime($e['data'])); ?></span>
          <?php echo htmlspecialchars($e['texto']); ?>
          <a href="paciente.php?deletar=<?php echo $e['id']; ?>"><button>Excluir</button></a>
          <button onclick="editarEntrada('<?php echo $e['id']; ?>','<?php echo htmlspecialchars(addslashes($e['texto'])); ?>')">Editar</button>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </section>

  <section class="calendario" id="calendario">
    <h1>Calendário</h1>
    <table>
      <tr><th>Dom</th><th>Seg</th><th>Ter</th><th>Qua</th><th>Qui</th><th>Sex</th><th>Sáb</th></tr>
      <?php
      $diaContador = 1;
      $primeiroDiaMes = date("w", strtotime("$ano_atual-$mes_atual-01"));
      $ultimoDiaMes = date("t", strtotime("$ano_atual-$mes_atual-01"));
      for($i=0;$i<6;$i++){
          echo "<tr>";
          for($j=0;$j<7;$j++){
              if($i===0 && $j<$primeiroDiaMes || $diaContador>$ultimoDiaMes){
                  echo "<td></td>";
              } else {
                  $classe = "";
                  if(isset($entradasPorDia[$diaContador]) && isset($diasComConsulta[$diaContador])){
                      $classe = "entrada"; $icon="📝📅";
                  }elseif(isset($entradasPorDia[$diaContador])){
                      $classe = "entrada"; $icon="📝";
                  }elseif(isset($diasComConsulta[$diaContador])){
                      $classe = "entrada"; $icon="📅";
                  }else{ $icon=''; }
                  echo '<td class="'.$classe.'" onclick="verEntradas('.$diaContador.')">'.$diaContador;
                  if($icon) echo ' <span>'.$icon.'</span>';
                  echo '</td>';
                  $diaContador++;
              }
          }
          echo "</tr>";
      }
      ?>
    </table>
  </section>

  <section class="agendamento" id="agendamento">
    <h1>Agendar Consulta</h1>
    <?php if(isset($mensagem_agenda)) echo "<p style='color:green; font-weight: bold;'>$mensagem_agenda</p>"; ?>
    <?php if(isset($erro_agenda)) echo "<p style='color:red; font-weight: bold;'>$erro_agenda</p>"; ?>
    <form method="POST">
      <label>Data:</label>
      <input type="date" name="data" required>
      <label>Hora:</label>
      <input type="time" name="hora" required>
      
      <label>Tipo de Atendimento:</label>
      <select name="tipo_atendimento" required>
        <option value="">Selecione o Tipo</option>
        <option value="Presencial">Presencial</option>
        <option value="Online">Online</option>
        <option value="Grupo">Grupo</option>
      </select>
      
      <input type="hidden" name="acao" value="agendar">
      <button type="submit">Agendar</button>
    </form>

    <h2>Consultas Agendadas</h2>
    <?php if(count($consultas)>0): ?>
      <ul>
      <?php foreach($consultas as $c): ?>
        <li>
            <?php 
                $data_hora_consulta = $c['data'] . ' ' . $c['hora'];
                $passou = (strtotime($data_hora_consulta) < time());
                
                // Determina a cor do status
                $status_atual = $c['status'] ?? 'Agendada';
                $cor_status = '#006600'; // Verde para Agendada
                
                if ($status_atual == 'Cancelada') {
                    $cor_status = '#b30000'; // Vermelho para Cancelada
                } elseif ($passou) {
                    $cor_status = '#777777'; // Cinza para Finalizada/Passada
                    $status_atual = 'Finalizada'; 
                }
            ?>
            <span style="font-weight: bold;">
                <?php echo date("d/m/Y", strtotime($c['data']))." às ".$c['hora']; ?>
            </span>
            <span>com <?php echo htmlspecialchars($c['psicologo_nome']); ?> 
            (<?php echo htmlspecialchars($c['tipo_atendimento']); ?>)</span>
            
            <span style="font-weight: bold; color: <?php echo $cor_status; ?>;">
                Status: <?php echo htmlspecialchars($status_atual); ?>
            </span>
            
            <?php if ($status_atual != 'Cancelada' && !$passou): ?>
                <a href="paciente.php?acao=cancelar_consulta&id=<?php echo $c['id']; ?>" 
                   onclick="return confirm('Deseja realmente desmarcar esta consulta? Esta ação não pode ser desfeita.')"
                   style="color: white; background-color: #e74c3c; padding: 5px 10px; border-radius: 5px; margin-left: 15px; font-weight: 500;">
                    <i class="fa-solid fa-ban"></i> Desmarcar
                </a>
            <?php endif; ?>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>Nenhuma consulta agendada.</p>
    <?php endif; ?>
  </section>
</div>
</main>
</div>

<div class="modal" id="modalEdit" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;">
<div class="modal-content" style="background:white;padding:25px;border-radius:16px;width:350px;max-height:450px;overflow-y:auto;">
<h3>Editar Entrada</h3>
<form method="POST">
<textarea id="modalText" name="texto" required style="width:100%;height:100px;border-radius:10px;border:1px solid #ccc;padding:10px;"></textarea>
<input type="hidden" name="id" id="modalId">
<input type="hidden" name="acao" value="editar">
<button type="submit">Salvar</button>
<button type="button" onclick="fecharModal()">Cancelar</button>
</form>
</div>
</div>

<script>
function editarEntrada(id,texto){
    document.getElementById('modalEdit').style.display='flex';
    document.getElementById('modalText').value = texto;
    document.getElementById('modalId').value = id;
}
function fecharModal(){ document.getElementById('modalEdit').style.display='none'; }

function verEntradas(dia){
    let mensagens = '';
    <?php
    foreach($entradasPorDia as $d => $entradasDia){
        foreach($entradasDia as $e){
            $textoJS = addslashes($e['texto']);
            echo "if(dia==$d){mensagens+='".$textoJS."\\n';}\n";
        }
    }
    ?>
    if(mensagens) alert(mensagens);
}

// Sidebar indicator
const sidebar = document.querySelector('.sidebar nav');
const links = sidebar.querySelectorAll('a');
const indicator = document.querySelector('.sidebar-indicator');
function updateSidebarIndicator(link){ indicator.style.top = link.offsetTop + 'px'; indicator.style.height = link.offsetHeight + 'px'; }
updateSidebarIndicator(sidebar.querySelector('a.active'));
links.forEach(link => {
  link.addEventListener('mouseover', () => updateSidebarIndicator(link));
  link.addEventListener('mouseout', () => updateSidebarIndicator(sidebar.querySelector('a.active')));
  link.addEventListener('click', () => {
    links.forEach(l => l.classList.remove('active'));
    link.classList.add('active');
    updateSidebarIndicator(link);
  });
});
</script>
</body>
</html>