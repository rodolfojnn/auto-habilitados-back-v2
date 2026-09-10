// ==UserScript==
// @name         Robô de Automação - Esalflores para Dirigir Agora
// @namespace    http://tampermonkey.net/
// @version      3.2
// @match        https://www.esalflores.com.br/*
// @grant        none
// ==/UserScript==

(function() {
    'use strict';

    // --- 1. LÓGICA DE EXECUÇÃO AUTOMÁTICA (DIRETA PÓS-REFRESH) ---
    const estaAtivo = localStorage.getItem('automacao') === 'true';

    if (estaAtivo) {
        const numeroDefinido = localStorage.getItem('automacao_numero') || '0';

        const dadosPayload = {
            url: window.location.href,
            numero: parseInt(numeroDefinido, 10)
        };

        fetch('https://api.dirigiragora.com.br/v1/simulado/newAutomacaoLead', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dadosPayload)
        })
        .then(response => {
            if (!response.ok) throw new Error(`Erro na API: ${response.status}`);
            return response.json();
        })
        .then(data => console.log('Lead enviado:', data))
        .catch(error => console.error('Falha ao enviar:', error));
    }

    // --- 2. CRIAÇÃO DA INTERFACE VISUAL (UI) ---
    const menuContainer = document.createElement('div');
    menuContainer.id = 'robo-menu-container';

    Object.assign(menuContainer.style, {
        position: 'relative',
        width: '100%',
        zIndex: '999999',
        fontFamily: 'sans-serif'
    });

    const shadow = menuContainer.attachShadow({ mode: 'open' });

    shadow.innerHTML = `
        <style>
            .navbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 10px 20px;
                background: #18181b;
                border-bottom: 2px solid #3f3f46;
            }
            .brand {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                font-weight: bold;
                color: #f4f4f5;
            }
            .brand-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
            }
            .dot-active { background: #10b981; }
            .dot-inactive { background: #ef4444; }

            .controls {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .input-num {
                background: #27272a;
                color: #fff;
                border: 1px solid #4e4e54;
                padding: 6px 10px;
                font-size: 13px;
                border-radius: 4px;
                width: 70px;
            }
            .btn {
                color: #fff;
                border: none;
                padding: 6px 14px;
                font-size: 13px;
                font-weight: bold;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn-active { background: #10b981; }
            .btn-active:hover { background: #059669; }
            .btn-inactive { background: #ef4444; }
            .btn-inactive:hover { background: #dc2626; }
        </style>

        <div class="navbar">
            <div class="brand">
                <div id="led" class="brand-dot"></div>
                <span>STATUS</span>
            </div>
            <div class="controls">
                <input type="number" id="input-parametro" class="input-num" placeholder="Número">
                <button id="btn-controle" class="btn"></button>
            </div>
        </div>
    `;

    // Injeta o menu no topo do body
    const injetarMenu = () => {
        if (document.body && !document.getElementById('robo-menu-container')) {
            document.body.insertBefore(menuContainer, document.body.firstChild);
            inicializarInterface();
        }
    };

    if (document.body) {
        injetarMenu();
    } else {
        const observer = new MutationObserver(() => {
            if (document.body) {
                injetarMenu();
                observer.disconnect();
            }
        });
        observer.observe(document.documentElement, { childList: true });
    }

    // --- 3. GERENCIAMENTO DE ESTADO E EVENTOS DA UI ---
    function inicializarInterface() {
        const btnControle = shadow.querySelector('#btn-controle');
        const ledStatus = shadow.querySelector('#led');
        const inputParametro = shadow.querySelector('#input-parametro');

        const numeroSalvo = localStorage.getItem('automacao_numero');
        if (numeroSalvo !== null) inputParametro.value = numeroSalvo;

        inputParametro.addEventListener('input', () => {
            localStorage.setItem('automacao_numero', inputParametro.value);
        });

        function atualizarInterface() {
            const ativo = localStorage.getItem('automacao') === 'true';

            if (ativo) {
                btnControle.textContent = "Parar Automação";
                btnControle.className = "btn btn-inactive";
                ledStatus.className = "brand-dot dot-active";
                inputParametro.disabled = true;
            } else {
                btnControle.textContent = "Iniciar Automação";
                btnControle.className = "btn btn-active";
                ledStatus.className = "brand-dot dot-inactive";
                inputParametro.disabled = false;
            }
        }

        atualizarInterface();

        btnControle.addEventListener('click', () => {
            const atualmenteAtivo = localStorage.getItem('automacao') === 'true';

            if (atualmenteAtivo) {
                localStorage.setItem('automacao', 'false');
                atualizarInterface();
            } else {
                localStorage.setItem('automacao_numero', inputParametro.value);
                localStorage.setItem('automacao', 'true');
                atualizarInterface();
                location.reload();
            }
        });
    }

})();