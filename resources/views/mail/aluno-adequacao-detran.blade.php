<!DOCTYPE html>
<html>
<head>
    <style>
        .logo {
            display: block;
            margin-top: 20px;
            max-width: 200px;
            height: auto;
        }
        .content {
            padding: 20px 0;
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="logo">
      <img src="https://www.dirigiragora.com.br/assets/images/logo-dirigiragora-texto-sm.png">
    </div>
    <div class="content">
        <p>Olá {{ $nome }}! Temos um aviso importante a respeito do seu cadastro no Dirigir Agora.</p>
        <p>Aguardamos a adequação dos sistemas do <strong>DETRAN</strong> de seu estado.</p>
        <p>Por enquanto, seu <strong>pré-cadastro</strong> está em fila de espera.</p>
        <p>Nos acompanhe nas redes sociais para ficar por dentro das novidades:<br>
        <strong>@dirigiragora</strong></p>
        <br>
        <p>Fale com a gente:</p>
        <p>Instagram: @dirigiragora</p>
        <p>WhatsApp: <a target="_blank" href="https://api.whatsapp.com/send?phone=551151924353&text=Olá! Tenho dúvidas sobre meu pré-cadastro no Dirigir Agora que está em fila de espera.">(11) 5192-4353</a></p>

        <img style="display: none" src="https://api.dirigiragora.com.br/email/track/{{ $email }}/aluno-adequacao-detran">
    </div>
</body>
</html>
