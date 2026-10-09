<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NotionLogisticaService
{
    public function getDashboardData(): array
    {
        $apiKey     = config('notion.api_key');
        $databaseId = config('notion.database_id');

        // 1) Llamar a Notion (aquí copias la lógica de data.php)
        $headers = [
            'Authorization'   => 'Bearer '.$apiKey,
            'Notion-Version'  => '2022-06-28',
            'Content-Type'    => 'application/json',
        ];

        $response = Http::withHeaders($headers)
            ->post('https://api.notion.com/v1/databases/'.$databaseId.'/query', [
                // mismos filtros y sorts que tu data.php
            ]);

        if (!$response->ok()) {
            return [
                'error' => true,
                'message' => 'Error consultando Notion',
            ];
        }

        $results = $response->json('results') ?? [];

        // 2) Procesar resultados igual que en data.php
        //    (contar estados, armar weeks, criticality, closing, etc.)
        //    Aquí copias/adaptas los bucles de data.php
        $kpis         = [/* ... */];
        $weeks        = [/* ... */];
        $status       = [/* ... */];
        $criticality  = [/* ... */];
        $closing      = [/* ... */];
        $hasReproceso = false;
        $reprocesoProjects = [];

        return [
            'error'             => false,
            'kpis'              => $kpis,
            'weeks'             => $weeks,
            'status'            => $status,
            'criticality'       => $criticality,
            'closing'           => $closing,
            'has_reproceso'     => $hasReproceso,
            'reproceso_projects'=> $reprocesoProjects,
            'lastSync'          => now()->format('Y-m-d H:i'),
            'source'            => 'Notion',
        ];
    }
}
