<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirma tu Correo Electrónico</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body style="font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #2d3748; margin: 0; padding: 0; line-height: 1.6;">
    <div class="container" style="max-width: 600px; margin: 40px auto; padding: 0 20px;">
        <div class="card" style="background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);">
            <div class="header" style="background: linear-gradient(135deg, #6c63ff, #564fd8); padding: 30px; text-align: center; color: white;">
                <h1 style="margin: 0; font-weight: 600; font-size: 24px; letter-spacing: 0.5px; color: #ffffff !important;">{{ config('app.name') }}</h1>
            </div>
            
            <div class="content" style="padding: 30px;">
                <h2 style="color: #2d3748; margin-top: 0; font-size: 20px; font-weight: 600;">¡Bienvenido/a a nuestra plataforma!</h2>
                <p style="color: #718096; margin-bottom: 20px; font-size: 15px;">Estás a un paso de completar tu registro. Por favor confirma tu dirección de correo electrónico para activar tu cuenta.</p>
                
                <div class="btn-container" style="text-align: center; margin: 25px 0;">
                    <a href="{{ $signed_url }}" style="display: inline-block; padding: 12px 28px; background: linear-gradient(135deg, #6c63ff, #564fd8); color: white !important; text-decoration: none; border-radius: 6px; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 3px 10px rgba(108, 99, 255, 0.3);">Confirmar mi correo</a>
                </div>
                
                <p style="color: #718096; margin-bottom: 20px; font-size: 15px;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                <a href="{{ $signed_url }}" style="color: #6c63ff; text-decoration: none; word-break: break-all; display: inline-block; margin-top: 10px;">{{ $signed_url }}</a>
                
                <div class="notice" style="font-size: 14px; color: #718096; padding: 15px; background-color: #f8f9fa; border-radius: 6px; margin-top: 20px;">
                    <p style="margin: 0; color: #718096;">Si no has solicitado crear una cuenta con nosotros, por favor ignora este mensaje.</p>
                </div>
                
                <p class="footer-text" style="font-size: 13px; color: #718096; text-align: center; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px;">© {{ date('Y') }} {{ config('app.name') }} · Todos los derechos reservados</p>
            </div>
        </div>
    </div>
</body>
</html>