<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\MarketingEmailDraft;
use Modules\Marketing\Models\MarketingEmailReceived;
use Modules\Marketing\Services\EmailTriageClassifierService;
use Modules\Marketing\Services\EmailTriageReaderService;
use Modules\Marketing\Services\EmailTriageSenderService;

/**
 * EmailTriagePendingController — MKT-01 Email Triage ET3.
 *
 * UI "Correos Pendientes": el panel donde una persona del equipo COMPIPRO
 * revisa los borradores generados por la IA, los edita si hace falta, y
 * decide aprobar (envía SMTP) o rechazar (descarta).
 *
 * Endpoints (autenticados):
 *   GET    /marketing/email-triage/drafts                 → lista paginada con filtros
 *   GET    /marketing/email-triage/drafts/{id}            → detalle (mail original + borrador)
 *   PUT    /marketing/email-triage/drafts/{id}            → editar subject/body/to/cc
 *   POST   /marketing/email-triage/drafts/{id}/approve    → aprobar (no envía aún)
 *   POST   /marketing/email-triage/drafts/{id}/reject     → rechazar
 *   POST   /marketing/email-triage/drafts/{id}/send       → enviar SMTP (manual)
 *   POST   /marketing/email-triage/run-now                → disparar reader+clasificador ad-hoc
 *   GET    /marketing/email-triage/drafts/count-pending   → badge para sidebar
 */
class EmailTriagePendingController extends Controller
{
    /** @var EmailTriageSenderService */
    private $sender;

    public function __construct(EmailTriageSenderService $sender)
    {
        $this->sender = $sender;
    }

    /**
     * GET /marketing/email-triage/drafts
     */
    public function index(Request $request)
    {
        $query = MarketingEmailDraft::query()
            ->with(['emailReceived' => function ($q) {
                $q->select(['id', 'from_email', 'from_name', 'to_email', 'subject',
                            'body_plain', 'received_at',
                            'classification', 'classification_confidence', 'has_attachments']);
            }, 'approver:id,name,email']);

        // Filtros
        $status = $request->input('status', 'pending');
        if ($status === 'pending') {
            $query->pending();
        } elseif (in_array($status, MarketingEmailDraft::STATUSES, true)) {
            $query->where('status', $status);
        }
        // 'all' no filtra

        if ($request->filled('classification')) {
            $query->whereHas('emailReceived', function ($q) use ($request) {
                $q->where('classification', $request->input('classification'));
            });
        }

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('draft_subject', 'like', $term)
                  ->orWhere('to_email', 'like', $term)
                  ->orWhereHas('emailReceived', function ($q2) use ($term) {
                      $q2->where('subject', 'like', $term)
                         ->orWhere('from_email', 'like', $term)
                         ->orWhere('from_name', 'like', $term);
                  });
            });
        }

        $drafts = $query->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json(['ok' => true, 'drafts' => $drafts]);
    }

    /**
     * GET /marketing/email-triage/drafts/count-pending
     *
     * Para el badge del sidebar (número de drafts pendientes de aprobación).
     */
    public function countPending()
    {
        $count = MarketingEmailDraft::pending()->count();
        return response()->json(['ok' => true, 'count' => $count]);
    }

    /**
     * GET /marketing/email-triage/drafts/{id}
     */
    public function show($id)
    {
        $draft = MarketingEmailDraft::with(['emailReceived', 'approver:id,name,email'])
            ->findOrFail($id);
        return response()->json(['ok' => true, 'draft' => $draft]);
    }

    /**
     * PUT /marketing/email-triage/drafts/{id}
     *
     * Body:
     *   draft_subject?: string
     *   draft_body?: string
     *   to_email?: string (email)
     *   cc_emails?: array of strings (emails)
     */
    public function update(Request $request, $id)
    {
        $draft = MarketingEmailDraft::findOrFail($id);

        if (!$draft->isPending()) {
            return response()->json([
                'ok' => false,
                'message' => "No se puede editar un borrador en estado '{$draft->status}'",
            ], 422);
        }

        $v = Validator::make($request->all(), [
            'draft_subject' => ['nullable', 'string', 'max:500'],
            'draft_body'    => ['nullable', 'string'],
            'to_email'      => ['nullable', 'email', 'max:255'],
            'cc_emails'     => ['nullable', 'array'],
            'cc_emails.*'   => ['email', 'max:255'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $changes = $request->only(['draft_subject', 'draft_body', 'to_email', 'cc_emails']);
        // Solo aplicar campos provistos
        $changes = array_filter($changes, function ($v) { return $v !== null; });

        if (empty($changes)) {
            return response()->json(['ok' => false, 'message' => 'Sin cambios para aplicar.'], 422);
        }

        $draft->markEdited($this->resolveUserId(), $changes);

        return response()->json(['ok' => true, 'draft' => $draft->fresh(['emailReceived', 'approver'])]);
    }

    /**
     * POST /marketing/email-triage/drafts/{id}/approve
     *
     * Marca el borrador como aprobado (no envía aún — el envío es separado para
     * que la UI pueda confirmar el flujo: aprobar primero, enviar después).
     */
    public function approve($id)
    {
        $draft = MarketingEmailDraft::findOrFail($id);

        if (!$draft->isPending()) {
            return response()->json([
                'ok' => false,
                'message' => "Solo se aprueban borradores en estado pending/edited (actual: {$draft->status})",
            ], 422);
        }

        $draft->approve($this->resolveUserId());

        return response()->json(['ok' => true, 'draft' => $draft->fresh(['emailReceived', 'approver'])]);
    }

    /**
     * POST /marketing/email-triage/drafts/{id}/reject
     *
     * Body: { reason?: string }
     */
    public function reject(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $draft = MarketingEmailDraft::findOrFail($id);

        if (!$draft->isPending()) {
            return response()->json([
                'ok' => false,
                'message' => "Solo se rechazan borradores en estado pending/edited (actual: {$draft->status})",
            ], 422);
        }

        $draft->reject($this->resolveUserId(), $request->input('reason'));

        // Marcar el correo recibido como discarded también
        if ($draft->emailReceived) {
            $draft->emailReceived->update([
                'status' => MarketingEmailReceived::STATUS_DISCARDED,
            ]);
        }

        return response()->json(['ok' => true, 'draft' => $draft->fresh(['emailReceived', 'approver'])]);
    }

    /**
     * POST /marketing/email-triage/drafts/{id}/send
     *
     * Envía el borrador vía SMTP. Solo permitido si el borrador está en estado
     * approved o send_error (reintento). Marca el correo recibido como replied
     * si el envío exitó.
     */
    public function send($id)
    {
        $draft = MarketingEmailDraft::findOrFail($id);

        if (!in_array($draft->status, [
            MarketingEmailDraft::STATUS_APPROVED,
            MarketingEmailDraft::STATUS_SEND_ERROR,
        ], true)) {
            return response()->json([
                'ok' => false,
                'message' => "El borrador debe estar approved o send_error para enviarse (actual: {$draft->status})",
            ], 422);
        }

        $result = $this->sender->send($draft);

        if (!$result['ok']) {
            return response()->json([
                'ok'       => false,
                'message'  => $result['error'],
                'draft'    => $draft->fresh(['emailReceived', 'approver']),
            ], 500);
        }

        // Marcar el correo recibido como replied
        if ($draft->emailReceived) {
            $draft->emailReceived->update([
                'status' => MarketingEmailReceived::STATUS_REPLIED,
            ]);
        }

        return response()->json([
            'ok'         => true,
            'message'    => 'Correo enviado exitosamente.',
            'message_id' => $result['message_id'],
            'draft'      => $draft->fresh(['emailReceived', 'approver']),
        ]);
    }

    /**
     * POST /marketing/email-triage/run-now
     *
     * Dispara una corrida ad-hoc del Email Triage SIN esperar al scheduler:
     *   1. Lee la bandeja IMAP (si tiene credenciales + ext-imap)
     *   2. Clasifica con IA + genera borradores para los correos nuevos
     *
     * Útil cuando la ejecutiva está esperando un correo URGENTE y no quiere
     * esperar al próximo tick del cron (que corre cada 5 min). También sirve
     * para destrabar el flujo cuando el cron NO está configurado todavía.
     *
     * Throttle: máximo 1 corrida cada 60 segundos por tenant para evitar
     * abuso (gasto de tokens IA + golpear el IMAP del cliente).
     *
     * Body (opcional):
     *   { skip_read: bool, read_limit: int, classify_limit: int }
     */
    public function runNow(Request $request, EmailTriageReaderService $reader)
    {
        // ── Throttle: 1 corrida cada 60s por tenant ──
        // Usamos la conexión `tenant` si existe, sino fallback a un global key
        $tenantKey = $this->resolveTenantThrottleKey();
        $lockKey   = 'email_triage:run_now:' . $tenantKey;

        $lastRun = Cache::get($lockKey);
        if ($lastRun) {
            $secondsAgo = max(0, time() - (int) $lastRun);
            if ($secondsAgo < 60) {
                $secondsLeft = max(1, 60 - $secondsAgo);
                return response()->json([
                    'ok'           => false,
                    'message'      => "Espera {$secondsLeft}s antes de volver a revisar la bandeja (throttle anti-abuso).",
                    'throttled'    => true,
                    'seconds_left' => $secondsLeft,
                ], 429);
            }
            // Pasaron >= 60s: el throttle expiró, dejamos pasar y refrescamos el timestamp
        }
        Cache::put($lockKey, time(), 90);

        $skipRead       = (bool) $request->input('skip_read', false);
        $readLimit      = (int)  $request->input('read_limit', 50);
        $classifyLimit  = (int)  $request->input('classify_limit', 20);

        $stats = [
            'imap_persisted'  => 0,
            'imap_duplicates' => 0,
            'imap_errors'     => 0,
            'imap_error_msg'  => null,
            'classified'      => 0,
            'drafts_created'  => 0,
            'discarded'       => 0,
            'classify_errors' => 0,
        ];

        // ── Fase 1: reader IMAP ──
        if (!$skipRead) {
            try {
                $r = $reader->read($readLimit);
                if (!$r['enabled']) {
                    $stats['imap_error_msg'] = $r['error_message'] ?: 'Email Triage deshabilitado.';
                } elseif (!empty($r['error_message'])) {
                    $stats['imap_error_msg'] = $r['error_message'];
                    $stats['imap_errors']    = 1;
                } else {
                    $stats['imap_persisted']  = (int) $r['persisted'];
                    $stats['imap_duplicates'] = (int) $r['duplicates'];
                    $stats['imap_errors']     = (int) $r['errors'];
                }
            } catch (\Exception $e) {
                $stats['imap_error_msg'] = 'Excepción IMAP: ' . $e->getMessage();
                $stats['imap_errors']    = 1;
            }
        }

        // ── Fase 2: clasificación + borradores ──
        // Resolvemos el classifier en este momento para que tome el provider
        // configurado actualmente (puede haber cambiado entre runs).
        $classifier = app(EmailTriageClassifierService::class);

        $emails = MarketingEmailReceived::pendingClassification()
            ->orderBy('received_at')
            ->limit($classifyLimit)
            ->get();

        foreach ($emails as $email) {
            try {
                $r = $classifier->process($email);
                if (!empty($r['error'])) {
                    $stats['classify_errors']++;
                    continue;
                }
                $stats['classified']++;
                if (!empty($r['draft_id'])) {
                    $stats['drafts_created']++;
                } else {
                    $stats['discarded']++;
                }
            } catch (\Exception $e) {
                $stats['classify_errors']++;
            }
        }

        // ── Mensaje resumen para la UI ──
        $msg = $this->buildRunNowSummary($stats, $skipRead);

        return response()->json([
            'ok'      => true,
            'message' => $msg,
            'stats'   => $stats,
        ]);
    }

    /**
     * Resolver ID del usuario autenticado (0 si no hay).
     */
    private function resolveUserId()
    {
        $u = Auth::user();
        return $u && isset($u->id) ? (int) $u->id : 0;
    }

    /**
     * Generar una clave única por tenant para el throttle del runNow.
     * El cache es por tenant (BD propia), pero la key igual incluye el tenant
     * por si en el futuro se usa cache compartido entre tenants.
     */
    private function resolveTenantThrottleKey()
    {
        try {
            $website = app(\Hyn\Tenancy\Environment::class)->tenant();
            if ($website && isset($website->id)) {
                return 'tenant_' . $website->id;
            }
        } catch (\Exception $e) {
            // ignorar
        }
        return 'global';
    }

    /**
     * Mensaje legible para la UI a partir de las estadísticas del run.
     */
    private function buildRunNowSummary(array $stats, $skipRead)
    {
        $parts = [];

        if (!$skipRead) {
            if (!empty($stats['imap_error_msg'])) {
                $parts[] = 'Lectura IMAP: ' . $stats['imap_error_msg'];
            } elseif ($stats['imap_persisted'] === 0 && $stats['imap_duplicates'] === 0) {
                $parts[] = 'No hay correos nuevos en la bandeja.';
            } else {
                $parts[] = sprintf(
                    'Leí %d correo(s) nuevos (%d duplicados ya conocidos).',
                    $stats['imap_persisted'], $stats['imap_duplicates']
                );
            }
        }

        if ($stats['classified'] > 0) {
            $parts[] = sprintf(
                'IA: %d clasificado(s), %d borrador(es) generado(s)' .
                ($stats['discarded'] > 0 ? ', %d descartado(s)' : ''),
                $stats['classified'], $stats['drafts_created'], $stats['discarded']
            );
        }

        if ($stats['classify_errors'] > 0) {
            $parts[] = $stats['classify_errors'] . ' error(es) en clasificación (revisar logs).';
        }

        if (empty($parts)) {
            return 'Revisión completada. Sin novedades.';
        }
        return implode(' ', $parts);
    }
}
