<?php

namespace Modules\Logistica\Helpers;

use GuzzleHttp\Client;
use Exception;

class LogisticaNotionDashboard
{
    protected $token;
    protected $databases;
    protected $client;

    public function __construct()
    {
        $this->token     = config('notion_logistica.token');
        $this->databases = config('notion_logistica.databases');

        $this->client = new Client([
            'base_uri' => 'https://api.notion.com/v1/',
            'timeout'  => 25,
        ]);
    }

    /**
     * ============================================================
     * CONSULTA COMPLETA A NOTION (con paginación)
     * ============================================================
     */
    protected function queryAll(string $databaseId): array
    {
        $results    = [];
        $hasMore    = true;
        $nextCursor = null;

        while ($hasMore) {
            $payload = ['page_size' => 100];
            if ($nextCursor) {
                $payload['start_cursor'] = $nextCursor;
            }

            try {
                $response = $this->client->post(
                    "databases/{$databaseId}/query",
                    [
                        'headers' => [
                            'Authorization'  => 'Bearer ' . $this->token,
                            'Notion-Version' => '2022-06-28',
                            'Content-Type'   => 'application/json',
                        ],
                        'json' => $payload,
                    ]
                );

                $data = json_decode($response->getBody()->getContents(), true);

                if (!isset($data['results'])) {
                    throw new Exception('Respuesta inválida de Notion');
                }

                $results   = array_merge($results, $data['results']);
                $hasMore   = $data['has_more'] ?? false;
                $nextCursor = $data['next_cursor'] ?? null;

            } catch (Exception $e) {
                return ['error' => $e->getMessage()];
            }
        }

        return ['results' => $results];
    }

    /**
     * ============================================================
     * DASHBOARD PRINCIPAL
     * ============================================================
     */
    public function getDashboardData(): array
    {
        $response = [
            'meta' => ['timestamp' => time(), 'source' => 'live'],
            'kpis' => ['activos' => 0, 'entregados' => 0, 'reprocesos' => 0, 'pendientes' => 0, 'avance_total' => 0],
            'status' => ['En curso'=>0, 'Entregado'=>0, 'Cerrado'=>0, 'Reproceso'=>0],       
            'weeks' => ['week1'=>[], 'week2'=>[], 'week3'=>[], 'week4'=>[], 'week5'=>[]],
            'criticality' => [], 
            'closing' => [
                'EXCELENTE'=>['count'=>0,'projects'=>[]], 
                'BUENO'=>['count'=>0,'projects'=>[]], 
                'REGULAR'=>['count'=>0,'projects'=>[]], 
                'PENDIENTE'=>['count'=>0,'projects'=>[]], 
                'REPROCESO'=>['count'=>0,'projects'=>[]]
            ],
            'has_reproceso' => false,
            'reproceso_projects' => []
        ];

        // 1. Obtener Datos (Con protección Try/Catch por si el servidor falla)
        $rawProyectos = $this->queryAll($this->databases['pipeline']);
        if (isset($rawProyectos['error'])) {
            $response['meta']['error'] = $rawProyectos['error'];
            return $response;
        }

        // 2. Procesar Proyectos
        $totalAvance = 0;
        $countProjects = 0;

        foreach ($rawProyectos['results'] as $page) {
            $props = $page['properties'];
        
            // --- EXTRACCIÓN SEGURA DE DATOS ---
            
            // Nombre
            $fullName = 'Sin Nombre';
            if (!empty($props['Nombre']['title'])) {
                $fullName = $props['Nombre']['title'][0]['plain_text'];
            }
            // Acortar nombre para gráficas (UTF-8 seguro)
            $shortName = (mb_strlen($fullName) > 18) ? mb_substr($fullName, 0, 18) . '...' : $fullName;

            // Estado
            $statusRaw = 'En curso';
            if (isset($props['Progreso']['status']['name'])) $statusRaw = $props['Progreso']['status']['name'];
            elseif (isset($props['Progreso']['select']['name'])) $statusRaw = $props['Progreso']['select']['name'];
            
            $status = 'En curso'; 
            if (mb_stripos($statusRaw, 'Entregado') !== false || mb_stripos($statusRaw, 'Aprobado') !== false) $status = 'Entregado';
            elseif (mb_stripos($statusRaw, 'Cerrado') !== false) $status = 'Cerrado';
            elseif (mb_stripos($statusRaw, 'Reproceso') !== false) $status = 'Reproceso';
            
            if (!isset($response['status'][$status])) $response['status'][$status] = 0;
            $response['status'][$status]++;

            // Lógica Reproceso (Alerta)
            $isReproceso = ($status === 'Reproceso');
            if ($isReproceso) {
                $response['has_reproceso'] = true;
                $response['reproceso_projects'][] = [
                    'name' => $fullName,
                    'criticality' => 'Verificar Estado'
                ];
            }

            // Criticidad Logística
            $critLogistica = 'NORMAL';
            if (isset($props['Criticidad Logistica']['select']['name'])) {
                $critLogistica = mb_strtoupper(trim($props['Criticidad Logistica']['select']['name']));
            }

            // Cierre
            $closingRaw = isset($props['Tipo de cierre']['select']['name']) ? mb_strtoupper(trim($props['Tipo de cierre']['select']['name'])) : 'PENDIENTE';
            $closing = 'PENDIENTE';
            if (strpos($closingRaw, 'EXCEL') !== false) $closing = 'EXCELENTE';
            elseif (strpos($closingRaw, 'BUEN') !== false) $closing = 'BUENO';
            elseif (strpos($closingRaw, 'REGULAR') !== false) $closing = 'REGULAR';
            elseif (strpos($closingRaw, 'REPROCESO') !== false) $closing = 'REPROCESO';
            
            if (!isset($response['closing'][$closing])) $response['closing'][$closing] = ['count'=>0, 'projects'=>[]];
            $response['closing'][$closing]['count']++;
            $response['closing'][$closing]['projects'][] = $fullName;

            // Avance
            $avanceRaw = $props['API_Avance']['formula']['number'] ?? null;
            if ($avanceRaw === null) {
                $avanceRaw = $props['Avance']['number'] ?? ($props['# Avance']['number'] ?? 0);
            }
            $avance = ($avanceRaw <= 1 && $avanceRaw > 0) ? $avanceRaw * 100 : $avanceRaw;
            $avance = round((float)$avance);

            // Semanas (Fecha Inicio)
            $dateStr = $props['Periodo']['date']['start'] ?? date('Y-m-d');
            $projTimestamp = strtotime($dateStr);
            
            if (date('Y-m', $projTimestamp) === date('Y-m')) {
                $day = date('j', $projTimestamp);
                $weekNum = ceil($day / 7);
                if($weekNum > 5) $weekNum = 5; if($weekNum < 1) $weekNum = 1;
                
                $response['weeks']['week'.$weekNum][] = [
                    'name' => $shortName,      
                    'fullName' => $fullName,   
                    'progress' => $avance,
                    'date' => $dateStr,      
                    'is_reproceso' => $isReproceso, 
                    'logistica' => $critLogistica   
                ];
            }

            $totalAvance += $avance;
            $countProjects++;
        }

        // Liberar memoria del array crudo grande
        unset($rawProyectos);

        // 3. Procesar Producción
        $rawProduccion = $this->queryAll($this->databases['backlog']);
        if (!isset($rawProduccion['error']) && !empty($rawProduccion['results'])) {
            foreach ($rawProduccion['results'] as $page) {
                $props = $page['properties'];
                $prodName = !empty($props['Producto']['title']) ? $props['Producto']['title'][0]['plain_text'] : 'Item';
                
                $urgenciaRaw = $props['Urgencia']['select']['name'] ?? 'Sin asignar';
                $urgencia = ucfirst(mb_strtolower($urgenciaRaw)); 
                
                if (!isset($response['criticality'][$urgencia])) {
                    $response['criticality'][$urgencia] = ['count' => 0, 'projects' => []];
                }
                $response['criticality'][$urgencia]['count']++;
                $response['criticality'][$urgencia]['projects'][] = $prodName;
            }
        }
        unset($rawProduccion);


        // 4. Calcular KPIs
        $response['kpis']['activos'] = $response['status']['En curso'] ?? 0;
        $response['kpis']['entregados'] = $response['status']['Entregado'] ?? 0;
        $response['kpis']['reprocesos'] = $response['status']['Reproceso'] ?? 0;
        $response['kpis']['pendientes'] = ($response['closing']['PENDIENTE']['count'] ?? 0) + 
                                        ($response['closing']['REPROCESO']['count'] ?? 0);
        $response['kpis']['avance_total'] = $countProjects > 0 ? round($totalAvance / $countProjects) : 0;


        // 5. Filtrado Top 5 + Urgentes
        foreach ($response['weeks'] as $k => $projects) {
            // A. Filtro Estricto: 'URGENTE'
            $filteredProjects = array_filter($projects, function($p) {
                return (strpos($p['logistica'], 'URGENTE') !== false);
            });
            // B. Top 5
            $response['weeks'][$k] = array_slice(array_values($filteredProjects), 0, 5);
        }

        return $response;
    }

    /**
     * ============================================================
     * HELPERS NOTION
     * ============================================================
     */

    protected function title(array $props, string $key): string
    {
        return $props[$key]['title'][0]['plain_text'] ?? 'Sin nombre';
    }

    protected function status(array $props, string $key): string
    {
        if (isset($props[$key]['status']['name'])) {
            return $props[$key]['status']['name'];
        }
        if (isset($props[$key]['select']['name'])) {
            return $props[$key]['select']['name'];
        }
        return '';
    }

    protected function formulaOrNumber(array $props, ...$keys): float
    {
        foreach ($keys as $key) {
            if (isset($props[$key]['formula']['number'])) {
                return (float) $props[$key]['formula']['number'];
            }
            if (isset($props[$key]['number'])) {
                return (float) $props[$key]['number'];
            }
        }
        return 0;
    }

    protected function date(array $props, string $key): ?string
    {
        return $props[$key]['date']['start'] ?? null;
    }
}
