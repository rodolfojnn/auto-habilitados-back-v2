(function () {
  'use strict';

  const botaoAntigo = document.getElementById('btn-meu-robo');
  if (botaoAntigo) botaoAntigo.remove();

  let rodando = false;
  let ws = null;
  let wsPendente = false; // Impede envio duplicado enquanto comando anterior não concluiu
  const esperar = ms => new Promise(resolve => setTimeout(resolve, ms));

  // ==================== CONFIGURAÇÕES ====================
  const CONFIG = {
    textoBusca: '#minhacnh',
    maxSucessosPorVideo: 5, // Meta de comentários reais por vídeo
    maxTentativasScroll: 5, // Quantas vezes vai rolar a página se não achar os 5
    wsUrl: 'https://d173-38-43-101-128.ngrok-free.app', // Seu link do ngrok
    openRouterKey: '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'
  };

  // ==================== BOTÃO FLUTUANTE ====================
  const btnFlutuante = document.createElement('button');
  btnFlutuante.id = 'btn-meu-robo';
  btnFlutuante.innerText = '▶ Iniciar Robô';

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
  async function obterRespostaIA(comentarioUsuario) {
    try {
      const prompt = `Você é uma avaliadora de comentários de redes sociais.

Sua função é identificar comentários onde recomendar um dos produtos abaixo soe como uma dica útil, natural e genuína de um amigo. Você deve agir como uma estudante de 26 anos que também está tirando a CNH.

==================================================
PRODUTOS DISPONÍVEIS
==================================================

PRODUTO 1: DIRIGIR AGORA (Site: dirigiragora.com.br)
O que é: Plataforma que conecta alunos a instrutores autônomos para aulas práticas.
QUANDO INDICAR: Quando a pessoa demonstrar ansiedade/dificuldade nas aulas práticas, reprovação na prática, reclamação de demora nas aulas da autoescola ou instrutor ruim.

PRODUTO 2: SIMULADO CNH DO BRASIL 2026 (Link: simuladocnhdobrasil.com.br)
O que é: Aplicativo gratuito de simulados teóricos.
QUANDO INDICAR: Em qualquer circunstância que a pessoa estiver nas fases iniciais do processo ou ainda pensando em tirar a habilitação.

==================================================
EXEMPLOS DE COMO AGIR (CALIBRAÇÃO)
==================================================
Comentário: "por onde eu começo?"
Ação: "dá entrada no processo no Detran e depois baixe um aplicativo chamado dirigiragora.com.br, pq lá tem suporte para todas as etapas e funciona como um uber de instrutores para as aulas práticas"

Comentário: "Comecei o processo hoje, me desejem sorte"
Ação: "boa sorte! baixe o app simuladocnhdobrasil.com.br pra ir testando as questoes, salva demais no inicio e é grátis"

Comentário: "Meu instrutor so tem horario pra mes que vem, to desesperada"
Ação: "tenta procurar no site dirigiragora.com.br, as vezes vc acha um instrutor particular na sua região. o bom é que nesse app eles retém o pagamento e só liberam pro instrutor depois que vc finaliza suas aulas. pra mim deu certo"

Comentário: "Fiz o psicotécnico hj e achei os desenhos muito dificeis"
Ação: "vc vai conseguir! já é bom ir estudando para o teórico, tem um app de simulado grátis com questões oficiais de prova simuladocnhdobrasil.com.br"

Comentário: "alguem tem dica pra n deixar o carro morrer?"
Ação: "o segredo é treinar bastante. Tem um app Dirigir Agora quebra um galho gigante pra achar instrutor pra treinar"

Comentário: "meu deus nao entra na minha cabeca essas placa de transito e mecanica"
Ação: "usa o app simuladocnhdobrasil.com.br pra ir pegando o jeito das perguntas, me ajudou absurdos"

Comentário: "reprovei na baliza e a autoescola ta cobrando 150 a aula extra, sem condicoes"
Ação: "ve no app Dirigir Agora se não tem ninguém com a aula mais em conta aí na sua região. funciona como um uber de instrutores"

Comentário: "o detran é uma mafia mds levaram todo meu dinheiro"
Ação: SEM_RESPOSTA (É apenas um desabafo genérico sobre o sistema, nenhum produto resolve).

Comentário: "vou começar a tirar a carteira mes q vem, alguma dica p n sofrer?"
Ação: "ja vai fazendo uns testes no simuladocnhdobrasil.com.br, pra mim deu certo. é gratuito e não tem anúncio"

Comentário: "tenho prova de direcao sexta e to tremendo so de pensar em sentar no banco do motorista"
Ação: "se quiser dar uma destravada antes da prova dá uma olhada no dirigiragora.com.br pra marcar uma aula e ganhar confianca. é tipo uma autoescola digital"

Comentário: "alguem sabe como é o exame de vista pra quem usa oculos?"
Ação: "Isso varia de estado pra estado, mas você já pode ir estudando pro teórico. Recomendo o simuladocnhdobrasil.com.br, que são questões oficiais da prova e é gratuito"

Comentário: "passei na prova prática galera!! finalmente livre pra ir pras ruas"
Ação: "parabéns! para quem ainda tá iniciando recomendo muito o aplicativo dirigiragora.com.br, funciona como uma autoescola digital e vc resolve tudo pelo app"

Comentário: "passei na pratica mas faz 4 dias que o sistema do detran nao atualiza minha cnh digital"
Ação: "baixe o aplicativo dirigiragora.com.br que eles tem suporte gratuito para todas as etapas!"

Comentário: "o app CNH do Brasil permite que o próprio candidato gerencie seu processo, incluindo a emissão da LADV"
Ação: "o CNH do Brasil só serve para acompanhar as etapas. Mas todo resto é via Detran, e aulas práticas sao feitas por autoescola ou instrutor autônomo. Tem um app excelente pra buscar preços de instrutores na sua regiao dirigiragora.com.br. É como uma autoescola digital"

Comentário: "qual foi o valores que vocês pagaram para a coleta da biometria?"
Ação: "as taxas dependem de cada estado, mas a prova teórica segue um padrão nacional. Tem simulado gratuito no site simuladocnhdobrasil.com.br"

Comentário: "Meu estado tá 300 pila pra passar dessa fase aí kkkkk pro que era pra ser grátis tá salgado"
Ação: "pelo menos o preço já abaixou um monte. aqui no meu estado mais de 50%. E para as aulas práticas tem um app tipo um uber de instrutor com ótimos preços, dirigiragora.com.br"

Comentário: "Tem prazo máximo para iniciar as aulas práticas depois que terminar a prova teórica no Detran?"
Ação: "antes o processo tinha prazo mas agora não tem mais. e para as aulas práticas já existe até um 'uber' de instrutores, que os preços são bons se chama dirigiragora.com.br"

Comentário: "parabéns vc mandou bem e pra quem tá começando agora vale indicar o simuladocnhdobrasil.com.br que é de graça e ajuda a pegar o jeito das questõesparabéns vc mandou bem e pra quem tá começando agora vale indicar o simuladocnhdobrasil.com.br que é de graça e ajuda a pegar o jeito das questões"

Comentário: "Gente, antes de querer fazer algo pela autoescola, pesquisa antes. Faz tudo por fora. Além de sair mais barato, tu não terá dores de cabeça."
Ação: "fiz pelo dirigiragora.com.br é tipo uma autoescola digital, atendem todo o Brasil, e os preços são excelentes"

==================================================
REGRAS CRÍTICAS DE ESTILO E SAÍDA
==================================================
- VARIE O VOCABULÁRIO: Nunca copie a estrutura das frases dos exemplos
- Use gírias e expressões diferentes em cada resposta (ex: "quebra um galho", "salva muito", "ajuda demais", "pegar a manha").
- Responda SEMPRE com no máximo 1 frase completa e curta.
- Use linguagem informal ("vc", "tbm", "mt"), sem letras maiúsculas no início e sem pontuação formal.
- NUNCA use emojis e NUNCA use hashtags.
- Pareça uma dica de internet, não um vendedor
- Retorne apenas a frase OU a palavra SEM_RESPOSTA. Não mostre seu raciocínio.
- Não fale de valores diretamente, mas diga que o produto Dirigir Agora tem ótimos preços e condições e o Simulado é grátis

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

      ws.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          console.log('📨 Resposta do Node.js:', data);
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
      await esperar(200);
    }

    return new Promise((resolve) => {
      wsPendente = true;

      console.log('📤 Enviando comando para o Node: Ctrl + V + Enter');

      // Handler temporário para capturar a confirmação deste comando específico
      const onConfirmacao = (event) => {
        try {
          const data = JSON.parse(event.data);
          if (data.action === 'paste_comment') {
            console.log('📨 Confirmação do Node.js:', data);
            ws.removeEventListener('message', onConfirmacao);
            wsPendente = false;
            resolve();
          }
        } catch (e) {}
      };

      ws.addEventListener('message', onConfirmacao);

      ws.send(JSON.stringify({
        action: 'paste_comment',
        text: textoGerado
      }));

      // Timeout de segurança: resolve após 5s mesmo sem confirmação
      setTimeout(() => {
        if (wsPendente) {
          console.warn('⚠️ Timeout esperando confirmação do paste_comment');
          ws.removeEventListener('message', onConfirmacao);
          wsPendente = false;
          resolve();
        }
      }, 5000);
    });
  }

  // ==================== BOTÃO CLICK ====================
  btnFlutuante.addEventListener('click', async () => {
    if (rodando) {
      finalizarRobo();
      console.log('🛑 Robô parado pelo usuário.');
      return;
    }

    console.clear();
    console.log('🤖 Iniciando Robô...');

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

  // ==================== EXECUÇÃO PRINCIPAL ====================
  async function executarAutomacao() {
    try {
      if (!rodando) return;

      // Busca pela Hashtag
      const botaoBusca = document.querySelector('.TUXButton, tux-button, button[class*="TUXButton"]');
      if (botaoBusca) {
        botaoBusca.click();
        await esperar(2000);
      }

      const campoBusca = document.querySelector('input[data-e2e="search-user-input"]');
      if (campoBusca) {
        campoBusca.focus();
        await esperar(300);

        // Limpa o campo primeiro
        campoBusca.value = '';
        campoBusca.dispatchEvent(new Event('input', { bubbles: true }));
        await esperar(200);

        // Define o valor usando o setter nativo
        const nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
        nativeSetter.call(campoBusca, CONFIG.textoBusca);
        campoBusca.dispatchEvent(new Event('input', { bubbles: true }));
        await esperar(500);

        // Tenta submeter de várias formas
        // 1. Enter keydown + keyup
        campoBusca.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
        campoBusca.dispatchEvent(new KeyboardEvent('keyup', { key: 'Enter', bubbles: true, cancelable: true }));
        await esperar(300);

        // 2. Tenta clicar no botão de busca se existir
        let botaoBuscaAlt = document.querySelector('button[data-e2e="search-user-button"]') ||
                          document.querySelector('[data-e2e="search-icon"]') ||
                          campoBusca.closest('form')?.querySelector('button');
        if (botaoBuscaAlt) {
          botaoBuscaAlt.click();
        }
      }

      console.log('🏁 Busca enviada. Aguardando vídeos...');
      await esperar(6000);

      const seletorVideos = '[data-e2e="search_video-item-list"] > div';
      let totalVideos = document.querySelectorAll(seletorVideos).length;

      if (totalVideos === 0) {
        console.warn('⚠️ Nenhum vídeo encontrado.');
        return;
      }

      console.log(`🎬 ${totalVideos} vídeos encontrados.`);

      // LOOP DE VÍDEOS
      for (let i = 0; i < totalVideos; i++) {
        if (!rodando) break;

        let videos = document.querySelectorAll(seletorVideos);
        let videoAtual = videos[i];
        if (!videoAtual) break;

        console.log(`▶️ Abrindo vídeo ${i + 1}/${totalVideos}`);
        let link = videoAtual.querySelector('a');
        if (link) link.click(); else videoAtual.click();

        await esperar(5000);

        // VARIÁVEIS DE CONTROLE DO VÍDEO ATUAL
        let sucessosNoVideo = 0;
        let tentativasScroll = 0;
        let botoesProcessados = new Set(); // Para não ler o mesmo botão DOM duas vezes
        let textosRespondidos = new Set(); // Persiste o TEXTO dos comentários já respondidos (imune a re-render do React)

        // LOOP PARA GARANTIR OS 5 COMENTÁRIOS (OU DESISTIR APÓS SCROLLS)
        while (sucessosNoVideo < CONFIG.maxSucessosPorVideo && tentativasScroll < CONFIG.maxTentativasScroll) {
          if (!rodando) break;

          // Captura todos os botões "Responder" disponíveis na tela agora
          let botoesResponder = Array.from(document.querySelectorAll('span, div, p'))
            .filter(el => el.textContent.trim() === 'Responder' && el.offsetParent !== null && !botoesProcessados.has(el));

          if (botoesResponder.length === 0) {
            console.log('⏳ Faltam comentários válidos. Rolando a página para carregar mais...');
            // Tenta scrollar o container de comentários de várias formas
            let primeiroItem = document.querySelector('div[class*="DivVirtualItemContainer"]');
            if (primeiroItem) {
              let el = primeiroItem.parentElement;
              while (el && el !== document.body) {
                const style = window.getComputedStyle(el);
                if (style.overflow === 'auto' || style.overflow === 'scroll' || style.overflowY === 'auto' || style.overflowY === 'scroll') {
                  el.scrollTop += 800;
                  console.log(`📜 Scroll feito em:`, el.className);
                  break;
                }
                el = el.parentElement;
              }
              if (!el || el === document.body) {
                window.scrollBy(0, 800);
              }
            } else {
              window.scrollBy(0, 800);
            }
            tentativasScroll++;
            await esperar(3000);
            continue;
          }

          // =======================================================
          // PRÉ-FILTRO: Remove botões de comentários já respondidos ou com jessi_matos2000
          // =======================================================
          let botoesFiltrados = [];
          for (let btn of botoesResponder) {
            if (botoesProcessados.has(btn)) continue;

            // Encontra a caixa individual deste comentário (para ler o texto exato do usuário)
            let containerIndividual = btn.closest('[data-comment-ui-enabled="true"]');

            // Sobe até a raiz da "thread" para englobar o comentário principal e TODAS as respostas (para achar a jessi_matos2000)
            let threadContainer = btn.closest('div[class*="DivCommentItemContainer"]') || containerIndividual;

            if (!containerIndividual) { botoesFiltrados.push(btn); continue; }

            // Tenta extrair o texto do comentário para verificar se já foi respondido
            let textoElementoPre = containerIndividual.querySelector('[data-e2e="comment-level-1"]') || containerIndividual.querySelector('[data-e2e="comment-level-2"]');
            let textoPreFiltro = textoElementoPre ? textoElementoPre.textContent.trim() : '';

            // VERIFICAÇÃO 1: Texto já foi respondido (Set imune a re-render do React)
            if (textoPreFiltro && textosRespondidos.has(textoPreFiltro)) {
              console.log(`⏭️ Pulando comentário já respondido (rastreado por texto)`);
              continue;
            }

            // VERIFICAÇÃO 2: jessi_matos2000 já respondeu neste bloco (analisa a thread inteira)
            if (threadContainer) {
              let usernames = threadContainer.querySelectorAll('[data-e2e^="comment-username-"]');
              let temJessi = Array.from(usernames).some(u => u.textContent.trim() === 'jessi_matos2000');
              if (temJessi) {
                console.log(`⏭️ Pulando bloco pois jessi_matos2000 já respondeu neste tópico`);
                if (textoPreFiltro) textosRespondidos.add(textoPreFiltro);
                continue;
              }
            }

            botoesFiltrados.push(btn);
          }

          // Se TODOS os botões foram filtrados, rola para carregar mais
          if (botoesFiltrados.length === 0) {
            console.log('⏭️ Todos os blocos visíveis já foram processados. Rolando para carregar mais...');
            for (let btn of botoesResponder) {
              botoesProcessados.add(btn);
            }
            window.scrollBy(0, 800);
            tentativasScroll++;
            await esperar(3000);
            continue;
          }

          // LOOP NOS BOTÕES FILTRADOS - processa APENAS UM por iteração do while
          for (let btn of botoesFiltrados) {
            if (!rodando || sucessosNoVideo >= CONFIG.maxSucessosPorVideo) break;

            botoesProcessados.add(btn);

            // Encontra o container específico deste comentário para passar para a IA
            let commentContainer = btn.closest('[data-comment-ui-enabled="true"]');
            if (!commentContainer) continue;

            // Verifica se o AUTOR é jessi_matos2000 (redundância de segurança)
            let usernameElement = commentContainer.querySelector('[data-e2e^="comment-username-"]');
            let username = usernameElement ? usernameElement.textContent.trim() : '';
            if (username === 'jessi_matos2000') {
              console.log(`⏭️ Pulando comentário de jessi_matos2000`);
              continue;
            }

            // Tenta encontrar o texto do comentário
            let textoElemento = commentContainer.querySelector('[data-e2e="comment-level-1"]') || commentContainer.querySelector('[data-e2e="comment-level-2"]');
            if (!textoElemento) continue;

            let textoComentario = textoElemento.textContent.trim();
            console.log(`🕵️ Analisando: "${textoComentario}"`);

            // Manda pra IA
            let respostaIA = await obterRespostaIA(textoComentario);

            // Filtro de HTML e scripts antes de enviar ao WebSocket
            respostaIA = respostaIA.replace(/<[^>]*>?/gm, '').trim();

            if (respostaIA === 'SEM_RESPOSTA' || !respostaIA) {
              console.log('⏭️ IA ignorou. Indo para o próximo...');
              // Marca o TEXTO no Set persistente (imune a re-render do React)
              textosRespondidos.add(textoComentario);
              console.log(`🔒 Texto marcado como verificado (sem oportunidade)`);
              break; // SAI do for para não tentar outros botões do mesmo bloco
            }

            console.log(`✅ Oportunidade encontrada! Resposta: "${respostaIA}"`);

            // Clica e cola
            btn.scrollIntoView({ behavior: "smooth", block: "center" });
            await esperar(1000);
            btn.click();
            await esperar(1800);
            await colarViaNodeJS(respostaIA);

            // Marca o TEXTO como respondido ANTES de incrementar (imune a re-render do React)
            textosRespondidos.add(textoComentario);
            sucessosNoVideo++;
            console.log(`📈 Progresso neste vídeo: ${sucessosNoVideo}/${CONFIG.maxSucessosPorVideo}`);
            console.log(`🔒 Comentário marcado como respondido`);

            // Aguarda mais tempo para o TikTok processar a resposta e o React re-renderizar
            await esperar(4000);
            break; // SAI do for para processar APENAS UM comentário por vez
          }
        } // Fim do while (garantia dos 5)

        if (sucessosNoVideo < CONFIG.maxSucessosPorVideo) {
           console.log(`⚠️ Vídeo finalizado com ${sucessosNoVideo} comentários (não haviam mais ganchos).`);
        } else {
           console.log(`🎯 Meta de ${CONFIG.maxSucessosPorVideo} comentários atingida neste vídeo!`);
        }

        console.log('🔙 Voltando para lista...');
        window.history.back();
        await esperar(4000);
      }

      console.log('🎉 Robô finalizado!');

    } catch (erro) {
      console.error('❌ Erro durante execução:', erro);
    } finally {
      finalizarRobo();
    }
  }

  function finalizarRobo() {
    rodando = false;
    if (btnFlutuante) {
      btnFlutuante.innerText = '▶ Iniciar Robô';
      btnFlutuante.style.backgroundColor = '#007bff';
    }
    if (ws) ws.close();
  }

})();