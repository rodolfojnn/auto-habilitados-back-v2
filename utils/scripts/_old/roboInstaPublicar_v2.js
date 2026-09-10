(function () {
    'use strict';

    // -----------------------------------------------------------------
    // CONFIGURAÇÃO WEBSOCKET (POWERSHOT / SWIPE INTEGRATION)
    // -----------------------------------------------------------------
    const WS_URL = 'https://db9f-38-43-101-128.ngrok-free.app'; // Atualize o link se o ngrok mudar
    const PIXELS_SWIPE = 200;
    let ws = null;

    // Objeto para lidar com respostas assíncronas do WebSocket
    const pendingPromises = {};

    function conectarWebSocket() {
        console.log(`🔌 Conectando ao servidor WebSocket em: ${WS_URL}...`);
        ws = new WebSocket(WS_URL);

        ws.onopen = () => console.log('✅ Conectado ao servidor WebSocket local!');

        ws.onmessage = (e) => {
            try {
                const data = JSON.parse(e.data);
                console.log('📩 Resposta WS:', data);

                // Resolve as promises baseadas na ação
                if (data.action && pendingPromises[data.action]) {
                    pendingPromises[data.action](data);
                    delete pendingPromises[data.action];
                }
            } catch (err) {
                console.log('📩 Resposta WS (Não-JSON):', e.data);
            }
        };

        ws.onerror = (e) => console.error('❌ Erro na conexão WebSocket:', e);
        ws.onclose = () => {
            console.warn('🔌 Conexão WebSocket encerrada. Tentando reconectar em 3s...');
            setTimeout(conectarWebSocket, 3000);
        };
    }

    // Inicializa a conexão WS imediatamente ao carregar o script
    conectarWebSocket();

    // Estado da automação
    let roboAtivo = false;
    // Lista (Set) para armazenar os @usuarios já processados
    const usuariosProcessados = new Set();
    // Armazena a descrição do vídeo atual para usar no contexto dos comentários
    let descricaoVideoAtual = "";

    // Funções utilitárias para pausar a execução (Delay)
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms + 5000));

    // NOVO: Timer humanizado que escolhe um tempo aleatório entre min e max
    const sleepRandom = (min, max) => {
        const tempo = Math.floor(Math.random() * (max - min + 1)) + min + 5000;
        return new Promise((resolve) => setTimeout(resolve, tempo));
    };

    // Função utilitária para enviar mensagem e aguardar resposta do WS
    function enviarMensagemWSAsync(mensagem) {
        return new Promise((resolve, reject) => {
            if (!ws || ws.readyState !== WebSocket.OPEN) {
                reject(new Error("WebSocket não está conectado."));
                return;
            }

            // Registra a promise esperando pela resposta com base no nome da action
            pendingPromises[mensagem.action] = resolve;

            ws.send(JSON.stringify(mensagem));

            // Timeout de segurança de 30 segundos
            setTimeout(() => {
                if (pendingPromises[mensagem.action]) {
                    delete pendingPromises[mensagem.action];
                    reject(new Error(`Timeout aguardando resposta para action: ${mensagem.action}`));
                }
            }, 30000);
        });
    }

    // -----------------------------------------------------------------
    // 1. INTEGRAÇÃO REAL COM A IA (GERAR RESPOSTA)
    // -----------------------------------------------------------------
    async function interagirComIAResposta(usuario, textoComentario) {
        console.log(`🧠 IA: Enviando comentário de @${usuario} para processamento...`);
        console.log(`📩 [PAYLOAD IA]: "${textoComentario}"`);

        try {
            const resposta = await enviarMensagemWSAsync({
                action: "generate_reply",
                description: descricaoVideoAtual,
                comment: textoComentario
            });

            if (resposta.status === 'done') {
                console.log("==========================================");
                console.log(`✅ IA [RESPOSTA PARA @${usuario}]:`);
                console.log(resposta.reply);
                console.log("==========================================");

                return resposta.reply;
            } else {
                console.error("❌ Erro da IA ao gerar resposta:", resposta.message);
                return null;
            }
        } catch (erro) {
            console.error("❌ Falha na comunicação com WebSocket ao gerar resposta:", erro);
            return null;
        }
    }

    // -----------------------------------------------------------------
    // 2. FECHAR MODAL COM TECLA ESC E ROLAR PARA O PRÓXIMO VÍDEO (VIA SWIPE)
    // -----------------------------------------------------------------
    async function fecharComentariosEProximoVideo() {
        console.log("🤖 Robô: Pressionando ESC para fechar o painel de comentários...");

        const eventoEsc = new KeyboardEvent('keydown', {
            key: 'Escape',
            code: 'Escape',
            keyCode: 27,
            which: 27,
            bubbles: true,
            cancelable: true
        });

        const textareaComentario = document.querySelector('textarea[aria-label="Adicione um comentário..."]') || document.querySelector('textarea[placeholder="Adicione um comentário..."]');

        if (textareaComentario) {
            textareaComentario.focus();
            textareaComentario.dispatchEvent(eventoEsc);
        }

        if (document.activeElement && document.activeElement !== textareaComentario) {
            document.activeElement.dispatchEvent(eventoEsc);
        }
        document.dispatchEvent(eventoEsc);
        window.dispatchEvent(eventoEsc);

        await sleepRandom(400, 700); // Pausa humanizada antes do swipe

        console.log("🤖 Robô: Enviando comando 'swipe_up' via WebSocket...");

        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                action: 'swipe_up',
                pixels: PIXELS_SWIPE
            }));
            console.log(`🖱️ Swipe Up de ${PIXELS_SWIPE}px executado no SO!`);
        } else {
            console.error("❌ Falha ao enviar Swipe: Conexão WebSocket não está aberta.");
        }

        await sleepRandom(1500, 2500); // Pausa pra rolagem acabar
    }

    // -----------------------------------------------------------------
    // 3. PROCESSAR, EXIBIR E VALIDAR A DESCRIÇÃO DO VÍDEO
    // -----------------------------------------------------------------
    async function processarDescricaoVideo() {
        const todasDivsTexto = document.querySelectorAll('div[style*="display: inline"]');

        if (todasDivsTexto.length === 0) {
            console.log("🤖 Robô: Nenhuma descrição/legenda encontrada.");
            descricaoVideoAtual = "";
            return false;
        }

        const divLegenda = todasDivsTexto[0];

        let blocoLegenda = divLegenda.parentElement;
        for (let i = 0; i < 4; i++) {
            if (blocoLegenda && blocoLegenda.parentElement) {
                blocoLegenda = blocoLegenda.parentElement;
            }
        }

        if (blocoLegenda) {
            const elementos = Array.from(blocoLegenda.querySelectorAll('div, span'));
            const elMais = elementos.find(el => el.innerText.trim() === 'mais' && el.children.length === 0);

            if (elMais) {
                const btnMais = elMais.closest('[role="button"]') || elMais;
                btnMais.click();
                console.log("🤖 Robô: Clicou em 'mais' para expandir a descrição.");
                await sleepRandom(800, 1200); // Pausa humanizada
            }
        }

        descricaoVideoAtual = divLegenda.innerText.replace(/\s*\n?mais$/, '').trim();

        console.log("==========================================");
        console.log("📝 DESCRIÇÃO DO VÍDEO EXTRAÍDA:");
        console.log(descricaoVideoAtual);
        console.log("==========================================");

        console.log("🤖 Verificando se o vídeo é relevante ao nicho (CNH)...");
        try {
            const resposta = await enviarMensagemWSAsync({
                action: "check_cnh",
                description: descricaoVideoAtual
            });

            if (resposta.status === 'done') {
                if (resposta.result === true) {
                    console.log("✅ IA APROVOU: O vídeo é sobre CNH. Continuando o fluxo...");
                    return true;
                } else {
                    console.log("⏭️ IA REJEITOU: O vídeo NÃO é sobre CNH. Pulando para o próximo...");
                    return false;
                }
            } else {
                console.error("❌ Erro da IA na validação:", resposta.message);
                return false;
            }
        } catch (erro) {
            console.error("❌ Falha na comunicação com WebSocket ao validar CNH:", erro);
            return false;
        }
    }

    // -----------------------------------------------------------------
    // 4. EXTRAIR NOME DE USUÁRIO E TEXTO DO COMENTÁRIO
    // -----------------------------------------------------------------
    function obterDadosDoComentario(spanResponder) {
        let ancestral = spanResponder.parentElement;
        let usuario = null;
        let textoComentario = "";

        for (let i = 0; i < 10; i++) {
            if (!ancestral) break;

            if (!usuario) {
                const linkPerfil = ancestral.querySelector('a[href^="/"]');
                if (linkPerfil) {
                    const href = linkPerfil.getAttribute('href');
                    const u = href.replace(/\//g, '').trim();
                    if (u && u !== 'explore') {
                        usuario = u;
                    }
                }
            }

            if (!textoComentario) {
                const divTexto = ancestral.querySelector('div[style*="display: inline"]');
                if (divTexto) {
                    textoComentario = divTexto.innerText.replace(/\s*\n?mais$/, '').trim();
                }
            }

            if (usuario && textoComentario) break;
            ancestral = ancestral.parentElement;
        }

        const palavras = textoComentario.trim().split(/\s+/).filter(Boolean);
        const qtdPalavras = palavras.length;

        return { usuario, textoComentario, qtdPalavras };
    }

    // -----------------------------------------------------------------
    // 5. FLUXO SEQUENCIAL DE RESPOSTAS
    // -----------------------------------------------------------------
    async function processarRespostasSequenciais() {
        let contador = 1;

        while (roboAtivo) {
            const todosSpans = Array.from(document.querySelectorAll('span'));
            const botoesResponder = todosSpans.filter(span => span.innerText.trim() === 'Responder');

            let proximoSpan = null;
            let usuarioAtual = null;
            let textoAtual = "";

            for (const span of botoesResponder) {
                const { usuario, textoComentario, qtdPalavras } = obterDadosDoComentario(span);

                if (usuario && !usuariosProcessados.has(usuario)) {
                    if (qtdPalavras < 7) {
                        console.log(`⏩ Robô: Ignorando @${usuario} (Apenas ${qtdPalavras} palavras: "${textoComentario}")`);
                        usuariosProcessados.add(usuario);
                        continue;
                    }

                    proximoSpan = span;
                    usuarioAtual = usuario;
                    textoAtual = textoComentario;
                    break;
                }
            }

            if (!proximoSpan || !usuarioAtual) {
                console.log("🤖 Robô: Todos os comentários elegíveis visíveis já foram processados (ou não há comentários).");
                break;
            }

            usuariosProcessados.add(usuarioAtual);

            const btnResponder = proximoSpan.closest('[role="button"]') || proximoSpan;

            btnResponder.scrollIntoView({ behavior: 'smooth', block: 'center' });
            await sleepRandom(1600, 2000); // Pausa humanizada ao scrollar

            if (!roboAtivo) break;

            btnResponder.click();

            console.log(`🤖 Robô: [${contador}] Respondedor clicado para @${usuarioAtual}`);
            console.log(`💬 Comentário de @${usuarioAtual}: "${textoAtual}"`);

            const textoRespostaIA = await interagirComIAResposta(usuarioAtual, textoAtual);

            if (!roboAtivo) break;

            // Pausa humanizada aguardando a caixa de comentário abrir / IA retornar
            await sleepRandom(1800, 3500);

            if (textoRespostaIA) {
                // Regex "à prova de balas" para variações de SEM_RESPOSTA
                const ehParaIgnorar = /sem\s*_?\s*resposta/i.test(textoRespostaIA);

                if (ehParaIgnorar) {
                    console.log(`⏩ IA decidiu IGNORAR o comentário de @${usuarioAtual} (Retornou SEM_RESPOSTA). Fechando e pulando para o próximo...`);
                } else {
                    try {
                        console.log(`🤖 Robô: Solicitando ao SO para colar a resposta...`);
                        await enviarMensagemWSAsync({
                            action: 'paste_comment',
                            text: textoRespostaIA
                        });

                        // Timer humanizado: "lendo" ou conferindo se colou certo (1,5 a 3 segundos)
                        console.log(`⏳ Simulando tempo humano antes de dar Enter...`);
                        await sleepRandom(3500, 5000);

                        // console.log(`🤖 Robô: Solicitando ao SO para enviar (ENTER)...`);
                        // await enviarMensagemWSAsync({
                        //     action: 'send_comment'
                        // });

                        console.log(`✅ Robô: Resposta enviada com sucesso para @${usuarioAtual}!`);

                        // Timer humanizado: respiro após envio antes de ir pro próximo (2,5 a 4,5 segundos)
                        console.log(`⏳ Aguardando a rede social registrar o comentário...`);
                        await sleepRandom(4500, 6500);
                    } catch (err) {
                        console.error(`❌ Erro durante o fluxo de Colar/Enviar para @${usuarioAtual}:`, err);
                    }
                }
            }

            if (!roboAtivo) break;

            // Fecha caso tenha clicado em responder e desistido (SEM_RESPOSTA) ou se sobrou algum modal
            const svgFechar = document.querySelector('svg[aria-label="Fechar"]');
            if (svgFechar) {
                const btnFechar = svgFechar.closest('[role="button"]') || svgFechar;
                btnFechar.click();
                console.log(`🤖 Robô: Formulário/modal fechado para @${usuarioAtual}`);
            }

            contador++;
            await sleepRandom(1000, 2000); // Pausa humanizada entre a finalização de um e a busca do próximo
        }

        console.log("🤖 Robô: Finalizou o processamento de todos os comentários deste vídeo.");
    }

    // -----------------------------------------------------------------
    // 6. AÇÕES DE INTERAÇÃO NA PÁGINA
    // -----------------------------------------------------------------
    async function clicarBotaoComentarViaWS() {
        console.log("🤖 Robô: Solicitando clique via WebSocket na posição atual do mouse...");

        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ action: 'click' }));
            console.log("🖱️ Comando de 'click' enviado para o servidor local!");
            return true;
        } else {
            console.error("❌ Falha ao enviar Click: Conexão WebSocket não está aberta.");
            return false;
        }
    }

    async function iniciarAutomacao() {
        console.log("🤖 Robô: Automação INICIADA.");
        console.log("⏳ Você tem 3 SEGUNDOS para posicionar o mouse sobre o balão de comentários...");

        await sleep(6000);

        while (roboAtivo) {
            console.log("🎬 Iniciando processamento de um novo vídeo...");

            usuariosProcessados.clear();
            descricaoVideoAtual = "";

            const clicou = await clicarBotaoComentarViaWS();

            if (clicou) {
                console.log("🤖 Robô: Aguardando comentários carregarem no DOM após o clique...");
                await sleepRandom(4000, 6000);

                if (!roboAtivo) break;

                const ehRelevante = await processarDescricaoVideo();

                if (!roboAtivo) break;

                if (ehRelevante) {
                    await processarRespostasSequenciais();
                } else {
                    console.log("🤖 Robô: Ignorando comentários deste vídeo, acionando próximo vídeo...");
                }

                if (!roboAtivo) break;

                await fecharComentariosEProximoVideo();

                console.log("⏳ Aguardando vídeo estabilizar antes de recomeçar o ciclo...");
                await sleepRandom(4000, 6500); // Tempo pra internet carregar o vídeo
            } else {
                console.log("❌ Falha ao tentar clicar no botão. Tentando novamente em 3s...");
                await sleep(6000);
            }
        }
        console.log("🛑 Automação interrompida totalmente.");
    }

    function pararAutomacao() {
        console.log("🤖 Robô: Automação PARADA.");
    }

    // -----------------------------------------------------------------
    // 7. CRIAÇÃO DO BOTÃO DE CONTROLE
    // -----------------------------------------------------------------
    function criarBotaoControle() {
        const BOTAO_ID = 'btn-robo-automacao';

        const botaoExistente = document.getElementById(BOTAO_ID);
        if (botaoExistente) {
            botaoExistente.remove();
        }

        const btn = document.createElement('button');
        btn.id = BOTAO_ID;
        btn.innerText = '▶️ Ligar Robô';

        Object.assign(btn.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: '999999',
            padding: '12px 20px',
            backgroundColor: '#4CAF50',
            color: 'white',
            border: 'none',
            borderRadius: '8px',
            fontWeight: 'bold',
            cursor: 'pointer',
            boxShadow: '0 4px 6px rgba(0,0,0,0.3)',
            transition: 'background-color 0.3s'
        });

        btn.addEventListener('click', () => {
            roboAtivo = !roboAtivo;

            if (roboAtivo) {
                btn.innerText = '⏹️ Parar Robô';
                btn.style.backgroundColor = '#F44336';
                iniciarAutomacao();
            } else {
                btn.innerText = '▶️ Ligar Robô';
                btn.style.backgroundColor = '#4CAF50';
                pararAutomacao();
            }
        });

        document.body.appendChild(btn);
    }

    criarBotaoControle();

})();