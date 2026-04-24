<?php
session_start();
// Verifica se a sessão do psicólogo está definida. Se não estiver, redireciona para o login.
// Alterado para 'psicologo_id' para garantir que é a sessão correta (Admin/Psicólogo)
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

$psicologo_id = $_SESSION['psicologo_id'];
$hoje = date('Y-m-d'); // Data atual no formato YYYY-MM-DD

// --- 1. BUSCA DADOS DO PSICÓLOGO LOGADO (Para Topo e Cards Individuais) ---
$sqlPsicologo = "SELECT * FROM psicologos WHERE id = ?";
$stmtPsicologo = $conn->prepare($sqlPsicologo);
$stmtPsicologo->bind_param("i", $psicologo_id);
$stmtPsicologo->execute();
$resultPsicologo = $stmtPsicologo->get_result();
$psicologo = $resultPsicologo->fetch_assoc();
$stmtPsicologo->close();

// --- 2. CONTA TODOS OS PACIENTES ATIVOS NO SITE (Card Total Geral) ---
$sqlCountTotal = "SELECT COUNT(*) AS total FROM pacientes";
$resultCountTotal = $conn->query($sqlCountTotal);
$totalPacientes = $resultCountTotal->fetch_assoc()['total'];

// --- 3. CONTA CONSULTAS DE HOJE (Inclui Agendada E Reagendada - VISÃO GERAL) ---
$sqlConsultasHojeCount = "SELECT COUNT(*) AS total FROM consultas WHERE data = ? AND status IN ('Agendada', 'Reagendada')";
$stmtConsultasHojeCount = $conn->prepare($sqlConsultasHojeCount);
$stmtConsultasHojeCount->bind_param("s", $hoje); 
$stmtConsultasHojeCount->execute();
$resultConsultasHojeCount = $stmtConsultasHojeCount->get_result();
$totalConsultasHoje = $resultConsultasHojeCount->fetch_assoc()['total'];
$stmtConsultasHojeCount->close();

// --- 3b. BUSCA CONSULTAS DE HOJE DETALHADAS (Inclui Agendada E Reagendada) ---
$sqlConsultasHojeDetalhe = "SELECT 
    c.id AS consulta_id, 
    c.hora, 
    c.status, 
    p.nome AS nome_paciente, 
    p.telefone,
    ps.nome AS nome_psicologo 
FROM 
    consultas c
JOIN 
    pacientes p ON c.paciente_id = p.id
JOIN
    psicologos ps ON c.psicologo_id = ps.id
WHERE 
    c.data = ? 
    AND c.status IN ('Agendada', 'Reagendada')
ORDER BY 
    ps.nome, c.hora"; 

$stmtConsultasHojeDetalhe = $conn->prepare($sqlConsultasHojeDetalhe);
$stmtConsultasHojeDetalhe->bind_param("s", $hoje); 
$stmtConsultasHojeDetalhe->execute();
$resultConsultasHojeDetalhe = $stmtConsultasHojeDetalhe->get_result();
$stmtConsultasHojeDetalhe->close(); 

// --- 4. BUSCA TODOS OS PACIENTES (Lista Geral - Inclui nome do Psicólogo) ---
$sqlPacientesTodos = "SELECT 
    p.nome AS nome_paciente, 
    p.email, 
    p.telefone, 
    p.idade, 
    p.criado_em,
    ps.nome AS nome_psicologo
FROM 
    pacientes p
JOIN 
    psicologos ps ON p.psicologo_id = ps.id
ORDER BY 
    ps.nome, p.nome";

$resultPacientesTodos = $conn->query($sqlPacientesTodos);

// --- 5. BUSCA TODOS OS PSICÓLOGOS (Lista Geral - INCLUI VALOR) ---
$sqlTodosPsicologos = "SELECT nome, email, especialidade, valor_consulta FROM psicologos ORDER BY nome";
$resultTodosPsicologos = $conn->query($sqlTodosPsicologos);

// --- 6. BUSCA O FATURAMENTO TOTAL POTENCIAL DO SISTEMA ---
$sqlFaturamentoTotal = "
    SELECT SUM(p.valor_consulta) AS total_faturamento 
    FROM consultas c
    JOIN psicologos p ON c.psicologo_id = p.id
";
$resultFaturamentoTotal = $conn->query($sqlFaturamentoTotal);
$faturamentoTotalSistema = $resultFaturamentoTotal->fetch_assoc()['total_faturamento'] ?? 0;
$faturamentoTotalSistemaFormatado = number_format($faturamentoTotalSistema, 2, ',', '.');


// --- 7. BUSCA TODAS AS ESPECIALIDADES ÚNICAS (Para o Card) ---
$sqlEspecialidadesUnicas = "SELECT DISTINCT especialidade FROM psicologos ORDER BY especialidade";
$resultEspecialidadesUnicas = $conn->query($sqlEspecialidadesUnicas);
$especialidades = [];
while ($row = $resultEspecialidadesUnicas->fetch_assoc()) {
    $especialidades[] = htmlspecialchars($row['especialidade']);
}
$listaEspecialidades = implode(', ', $especialidades);


// =======================================================
// --- NOVO: BUSCA DE ENTRADAS DO DIÁRIO DO PSICÓLOGO LOGADO ---
// Filtra apenas as entradas dos pacientes do psicólogo logado ($psicologo_id)
// =======================================================
$sqlDiario = "
    SELECT 
        d.id,
        d.texto,
        d.data,
        p.nome AS nome_paciente
    FROM 
        diario d
    JOIN 
        pacientes p ON d.paciente_id = p.id
    WHERE
        p.psicologo_id = ? 
    ORDER BY 
        d.data DESC
    LIMIT 100; -- Limita para não sobrecarregar
";

$stmtDiario = $conn->prepare($sqlDiario);
$stmtDiario->bind_param("i", $psicologo_id); 
$stmtDiario->execute();
$resultDiario = $stmtDiario->get_result();
$stmtDiario->close();


// =======================================================
// --- NOVO: BUSCA DE DADOS PARA GRÁFICOS (DINÂMICO) ---
// =======================================================

// --- Gráfico 1: DADOS PARA GRÁFICO DE BARRAS (Sessões por Dia da Semana: Seg a Sex) ---
// DAYOFWEEK(data): 1=Domingo, 2=Segunda, ..., 6=Sexta, 7=Sábado (MySQL)
$sqlSessoesPorDia = "
    SELECT 
        DAYOFWEEK(data) AS dia_semana, 
        COUNT(*) AS total_sessoes 
    FROM consultas
    WHERE status IN ('Agendada', 'Reagendada')
    GROUP BY dia_semana
    ORDER BY dia_semana
";
$resultSessoesPorDia = $conn->query($sqlSessoesPorDia);

// Inicializa com 0 para todos os 7 dias
$sessoesPorDia = array_fill(1, 7, 0); 
while($row = $resultSessoesPorDia->fetch_assoc()) {
    $sessoesPorDia[$row['dia_semana']] = (int)$row['total_sessoes'];
}

// Pega os dados dos dias 2 a 6 (Segunda a Sexta)
$dadosBarChart = array(
    $sessoesPorDia[2] ?? 0, // Segunda
    $sessoesPorDia[3] ?? 0, // Terça
    $sessoesPorDia[4] ?? 0, // Quarta
    $sessoesPorDia[5] ?? 0, // Quinta
    $sessoesPorDia[6] ?? 0  // Sexta
);

// --- Gráfico 2: DADOS PARA GRÁFICO DE PIZZA (Distribuição por Tipo de Atendimento) ---
$sqlTiposAtendimento = "
    SELECT 
        tipo_atendimento, 
        COUNT(*) AS total_consultas 
    FROM consultas
    WHERE status IN ('Agendada', 'Reagendada')
    GROUP BY tipo_atendimento
    ORDER BY total_consultas DESC
";
$resultTiposAtendimento = $conn->query($sqlTiposAtendimento);

$labelsPieChart = [];
$dadosPieChart = [];
// Cores inspiradas no seu layout: Roxo Principal, Roxo Médio, Roxo Claro
$coresPieChart = ['#6a0dad', '#a46de0', '#d3b4f3', '#c49be2', '#7a1fa2']; 
$coresFinaisPieChart = [];

$i = 0;
while($row = $resultTiposAtendimento->fetch_assoc()) {
    $labelsPieChart[] = htmlspecialchars($row['tipo_atendimento']);
    $dadosPieChart[] = (int)$row['total_consultas'];
    $coresFinaisPieChart[] = $coresPieChart[$i % count($coresPieChart)]; 
    $i++;
}

// Fecha a conexão após todas as consultas
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mente Conectada - Dashboard Psicólogo</title>

  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

  <style>
    * {margin:0; padding:0; box-sizing:border-box;}
    a {text-decoration:none;}
    body {
      font-family:'Roboto',sans-serif;
      background:#f9f9fb;
      color:#333;
      display:flex;
      flex-direction:column;
      min-height:100vh;
    }

    .container { flex:1; display:flex; overflow:hidden; }

    /* === SIDEBAR === */
    .sidebar {
      background:linear-gradient(180deg,#c49be2,#8a4fcf);
      width:230px;
      padding:30px 20px;
      display:flex;
      flex-direction:column;
      align-items:center;
      box-shadow:2px 0 8px rgba(0,0,0,0.1);
      position:relative;
    }

    .sidebar img {
      width:120px;
      margin-bottom:40px;
    }

    .sidebar nav {
      display:flex;
      flex-direction:column;
      gap:12px;
      width:100%;
      position:relative;
    }

    .sidebar a {
      display:flex;
      align-items:center;
      gap:10px;
      padding:10px 15px;
      border-radius:8px;
      color:#fff;
      font-weight:500;
      font-size:16px;
      transition:background 0.3s;
    }

    .sidebar a:hover { background:rgba(255,255,255,0.25); }
    .sidebar a.active { background:rgba(255,255,255,0.3); }

    /* === BOTÕES DA SIDEBAR === */
    #acessibilidade-btn, #logout-btn {
      width:100%;
      margin-top:10px;
      background:#fff;
      color:#7a1fa2;
      font-weight:600;
      font-size:15px;
      padding:12px 20px;
      border:none;
      border-radius:25px;
      cursor:pointer;
      transition:all 0.3s ease;
      box-shadow:0 3px 8px rgba(0,0,0,0.15);
      display:flex;
      align-items:center;
      justify-content:center;
      gap:8px;
    }

    #acessibilidade-btn:hover, #logout-btn:hover {
      background:#7a1fa2;
      color:#fff;
      transform:translateY(-2px);
      box-shadow:0 4px 12px rgba(0,0,0,0.25);
    }

    #acessibilidade-btn i, #logout-btn i {
      font-size:18px;
    }

    /* === CONTEÚDO === */
    .main { 
      flex:1; 
      display:flex; 
      flex-direction:column; 
      background:#fff; 
      overflow-y:auto;
    }

    .top-bar {
      display:flex; 
      justify-content:space-between; 
      align-items:center;
      padding:20px 40px; 
      background:#fff;
      box-shadow:0 2px 8px rgba(0,0,0,0.05);
    }

    .login {
      display:flex; 
      align-items:center;
      font-size:14px; 
      font-weight:500; 
      color:#555;
    }

    .login img { 
      width:32px; 
      height:32px; 
      border-radius:50%; 
      margin-right:8px; 
    }

    .dashboard { 
      padding:40px 60px; 
      display:flex; 
      flex-direction:column; 
      gap:30px; 
    }

    .dashboard h1 { 
      font-family:'Playfair Display',serif; 
      font-size:32px; 
      color:#4a2c6e; 
      margin-bottom:10px; 
    }

    .cards {
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
      gap:20px;
    }

    .card {
      background:linear-gradient(145deg,#ffffff,#f3e8fa);
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.08);
      padding:20px;
      transition:transform 0.3s,box-shadow 0.3s;
    }

    .card:hover { 
      transform:translateY(-5px); 
      box-shadow:0 8px 18px rgba(0,0,0,0.12); 
    }

    .card i { 
      font-size:26px; 
      color:#8a5ec4; 
      margin-bottom:12px; 
    }

    .card h3 { 
      font-size:18px; 
      margin-bottom:6px; 
      color:#4a2c6e; 
    }

    .table-section {
      background:#f4f0f8; 
      padding:25px;
      border-radius:12px; 
      box-shadow:0 4px 10px rgba(0,0,0,0.05);
      overflow-x:auto;
    }

    .table-section h2 { 
      font-size:22px; 
      color:#6b409f;
      margin-bottom:15px; 
    }

    table { 
      width:100%; 
      border-collapse:collapse; 
      background:#fff; 
      border-radius:8px; 
      overflow:hidden; 
    }

    th, td { 
      padding:12px 16px; 
      text-align:left; 
      border-bottom:1px solid #eee; 
    }

    th { 
      background:#e9d7f4; 
      color:#4a2c6e; 
    }

    /* CSS para Ações de Consulta */
    .btn-acao {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        margin-right: 5px;
        transition: background-color 0.3s, transform 0.2s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        color: white; /* Cor do texto branco */
        text-decoration: none; /* Remove o sublinhado */
    }

    .btn-acao:active {
        transform: translateY(1px);
    }

    .btn-cancelar {
        background-color: #e74c3c;
        color: white;
        border: none;
    }

    .btn-cancelar:hover {
        background-color: #c0392b;
    }

    .btn-reagendar {
        background-color: #f39c12;
        color: white;
        border: none;
    }

    .btn-reagendar:hover {
        background-color: #e67e22;
    }

    .btn-acao i {
        font-size: 14px;
    }
    
    /* NOVO: ESTILOS PARA O DIÁRIO EM CAIXAS */
    .diary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); /* Cria colunas flexíveis */
        gap: 20px;
        margin-top: 20px;
    }

    .diary-box {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        padding: 20px;
        text-align: center;
        transition: transform 0.3s;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        min-height: 150px; /* Garante um formato de caixa */
    }

    .diary-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.15);
    }

    .diary-box h4 {
        font-size: 18px;
        color: #4a2c6e;
        margin-bottom: 5px;
        font-family: 'Playfair Display', serif;
    }

    .diary-box small {
        font-size: 12px;
        color: #777;
        margin-bottom: 15px;
        display: block;
    }


    .charts {
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
      gap:25px;
      background:#f4f0f8;
      padding:25px;
      border-radius:12px;
    }

    .chart-card {
      background:#fff;
      border-radius:10px;
      padding:20px;
      box-shadow:0 4px 10px rgba(0,0,0,0.05);
    }

    #downloadPDF {
      background:#6a0dad;
      color:white;
      padding:10px 20px;
      border:none;
      border-radius:6px;
      cursor:pointer;
      font-weight:500;
      transition:background 0.3s;
      margin-top:10px;
    }
    #downloadPDF:hover { background:#4a067c; }

    /* === MODO ROXO ESCURO === */
    body.modo-roxo {
      background:#e2c6f5;
    }
    body.modo-roxo .sidebar { 
      background:linear-gradient(180deg,#7a1fa2,#5b0888); }
    body.modo-roxo .card { background:linear-gradient(145deg,#f3d6fa,#e0bff3); }
    body.modo-roxo th { background:#b37bd6; color:#fff; }
    body.modo-roxo #acessibilidade-btn,
    body.modo-roxo #logout-btn {
      background:#fff;
      color:#4a067c;
    }
    body.modo-roxo #acessibilidade-btn:hover,
    body.modo-roxo #logout-btn:hover {
      background:#4a067c;
      color:#fff;
    }

    footer {
      background:#f5eefb; 
      text-align:center;
      padding:10px; 
      color:#5b2a83; 
      font-size:14px;
      font-weight:500; 
      box-shadow:0 -2px 6px rgba(0,0,0,0.05);
      margin-top:auto;
    }
  </style>
</head>
<body>

  <div class="container">
    <aside class="sidebar">
      <img src="img/logo.png.png" alt="Logo Mente Conectada">
      <button id="acessibilidade-btn"><i class="fa-solid fa-universal-access"></i> Modo Acessível</button>
      <button id="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Sair</button>
    </aside>

    <main class="main">
      <div class="top-bar">
        <h2 style="color:#6a0dad;">Painel Administrativo</h2>
        <div class="login">
          <img src="img/psicologo.png" alt="Foto Psicólogo">
          <span><?php echo htmlspecialchars($psicologo['nome']); ?> (Admin)</span>
        </div>
      </div>

      <section class="dashboard">
        <h1>Visão Geral do Sistema 👋</h1>

        <div class="cards">
          <div class="card"><i class="fa-solid fa-user-group"></i><h3>Total de Pacientes</h3><p><?php echo $totalPacientes; ?></p></div>
          <div class="card"><i class="fa-solid fa-calendar-check"></i><h3>Total Consultas Hoje</h3><p><?php echo $totalConsultasHoje; ?></p></div>
          <div class="card"><i class="fa-solid fa-hand-holding-usd"></i><h3>Faturamento Total</h3><p>R$ <?php echo $faturamentoTotalSistemaFormatado; ?></p></div>
          <div class="card"><i class="fa-solid fa-star"></i><h3>Especialidades no Sistema</h3><p><?php echo $listaEspecialidades; ?></p></div>
        </div>

        <div class="table-section">
          <h2>Consultas Ativas para Hoje (Visão Geral - <?php echo date('d/m/Y'); ?>)</h2>
          <p style="margin-bottom: 15px; font-size: 14px; color: #777;">Mostrando consultas com status 'Agendada' ou 'Reagendada'.</p>
          <table class="consultas-hoje-table">
            <thead>
              <tr>
                <th>Hora</th>
                <th>Paciente</th>
                <th>Psicólogo</th>
                <th>Telefone</th>
                <th>Status</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultConsultasHojeDetalhe->num_rows > 0): ?>
                  <?php while($consulta = $resultConsultasHojeDetalhe->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo date('H:i', strtotime($consulta['hora'])); ?></td>
                    <td><?php echo htmlspecialchars($consulta['nome_paciente']); ?></td>
                    <td><?php echo htmlspecialchars($consulta['nome_psicologo']); ?></td>
                    <td><?php echo htmlspecialchars($consulta['telefone']); ?></td>
                    <td><?php echo htmlspecialchars($consulta['status'] ?? 'N/A'); ?></td>
                    <td>
                      <a href="processa_consulta.php?action=cancelar&id=<?php echo $consulta['consulta_id']; ?>" class="btn-acao btn-cancelar" onclick="return confirm('Deseja realmente desmarcar esta consulta?')"><i class="fa-solid fa-ban"></i> Desmarcar</a>
                      <a href="reagendar.php?id=<?php echo $consulta['consulta_id']; ?>" class="btn-acao btn-reagendar"><i class="fa-solid fa-calendar-day"></i> Reagendar</a>
                    </td>
                  </tr>
                  <?php endwhile; ?>
              <?php else: ?>
                  <tr>
                    <td colspan="6">Nenhuma consulta agendada ou reagendada para hoje.</td>
                  </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="table-section">
          <h2>Lista de Todos os Pacientes Cadastrados</h2>
          <p style="margin-bottom: 15px; font-size: 14px; color: #777;">Pacientes de todos os psicólogos do sistema.</p>
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>Psicólogo Responsável</th>
                <th>Email</th>
                <th>Telefone</th>
                <th>Idade</th>
                <th>Cadastrado em</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultPacientesTodos->num_rows > 0): ?>
                  <?php while($paciente = $resultPacientesTodos->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($paciente['nome_paciente']); ?></td>
                    <td><?php echo htmlspecialchars($paciente['nome_psicologo']); ?></td>
                    <td><?php echo htmlspecialchars($paciente['email']); ?></td>
                    <td><?php echo htmlspecialchars($paciente['telefone']); ?></td>
                    <td><?php echo htmlspecialchars($paciente['idade']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($paciente['criado_em'])); ?></td>
                  </tr>
                  <?php endwhile; ?>
              <?php else: ?>
                  <tr>
                    <td colspan="6">Nenhum paciente encontrado no sistema.</td>
                  </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="table-section">
          <h2>Lista de Todos os Psicólogos</h2>
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>Email</th>
                <th>Especialidade</th>
                <th>Valor Consulta</th> </tr>
            </thead>
            <tbody>
              <?php if ($resultTodosPsicologos->num_rows > 0): ?>
                <?php while($outroPsicologo = $resultTodosPsicologos->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($outroPsicologo['nome']); ?></td>
                    <td><?php echo htmlspecialchars($outroPsicologo['email']); ?></td>
                    <td><?php echo htmlspecialchars($outroPsicologo['especialidade']); ?></td>
                    <td>R$ <?php echo number_format($outroPsicologo['valor_consulta'] ?? 0, 2, ',', '.'); ?></td> </tr>
                <?php endwhile; ?>
              <?php else: ?>
                  <tr>
                      <td colspan="4">Nenhum psicólogo encontrado no sistema.</td>
                  </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        
        <div class="table-section">
          <h2>Diário Emocional dos Seus Pacientes</h2>
          <p style="margin-bottom: 15px; font-size: 14px; color: #777;">Clique no botão "Ver Entrada" para ler o diário completo.</p>
          
          <div class="diary-grid"> 
              <?php if ($resultDiario->num_rows > 0): ?>
                  <?php while($entrada = $resultDiario->fetch_assoc()): 
                    // Preparação segura do texto do diário para ser passado ao JavaScript
                    // Escapa aspas para JS e converte quebras de linha para \n
                    $texto_js_safe = addslashes(str_replace("\n", '\\n', htmlspecialchars($entrada['texto'], ENT_QUOTES)));
                  ?>
                  <div class="diary-box"> 
                      <h4><?php echo htmlspecialchars($entrada['nome_paciente']); ?></h4>
                      <small><?php echo date('d/m/Y H:i', strtotime($entrada['data'])); ?></small>
                      
                      <a href="#" onclick="mostrarDiario('<?php echo htmlspecialchars($entrada['nome_paciente'], ENT_QUOTES); ?>', '<?php echo $texto_js_safe; ?>'); return false;" class="btn-acao" style="background-color: #6a0dad;">
                          <i class="fa-solid fa-book-open"></i> Ver Entrada
                      </a>
                  </div>
                  <?php endwhile; ?>
              <?php else: ?>
                  <p style="text-align: center; color: #555; padding: 20px 0;">Nenhuma entrada de diário encontrada dos seus pacientes.</p>
              <?php endif; ?>
          </div>
          
        </div>
        <div class="charts">
          <div class="chart-card">
            <h3>Distribuição de Sessões (Seg-Sex)</h3>
            <canvas id="barChart"></canvas>
          </div>
          <div class="chart-card">
            <h3>Tipos de Atendimento</h3>
            <canvas id="pieChart"></canvas>
          </div>
        </div>

        <button id="downloadPDF"><i class="fa-solid fa-file-pdf"></i> Salvar Gráficos em PDF</button>
      </section>
    </main>
  </div>

  <footer>© 2025 Mente Conectada - Todos os direitos reservados.</footer>

  <script>
    document.getElementById('acessibilidade-btn').onclick = () => {
      document.body.classList.toggle('modo-roxo');
    };
    document.getElementById('logout-btn').onclick = () => {
      if (confirm('Tem certeza que deseja sair?')) {
        // Altere 'login_psicologo.php' se o arquivo de logout for outro
        window.location.href = 'login_psicologo.php'; 
      }
    };

    const barCtx = document.getElementById('barChart').getContext('2d');
    const pieCtx = document.getElementById('pieChart').getContext('2d');

    // Dados dinâmicos para o Gráfico de Barras (Sessões por Dia)
    new Chart(barCtx, {
      type: 'bar',
      data: {
        labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex'],
        datasets: [{ 
            label: 'Sessões por Dia (Agendadas/Reagendadas)', 
            // DADOS DINÂMICOS VINDOS DO PHP
            data: <?php echo json_encode($dadosBarChart); ?>, 
            backgroundColor: '#8a4fcf' 
        }]
      },
      options: {
        scales: {
            y: { 
                beginAtZero: true, 
                ticks: { precision: 0 } 
            }
        }
      }
    });

    // Dados dinâmicos para o Gráfico de Pizza (Tipos de Atendimento)
    new Chart(pieCtx, {
      type: 'pie',
      data: {
        // DADOS DINÂMICOS: LABELS (Tipos de Atendimento: Online, Presencial, Grupo)
        labels: <?php echo json_encode($labelsPieChart); ?>,
        datasets: [{ 
            // DADOS DINÂMICOS: CONTAGEM DE CONSULTAS POR TIPO
            data: <?php echo json_encode($dadosPieChart); ?>, 
            // DADOS DINÂMICOS: CORES
            backgroundColor: <?php echo json_encode($coresFinaisPieChart); ?>
        }]
      }
    });

    // Função para gerar PDF dos Gráficos
    document.getElementById('downloadPDF').onclick = async () => {
      const { jsPDF } = window.jspdf;
      const pdf = new jsPDF();
      const bar = document.getElementById('barChart');
      const pie = document.getElementById('pieChart');
      pdf.text("Relatório de Gráficos - Mente Conectada", 20, 20);
      pdf.addImage(bar.toDataURL('image/png'), 'PNG', 15, 30, 180, 80);
      pdf.addImage(pie.toDataURL('image/png'), 'PNG', 15, 120, 180, 80);
      pdf.save('graficos_mente_conectada.pdf');
    };

    // =======================================================
    // --- NOVA FUNÇÃO: EXIBIR DIÁRIO DO PACIENTE (POR CLICK) ---
    // =======================================================
    function mostrarDiario(nome, texto) {
        // Formata o texto para exibir quebras de linha corretamente no alert
        let textoFormatado = texto.replace(/\\n/g, '\n');
        
        let dataHora = new Date().toLocaleDateString('pt-BR') + ' ' + new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
        let titulo = `Diário de ${nome} (Consulta em ${dataHora})`;
        
        // Exibe o texto completo do diário na tela
        alert(`--- ${titulo} ---\n\n${textoFormatado}`);
    }
    
  </script>
</body>
</html>