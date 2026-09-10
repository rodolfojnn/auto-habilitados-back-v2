(function () {
    'use strict';

    // Estado da automação
    let roboAtivo = false;
    // Lista (Set) para armazenar os @usuarios já processados
    const usuariosProcessados = new Set();

    // Função utilitária para pausar a execução (Delay)
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    // -----------------------------------------------------------------
    // 1. MOCK DE ENVIO PARA IA (SIMULAÇÃO)
    // -----------------------------------------------------------------
    async function enviarParaIAMock(usuario, texto) {
        console.log(`🧠 IA: Enviando comentário de @${usuario} para processamento...`);
        console.log(`📩 [PAYLOAD IA]: "${texto}"`);

        // Simula o tempo de resposta da API da IA (3 segundos)
        await sleep(3000);

        console.log(`✅ IA: Resposta da IA recebida para @${usuario}!`);
    }

    // -----------------------------------------------------------------
    // 2. PROCESSAR E EXIBIR A DESCRIÇÃO DO VÍDEO
    // -----------------------------------------------------------------
    async function processarDescricaoVideo() {
        const todasDivsTexto = document.querySelectorAll('div[style*="display: inline"]');

        if (todasDivsTexto.length === 0) {
            console.log("🤖 Robô: Nenhuma descrição/legenda encontrada.");
            return;
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
                await sleep(800);
            }
        }

        const textoCompletoLegenda = divLegenda.innerText.replace(/\s*\n?mais$/, '').trim();

        console.log("==========================================");
        console.log("📝 DESCRIÇÃO DO VÍDEO:");
        console.log(textoCompletoLegenda);
        console.log("==========================================");
    }

    // -----------------------------------------------------------------
    // 3. EXTRAIR NOME DE USUÁRIO E TEXTO DO COMENTÁRIO
    // -----------------------------------------------------------------
    function obterDadosDoComentario(spanResponder) {
        let ancestral = spanResponder.parentElement;
        let usuario = null;
        let textoComentario = "";

        for (let i = 0; i < 10; i++) {
            if (!ancestral) break;

            // Busca o link de perfil
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

            // Busca a div do texto do comentário
            if (!textoComentario) {
                const divTexto = ancestral.querySelector('div[style*="display: inline"]');
                if (divTexto) {
                    textoComentario = divTexto.innerText.replace(/\s*\n?mais$/, '').trim();
                }
            }

            if (usuario && textoComentario) break;
            ancestral = ancestral.parentElement;
        }

        // Conta a quantidade de palavras no comentário
        const palavras = textoComentario.trim().split(/\s+/).filter(Boolean);
        const qtdPalavras = palavras.length;

        return { usuario, textoComentario, qtdPalavras };
    }

    // -----------------------------------------------------------------
    // 4. FLUXO SEQUENCIAL BASEADO EM NOME DE USUÁRIO E TAMANHO DO TEXTO
    // -----------------------------------------------------------------
    async function processarRespostasSequenciais() {
        let contador = 1;

        while (roboAtivo) {
            // Re-mapeia os botões "Responder" no DOM atual
            const todosSpans = Array.from(document.querySelectorAll('span'));
            const botoesResponder = todosSpans.filter(span => span.innerText.trim() === 'Responder');

            let proximoSpan = null;
            let usuarioAtual = null;
            let textoAtual = "";

            // Percorre os botões até achar um usuário válido e não processado
            for (const span of botoesResponder) {
                const { usuario, textoComentario, qtdPalavras } = obterDadosDoComentario(span);

                if (usuario && !usuariosProcessados.has(usuario)) {
                    // Se tiver menos de 5 palavras, marca como processado e pula
                    if (qtdPalavras < 5) {
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

            // Se não restarem comentários elegíveis de novos usuários, encerra
            if (!proximoSpan || !usuarioAtual) {
                console.log("🤖 Robô: Todos os comentários elegíveis visíveis já foram processados.");
                break;
            }

            // ADICIONA O USUÁRIO À LISTA IMEDIATAMENTE PARA BLOQUEAR REPETIÇÕES
            usuariosProcessados.add(usuarioAtual);

            const btnResponder = proximoSpan.closest('[role="button"]') || proximoSpan;

            // Centraliza a visão no comentário
            btnResponder.scrollIntoView({ behavior: 'smooth', block: 'center' });
            await sleep(800);

            if (!roboAtivo) break;

            // 1. Clica em 'Responder'
            btnResponder.click();

            // 2. CONSOLE.LOG DO COMENTÁRIO EXTRAÍDO
            console.log(`🤖 Robô: [${contador}] Respondedor clicado para @${usuarioAtual}`);
            console.log(`💬 Comentário de @${usuarioAtual}: "${textoAtual}"`);

            // 3. MOCK DA IA - Aguarda 3 segundos simulando a chamada
            await enviarParaIAMock(usuarioAtual, textoAtual);

            if (!roboAtivo) break;

            // 4. Aguarda o formulário estar pronto antes de fechar
            await sleep(500);

            // 5. Clica no botão 'X' (Fechar)
            const svgFechar = document.querySelector('svg[aria-label="Fechar"]');
            if (svgFechar) {
                const btnFechar = svgFechar.closest('[role="button"]') || svgFechar;
                btnFechar.click();
                console.log(`🤖 Robô: [${contador}] Fechado formulário para @${usuarioAtual}`);
            } else {
                console.log(`🤖 Robô: Botão 'Fechar' não encontrado para @${usuarioAtual}`);
            }

            contador++;

            // 6. Pausa antes de ir para o próximo comentário
            await sleep(1500);
        }

        console.log("🤖 Robô: Finalizou o processamento de todos os comentários.");
    }

    // -----------------------------------------------------------------
    // 5. AÇÕES DE INTERAÇÃO NA PÁGINA
    // -----------------------------------------------------------------
    function clicarBotaoComentar() {
        const svgComentar = document.querySelector('svg[aria-label="Comentar"]');

        if (svgComentar) {
            const botao = svgComentar.closest('[role="button"]');

            if (botao) {
                botao.click();
                console.log("🤖 Robô: Clique no botão 'Comentar' efetuado com sucesso.");
                return true;
            }
        }
        console.log("🤖 Robô: Botão de comentar não foi encontrado.");
        return false;
    }

    async function iniciarAutomacao() {
        console.log("🤖 Robô: Automação INICIADA.");
        usuariosProcessados.clear(); // Limpa a lista a cada nova execução

        const clicou = clicarBotaoComentar();

        if (clicou) {
            console.log("🤖 Robô: Aguardando comentários carregarem no DOM...");
            await sleep(1500);

            if (!roboAtivo) return;

            // 1. Copia e imprime a descrição do vídeo
            await processarDescricaoVideo();

            if (!roboAtivo) return;

            // 2. Processa cada comentário garantindo 1 por Usuário e exibe o texto
            await processarRespostasSequenciais();
        }
    }

    function pararAutomacao() {
        console.log("🤖 Robô: Automação PARADA.");
    }

    // -----------------------------------------------------------------
    // 6. CRIAÇÃO / REUTILIZAÇÃO DO BOTÃO DE CONTROLE
    // -----------------------------------------------------------------
    function criarBotaoControle() {
        const BOTAO_ID = 'btn-robo-automacao';

        const botaoExistente = document.getElementById(BOTAO_ID);
        if (botaoExistente) {
            botaoExistente.remove();
        }

        const btn = document.createElement('button');
        btn.id = BOTAO_ID;
        btn.innerText = '🤖 Ligar Robô';

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
                btn.innerText = '🛑 Parar Robô';
                btn.style.backgroundColor = '#F44336';
                iniciarAutomacao();
            } else {
                btn.innerText = '🤖 Ligar Robô';
                btn.style.backgroundColor = '#4CAF50';
                pararAutomacao();
            }
        });

        document.body.appendChild(btn);
    }

    criarBotaoControle();

})();