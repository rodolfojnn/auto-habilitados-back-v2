// // ----- Pegar lista de cidade
// JSON.stringify([...$0.querySelectorAll('.ng-option')].map(el => el.textContent.trim())) // no ng-dropdown-panel-items


// =================================================================
// CONFIGURAÇÕES INICIAIS
// =================================================================
const municipios = ["BRASILIA","ACARAPE","ACARAU","ACOPIARA","AIUABA","ALCANTARAS","ALTANEIRA","ALTO SANTO","AMONTADA","ANTONINA DO NORTE","APUIARES","AQUIRAZ","ARACATI","ARACOIABA","ARARENDA","ARARIPE","ARATUBA","ARNEIROZ","ASSARE","AURORA","BAIXIO","BANABUIU","BARBALHA","BARREIRA","BARRO","BARROQUINHA","BATURITE","BEBERIBE","BELA CRUZ","BOA VIAGEM","BREJO SANTO","CAMOCIM","CAMPOS SALES","CANINDE","CAPISTRANO","CARIDADE","CARIRE","CARIRIACU","CARIUS","CARNAUBAL","CASCAVEL","CATARINA","CATUNDA","CAUCAIA","CEDRO","CHAVAL","CHORO","CHOROZINHO","COREAU","CRATEUS","CRATO","CROATA","CRUZ","DEPUTADO IRAPUAN PINHEIRO","ERERE","EUSEBIO","FARIAS BRITO","FORQUILHA","FORTALEZA","FORTIM","FRECHEIRINHA","GENERAL SAMPAIO","GRACA","GRANJA","GRANJEIRO","GROAIRAS","GUAIUBA","GUARACIABA DO NORTE","GUARAMIRANGA","HIDROLANDIA","HORIZONTE","IBARETAMA","IBIAPINA","IBICUITINGA","ICAPUI","ICO","IGUATU","INDEPENDENCIA","IPAPORANGA","IPAUMIRIM","IPU","IPUEIRAS","IRACEMA","IRAUCUBA","ITAICABA","ITAITINGA","ITAPAJE","ITAPIPOCA","ITAPIUNA","ITAREMA","ITATIRA","JAGUARETAMA","JAGUARIBARA","JAGUARIBE","JAGUARUANA","JARDIM","JATI","JIJOCA DE JERICOACOARA","JUAZEIRO DO NORTE","JUCAS","LAVRAS DA MANGABEIRA","LIMOEIRO DO NORTE","MADALENA","MARACANAU","MARANGUAPE","MARCO","MARTINOPOLE","MASSAPE","MAURITI","MERUOCA","MILAGRES","MILHA","MIRAIMA","MISSAO VELHA","MOMBACA","MONSENHOR TABOSA","MORADA NOVA","MORAUJO","MORRINHOS","MUCAMBO","MULUNGU","NOVA OLINDA","NOVA RUSSAS","NOVO ORIENTE","OCARA","OROS","PACAJUS","PACATUBA","PACOTI","PACUJA","PALHANO","PALMACIA","PARACURU","PARAIPABA","PARAMBU","PARAMOTI","PEDRA BRANCA","PENAFORTE","PENTECOSTE","PEREIRO","PINDORETAMA","PIQUET CARNEIRO","PIRES FERREIRA","PORANGA","PORTEIRAS","POTENGI","POTIRETAMA","QUITERIANOPOLIS","QUIXADA","QUIXELO","QUIXERAMOBIM","QUIXERE","REDENCAO","RERIUTABA","RUSSAS","SABOEIRO","SALITRE","SANTA QUITERIA","SANTANA DO ACARAU","SANTANA DO CARIRI","SAO BENEDITO","SAO GONCALO DO AMARANTE","SAO JOAO DO JAGUARIBE","SAO LUIS DO CURU","SENADOR POMPEU","SENADOR SA","SOBRAL","SOLONOPOLE","TABULEIRO DO NORTE","TAMBORIL","TARRAFAS","TAUA","TEJUCUOCA","TIANGUA","TRAIRI","TURURU","UBAJARA","UMARI","UMIRIM","URUBURETAMA","URUOCA","VARJOTA","VARZEA ALEGRE","VICOSA DO CEARA"];
const URL_ALVO = "/portalservicos-ws/instrutor";

let resolverPromessaDados = null;

// Garante o reset dos protótipos originais
XMLHttpRequest.prototype.open = window._originalOpen || XMLHttpRequest.prototype.open;
XMLHttpRequest.prototype.send = window._originalSend || XMLHttpRequest.prototype.send;
if (!window._originalOpen) window._originalOpen = XMLHttpRequest.prototype.open;
if (!window._originalSend) window._originalSend = XMLHttpRequest.prototype.send;

XMLHttpRequest.prototype.open = function(method, url) { this._url = url; return window._originalOpen.apply(this, arguments); };

XMLHttpRequest.prototype.send = function(body) {
    this.addEventListener("load", function() {
        if (this._url.includes(URL_ALVO) && resolverPromessaDados) {
            try {
                const objetoResposta = JSON.parse(this.response);
                resolverPromessaDados(objetoResposta);
            } catch (e) {
                console.error("[Robô] Erro ao processar JSON:", e);
                resolverPromessaDados({ content: [], totalPages: 1 });
            }
            resolverPromessaDados = null;
        }
    });
    return window._originalSend.apply(this, arguments);
};

const esperarTempo = (ms) => new Promise(resolve => setTimeout(resolve, ms));
const esperarRespostaDoServidor = () => new Promise(resolve => { resolverPromessaDados = resolve; });

async function mudarParaPagina(numeroPagina) {
    const spans = Array.from(document.querySelectorAll('span'));
    const spanPagina = spans.find(el => el.textContent.trim() === 'Página');
    if (!spanPagina) return false;

    const brSelectPagina = spanPagina.nextElementSibling;
    if (!brSelectPagina || brSelectPagina.tagName !== 'BR-SELECT') return false;

    const inputPagina = brSelectPagina.querySelector('input[type="text"]');
    if (!inputPagina) return false;

    inputPagina.value = numeroPagina.toString();
    inputPagina.dispatchEvent(new Event('input', { bubbles: true }));
    inputPagina.dispatchEvent(new Event('change', { bubbles: true }));

    await esperarTempo(250);

    const option = document.querySelector('.ng-option.ng-option-marked') ||
                   document.querySelector('.ng-option.ng-option-selected');

    if (option) {
        option.click();
        return true;
    }
    return false;
}

// =================================================================
// FUNÇÃO PRINCIPAL DO ROBÔ (LISTA GERAL UNIFICADA)
// =================================================================
async function rodarRobo() {
    console.log("=== INICIANDO ROBÔ DE EXTRAÇÃO GERAL ===");

    // Esta array agora vai conter TODOS os instrutores do estado de forma direta
    const todosOsInstrutoresGeral = [];

    for (const municipio of municipios) {
        console.log(`\n[Município]: ${municipio}`);

        // 1. Altera o município no select principal
        const brSelect = document.querySelector('br-select[formcontrolname="municipio"]');
        if (!brSelect) { console.error("br-select não encontrado. Parando."); break; }

        const input = brSelect.querySelector('input[type="text"]');
        input.value = municipio;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        await esperarTempo(250);

        const option = document.querySelector('.ng-option.ng-option-marked') || document.querySelector('.ng-option.ng-option-selected');
        if (!option) { console.warn(`Opção "${municipio}" não apareceu. Pulando...`); continue; }
        option.click();
        await esperarTempo(150);

        // 2. Clica em Consultar para trazer a Página 1
        const botoes = document.querySelectorAll('button.br-button.primary.footer-button');
        const botaoConsultar = Array.from(botoes).find(el => el.textContent.trim() === 'Consultar');
        if (!botaoConsultar) { console.error("Botão consultar não encontrado."); break; }

        let promessaDados = esperarRespostaDoServidor();
        botaoConsultar.click();
        console.log(`[Página 1] Aguardando dados iniciais...`);

        let respostaServidor = await promessaDados;

        // Adiciona os instrutores da página 1 direto no listão geral
        if (respostaServidor.content && respostaServidor.content.length > 0) {
            todosOsInstrutoresGeral.push(...respostaServidor.content);
        }

        const totalPaginas = respostaServidor.totalPages || 1;
        console.log(`[Info] ${municipio} possui ${totalPaginas} página(s).`);

        // 3. Sub-loop para percorrer as demais páginas (se houverem)
        for (let paginaAtual = 2; paginaAtual <= totalPaginas; paginaAtual++) {
            console.log(`[Página ${paginaAtual}] Mudando de página...`);

            promessaDados = esperarRespostaDoServidor();
            const mudouComSucesso = await mudarParaPagina(paginaAtual);

            if (mudouComSucesso) {
                console.log(`[Página ${paginaAtual}] Aguardando resposta do servidor...`);
                respostaServidor = await promessaDados;

                // Despeja os instrutores das outras páginas direto no listão geral também
                if (respostaServidor.content && respostaServidor.content.length > 0) {
                    todosOsInstrutoresGeral.push(...respostaServidor.content);
                    console.log(`[Página ${paginaAtual}] Mais ${respostaServidor.content.length} registros adicionados ao listão.`);
                }
            } else {
                console.warn(`[Aviso] Falha ao ir para a página ${paginaAtual}.`);
                break;
            }
            await esperarTempo(1500);
        }

        console.log(`[Parcial] Progresso atual do listão: ${todosOsInstrutoresGeral.length} instrutores acumulados.`);
        await esperarTempo(2000);
    }

    // Código final unificado conforme solicitado:
    console.log("\n=== ROBÔ FINALIZADO COM SUCESSO ===");
    console.log(JSON.stringify(todosOsInstrutoresGeral));
    copy(todosOsInstrutoresGeral);
    console.log("Copiado com sucesso para a área de transferência!");
}

// Inicia o processo
rodarRobo();