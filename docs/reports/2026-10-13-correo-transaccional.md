# Correo transaccional (Mailtrap) — 2026-10-13

Rama `correo-mailtrap`. Los correos de invitación, restablecer contraseña y verificar correo ya pasaban por el mailer de Laravel, pero `MAIL_MAILER=log` los dejaba en el log. Ahora pueden salir por correo real, en español y por cola.

## Qué se hizo

- **Avisos en español y por cola.**
  - `App\Notifications\RestablecerContrasena` y `VerificarCorreo` extienden las clases de Laravel (`ResetPassword`, `VerifyEmail`), implementan `ShouldQueue` y traen asunto, texto y botón en español, con el tiempo de caducidad tomado de `config/auth.php`.
  - `User::sendPasswordResetNotification` y `sendEmailVerificationNotification` los usan.
  - `InvitacionResponsableNivel` ahora también implementa `ShouldQueue`.
- **Mailer `mailtrap-sdk`** en `config/mail.php` (producción, por API). Lee `MAILTRAP_API_KEY` y `MAILTRAP_HOST` del entorno.
- **Paquetes:** `railsware/mailtrap-php`, `symfony/http-client`, `nyholm/psr7`.
- **`.env.example`** explica los dos modos: desarrollo con `smtp` hacia el sandbox de Mailtrap y producción con `mailtrap-sdk`. Sin secretos.

## Decisiones

- **Desarrollo por SMTP, no por el SDK.** El sandbox de Mailtrap es SMTP (`sandbox.smtp.mailtrap.io:2525`), así que el mailer `smtp` ya incluido basta. El mailer `mailtrap-sdk` queda para producción, donde hace falta un dominio verificado para escribirle a otras personas (el dominio demo solo entrega al dueño de la cuenta).
- **Por cola.** Un proveedor lento o caído no bloquea la petición, y un envío fallido queda en `failed_jobs` y se puede reintentar. Costo: hay que correr `php artisan queue:work` junto al servidor; sin eso ningún correo sale.
- **Subclases en vez de `toMailUsing`.** Laravel no deja encolar sus clases originales; subclasear permite `ShouldQueue` y mantiene el texto en un solo lugar.

## Evidencia

- Suite completa **1253/1253** (4132 aserciones). PHPStan nivel 5 + reglas PHPat: 0 errores. Pint limpio.
- Pruebas nuevas en `tests/Feature/Auth/CorreosTransaccionalesTest.php` (5), vistas fallando antes de implementar. `PasswordResetTest` ahora comprueba `RestablecerContrasena`.

## Lo que no se verificó

- **Envío real.** Las pruebas usan `Notification::fake()` y el mailer `array`; ningún correo se envió al sandbox desde esta sesión.
- **Aspecto del correo** en un cliente de correo.
- **Enlace de invitación:** sigue caducando a los 60 minutos (ADR-015); con la cola, un worker detenido puede hacer que el enlace llegue ya caducado.
- **`composer require` avisó de 3 advisories en 2 paquetes**; no se revisó si ya existían antes de este cambio.
- **Producción:** falta verificar un dominio en Mailtrap y definir `MAIL_FROM_ADDRESS`; el valor actual es el placeholder `hello@example.com`.
