const fs = require('fs');
const path = require('path');

const caminhoJson = path.join(__dirname, 'fotos-faltantes.json');
const pastaBase = path.join(__dirname, 'fotos-faltantes');

try {
  // 1. Lê o arquivo JSON
  const conteudo = fs.readFileSync(caminhoJson, 'utf8');
  let dados = JSON.parse(conteudo);

  // --- TRATAMENTO DE FORMATO DO JSON ---
  if (!Array.isArray(dados)) {
    if (dados && typeof dados === 'object' && dados.id !== undefined) {
      // Caso 1: O arquivo é um único objeto { id: 1, ... }
      dados = [dados];
    } else if (typeof dados === 'object') {
      // Caso 2: O array está dentro de uma chave do objeto (ex: { "data": [...] })
      const chaveLista = Object.keys(dados).find(key => Array.isArray(dados[key]));
      if (chaveLista) {
        dados = dados[chaveLista];
      } else {
        console.error('❌ Não foi encontrada nenhuma lista/array no seu arquivo JSON.');
        console.log('Estrutura recebida:', dados);
        process.exit(1);
      }
    }
  }

  console.log(`🔍 Iniciando verificação de ${dados.length} registro(s)...\n`);

  let totalFaltantes = 0;

  // 2. Percorre cada registro da lista
  dados.forEach((item, index) => {
    const { id, fotosCarN = 0, fotosBkN = 0 } = item;
    const arquivosAusentes = [];

    // --- Validação 1: Picture (id.webp) ---
    const caminhoPicture = path.join(pastaBase, 'picture', `${id}.webp`);
    if (!fs.existsSync(caminhoPicture)) {
      arquivosAusentes.push(`picture/${id}.webp`);
    }

    // --- Validação 2: Car (id_0.webp, id_1.webp, ...) ---
    for (let i = 0; i < fotosCarN; i++) {
      const caminhoCar = path.join(pastaBase, 'car', `${id}_${i}.webp`);
      if (!fs.existsSync(caminhoCar)) {
        arquivosAusentes.push(`car/${id}_${i}.webp`);
      }
    }

    // --- Validação 3: Bike (id_0.webp, id_1.webp, ...) ---
    for (let i = 0; i < fotosBkN; i++) {
      const caminhoBike = path.join(pastaBase, 'bike', `${id}_${i}.webp`);
      if (!fs.existsSync(caminhoBike)) {
        arquivosAusentes.push(`bike/${id}_${i}.webp`);
      }
    }

    // 3. Exibe os erros no console
    if (arquivosAusentes.length > 0) {
      totalFaltantes += arquivosAusentes.length;
      console.log(`❌ [Linha/Item ${index + 1} | ID: ${id}] - Objeto:`, JSON.stringify(item));
      console.log('   Arquivos ausentes:');
      arquivosAusentes.forEach((arquivo) => console.log(`   └─ ${arquivo}`));
      console.log('--------------------------------------------------');
    }
  });

  // Resumo final
  if (totalFaltantes === 0) {
    console.log('✅ Todos os arquivos físicos foram encontrados!');
  } else {
    console.log(`\n⚠️  Verificação concluída. Total de arquivos ausentes: ${totalFaltantes}`);
  }

} catch (erro) {
  if (erro.code === 'ENOENT') {
    console.error('Erro: Não foi possível encontrar o arquivo "fotos-faltantes.json".');
  } else {
    console.error('Erro ao executar o script:', erro.message);
  }
}