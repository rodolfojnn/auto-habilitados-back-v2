const WebSocket = require('ws');
const { execSync } = require('child_process');

// Configurações
const PORT = 3000;
const wss = new WebSocket.Server({ port: PORT });

const CONFIG = {
  // COLOQUE SUA CHAVE DO OPENROUTER AQUI
  openRouterKey: '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',
  modeloIA: 'openrouter/auto' // Você estava usando openrouter/free, se der falhas, mude para um modelo específico
};

console.log(`🚀 Servidor WebSocket rodando na porta ${PORT}`);
console.log('Aguardando conexão da página...\n');

// ------------------------------------------------------------------------
// MÉTODOS DE INTELIGÊNCIA ARTIFICIAL
// ------------------------------------------------------------------------

// Método 1: Verifica se o vídeo tem relação com CNH (Retorna true ou false)
async function verificarRelevanciaCNH(descricaoVideo) {
  try {
    const prompt = `Você é um classificador estrito. Sua única função é ler a descrição de um vídeo e determinar se o assunto está relacionado a CNH (Carteira Nacional de Habilitação), aulas de direção, simulado do Detran, prova teórica, instrutores de trânsito, autoescola ou educação no trânsito.

Responda APENAS com a palavra "true" se estiver relacionado, ou "false" se NÃO estiver. Não adicione nenhuma pontuação, explicação ou texto extra.

Descrição do vídeo:
"${descricaoVideo}"`;

    const response = await fetch('https://openrouter.ai/api/v1/chat/completions', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${CONFIG.openRouterKey}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        model: CONFIG.modeloIA,
        messages: [{ role: 'user', content: prompt }]
      }),
    });

    const data = await response.json();
    let textoGerado = data.choices[0].message.content.trim().toLowerCase();
    console.log(textoGerado);

    // Limpa a resposta para garantir que retorne um booleano real
    if (textoGerado === 'true' || textoGerado.includes('true')) return true;
    return false;

  } catch (erro) {
    console.error('❌ Erro ao chamar IA (verificarRelevanciaCNH):', erro);
    return false; // Em caso de erro, assume false
  }
}

// Método 2: Gera a resposta para o comentário baseado na descrição
async function obterRespostaIA(descricaoVideo, comentarioUsuario) {
  try {
    const prompt = `Você é uma avaliadora de comentários de redes sociais.

Sua função é identificar comentários onde recomendar um dos produtos abaixo soe como uma dica útil, natural e genuína de um amigo. Você deve agir como uma estudante de 26 anos que também está tirando a CNH. Analise o comentário levando em consideração o contexto do vídeo onde ele foi publicado.

Caso não sejam encontrados oportunidades de divulgação dos produtos, responda APENAS com o texto SEM_RESPOSTA, sem nenhuma explicação do motivo

==================================================
CONTEXTO DO VÍDEO ATUAL (Título / Descrição)
==================================================
"${descricaoVideo}"

==================================================
PRODUTOS DISPONÍVEIS
==================================================

PRODUTO 1: @dirigiragora (@dirigiragora)
O que é: Plataforma que conecta alunos a instrutores autônomos para aulas práticas.
QUANDO INDICAR: Quando a pessoa demonstrar ansiedade/dificuldade nas aulas práticas, reprovação na prática, reclamação de demora nas aulas da autoescola ou instrutor ruim.

PRODUTO 2: SIMULADO CNH DO BRASIL 2026 (@simuladocnhdobrasil)
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

Então quando o comentário for sobre as etapas antes das aulas práticas, indique sempre o produto 2 (Simulado). Apenas quando tratar de aulas práticas ou preços e valores de autoescola, indique o produto 1 (@dirigiragora).

Para a pessoa ter sido aprovada na prova teórica ela tem que ter acertado 20 ou mais questões. Caso a pessoa estiver aprovada, indique o @dirigiragora. Caso reprovada indique o simulado.

==================================================
EXEMPLOS DE COMO AGIR (CALIBRAÇÃO)
==================================================
Comentário: "passei! acertei 26 de 30 questões"
Ação: "parabéns pela aprovação! agora para as aulas práticas veja no @dirigiragora que funciona tipo um uber de instrutores, sai bem mais em conta e rápido"

Comentário: "Comecei o processo hoje, me desejem sorte"
Ação: "boa sorte! baixe o app @simuladocnhdobrasil pra ir estudando pro teórico, ajuda muito no inicio e é grátis"

Comentário: "Meu instrutor so tem horario pra mes que vem, to desesperada"
Ação: "tenta procurar no site @dirigiragora, as vezes vc acha um instrutor particular na sua região"

Comentário: "Fiz o psicotécnico hj e achei os desenhos muito dificeis"
Ação: "vc vai conseguir! já é bom ir estudando para o teórico, tem o app @simuladocnhdobrasil grátis com questões oficiais de prova. É excelente!"

Comentário: "alguem tem dica pra n deixar o carro morrer?"
Ação: "o segredo é treinar bastante. Tem um app @dirigiragora quebra um galho gigante pra achar instrutor pra treinar"

Comentário: "meu deus, nao entra na minha cabeca essas placa de transito e mecanica"
Ação: "usa o app @simuladocnhdobrasil pra ir treinando, me ajudou muito"

Comentário: "reprovei na baliza e a autoescola ta cobrando 150 a aula extra, sem condicoes"
Ação: "vê no app @dirigiragora se não tem ninguém com a aula mais em conta na sua região. funciona como um uber de instrutores"

Comentário: "o detran é uma mafia mds levaram todo meu dinheiro"
Ação: SEM_RESPOSTA (É apenas um desabafo genérico sobre o sistema, nenhum produto resolve).

Comentário: "vou começar a tirar a carteira mes q vem, alguma dica p n sofrer?"
Ação: "ja vai estudando no @simuladocnhdobrasil, pra mim deu certo. é gratuito e não tem anúncio"

Comentário: "tenho prova de direcao sexta e to tremendo so de pensar em sentar no banco do motorista"
Ação: "dá uma olhada no @dirigiragora pra marcar mais aulas e ganhar confianca. é tipo uma autoescola digital"

Comentário: "alguem sabe como é o exame de vista pra quem usa oculos?"
Ação: "Isso varia de estado pra estado, mas você já pode ir estudando pro teórico. Recomendo o @simuladocnhdobrasil, que são questões oficiais da prova e é gratuito"

Comentário: "passei na prova prática galera!! finalmente livre pra ir pras ruas"
Ação: "parabéns! para quem ainda tá iniciando recomendo o aplicativo @dirigiragora, funciona como uma autoescola digital e vc resolve tudo pelo app"

Comentário: "passei na pratica mas faz 4 dias que o sistema do detran nao atualiza minha cnh digital"
Ação: "baixe o aplicativo @dirigiragora que eles tem suporte gratuito para todas as etapas!"

Comentário: "baixa o app CNH do Brasil"
Ação: "o CNH do Brasil só serve para acompanhar as etapas. Mas todo resto é via Detran, e aulas práticas sao feitas por autoescola ou instrutor autônomo. Tem um app excelente pra buscar preços de instrutores na sua regiao @dirigiragora. É como uma autoescola digital"

Comentário: "qual foi o valores que vocês pagaram para a coleta da biometria?"
Ação: "as taxas dependem de cada estado, mas a prova teórica segue um padrão nacional. Tem um simulado gratuito e sem propagandas, o @simuladocnhdobrasil. vale a pena!"

Comentário: "Tem prazo máximo para iniciar as aulas práticas depois que terminar a prova teórica no Detran?"
Ação: "antes o processo tinha prazo mas agora não tem mais. e para as aulas práticas já existe até um 'uber' de instrutores, que os preços são bons se chama @dirigiragora"

Comentário: "Gente, antes de querer fazer algo pela autoescola, pesquisa antes. Faz tudo por fora. Além de sair mais barato, tu não terá dores de cabeça."
Ação: "fiz pelo @dirigiragora é tipo uma autoescola digital, atendem todo o Brasil, e os preços são excelentes"

Comentário: "faça pela autoescola"
Ação: "dá pra fazer por aplicativo, funciona tipo um uber de instrutores @dirigiragora. Aqui na minha cidade saiu bem mais em conta e bem mais rápido"

Comentário: "prefiro fazer na auto escola"
Ação: "hoje dá pra fazer por um app, o @dirigiragora, funciona tipo uma autoescola digital. Você acha instrutores próximos de vc com preços excelentes. Pra mim deu super certo"

Comentário: "quero fazer aulas práticas com instrutor autônomo. Como funciona?"
Ação: "o melhor é você contratar através de alguma empresa que é mais seguro. Tem um app que funciona como um Uber de instrutores e os instrutores só recebem o pagamento no final de todas as suas aulas. Acho bem mais seguro assim! Veja lá em @dirigiragora"

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
- Não fale de valores diretamente, diga que o produto @dirigiragora é mais barato que autoescola e o instrutor vem atender em casa. O Simulado é grátis e sem propagandas

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
        model: CONFIG.modeloIA,
        messages: [{ role: 'user', content: prompt }]
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
    console.error('❌ Erro ao chamar IA (obterRespostaIA):', erro);
    return 'SEM_RESPOSTA';
  }
}


// ------------------------------------------------------------------------
// MÉTODOS DE AUTOMAÇÃO (PowerShell)
// ------------------------------------------------------------------------

function executarPowerShellEncoded(scriptPS) {
  try {
    const base64Script = Buffer.from(scriptPS, 'utf16le').toString('base64');
    execSync(`powershell -NoProfile -EncodedCommand ${base64Script}`, { stdio: 'ignore' });
    return true;
  } catch (err) {
    console.error('Erro ao executar PowerShell:', err.message);
    return false;
  }
}

function executarSendKeys(keys) {
  const scriptPS = `
    Add-Type -AssemblyName System.Windows.Forms;
    [System.Windows.Forms.SendKeys]::SendWait('${keys}');
  `;
  return executarPowerShellEncoded(scriptPS);
}

function executarClick() {
  const scriptPS = `
    Add-Type @"
      using System;
      using System.Runtime.InteropServices;
      public class MouseSimulator {
        [DllImport("user32.dll")] public static extern void mouse_event(uint dwFlags, uint dx, uint dy, uint dwData, int dwExtraInfo);
        public const uint MOUSEEVENTF_LEFTDOWN = 0x02;
        public const uint MOUSEEVENTF_LEFTUP = 0x04;
      }
"@
    [MouseSimulator]::mouse_event([MouseSimulator]::MOUSEEVENTF_LEFTDOWN, 0, 0, 0, 0)
    Start-Sleep -Milliseconds 50
    [MouseSimulator]::mouse_event([MouseSimulator]::MOUSEEVENTF_LEFTUP, 0, 0, 0, 0)
  `;
  return executarPowerShellEncoded(scriptPS);
}

function executarClickEm(x, y) {
  const scriptPS = `
    Add-Type @"
      using System;
      using System.Runtime.InteropServices;
      public class MouseSimulator {
        [DllImport("user32.dll")] public static extern void mouse_event(uint dwFlags, uint dx, uint dy, uint dwData, int dwExtraInfo);
        [DllImport("user32.dll")] public static extern bool SetCursorPos(int x, int y);
        public const uint MOUSEEVENTF_LEFTDOWN = 0x02;
        public const uint MOUSEEVENTF_LEFTUP = 0x04;
      }
"@
    [MouseSimulator]::SetCursorPos(${x}, ${y})
    Start-Sleep -Milliseconds 100
    [MouseSimulator]::mouse_event([MouseSimulator]::MOUSEEVENTF_LEFTDOWN, 0, 0, 0, 0)
    Start-Sleep -Milliseconds 50
    [MouseSimulator]::mouse_event([MouseSimulator]::MOUSEEVENTF_LEFTUP, 0, 0, 0, 0)
  `;
  return executarPowerShellEncoded(scriptPS);
}

function executarSwipeUp(pixels) {
  const scriptPS = `
    Add-Type @"
      using System;
      using System.Runtime.InteropServices;
      public class MouseSimulator {
        [DllImport("user32.dll")] public static extern void mouse_event(uint dwFlags, uint dx, uint dy, uint dwData, int dwExtraInfo);
        [DllImport("user32.dll")] public static extern bool SetCursorPos(int x, int y);
        [DllImport("user32.dll")] public static extern bool GetCursorPos(out POINT lpPoint);
        public const uint MOUSEEVENTF_LEFTDOWN = 0x02;
        public const uint MOUSEEVENTF_LEFTUP = 0x04;
      }
      public struct POINT { public int x; public int y; }
"@
    $pt = New-Object POINT
    [MouseSimulator]::GetCursorPos([ref]$pt)
    $xInicial = $pt.x
    $yInicial = $pt.y
    $yAlvo = $yInicial - ${pixels}

    # Pressiona o botão
    [MouseSimulator]::mouse_event([MouseSimulator]::MOUSEEVENTF_LEFTDOWN, 0, 0, 0, 0)
    Start-Sleep -Milliseconds 50

    # Arrasta para cima (swipe up)
    [MouseSimulator]::SetCursorPos($xInicial, $yAlvo)
    Start-Sleep -Milliseconds 100

    # Solta o botão
    [MouseSimulator]::mouse_event([MouseSimulator]::MOUSEEVENTF_LEFTUP, 0, 0, 0, 0)
    Start-Sleep -Milliseconds 50

    # Volta para a posição inicial
    [MouseSimulator]::SetCursorPos($xInicial, $yInicial)
  `;
  return executarPowerShellEncoded(scriptPS);
}

function setClipboard(texto) {
  try {
    const base64Text = Buffer.from(texto, 'utf8').toString('base64');
    const scriptPS = `
      $texto = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String('${base64Text}'));
      Set-Clipboard -Value $texto;
    `;
    return executarPowerShellEncoded(scriptPS);
  } catch (err) {
    console.error('Erro ao definir a área de transferência:', err.message);
    return false;
  }
}

// ------------------------------------------------------------------------
// SERVIDOR WEBSOCKET
// ------------------------------------------------------------------------

wss.on('connection', (ws) => {
  console.log('✅ Página web conectada!');

  // Transformado em async para podermos aguardar a IA
  ws.on('message', async (data) => {
    try {
      const msg = JSON.parse(data);
      console.log('📨 Recebido:', msg.action); // Log mais limpo para não poluir o terminal com descrições gigantes

      let sucesso = false;

      switch (msg.action) {

        // -----------------------------------
        // MÉTODOS DE IA
        // -----------------------------------
        case 'check_cnh': // Espera { action: 'check_cnh', description: "..." }
          if (msg.description) {
            console.log('🤖 Analisando relevância do vídeo (CNH)...');
            const ehRelevante = await verificarRelevanciaCNH(msg.description);
            console.log(`🤖 Resultado da análise: ${ehRelevante}`);
            ws.send(JSON.stringify({
              action: msg.action,
              status: 'done',
              result: ehRelevante
            }));
          } else {
            ws.send(JSON.stringify({ action: msg.action, status: 'error', message: 'Falta o parâmetro "description"' }));
          }
          return; // Sai do switch para não enviar a confirmação dupla abaixo

        case 'generate_reply': // Espera { action: 'generate_reply', description: "...", comment: "..." }
          if (msg.description && msg.comment) {
            console.log('🤖 Gerando resposta para o comentário...');
            const respostaIA = await obterRespostaIA(msg.description, msg.comment);
            console.log(respostaIA);
            console.log('🤖 Resposta gerada com sucesso.');
            ws.send(JSON.stringify({
              action: msg.action,
              status: 'done',
              reply: respostaIA
            }));
          } else {
            ws.send(JSON.stringify({ action: msg.action, status: 'error', message: 'Faltam os parâmetros "description" ou "comment"' }));
          }
          return; // Sai do switch para não enviar a confirmação dupla abaixo

        // -----------------------------------
        // MÉTODOS DE AUTOMAÇÃO
        // -----------------------------------
        case 'paste':                    // Ctrl + V
          if (msg.text) {
            console.log('📋 Copiando texto recebido para a área de transferência...');
            setClipboard(msg.text);
          }
          console.log('⌨️ Executando Ctrl + V via PowerShell...');
          sucesso = executarSendKeys('^v');
          break;

        case 'page_down':                // Page Down
          console.log('⌨️ Executando Page Down via PowerShell...');
          sucesso = executarSendKeys('{PGDN}');
          break;

        case 'enter':                    // Enter
          console.log('⌨️ Executando Enter via PowerShell...');
          sucesso = executarSendKeys('{ENTER}');
          break;

        case 'click':                    // Clique do mouse na posição atual
          console.log('🖱️ Executando clique do mouse via PowerShell...');
          sucesso = executarClick();
          break;

        case 'click_at':                 // Clique do mouse nas coordenadas (x, y)
          if (msg.x !== undefined && msg.y !== undefined) {
            console.log(`🖱️ Executando clique em (x: ${msg.x}, y: ${msg.y}) via PowerShell...`);
            sucesso = executarClickEm(msg.x, msg.y);
          } else {
            console.log('❌ click_at requer "x" e "y" (números)');
            sucesso = false;
          }
          break;

        case 'swipe_up':                 // Swipe Up do mouse
          if (msg.pixels !== undefined && msg.pixels > 0) {
            console.log('🖱️ Executando Swipe Up via PowerShell...');
            sucesso = executarSwipeUp(msg.pixels);
          } else {
            console.log('❌ swipe_up requer "pixels" (número > 0)');
            sucesso = false;
          }
          break;

        case 'paste_comment':            // Ctrl + V + Enter
          if (msg.text) {
            console.log('📋 Copiando texto recebido para a área de transferência...');
            setClipboard(msg.text);
            setTimeout(() => {
              console.log('⌨️ Executando Ctrl + V (após delay de clipboard)...');
              const ok = executarSendKeys('^v');
              setTimeout(() => {
                executarSendKeys('{ENTER}');
                ws.send(JSON.stringify({
                  status: ok ? 'done' : 'error',
                  action: msg.action,
                  success: ok
                }));
              }, 200);
            }, 300);
            return;
          } else {
            console.log('⌨️ Executando Ctrl + V + Enter (sem texto)...');
            sucesso = executarSendKeys('^v');
            setTimeout(() => {
              executarSendKeys('{ENTER}');
              ws.send(JSON.stringify({
                status: sucesso ? 'done' : 'error',
                action: msg.action,
                success: sucesso
              }));
            }, 200);
            return;
          }

        default:
          console.log('❓ Ação desconhecida:', msg.action);
      }

      // Confirmação para o frontend (apenas para automações síncronas: paste, page_down, etc)
      ws.send(JSON.stringify({
        status: sucesso ? 'done' : 'error',
        action: msg.action,
        success: sucesso
      }));

    } catch (err) {
      console.error('Erro ao processar mensagem:', err);
      ws.send(JSON.stringify({ status: 'error', message: err.message }));
    }
  });

  ws.on('close', () => {
    console.log('🔌 Conexão fechada.');
  });
});

console.log('🎯 Comandos de Automação:');
console.log('   → { "action": "paste", "text": "opcional" }');
console.log('   → { "action": "paste_comment", "text": "opcional" }');
console.log('   → { "action": "page_down" }, { "action": "click" }, { "action": "enter" }');
console.log('   → { "action": "swipe_up", "pixels": 150 }');
console.log('   → { "action": "click_at", "x": 100, "y": 200 }');
console.log('🎯 Comandos de Inteligência Artificial:');
console.log('   → { "action": "check_cnh", "description": "descrição do video" }');
console.log('   → { "action": "generate_reply", "description": "descrição do video", "comment": "comentário usuário" }');