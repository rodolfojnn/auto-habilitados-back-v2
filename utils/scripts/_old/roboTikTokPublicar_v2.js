(function () {
  'use strict';

  const botaoAntigo = document.getElementById('btn-meu-robo');
  if (botaoAntigo) botaoAntigo.remove();

  let rodando = false;
  let ws = null;
  let wsPendente = false; // Impede envio duplicado enquanto comando anterior não concluiu
  let wsCallbacks = [];    // Fila de callbacks para confirmações do WebSocket (evita acúmulo de listeners)
  const esperar = ms => new Promise(resolve => setTimeout(resolve, ms));

  // ==================== CONFIGURAÇÕES ====================
  const CONFIG = {
    maxScrollsSemSucesso: 10, // Quantas vezes vai rolar a página até "desistir" e entender que os comentários acabaram
    wsUrl: 'https://d842-38-43-101-128.ngrok-free.app', // Seu link do ngrok
    openRouterKey: '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'
  };

  // ==================== BOTÃO FLUTUANTE ====================
  const btnFlutuante = document.createElement('button');
  btnFlutuante.id = 'btn-meu-robo';
  btnFlutuante.innerText = '▶ Iniciar Robô no Vídeo Atual';

  btnFlutuante.style.cssText = `
    position: fixed !important;
    top: 20px !important;
    right: 20px !important;
    z-index: 2147483647 !important;
    padding: 12px 20px !important;
    background-color: #007bff !important;
    color: white !important;
    border: 2px solid white !important;
    border-radius: 8px !important;
    font-size: 16px !important;
    font-weight: bold !important;
    cursor: pointer !important;
    box-shadow: 0px 4px 10px rgba(0,0,0,0.5) !important;
  `;

  document.documentElement.appendChild(btnFlutuante);

  // ==================== INTEGRAÇÃO COM IA ====================
  async function obterRespostaIA(comentarioUsuario, tituloVideo) {
    try {
      const prompt = `Você é uma avaliadora de comentários de redes sociais.

Sua função é identificar comentários onde recomendar um dos produtos abaixo soe como uma dica útil, natural e genuína de um amigo. Você deve agir como uma estudante de 26 anos que também está tirando a CNH. Analise o comentário levando em consideração o contexto do vídeo onde ele foi publicado.

==================================================
CONTEXTO DO VÍDEO ATUAL (Título / Descrição)
==================================================
"${tituloVideo}"

==================================================
PRODUTOS DISPONÍVEIS
==================================================

PRODUTO 1: DIRIGIR AGORA (Site: dirigiragora.com.br)
O que é: Plataforma que conecta alunos a instrutores autônomos para aulas práticas.
QUANDO INDICAR: Quando a pessoa demonstrar ansiedade/dificuldade nas aulas práticas, reprovação na prática, reclamação de demora nas aulas da autoescola ou instrutor ruim.

PRODUTO 2: SIMULADO CNH DO BRASIL 2026 (Link: "Simulado CNH do Brasil 2026" na play store)
O que é: Aplicativo gratuito de simulados teóricos.
QUANDO INDICAR: Em qualquer circunstância que a pessoa estiver nas fases iniciais do processo ou ainda pensando em tirar a habilitação.

==================================================
SEQUÊNCIA LÓGICAS DA DIVULGAÇÃO DOS PRODUTOS
==================================================
As etapas do processo são:
1. Abertura do processo no Detran
2. Exame médico, toxicológico e psicológico
3. Prova teórica
4. Aulas práticas
5. Exame prático

Então quando o comentário for sobre as etapas antes das aulas práticas, indique sempre o produto 2 (Simulado). Apenas quando tratar de aulas práticas ou preços e valores de autoescola, indique o produto 1 (Dirigir Agora).

Para a pessoa ter sido aprovada na prova teórica ela tem que ter acertado 20 ou mais questões. Caso a pessoa estiver aprovada, indique o "Dirigir Agora". Caso reprovada indique o simulado.

==================================================
EXEMPLOS DE COMO AGIR (CALIBRAÇÃO)
==================================================
Comentário: "passei! acertei 26 de 30 questões"
Ação: "parabéns pela aprovação! agora para as aulas práticas veja no "Dirigir Agora" que funciona tipo um uber de instrutores, sai bem mais em conta e rápido"

Comentário: "por onde eu começo?"
Ação: "dá entrada no processo no Detran e depois baixe um aplicativo chamado Dirigir Agora, pq lá tem suporte para todas as etapas e funciona como um uber de instrutores para as aulas práticas"

Comentário: "Comecei o processo hoje, me desejem sorte"
Ação: "boa sorte! baixe o app "Simulado CNH do Brasil 2026" na play store pra ir testando as questoes, salva demais no inicio e é grátis"

Comentário: "Meu instrutor so tem horario pra mes que vem, to desesperada"
Ação: "tenta procurar no site "Dirigir Agora", as vezes vc acha um instrutor particular na sua região. o bom é que nesse app eles retém o pagamento e só liberam pro instrutor depois que vc finaliza suas aulas. pra mim deu certo"

Comentário: "Fiz o psicotécnico hj e achei os desenhos muito dificeis"
Ação: "vc vai conseguir! já é bom ir estudando para o teórico, tem um app de simulado grátis com questões oficiais de prova "Simulado CNH do Brasil 2026" na play store. É excelente!"

Comentário: "alguem tem dica pra n deixar o carro morrer?"
Ação: "o segredo é treinar bastante. Tem um app Dirigir Agora quebra um galho gigante pra achar instrutor pra treinar"

Comentário: "meu deus nao entra na minha cabeca essas placa de transito e mecanica"
Ação: "usa o app "Simulado CNH do Brasil 2026" na play store pra ir treinando, me ajudou absurdos"

Comentário: "reprovei na baliza e a autoescola ta cobrando 150 a aula extra, sem condicoes"
Ação: "ve no app Dirigir Agora se não tem ninguém com a aula mais em conta aí na sua região. funciona como um uber de instrutores"

Comentário: "o detran é uma mafia mds levaram todo meu dinheiro"
Ação: SEM_RESPOSTA (É apenas um desabafo genérico sobre o sistema, nenhum produto resolve).

Comentário: "vou começar a tirar a carteira mes q vem, alguma dica p n sofrer?"
Ação: "ja vai estudando no "Simulado CNH do Brasil 2026" na play store, pra mim deu certo. é gratuito e não tem anúncio"

Comentário: "tenho prova de direcao sexta e to tremendo so de pensar em sentar no banco do motorista"
Ação: "se quiser dar uma destravada antes da prova dá uma olhada no "Dirigir Agora" pra marcar uma aula e ganhar confianca. é tipo uma autoescola digital"

Comentário: "alguem sabe como é o exame de vista pra quem usa oculos?"
Ação: "Isso varia de estado pra estado, mas você já pode ir estudando pro teórico. Recomendo o "Simulado CNH do Brasil 2026" na play store, que são questões oficiais da prova e é gratuito"

Comentário: "passei na prova prática galera!! finalmente livre pra ir pras ruas"
Ação: "parabéns! para quem ainda tá iniciando recomendo muito o aplicativo "Dirigir Agora", funciona como uma autoescola digital e vc resolve tudo pelo app"

Comentário: "passei na pratica mas faz 4 dias que o sistema do detran nao atualiza minha cnh digital"
Ação: "baixe o aplicativo Dirigir Agora que eles tem suporte gratuito para todas as etapas!"

Comentário: "o app CNH do Brasil permite que o próprio candidato gerencie seu processo, incluindo a emissão da LADV"
Ação: "o CNH do Brasil só serve para acompanhar as etapas. Mas todo resto é via Detran, e aulas práticas sao feitas por autoescola ou instrutor autônomo. Tem um app excelente pra buscar preços de instrutores na sua regiao "Dirigir Agora". É como uma autoescola digital"

Comentário: "qual foi o valores que vocês pagaram para a coleta da biometria?"
Ação: "as taxas dependem de cada estado, mas a prova teórica segue um padrão nacional. Tem simulado gratuito no site "Simulado CNH do Brasil 2026" na play store, vale a pena"

Comentário: "Meu estado tá 300 pila pra passar dessa fase aí kkkkk pro que era pra ser grátis tá salgado"
Ação: "pelo menos o preço já abaixou um monte. aqui no meu estado mais de 50%. E para as aulas práticas tem um app tipo um uber de instrutor com ótimos preços chamado "Dirigir Agora""

Comentário: "Tem prazo máximo para iniciar as aulas práticas depois que terminar a prova teórica no Detran?"
Ação: "antes o processo tinha prazo mas agora não tem mais. e para as aulas práticas já existe até um 'uber' de instrutores, que os preços são bons se chama "Dirigir Agora""

Comentário: "Gente, antes de querer fazer algo pela autoescola, pesquisa antes. Faz tudo por fora. Além de sair mais barato, tu não terá dores de cabeça."
Ação: "fiz pelo "Dirigir Agora" é tipo uma autoescola digital, atendem todo o Brasil, e os preços são excelentes"

Comentário: "faça pela autoescola"
Ação: "dá pra fazer por aplicativo, funciona tipo um uber de instrutores "Dirigir Agora". Aqui na minha cidade saiu bem mais em conta e bem mais rápido"

Comentário: "prefiro fazer na auto escola"
Ação: "hoje dá pra fazer por um app, o Dirigir Agora, funciona tipo uma autoescola digital. Você acha instrutores próximos de vc com preços excelentes. Pra mim deu super certo"

Comentário: "quero fazer aulas práticas com instrutor autônomo. Como funciona?"
Ação: "o melhor é você contratar através de alguma empresa que é mais seguro. Tem um app que funciona como um Uber de instrutores e os instrutores só recebem o pagamento no final de todas as suas aulas. Acho bem mais seguro assim!"

==================================================
REGRAS CRÍTICAS DE ESTILO E SAÍDA
==================================================
- VARIE O VOCABULÁRIO: Nunca copie a estrutura das frases dos exemplos.
- Use gírias e expressões diferentes nas respostas (ex: "quebra um galho", "salva muito", "ajuda demais", "ajuda pra caramba", "né?", "tipo").
- Responda SEMPRE com no máximo 1 frase completa e curta, com no máximo 25 palavras.
- Use linguagem informal ("vc", "tbm"), sem letras maiúsculas no início e sem pontuação formal.
- NUNCA use emojis e NUNCA use hashtags.
- Pareça uma dica de internet, não um vendedor
- Retorne apenas a frase OU a palavra SEM_RESPOSTA. Não mostre seu raciocínio.
- Seja criterioso na resposta, não faça propaganda caso não tenha contexto o suficiente pra isso.
- Não fale de valores diretamente, diga que o produto Dirigir Agora é mais barato que autoescola e o instrutor vem atender em casa. O Simulado é grátis e sem propagandas

==================================================
COMENTÁRIO PARA RESPONDER:
==================================================
${comentarioUsuario}`;

      const response = await fetch('https://openrouter.ai/api/v1/chat/completions', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${CONFIG.openRouterKey}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          model: 'openrouter/free',
          messages: [{ role: 'user', content: prompt }],
          tools: [{ type: 'openrouter:web_fetch' }]
        }),
      });

      const data = await response.json();
      let textoGerado = data.choices[0].message.content.trim();

      // Remove aspas que a IA possa colocar acidentalmente no início e fim
      if(textoGerado.startsWith('"') && textoGerado.endsWith('"')) {
          textoGerado = textoGerado.substring(1, textoGerado.length - 1);
      }
      return textoGerado;

    } catch (erro) {
      console.error('❌ Erro ao chamar IA:', erro);
      return 'SEM_RESPOSTA';
    }
  }

  // ==================== CONEXÃO WEBSOCKET ====================
  function conectarWebSocket() {
    return new Promise((resolve, reject) => {
      console.log('🔌 Tentando conectar ao:', CONFIG.wsUrl);
      ws = new WebSocket(CONFIG.wsUrl);

      ws.onopen = () => {
        console.log('✅ Conectado com sucesso ao WebSocket!');
        resolve();
      };

      ws.onerror = (err) => {
        console.error('❌ Erro na conexão WebSocket. Verifique se o ngrok e o server.js estão rodando.');
        reject(err);
      };

      // Handler PERMANENTE único: despacha mensagens para a fila de callbacks
      ws.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          if (data.action === 'paste_comment') {
            // Chama o próximo callback pendente e remove da fila
            const cb = wsCallbacks.shift();
            if (cb) {
              console.log('📨 Confirmação do Node.js:', data);
              cb(data);
            }
          }
        } catch (e) {}
      };
    });
  }

  // ==================== COLAR VIA NODE.JS ====================
  async function colarViaNodeJS(textoGerado) {
    if (!ws || ws.readyState !== WebSocket.OPEN) {
      console.warn('⚠️ WebSocket não está conectado!');
      return;
    }

    // Aguarda comando pendente anterior ser concluído (evita duplicação)
    while (wsPendente) {
      console.log('⏳ Aguardando comando anterior terminar...');
      await esperar(300);
    }

    return new Promise((resolve) => {
      wsPendente = true;

      console.log('📤 Enviando comando para o Node: Ctrl + V + Enter');

      // Registra callback na fila (UM único onmessage despacha para cá)
      wsCallbacks.push((data) => {
        wsPendente = false;
        resolve();
      });

      ws.send(JSON.stringify({
        action: 'paste_comment',
        text: textoGerado
      }));

      // Timeout de segurança: resolve após 8s mesmo sem confirmação
      setTimeout(() => {
        if (wsPendente) {
          console.warn('⚠️ Timeout esperando confirmação do paste_comment');
          // Remove este callback da fila se ainda estiver lá
          const idx = wsCallbacks.findIndex(cb => !cb._done);
          if (idx !== -1) wsCallbacks.splice(idx, 1);
          wsPendente = false;
          resolve();
        }
      }, 8000);
    });
  }

  // ==================== FUNÇÃO DE ROLAGEM ====================
  function rolarContainerDeComentarios() {
    let primeiroItem = document.querySelector('div[class*="DivVirtualItemContainer"]');
    if (primeiroItem) {
      let el = primeiroItem.parentElement;
      while (el && el !== document.body) {
        const style = window.getComputedStyle(el);
        if (style.overflow === 'auto' || style.overflow === 'scroll' || style.overflowY === 'auto' || style.overflowY === 'scroll') {
          el.scrollTop += 800;
          console.log(`📜 Scroll feito internamente no container de comentários.`);
          return;
        }
        el = el.parentElement;
      }
    }
    // Fallback caso não ache o container (ex: página inteira)
    window.scrollBy(0, 800);
    console.log(`📜 Scroll feito na página global.`);
  }

  // ==================== BOTÃO CLICK ====================
  btnFlutuante.addEventListener('click', async () => {
    if (rodando) {
      finalizarRobo();
      console.log('🛑 Robô parado pelo usuário.');
      return;
    }

    console.clear();
    console.log('🤖 Iniciando Robô no Vídeo Atual...');

    try {
      await conectarWebSocket();
    } catch (e) {
      console.error('❌ Falha ao conectar no WebSocket.');
      return;
    }

    rodando = true;
    btnFlutuante.innerText = '⏹ Parar Robô';
    btnFlutuante.style.backgroundColor = '#dc3545';

    await executarAutomacao();
  });

  // ==================== EXECUÇÃO PRINCIPAL (SÓ O VÍDEO ATUAL) ====================
  async function executarAutomacao() {
    try {
      let sucessosNoVideo = 0;
      let tentativasScroll = 0;
      let botoesProcessados = new Set(); // Para não ler o mesmo botão DOM duas vezes
      let textosRespondidos = new Set(); // Persiste o TEXTO dos comentários já respondidos

      // CAPTURA DO CONTEXTO DO VÍDEO (Captura uma vez no início da automação do vídeo)
      const descElemento = document.querySelector('[data-e2e="browse-video-desc"]');
      const tituloVideo = descElemento ? descElemento.textContent.trim() : 'Sem descrição/título disponível';
      console.log(`📹 Contexto do Vídeo Capturado: "${tituloVideo}"`);

      console.log('▶️ Iniciando varredura contínua de comentários neste vídeo...');

      // LOOP ATÉ ACABAREM OS COMENTÁRIOS (ou atingir o limite de scrolls sem achar nada)
      while (tentativasScroll < CONFIG.maxScrollsSemSucesso) {
        if (!rodando) break;

        // Captura todos os botões "Responder" disponíveis na tela agora
        let botoesResponder = Array.from(document.querySelectorAll('span, div, p'))
          .filter(el => el.textContent.trim() === 'Responder' && el.offsetParent !== null && !botoesProcessados.has(el));

        if (botoesResponder.length === 0) {
          console.log(`⏳ Faltam comentários válidos. Rolando a página... (${tentativasScroll + 1}/${CONFIG.maxScrollsSemSucesso})`);
          rolarContainerDeComentarios();
          tentativasScroll++;
          await esperar(8000);
          continue;
        }

        // =======================================================
        // PRÉ-FILTRO: Remove botões de comentários já respondidos ou com jessi_matos2000
        // =======================================================
        let botoesFiltrados = [];
        for (let btn of botoesResponder) {
          if (botoesProcessados.has(btn)) continue;

          let containerIndividual = btn.closest('[data-comment-ui-enabled="true"]');
          let threadContainer = btn.closest('div[class*="DivCommentItemContainer"]') || containerIndividual;

          if (!containerIndividual) { botoesFiltrados.push(btn); continue; }

          let textoElementoPre = containerIndividual.querySelector('[data-e2e="comment-level-1"]') || containerIndividual.querySelector('[data-e2e="comment-level-2"]');
          let textoPreFiltro = textoElementoPre ? textoElementoPre.textContent.trim() : '';

          // VERIFICAÇÃO 1: Texto já foi respondido
          if (textoPreFiltro && textosRespondidos.has(textoPreFiltro)) {
            continue;
          }

          // VERIFICAÇÃO 2: jessi_matos2000 já respondeu neste bloco
          if (threadContainer) {
            let usernames = threadContainer.querySelectorAll('[data-e2e^="comment-username-"]');
            let temJessi = Array.from(usernames).some(u => u.textContent.trim() === 'jessi_matos2000');
            if (temJessi) {
              if (textoPreFiltro) textosRespondidos.add(textoPreFiltro);
              continue;
            }
          }

          botoesFiltrados.push(btn);
        }

        // Se TODOS os botões da tela já foram filtrados/pulados, rola para baixo
        if (botoesFiltrados.length === 0) {
          console.log(`⏭️ Blocos visíveis já processados/ignorados. Rolando... (${tentativasScroll + 1}/${CONFIG.maxScrollsSemSucesso})`);
          for (let btn of botoesResponder) {
            botoesProcessados.add(btn);
          }
          rolarContainerDeComentarios();
          tentativasScroll++;
          await esperar(8000);
          continue;
        }

        // ACHOU BOTÕES NOVOS: Zera o contador de desistência do scroll!
        tentativasScroll = 0;

        // LOOP NOS BOTÕES FILTRADOS - processa APENAS UM por iteração do while maior para o React renderizar direito
        for (let btn of botoesFiltrados) {
          if (!rodando) break;

          botoesProcessados.add(btn);

          let commentContainer = btn.closest('[data-comment-ui-enabled="true"]');
          if (!commentContainer) continue;

          let usernameElement = commentContainer.querySelector('[data-e2e^="comment-username-"]');
          let username = usernameElement ? usernameElement.textContent.trim() : '';
          if (username === 'jessi_matos2000') continue;

          let textoElemento = commentContainer.querySelector('[data-e2e="comment-level-1"]') || commentContainer.querySelector('[data-e2e="comment-level-2"]');
          if (!textoElemento) continue;

          let textoComentario = textoElemento.textContent.trim();

          // VERIFICAÇÃO CRÍTICA ANTES DE PROCESSAR:
          // Marca o texto como "em processamento" IMEDIATAMENTE para evitar
          // que outra iteração ou outro botão do mesmo comentário dispare em paralelo
          if (textosRespondidos.has(textoComentario)) {
            console.log(`⏭️ Texto já processado/em fila: "${textoComentario}"`);
            continue;
          }
          textosRespondidos.add(textoComentario);

          console.log(`🕵️ Analisando: "${textoComentario}"`);

          // Manda pra IA (Passando o texto do comentário E o título do vídeo capturado)
          let respostaIA = await obterRespostaIA(textoComentario, tituloVideo);

          // Filtro de HTML e scripts
          respostaIA = respostaIA.replace(/<[^>]*>?/gm, '').trim();

          // Corrige qualquer ocorrência de "simuladocnhdobrasil.com" que esteja SEM o ".br"
          // Passo 1: "simuladocnhdobrasil.com." → "simuladocnhdobrasil.com.br" (ponto de pontuação vira .br)
          respostaIA = respostaIA.replace(/simuladocnhdobrasil\.com\.(?!br)/gi, 'simuladocnhdobrasil.com.br');
          // Passo 2: "simuladocnhdobrasil.com" (sem .br à frente) → "simuladocnhdobrasil.com.br"
          respostaIA = respostaIA.replace(/simuladocnhdobrasil\.com(?!\.?br)/gi, 'simuladocnhdobrasil.com.br');

          // Verificação ultra-robusta de SEM_RESPOSTA
          // Cobre: SEM_RESPOSTA, SE_RESPOSTA, sem_resposta, Sem Resposta, RESPOSTA, _resposta, etc.
          const textoLimpo = respostaIA.replace(/[\s_\-]+/g, '').toLowerCase();
          const ehSemResposta = !respostaIA
            || respostaIA.trim() === ''
            || textoLimpo === 'semresposta'
            || textoLimpo === 'seresposta'
            || /^(sem?|s\/?)?resposta$/i.test(respostaIA.replace(/[\s_\-]+/g, ''))
            || (respostaIA.length <= 25 && /resposta/i.test(respostaIA.replace(/[\s_\-]+/g, '')));
          if (ehSemResposta) {
            console.log('⏭️ IA ignorou (Desabafo/Sem Contexto). Indo para o próximo...');
            // texto já foi adicionado ao textosRespondidos no início do bloco
            break; // SAI do for para checar novos botões
          }

          // Detecta e corrige texto duplicado/concatenado da IA
          // Método 1: Se domínios promovidos aparecem 2x+, a IA concatenou respostas
          const dominios = ['dirigiragora.com.br', 'simuladocnhdobrasil.com.br'];
          for (const dominio of dominios) {
            const primeiraOcorrencia = respostaIA.indexOf(dominio);
            const segundaOcorrencia = respostaIA.indexOf(dominio, primeiraOcorrencia + dominio.length);
            if (segundaOcorrencia !== -1) {
              console.warn(`⚠️ Domínio "${dominio}" aparece 2x — IA concatenou respostas. Cortando...`);
              // Corta tudo a partir do início da 2ª menção (fica só a 1ª resposta)
              // Mas tenta preservar a 1ª frase inteira: procura o último ponto antes da segunda menção
              const antesDaSegunda = respostaIA.substring(0, segundaOcorrencia);
              const ultimoPonto = antesDaSegunda.lastIndexOf('.');
              if (ultimoPonto !== -1) {
                respostaIA = antesDaSegunda.substring(0, ultimoPonto + 1).trim();
              } else {
                respostaIA = antesDaSegunda.trim();
              }
              break;
            }
          }

          // Método 2: Fallback — verifica se os primeiros 40 caracteres se repetem depois
          const metade = Math.floor(respostaIA.length / 2);
          const primeiraMetade = respostaIA.substring(0, metade);
          if (metade > 20 && respostaIA.indexOf(primeiraMetade.substring(0, Math.min(40, metade))) > 0) {
            console.warn('⚠️ Detectado texto duplicado da IA (repetição exata), usando apenas a primeira metade.');
            respostaIA = primeiraMetade.trim();
            const metade2 = Math.floor(respostaIA.length / 2);
            if (respostaIA.substring(0, metade2) === respostaIA.substring(metade2)) {
              respostaIA = respostaIA.substring(0, metade2).trim();
            }
          }

          // Validação final: resposta muito curta ou só espaços
          if (respostaIA.length < 3) {
            console.log('⏭️ Resposta da IA muito curta, ignorando...');
            break;
          }

          console.log(`✅ Oportunidade encontrada! Resposta: "${respostaIA}"`);

          // Clica e cola
          btn.scrollIntoView({ behavior: "smooth", block: "center" });
          await esperar(6000);
          btn.click();
          await esperar(6000);
          await colarViaNodeJS(respostaIA);

          sucessosNoVideo++;
          console.log(`📈 Respostas enviadas com sucesso neste vídeo até agora: ${sucessosNoVideo}`);

          // Aguarda MAIS tempo (6s) para garantir que o TikTok renderize a resposta
          // e o username "jessi_matos2000" fique visível no DOM antes da próxima varredura
          await esperar(10000);
          break; // SAI do for para processar um de cada vez e atualizar a tela
        }
      }

      console.log(`🎉 Vídeo finalizado! Foram respondidos ${sucessosNoVideo} comentários no total.`);

    } catch (erro) {
      console.error('❌ Erro durante execução no vídeo:', erro);
    } finally {
      finalizarRobo();
    }
  }

  function finalizarRobo() {
    rodando = false;
    if (btnFlutuante) {
      btnFlutuante.innerText = '▶ Iniciar Robô no Vídeo Atual';
      btnFlutuante.style.backgroundColor = '#007bff';
    }
    if (ws) ws.close();
  }

})();