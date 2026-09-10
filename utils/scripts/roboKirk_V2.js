// ==UserScript==
// @name         Buscas de Renach
// @namespace    http://tampermonkey.net/
// @version      8.2
// @match        https://www.habilitacao.detran.pr.gov.br/*
// @grant        none
// ==/UserScript==

(function() {
    'use strict';

    // --- 1. CONFIGURAÇÃO DO LOCALSTORAGE ---
    const getEstadoAutomacao = () => localStorage.getItem('automacao_ativa') === 'true';
    const setEstadoAutomacao = (status) => localStorage.setItem('automacao_ativa', status);

    const getUltimoRenach = () => localStorage.getItem('ultimo_renach') || '';
    const setUltimoRenach = (renach) => localStorage.setItem('ultimo_renach', renach);

    // Métodos para persistência e leitura das etapas de coleta
    const salvarDadosEtapa1 = (dados) => localStorage.setItem('dados_guia_etapa1', JSON.stringify(dados));
    const getDadosEtapa1 = () => JSON.parse(localStorage.getItem('dados_guia_etapa1')) || {};

    const salvarDadosEtapa2 = (dados) => localStorage.setItem('dados_pdf_etapa2', JSON.stringify(dados));
    const getDadosEtapa2 = () => JSON.parse(localStorage.getItem('dados_pdf_etapa2')) || {};

    // Limpeza de cache pós-envio
    const limparDadosTemporarios = () => {
        localStorage.removeItem('dados_guia_etapa1');
        localStorage.removeItem('dados_pdf_etapa2');
        localStorage.removeItem('ultimo_renach');
    };

    // FUNÇÃO AUXILIAR DE DELAY ALEATÓRIO (Entre 5 e 10 segundos)
    const esperarTempoAleatorio = () => {
        // 15000 (15s) - 5000 (5s) = 10000
        const tempo = Math.floor(Math.random() * (15000 - 5000 + 1)) + 5000;
        console.log(`Aguardando um tempo aleatório de ${(tempo / 1000).toFixed(1)} segundos antes da requisição...`);
        return new Promise(resolve => setTimeout(resolve, tempo));
    };

    // --- 2. CRIAÇÃO DO BOTÃO ABSOLUTO E TRANSPARENTE ---
    const btnControle = document.createElement('button');
    btnControle.id = 'btn-automacao-flutuante';
    btnControle.textContent = "Iniciar";

    Object.assign(btnControle.style, {
        position: 'absolute',
        top: '10px',
        right: '20px',
        zIndex: '999999',
        fontFamily: 'sans-serif',
        color: '#fff',
        border: 'none',
        padding: '10px 20px',
        fontSize: '13px',
        fontWeight: 'bold',
        borderRadius: '4px',
        cursor: 'pointer',
        backgroundColor: '#10b981',
        boxShadow: '0 2px 5px rgba(0,0,0,0.15)',
        opacity: '0.7',
        transition: 'opacity 0.2s ease, background-color 0.2s'
    });

    btnControle.addEventListener('mouseenter', () => { btnControle.style.opacity = '1'; });
    btnControle.addEventListener('mouseleave', () => { btnControle.style.opacity = '0.7'; });

    const injetarBotao = () => {
        if (document.body && !document.getElementById('btn-automacao-flutuante')) {
            document.body.appendChild(btnControle);
            inicializarInterface();
        }
    };

    if (document.body) {
        injetarBotao();
    } else {
        const observer = new MutationObserver(() => {
            if (document.body) {
                injetarBotao();
                observer.disconnect();
            }
        });
        observer.observe(document.documentElement, { childList: true });
    }

    // --- 3. BIBLIOTECA PDF.JS EXTERNA ---
    function carregarPdfJs(callback) {
        if (window.pdfjsLib) { callback(); return; }
        console.log("Carregando biblioteca de extração de PDF...");
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
        script.onload = () => {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            callback();
        };
        document.head.appendChild(script);
    }

    async function extrairTextoPdf(arrayBuffer) {
        try {
            const loadingTask = window.pdfjsLib.getDocument({ data: arrayBuffer });
            const pdf = await loadingTask.promise;
            let textoCompleto = "";

            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                const textoPagina = textContent.items.map(item => item.str).join(" ");
                textoCompleto += textoPagina + " ";
            }

            // Regex para extração dos dados do PDF
            const regexTelefone = /\(\d{2}\)\s?\d{4,5}-\d{4}/g;
            const telefonesEncontrados = textoCompleto.match(regexTelefone);
            const telefone = telefonesEncontrados ? telefonesEncontrados[0] : "Não encontrado";

            const regexEmail = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
            const emailsEncontrados = textoCompleto.match(regexEmail);
            const email = emailsEncontrados ? emailsEncontrados[0] : "Não encontrado";

            const regexCep = /\b\d{2}\.?\d{3}-\d{3}\b/g;
            const cepsEncontrados = textoCompleto.match(regexCep);
            const cep = cepsEncontrados ? cepsEncontrados[0] : "Não encontrado";

            const dadosPdf = {
                telefone: telefone,
                email: email,
                cep: cep
            };

            // Salva dados da Etapa 2
            salvarDadosEtapa2(dadosPdf);
            console.log("Etapa 2 concluída: Dados do PDF guardados.");

            // COMPLEMENTO FINAL: Dispara o envio do pacote consolidado para a API externa
            await enviarDadosParaApiFinal();

        } catch (erro) {
            console.error("Erro crítico ao decodificar o PDF:", erro);
        }
    }

    // --- 4. FUNÇÃO CENTRALIZADA DE LOOP (MECANISMO DE BUSCA) ---
    function buscarEExecutarProximoRenach() {
        if (!getEstadoAutomacao()) return;

        console.log("Buscando número de RENACH na API...");

        fetch('https://api.dirigiragora.com.br/v1/simulado/getRenach', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(response => {
            if (!response.ok) throw new Error(`Erro na API (${response.status})`);
            return response.json();
        })
        .then(data => {
            if (data && data.renach) {
                const renachLimpo = data.renach.replace(/\D/g, '');

                setUltimoRenach(renachLimpo);
                console.log(`Próximo RENACH encontrado: ${renachLimpo}. Redirecionando...`);

                enviarFormularioDetran(renachLimpo);
            } else {
                // MUDANÇA AQUI: Aguarda e tenta novamente em vez de desligar
                console.warn("Nenhum RENACH disponível na fila. Tentando novamente em 10 segundos...");
                setTimeout(() => {
                    buscarEExecutarProximoRenach();
                }, 10000);
            }
        })
        .catch(error => {
            // MUDANÇA AQUI: Também continua tentando se houver uma instabilidade na rede/API
            console.warn(`Falha na comunicação com a API: ${error.message}. Tentando novamente em 10 segundos...`);
            setTimeout(() => {
                buscarEExecutarProximoRenach();
            }, 10000);
        });
    }

    function enviarFormularioDetran(renachApenasDigitos) {
        console.log(`Enviando formulário Detran com o RENACH: ${renachApenasDigitos}...`);

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'https://www.habilitacao.detran.pr.gov.br/detran-habilitacao/reemissaoSSHGRD.do?action=obterProcesso';
        form.target = '_self';

        const payload = `usuarioPublico=true&page=1&numProcesso=${renachApenasDigitos}`;
        const params = new URLSearchParams(payload);

        for (const [key, value] of params) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }

    function desativarAutomacaoVisualmente() {
        setEstadoAutomacao(false);
        limparDadosTemporarios();
        if (btnControle) {
            btnControle.textContent = "Iniciar";
            btnControle.style.backgroundColor = '#10b981';
        }
    }

    // --- 5. ENVIO CONSOLIDADO PARA A API FINAL E REINÍCIO ---
    async function enviarDadosParaApiFinal() {
        console.log("Preparando consolidação de dados para envio à API...");

        const etapa1 = getDadosEtapa1();
        const etapa2 = getDadosEtapa2();
        const renach = getUltimoRenach();

        const payloadFinal = {
            renach: renach,
            utr: etapa1.utr || "Não encontrado",
            motivo: etapa1.motivo || "Não encontrado",
            categoria: etapa1.categoria || "Não encontrado",
            dataEmissao: etapa1.dataEmissao || "Não encontrado",
            telefone: etapa2.telefone || "Não encontrado",
            email: etapa2.email || "Não encontrado",
            cep: etapa2.cep || "Não encontrado"
        };

        console.log("Enviando Payload Final para o servidor:", payloadFinal);

        try {
            const response = await fetch('https://api.dirigiragora.com.br/v1/simulado/postRenach', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payloadFinal)
            });

            if (!response.ok) throw new Error(`Erro no envio do POST (${response.status})`);

            const respostaApi = await response.json();
            console.log("%c=== AUTOMAÇÃO CONCLUÍDA COM SUCESSO ===", "background: #10b981; color: #fff; font-size: 13px; font-weight: bold;");
            console.log("Resposta da API Simulado:", respostaApi);

            // Limpa o cache da rodada anterior para não misturar os dados
            limparDadosTemporarios();

            // REINÍCIO DO LOOP: Aguarda 2 segundos para dar tempo de ver o log e busca o próximo da fila
            console.log("Aguardando o próximo ciclo...");
            setTimeout(() => {
                buscarEExecutarProximoRenach();
            }, 2000);

        } catch (error) {
            console.error("Falha crítica ao enviar dados para a API Final. O loop foi interrompido.", error);
            desativarAutomacaoVisualmente();
        }
    }

    // --- 6. EXECUÇÃO INTEGRADA DAS ETAPAS 1 E 2 ---
    function executarFluxoDeCaptura() {
        console.log("Iniciando varredura da página...");

        // --- ETAPA 1: Captura da UTR e Dados da Tabela ---
        let utrEncontrada = "Não encontrado";

        const celulasLabel = Array.from(document.querySelectorAll('.form_label'));
        const celulaUtr = celulasLabel.find(el => el.textContent.trim() === 'UTR');
        if (celulaUtr && celulaUtr.nextElementSibling) {
            utrEncontrada = celulaUtr.nextElementSibling.textContent.trim();
        }

        const titulos = Array.from(document.querySelectorAll('h2'));
        const tituloGuias = titulos.find(el => el.textContent.includes('GUIAS REEMITIR'));

        if (!tituloGuias) {
            console.log("Tabela de guias não localizada. Ignorando raspagem de dados.");
            return;
        }

        const tabelaGuias = tituloGuias.nextElementSibling;
        if (tabelaGuias && tabelaGuias.tagName === 'TABLE') {
            const linhas = tabelaGuias.querySelectorAll('tr');
            if (linhas.length > 1) {
                const colunas = linhas[1].querySelectorAll('td');
                if (colunas.length >= 3) {
                    const dadosEtapa1 = {
                        utr: utrEncontrada,
                        motivo: colunas[0].textContent.trim(),
                        categoria: colunas[1].textContent.trim(),
                        dataEmissao: colunas[2].textContent.trim()
                    };
                    salvarDadosEtapa1(dadosEtapa1);
                    console.log("Etapa 1 concluída: Dados da tabela e UTR guardados.");
                }
            }
        }

        // --- ETAPA 2: Requisição em Segundo Plano e Leitura do PDF ---
        carregarPdfJs(async () => {
            const formOriginal = document.forms[0] || document.reemissaoSshForm;
            if (!formOriginal) {
                console.error("Formulário do Detran não encontrado para requisições de PDF.");
                return;
            }

            const campoGuia = formOriginal.guiaSel;
            if (campoGuia) {
                if (campoGuia.length > 1) campoGuia[0].checked = true;
                else campoGuia.checked = true;
            }

            const formData = new FormData(formOriginal);
            formData.set('page', '2');

            try {
                // Aguarda tempo aleatório de 5 a 10s antes do Request 1
                await esperarTempoAleatorio();
                console.log("Fazendo Request 1: Sincronizando conclusão do processo...");
                const resConcluir = await fetch("https://www.habilitacao.detran.pr.gov.br/detran-habilitacao/reemissaoSSHGRD.do?action=concluirProcesso", {
                    method: 'POST',
                    body: formData
                });

                if (!resConcluir.ok) throw new Error(`Erro no status da conclusão: ${resConcluir.status}`);

                // Aguarda tempo aleatório de 5 a 10s antes do Request 2
                await esperarTempoAleatorio();
                console.log("Fazendo Request 2: Baixando fluxo de dados do PDF...");
                const resPDF = await fetch("https://www.habilitacao.detran.pr.gov.br/detran-habilitacao/reemissaoSSHGRD.do?action=exibirRelatorio", {
                    method: 'GET'
                });

                if (!resPDF.ok) throw new Error(`Erro no status do relatório: ${resPDF.status}`);

                const buffer = await resPDF.arrayBuffer();

                const arr = new Uint8Array(buffer.slice(0, 4));
                const header = String.fromCharCode(...arr);

                // === AQUI ESTÁ A MUDANÇA PRINCIPAL ===
                if (header !== "%PDF") {
                    console.error("O retorno obtido da requisição não é um PDF válido. Pulando para o próximo...");
                    limparDadosTemporarios(); // Limpa os dados parciais
                    setTimeout(() => {
                        buscarEExecutarProximoRenach(); // Chama o próximo da fila
                    }, 2000);
                    return;
                }

                await extrairTextoPdf(buffer);

            } catch (error) {
                // === E AQUI O SCRIPT CONTINUA CASO A REQUISIÇÃO FALHE GERAL ===
                console.error("Falha ao executar o fluxo de captura de requisições:", error);
                console.log("Reiniciando o ciclo para o próximo RENACH após o erro...");
                limparDadosTemporarios();
                setTimeout(() => {
                    buscarEExecutarProximoRenach();
                }, 2000);
            }
        });
    }

    // --- 7. GERENCIAMENTO LOGIC / INTERFACE ---
    function inicializarInterface() {

        function configurarBotaoAtivo(ativo) {
            if (ativo) {
                btnControle.textContent = "Parar";
                btnControle.style.backgroundColor = '#ef4444';
            } else {
                btnControle.textContent = "Iniciar";
                btnControle.style.backgroundColor = '#10b981';
            }
        }

        btnControle.addEventListener('click', () => {
            const ativa = getEstadoAutomacao();

            if (ativa) {
                console.log('Automação interrompida pelo usuário.');
                desativarAutomacaoVisualmente();
            } else {
                setEstadoAutomacao(true);
                configurarBotaoAtivo(true);
                // Inicia o loop contínuo chamando o buscador pela primeira vez
                buscarEExecutarProximoRenach();
            }
        });

        // --- VERIFICAÇÃO PÓS-REFRESH ---
        if (getEstadoAutomacao()) {
            configurarBotaoAtivo(true);
            console.log(`Automação Ativa. Processando RENACH: ${getUltimoRenach()}`);

            executarFluxoDeCaptura();
        }
    }

})();