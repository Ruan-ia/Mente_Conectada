<?php include 'header.php'; ?> <!-- cabeçalho com menu superior -->
<?php include 'sidebar.php'; ?> <!-- menu lateral -->

<main style="padding: 20px; flex: 1;">
  <h1>Agendar Sessão</h1>

  <form action="salvar_agendamento.php" method="POST" class="form-agendamento">
    <label for="paciente">Nome do Paciente:</label>
    <input type="text" id="paciente" name="paciente" required>

    <label for="psicologo">Psicólogo:</label>3'''
    <select id="psicologo" name="psicologo" required>
      <option value="">Selecione...</option>
      <option value="Dra. Ana Silva">Dra. Ana Silva</option>
      <option value="Dr. João Pereira">Dr. João Pereira</option>
      <option value="Dra. Mariana Costa">Dra. Mariana Costa</option>
    </select>

    <label for="data">Data:</label>
    <input type="date" id="data" name="data" required>

    <label for="hora">Horário:</label>
    <input type="time" id="hora" name="hora" required>

    <button type="submit">Agendar</button>
  </form>
</main>

<?php include 'footer.php'; ?> <!-- rodapé (se você tiver) -->
