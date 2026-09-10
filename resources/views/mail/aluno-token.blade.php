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
        <p>Olá!</p>
        <p>O código de verificação da sua conta é:</p>
        <p><h1>{{ $token }}</h1></p>
        <p>Em caso de dúvidas, fale conosco via WhatsApp: <a target="_blank" href="https://api.whatsapp.com/send?phone=551151924353&text=Olá! Recebi o token e tenho outras dúvidas.">(11) 5192-4353</a></p>
    </div>
</body>
</html>
