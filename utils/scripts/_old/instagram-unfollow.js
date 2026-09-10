// REMOVER QUEM NÃO ESTÁ NA MINHA LISTA DE SEGUIDORES
(async () => {
    const listaSeguidores = ["giselle.katia","stromm.studio","dii_gao","viciusni","julci_rs","janacruzara","fabionagano_","lorenna_bernardi","alinefbomfim","rcmarchis","raphael_friedel_","73xande","cristianechyla","deh.mathias","silviodesouzajr","nilce_marise","roseborges238","maria_martins_cwb","dra.marillise","franfagundeskike","euagdapiasecki","anapnicktho","momcdp","suelensrrosa","adairdias_","lucas.bertolett","thiagojantsch","daninhapf","jo_olivasol","marcio_souza_mendes","tamaracwb","andreaito1","amorinha_amanda","stelarortiz","lemoleri","fernanda_mmez","polyana.lupion","rafaelac_c","renatazadra","deebmariano","diana_msb7","danielerui","monica.born","anapaulasteilein","karinfabiolaf","liliancorci","rafahpch","janam03","kamila.lopes.pereira","angelicagollo","roseli_apoliveira","saulorousseau86","jackesbarecafe","junanamaia","soaress_kah85","marciele.bedford","laralaravilha","spinassichocolate.ctba","cvc.pr.shopsaojose","restaurante_tempeadori","mrhoppygetuliovargas","carmemcas","alinekieski","mascbeer","anamathuchenko","gukropiwiec","pietronardelli","thi.marchis","samantharamospereira","maga.scastro","churrascarianovaestrela","floresdacidadecuritiba","tfg.tatiane","cracklinghouse","julianak.psicologa","lu_westphalen","marleymello","rafaelbki","lucastvnovaes","fernandoesperanca","dacoregio","babimolodowski","gisely.santana.7","eloir.lourenco.1","gibson.lara.5","hellen_carminatti","loanacurial","sondacoregio","pousada.estrelaguia_ofc","guh.messias","fehavilas","gabremiranda","lincolngomes","chris.santiago.nemer","xandradutra","cleitontamanini","asuos_oiciruam","taciana.camargo.9","joelmamariarosa","maria_20jose","faledissocuritiba", "expedicaoandandoporai", "carlos.nog.9", "anapaulakotinski"];

    if (!$0) {
        console.error("Erro: Nenhum elemento selecionado como $0. Selecione o container da lista no painel Elements.");
        return;
    }

    const listaLower = listaSeguidores.map(u => u.trim().toLowerCase());
    const helperWait = ms => new Promise(res => setTimeout(res, ms));

    // Captura todas as linhas de usuários visíveis dentro do container $0
    const linhas = $0.querySelectorAll('div.x1qnrgzn.x1cek8b2');
    console.log(`🤖 Robô iniciado. Analisando ${linhas.length} usuários visíveis na tela...`);

    for (const linha of linhas) {
        // Encontra o link que contém o text do username
        const userSpan = linha.querySelector('a span._ap3a');
        if (!userSpan) continue;

        const username = userSpan.textContent.trim().toLowerCase();

        // Se o usuário da tela NÃO estiver na sua lista, executa a ação
        if (!listaLower.includes(username)) {
            console.log(`🚩 @${username} não está na lista. Removendo...`);

            // Localiza o botão "Seguindo" desta linha específica
            const botoes = linha.querySelectorAll('button');
            let botaoSeguindo = null;
            for (const btn of botoes) {
                if (btn.textContent.includes('Seguindo')) {
                    botaoSeguindo = btn;
                    break;
                }
            }

            if (botaoSeguindo) {
                botaoSeguindo.click();

                // Aguarda 1.5 segundos para o modal de confirmação renderizar na tela
                await helperWait(1500);

                // Procura pelo botão de confirmação "Deixar de seguir" dentro do modal aberto
                const botoesModal = document.querySelectorAll('button');
                let botaoConfirmar = null;
                for (const btnM of botoesModal) {
                    if (btnM.textContent.trim() === 'Deixar de seguir') {
                        botaoConfirmar = btnM;
                        break;
                    }
                }

                if (botaoConfirmar) {
                    botaoConfirmar.click();
                    console.log(`✅ Sucesso: Deixou de seguir @${username}.`);
                } else {
                    console.warn(`⚠️ Modal abriu, mas o botão 'Deixar de seguir' não foi encontrado. O Instagram pode ter alterado o texto.`);
                    // Caso o modal tenha ficado aberto por falha no clique, tenta fechar clicando fora ou cancelando se necessário
                }
            } else {
                console.warn(`⚠️ Botão 'Seguindo' não foi encontrado para o usuário @${username}.`);
            }

            // Cooldown de 5 segundos solicitado para evitar blocks temporários da plataforma
            console.log(`⏱️ Aguardando 5 segundos antes do próximo...`);
            await helperWait(5000);
        }
    }

    console.log('🏁 Varredura concluída para os elementos atualmente carregados nesta aba!');
})();

// REMOVE QUEM NÃO SIGO DE VOLTA
(async () => {
    // Captura todas as linhas filhas do elemento selecionado ($0)
    const rows = Array.from($0.children);
    console.log(`Total de linhas encontradas para análise: ${rows.length}`);

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];

        // Verifica se o texto "Seguir" está visível/presente nesta linha
        if (row.textContent.includes("Seguir")) {
            // Busca o primeiro botão "Remover" na linha
            const removerBtn = Array.from(row.querySelectorAll('[role="button"], button'))
                .find(el => el.textContent.trim() === 'Remover');

            if (removerBtn) {
                removerBtn.click();
                console.log(`[${i + 1}/${rows.length}] Primeiro botão 'Remover' clicado. Aguardando modal...`);

                // Aguarda 500 milissegundos para o modal abrir e renderizar no DOM
                await new Promise(resolve => setTimeout(resolve, 500));

                // CORREÇÃO: Busca especificamente o modal ativo que tem o texto de confirmação
                const modal = Array.from(document.querySelectorAll('[role="dialog"], [aria-modal="true"]'))
                    .find(m => m.textContent.includes("Remover seguidor?"));

                if (modal) {
                    // Procura o botão que contém "Remover" dentro do modal correto
                    const modalConfirmBtn = Array.from(modal.querySelectorAll('button'))
                        .find(el => el.textContent.trim() === 'Remover');

                    if (modalConfirmBtn) {
                        modalConfirmBtn.click();
                        console.log(`[${i + 1}/${rows.length}] Confirmado! Removido no modal.`);
                    } else {
                        console.log(`[${i + 1}/${rows.length}] Modal correto encontrado, mas o botão 'Remover' falhou.`);
                    }
                } else {
                    console.log(`[${i + 1}/${rows.length}] Erro: O modal de confirmação não foi detectado.`);
                }
            } else {
                console.log(`[${i + 1}/${rows.length}] Texto 'Seguir' encontrado, mas o botão 'Remover' da linha não foi localizado.`);
            }

            // Aguarda o tempo restante para segurança (totalizando 5s)
            await new Promise(resolve => setTimeout(resolve, 4500));

        } else {
            console.log(`[${i + 1}/${rows.length}] 'Seguir' não está presente. Pulando linha imediatamente...`);
        }
    }

    console.log("Análise de todas as linhas concluída!");
})();