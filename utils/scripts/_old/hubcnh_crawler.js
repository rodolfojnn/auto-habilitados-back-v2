(async () => {
  const baseUrl = "https://api.hubcnh.com.br/api/instrutores/";
  const batchSize = 100;
  const maxId = 1400;

  let instrutores = [];

  for (let start = 1; start <= maxId; start += batchSize) {
    const end = Math.min(start + batchSize - 1, maxId);

    console.log(`Buscando IDs de ${start} até ${end}...`);

    const requests = [];

    for (let id = start; id <= end; id++) {
      requests.push(
        fetch(baseUrl + id)
          .then(res => res.ok ? res.json() : null)
          .then(json => (json && json.success ? json.data : null))
          .catch(() => null)
      );
    }

    const responses = await Promise.all(requests);
    const validos = responses.filter(Boolean);

    instrutores.push(...validos);

    console.log(`Lote ${start}-${end} concluído. Total até agora: ${instrutores.length}`);

    // Pequena pausa opcional pra aliviar ainda mais (200ms)
    await new Promise(resolve => setTimeout(resolve, 200));
  }

  console.log("Finalizado!");
  console.log("Total de instrutores:", instrutores.length);
  console.log(instrutores);

  window.instrutores = instrutores;
})();

///////////////////////////

console.log(instrutores.map(v => v.nome + ' ' + v.telefone).join('\n'));

///////////////////////////

