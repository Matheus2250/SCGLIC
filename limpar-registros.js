// limpar-registros.js
const sqlite3 = require('sqlite3').verbose();
const path = require('path');

// Conectar ao banco de dados
const db = new sqlite3.Database(path.join(__dirname, 'database.db'), (err) => {
  if (err) {
    console.error('Erro ao conectar ao banco de dados:', err.message);
    process.exit(1);
  }
  console.log('Conectado ao banco de dados SQLite');
  
  // Iniciar transação
  db.serialize(() => {
    // Confirmar o número de registros antes da limpeza
    db.get('SELECT COUNT(*) as count FROM registros', (err, row) => {
      if (err) {
        console.error('Erro ao contar registros:', err.message);
        db.close();
        return;
      }
      
      const countBefore = row.count;
      console.log(`Total de registros antes da limpeza: ${countBefore}`);
      
      // Pedir confirmação ao usuário para prosseguir
      console.log('\nATENÇÃO: Esta operação excluirá TODOS os registros do sistema.');
      console.log('Se quiser prosseguir, digite SIM em maiúsculas e pressione Enter:');
      
      // Variável global para armazenar a entrada do usuário
      let confirmInput = '';
      
      process.stdin.setEncoding('utf8');
      process.stdin.on('data', (data) => {
        confirmInput = data.trim();
        
        if (confirmInput === 'SIM') {
          // Excluir todos os registros
          db.run('DELETE FROM registros', function(err) {
            if (err) {
              console.error('Erro ao excluir registros:', err.message);
            } else {
              console.log(`SUCESSO: ${this.changes} registros foram excluídos.`);
            }
            
            // Resetar o contador de autoincremento
            db.run('DELETE FROM sqlite_sequence WHERE name = "registros"', function(err) {
              if (err) {
                console.error('Erro ao resetar sequência:', err.message);
              } else {
                console.log('Contador de ID foi resetado.');
              }
              
              // Confirmar que a tabela está vazia
              db.get('SELECT COUNT(*) as count FROM registros', (err, row) => {
                if (err) {
                  console.error('Erro ao verificar limpeza:', err.message);
                } else {
                  console.log(`Total de registros após limpeza: ${row.count}`);
                  console.log('\nLimpeza concluída com sucesso!');
                }
                
                // Fechar a conexão
                db.close(() => {
                  process.exit(0);
                });
              });
            });
          });
        } else {
          console.log('Operação cancelada pelo usuário.');
          db.close(() => {
            process.exit(0);
          });
        }
      });
    });
  });
});