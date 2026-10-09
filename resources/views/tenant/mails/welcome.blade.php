<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bienvenido</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      line-height: 1.6;
      color: #333;
      background-color: #f4f4f4;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 600px;
      margin: 20px auto;
      background-color: #ffffff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 40px 20px;
      text-align: center;
    }
    .header h1 {
      margin: 0;
      font-size: 28px;
    }
    .content {
      padding: 30px;
    }
    .content h2 {
      color: #667eea;
      margin-top: 0;
    }
    .info-box {
      background-color: #f8f9fa;
      border-left: 4px solid #667eea;
      padding: 15px;
      margin: 20px 0;
      border-radius: 4px;
    }
    .footer {
      background-color: #f8f9fa;
      padding: 20px;
      text-align: center;
      font-size: 12px;
      color: #666;
    }
    .button {
      display: inline-block;
      padding: 12px 30px;
      background-color: #667eea;
      color: white;
      text-decoration: none;
      border-radius: 5px;
      margin: 20px 0;
    }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>¡Bienvenido al Sistema!</h1>
  </div>

  <div class="content">
    <h2>Hola, {{ $user->userName }}!</h2>

    <p>Nos complace darte la bienvenida a nuestro sistema. Tu cuenta ha sido creada exitosamente.</p>

    <div class="info-box">
      <strong>Información de tu cuenta:</strong><br>
      📧 Email: {{ $user->userEmail }}<br>
      🏢 Establecimiento: {{ ucfirst($user->userEstablishmentDescription) }}<br>
      👤 Tipo de usuario: {{ ucfirst($user->userType) }}
    </div>

    <p>Ya puedes iniciar sesión en el sistema con las credenciales que te han sido proporcionadas:</p>

    <p style="text-align: center; font-weight: bolder;">
      <a href="https://{{ $user->userUrlFqdn }}" target="_blank" title="Ingresa Aqui al Sistema">Ingresa Aqui al Sistema</a>
    </p>

    <p>Si tienes alguna pregunta o necesitas ayuda, no dudes en contactar con el administrador del sistema.</p>

    <p style="margin-top: 30px;">Saludos cordiales,<br><strong>El equipo de Compipro</strong></p>
  </div>

  <div class="footer">
    <p>Este es un correo automático, por favor no responder a este mensaje.</p>
    <p>&copy; {{ date('Y') }} - Todos los derechos reservados</p>
  </div>
</div>
</body>
</html>
