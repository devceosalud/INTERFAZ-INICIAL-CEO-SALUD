<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SunatService
{
    protected $token;
    protected $url;

    public function __construct()
    {
        //$this->token = config('apidatosperu.apisperu.token');
        //$this->url = config('apidatosperu.apisperu.url');

        $this->token = config('apidatosperu.aqpfact.token');
        $this->url = config('apidatosperu.aqpfact.url_ruc');
    }


    public function consultar($ruc)
    {
        /*
        //https://dniruc.apisperu.com/api/v1/ruc/20131312955?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6ImFudGhvbnkuYW5lY0BnbWFpbC5jb20ifQ.af-ZYh3Qr0_wjCG8HTWMWtHw5sFsOVjSekaS2d818sA
        $response = Http::acceptJson()
            ->get("{$this->url}/{$ruc}", [
                'token' => $this->token
            ]);


        if (!$response->successful()) {
            return null;
        }

        $json = $response->json();
        if (!isset($json['ruc'])) {
            return null;
        }

        return [
            'ruc' => $json['ruc'],
            'razon_social' => $json['razonSocial'],
            'nombre_comercial' => $json['nombreComercial'],
            'estado' => $json['estado'],
            'condicion' => $json['condicion'],
            'direccion' => $json['direccion'],
            'departamento' => $json['departamento'],
            'provincia' => $json['provincia'],
            'distrito' => $json['distrito'],
            'ubigeo' => $json['ubigeo'],
            'capital' => $json['capital'],
        ];
        */

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->get("{$this->url}/{$ruc}");

        if (!$response->successful()) {
            return null;
        }

        $json = $response->json();

        if (!isset($json['success']) || $json['success'] === false) {
            return null;
        }

        return [
            'ruc' => $json['data']['ruc'],
            'razon_social' => $json['data']['nombre_o_razon_social'],
            'nombre_comercial' => $json['data']['trade_name'],
            'direccion' => $json['data']['direccion'],
            'direccion_completa' => $json['data']['direccion_completa'],
            'estado' => $json['data']['estado'],
            'condicion' => $json['data']['condicion'],
            'departamento' => $json['data']['departamento'],
            'provincia' => $json['data']['provincia'],
            'distrito' => $json['data']['distrito'],
            'ubigeo' => $json['data']['ubigeo_sunat'],
            'es_agente_retencion' => !empty($json['data']['es_agente_de_retencion']),
            'es_buen_contribuyente' => !empty($json['data']['es_buen_contribuyente']),
        ];
    }
}
