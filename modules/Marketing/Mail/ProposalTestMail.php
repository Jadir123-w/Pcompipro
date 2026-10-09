<?php

namespace Modules\Marketing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * ProposalTestMail — Correo de PRUEBA de una Cotización Final (COM-02 Req #14).
 *
 * Envía la propuesta (mismo PDF que se emitiría al cliente) a una casilla
 * interna de Compipro, para que la ejecutiva valide cómo le llega al cliente
 * ANTES de emitir de verdad. Es repetible y NO cambia el estado de la
 * propuesta.
 *
 * El asunto lleva el prefijo [PRUEBA] para que quede claro que no es el envío
 * definitivo.
 */
class ProposalTestMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var string Número de cotización (ej. MAP250327-06_02) */
    public $numeroCotizacion;

    /** @var string Nombre del destinatario real de la propuesta (referencia) */
    public $destinatarioNombre;

    /** @var string Empresa destinataria (referencia) */
    public $empresaNombre;

    /** @var string Ruta del PDF en el disco public (storage/app/public/...) */
    private $pdfPath;

    /** @var string Nombre amigable del archivo adjunto */
    private $pdfFilename;

    /** @var string|null Dirección remitente (debe alinear con el servidor SMTP) */
    private $fromAddress;

    /** @var string|null Nombre remitente */
    private $fromName;

    /**
     * @param  string       $numeroCotizacion
     * @param  string       $pdfPath              Ruta relativa en disk('public')
     * @param  string       $destinatarioNombre
     * @param  string       $empresaNombre
     * @param  string|null  $fromAddress          Remitente (ej. informacion@compipro.net)
     * @param  string|null  $fromName             Nombre del remitente
     */
    public function __construct(
        $numeroCotizacion,
        $pdfPath,
        $destinatarioNombre = '',
        $empresaNombre = '',
        $fromAddress = null,
        $fromName = null
    ) {
        $this->numeroCotizacion   = $numeroCotizacion;
        $this->pdfPath            = $pdfPath;
        $this->destinatarioNombre = $destinatarioNombre;
        $this->empresaNombre      = $empresaNombre;
        $this->fromAddress        = $fromAddress;
        $this->fromName           = $fromName;

        $safeNumero = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $numeroCotizacion);
        $this->pdfFilename = 'Cotizacion_' . $safeNumero . '.pdf';
    }

    /**
     * Construir el mensaje.
     *
     * @return $this
     */
    public function build()
    {
        $mail = $this->subject('[PRUEBA] Cotización ' . $this->numeroCotizacion . ' — Compipro')
                     ->view('marketing::emails.proposal-test');

        // Fijar el remitente EXPLÍCITAMENTE. Es clave para la entrega: si no se
        // fija, Laravel usa el "from" global (que en runtime puede quedar en el
        // default hello@example.com), y servidores como Hotmail/Outlook rechazan
        // o marcan como spam los correos con remitente de dominio inexistente.
        // Alineamos el From con la cuenta SMTP (mail.compipro.net) para máxima
        // entregabilidad.
        if (!empty($this->fromAddress)) {
            $mail->from($this->fromAddress, $this->fromName ?: 'COMPIPRO');
        }

        // Adjuntar el PDF si existe en el disco public.
        if (!empty($this->pdfPath) && Storage::disk('public')->exists($this->pdfPath)) {
            $mail->attach(Storage::disk('public')->path($this->pdfPath), [
                'as'   => $this->pdfFilename,
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
