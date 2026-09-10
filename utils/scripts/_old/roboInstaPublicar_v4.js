(function () {
    'use strict';

    // ===== CONFIGURAÇÕES MANUAIS =====
    // Defina a descrição do vídeo aqui:
    const DESCRICAO_DO_VIDEO = "Esta é a descrição do vídeo para a IA analisar o contexto.";

    // ===== WebSocket =====
    const WS_URL = 'https://bd57-38-43-101-128.ngrok-free.app';
    let ws = null;
    const pendingPromises = {};

    function conectarWebSocket() {
        console.log(`🔌 Conectando ao servidor WebSocket em: ${WS_URL}...`);
        ws = new WebSocket(WS_URL);
        ws.onopen = () => console.log('✅ Conectado ao servidor WebSocket local!');
        ws.onmessage = (e) => {
            try {
                const data = JSON.parse(e.data);
                if (data.action && pendingPromises[data.action]) {
                    pendingPromises[data.action](data);
                    delete pendingPromises[data.action];
                }
            } catch (err) {}
        };
        ws.onclose = () => {
            console.warn('🔴 WebSocket desconectado. Reconectando em 3s...');
            // NOVO: Destrava o robô instantaneamente se o servidor local cair ou reiniciar
            for (const key in pendingPromises) {
                pendingPromises[key]({ reply: "SEM_RESPOSTA" });
                delete pendingPromises[key];
            }
            setTimeout(conectarWebSocket, 3000);
        };
    }
    conectarWebSocket();

    const sleep = (ms) => new Promise(r => setTimeout(r, ms));

    function enviarComandoWS(comando) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify(comando));
        } else {
            console.warn('⚠️ WebSocket não conectado. Comando não enviado:', comando);
        }
    }

    // ===== INTEGRAÇÃO COM A IA =====
    function pedirRespostaIA(descricao, comentario) {
        return new Promise((resolve) => {
            const actionName = 'generate_reply';

            pendingPromises[actionName] = resolve;

            const comando = { action: actionName, description: descricao, comment: comentario };
            enviarComandoWS(comando);
            console.log(`🧠 Enviado para IA →`, comando);

            // Timeout de 20 segundos
            setTimeout(() => {
                if (pendingPromises[actionName]) {
                    delete pendingPromises[actionName];
                    console.warn(`⏳ Timeout! A IA não respondeu a tempo para o comentário.`);
                    resolve({ reply: "SEM_RESPOSTA" });
                }
            }, 20000);
        });
    }

    // ===== BOLINHA VERMELHA =====
    let bolinhaElement = null;
    function criarBolinha() {
        if (bolinhaElement) return;
        const el = document.createElement('div');
        el.style.cssText = `
            position: fixed;
            width: 24px;
            height: 24px;
            background: red;
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(255,0,0,0.9);
            pointer-events: none;
            z-index: 999999;
            transform: translate(-50%, -50%);
            animation: pulse-bolinha 1s infinite ease-in-out;
        `;
        if (!document.getElementById('bolinha-style')) {
            const style = document.createElement('style');
            style.id = 'bolinha-style';
            style.textContent = `
                @keyframes pulse-bolinha {
                    0% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.7; }
                    50% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
                    100% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.7; }
                }
            `;
            document.head.appendChild(style);
        }
        document.body.appendChild(el);
        bolinhaElement = el;
    }
    function atualizarBolinha(x, y) {
        if (!bolinhaElement) criarBolinha();
        bolinhaElement.style.left = x + 'px';
        bolinhaElement.style.top = y + 'px';
    }
    function removerBolinha() {
        if (bolinhaElement) {
            bolinhaElement.remove();
            bolinhaElement = null;
        }
    }

    // ===== EXTRAÇÃO DE COMENTÁRIOS =====
    function extrairComentariosDoDialog(dialog) {
        const comentarios = [];
        const links = dialog.querySelectorAll('a[href^="/"]:not([href*="explore"])');
        const vistos = new Set();

        for (const link of links) {
            let bloco = link.closest('div[class*="html-div"]');
            if (!bloco) continue;

            const nomeSpan = bloco.querySelector('._ap3a');
            const nome = nomeSpan ? nomeSpan.textContent.trim() : link.textContent.trim();

            let texto = '';

            const elementosTexto = bloco.querySelectorAll('span[dir="auto"], div[dir="auto"]');
            for (const el of elementosTexto) {
                if (el.closest('a[href^="/"]')) continue;
                const txt = el.textContent.trim();
                if (!txt || txt === nome) continue;

                if (/^\d+\s*(s|m|h|d|w|semana|dia|hora|minuto|curtida|resposta)s?/i.test(txt)) continue;
                if (/^(Responder|Reply|Editado|Edited)$/i.test(txt)) continue;
                if (/^Ver todas as \d+ respostas?$/i.test(txt)) continue;

                texto = txt;
                break;
            }

            if (!texto) {
                const linhas = (bloco.innerText || '').split('\n').map(l => l.trim()).filter(Boolean);
                for (const linha of linhas) {
                    if (linha === nome) continue;
                    if (/^\d+\s*(s|m|h|d|w|semana|dia|hora|minuto|curtida|resposta)s?/i.test(linha)) continue;
                    if (/^(Responder|Reply|Editado|Edited)$/i.test(linha)) continue;
                    if (/^Ver todas as/i.test(linha)) continue;
                    texto = linha;
                    break;
                }
            }

            if (!nome || !texto) continue;

            let responderEl = null;
            const spansAll = bloco.querySelectorAll('span, div');
            for (const span of spansAll) {
                const spanTxt = span.textContent.trim();
                if (spanTxt === 'Responder' || spanTxt === 'Reply') {
                    // NOVO: Verifica se o elemento tem tamanho real na tela (ignora tags ocultas do Instagram)
                    const rect = span.getBoundingClientRect();
                    if (rect.width > 0 && rect.height > 0) {
                        responderEl = span.closest('[role="button"]') || span;
                        break;
                    }
                }
            }

            const id = `${nome}|${texto}`;

            if (!vistos.has(id)) {
                vistos.add(id);
                comentarios.push({ nome, texto, elemento: bloco, responderEl });
            }
        }
        return comentarios;
    }

    // ===== PERCORRER COMENTÁRIOS COM CLICK E IA =====
    // ===== PERCORRER COMENTÁRIOS COM CLICK E IA =====
    async function percorrerComentariosDoDialog(dialog, idSessao) {
        const comentarios = extrairComentariosDoDialog(dialog);
        if (comentarios.length === 0) {
            console.warn('❌ Nenhum comentário encontrado.');
            return;
        }
        console.log(`✅ Encontrados ${comentarios.length} comentários.`);

        for (let i = 0; i < comentarios.length; i++) {
            if (!roboAtivo || sessaoAtual !== idSessao) {
                console.log('🛑 Automação interrompida durante o percurso.');
                removerBolinha();
                return;
            }

            const { nome, texto, elemento, responderEl } = comentarios[i];

            // 🛡️ VERIFICAÇÃO DE SEGURANÇA: Se o elemento já foi removido junto com algum pai anterior, pula
            if (!document.body.contains(elemento)) {
                console.warn(`⚠️ Comentário de @${nome} não existe mais no DOM (removido em bloco anterior). Pulando...`);
                continue;
            }

            // 1. Rola até o comentário
            elemento.scrollIntoView({ behavior: 'smooth', block: 'center' });
            await sleep(800);

            let cx = 0, cy = 0;
            if (responderEl && document.body.contains(responderEl)) {
                const rect = responderEl.getBoundingClientRect();
                if (rect.width > 0) { // Garante que é um botão visível
                    cx = rect.left + rect.width / 2;
                    cy = rect.top + rect.height / 2;
                }
            }

            // Fallback: Se não achou o "Responder", clica no meio do comentário
            if (cx === 0 || cy === 0) {
                const rect = elemento.getBoundingClientRect();
                cx = rect.left + rect.width / 2;
                cy = rect.top + rect.height / 2;
            }

            atualizarBolinha(cx, cy);

            // 2. Envia o clique
            const x = Math.round(cx);
            const y = Math.round(cy);
            console.log(`🖱️ Clicando nas coordenadas (${x}, ${y}) no comentário de @${nome}`);
            enviarComandoWS({ action: 'click_at', x, y });

            await sleep(2500);

            // 3. 🔥 FILTRO: Ignora comentários com menos de 5 palavras
            const numeroDePalavras = texto.trim().split(/\s+/).length;
            if (numeroDePalavras < 5) {
                console.log(`⏭️ IA Ignorada ${i+1}/${comentarios.length} - @${nome}: Menos de 5 palavras ("${texto}")`);

                // Remove o bloco
                const alvoRemocao = elemento.parentElement?.parentElement || elemento;
                alvoRemocao.remove();

                // ⏳ TIMER CRUCIAL: Aguarda o layout se reajustar antes de ir para o próximo
                await sleep(1500);
                continue;
            }

            // 4. 🔥 INTEGRAÇÃO IA (5 palavras ou mais)
            const respostaServidor = await pedirRespostaIA(DESCRICAO_DO_VIDEO, texto);
            const respostaIA = respostaServidor.reply || "SEM_RESPOSTA";

            if (respostaIA.toUpperCase() !== "SEM_RESPOSTA") {
                console.log(`   → { "action": "paste_comment", "text": "${respostaIA}" }`);
                enviarComandoWS({ action: 'paste_comment', text: respostaIA });
                await sleep(4000);
            } else {
                console.log(`🤖 IA avaliou e retornou SEM_RESPOSTA para @${nome}.`);
            }

            // Pausa de respiro
            await sleep(2000);

            // Remove o bloco processado
            const alvoRemocao = elemento.parentElement?.parentElement || elemento;
            alvoRemocao.remove();

            // ⏳ TIMER CRUCIAL: Aguarda o layout da página estabilizar após deletar o nó do DOM
            await sleep(1500);
        }
        console.log('🏁 Todos os comentários percorridos!');
        removerBolinha();
    }

    // ===== LOOP PRINCIPAL =====
    let roboAtivo = false;
    let sessaoAtual = 0;

    async function iniciarAutomacao() {
        if (roboAtivo) return;
        roboAtivo = true;
        sessaoAtual++;
        const idSessao = sessaoAtual;
        console.log("🤖 Modo PERCORRER COMENTÁRIOS INICIADO (sessão " + idSessao + ").");

        while (roboAtivo && sessaoAtual === idSessao) {
            let dialog = document.querySelector('div[role="dialog"]');
            let tentativas = 0;
            while (!dialog && roboAtivo && sessaoAtual === idSessao && tentativas < 10) {
                console.log('⏳ Aguardando diálogo de comentários...');
                await sleep(2000);
                dialog = document.querySelector('div[role="dialog"]');
                tentativas++;
            }

            if (!roboAtivo || sessaoAtual !== idSessao) break;

            if (!dialog) {
                console.warn('⚠️ Diálogo não encontrado. Aguardando 3s...');
                await sleep(3000);
                continue;
            }

            await percorrerComentariosDoDialog(dialog, idSessao);

            if (!roboAtivo || sessaoAtual !== idSessao) break;

            console.log('🔄 Reiniciando percurso (novos comentários?)...');
            await sleep(3000);
        }

        removerBolinha();
        if (sessaoAtual === idSessao) {
            console.log('🔴 Automação finalizada normalmente.');
        } else {
            console.log('🛑 Automação interrompida pelo usuário.');
        }
    }

    function pararAutomacao() {
        roboAtivo = false;
        sessaoAtual++;
        removerBolinha();
        console.log("🛑 Comando de PARADA executado.");
    }

    // ===== BOTÃO =====
    function criarBotao() {
        const id = 'btn-robo-automacao';
        const old = document.getElementById(id);
        if (old) old.remove();

        const btn = document.createElement('button');
        btn.id = id;
        btn.innerText = '📋 Ligar Robô (Percorrer)';
        Object.assign(btn.style, {
            position: 'fixed', top: '20px', right: '20px', zIndex: '999999',
            padding: '12px 20px', backgroundColor: '#4CAF50', color: 'white',
            border: 'none', borderRadius: '8px', fontWeight: 'bold', cursor: 'pointer'
        });
        btn.addEventListener('click', () => {
            if (roboAtivo) {
                pararAutomacao();
                btn.innerText = '📋 Ligar Robô (Percorrer)';
                btn.style.backgroundColor = '#4CAF50';
            } else {
                btn.innerText = '⏹️ Parar Robô';
                btn.style.backgroundColor = '#F44336';
                iniciarAutomacao();
            }
        });
        document.body.appendChild(btn);
    }
    criarBotao();
})();