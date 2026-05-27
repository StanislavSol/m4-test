<?php

namespace M4;

use GuzzleHttp\Client;

class M4ApiClient implements ApiClientInterface
{
    private $http;
    private $token;
    private $sdUrl;
    private $storageUrl;

    public function __construct()
    {
        $this->http = new Client(['verify' => false, 'timeout' => 30]);
    }

    public function auth($login, $pass)
    {
        $resp = $this->http->post('https://developer-api.m4.systems:4443/api_auth/login_check', [
            'json' => ['username' => $login, 'password' => $pass]
        ]);
        
        $data = json_decode($resp->getBody(), true);
        $this->token = $data['token'];
        
        foreach ($data['services'] as $svc) {
            if ($svc['code'] === 'SD') $this->sdUrl = rtrim($svc['apiUrl'], '/');
            if ($svc['code'] === 'STORAGE') $this->storageUrl = rtrim($svc['apiUrl'], '/');
        }
        
        return $data;
    }

    public function getTasks($from)
    {
        $resp = $this->http->post($this->sdUrl, [
            'headers' => ['Authorization' => "Bearer $this->token"],
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'M4GetTasks',
                'params' => ['lastUpdate' => $from],
                'id' => 1
            ]
        ]);
        
        $data = json_decode($resp->getBody(), true);
        return $data['result'] ?? [];
    }

    public function getTask($id)
    {
        $resp = $this->http->post($this->sdUrl, [
            'headers' => ['Authorization' => "Bearer $this->token"],
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'M4GetTaskDetails',
                'params' => ['taskId' => (int)$id],
                'id' => 2
            ]
        ]);
        
        $data = json_decode($resp->getBody(), true);
        return $data['result'] ?? [];
    }

    public function upload($file)
    {
        $resp = $this->http->post($this->storageUrl . '/putfile.php', [
            'headers' => ['Authorization' => "Bearer $this->token"],
            'multipart' => [['name' => 'file', 'contents' => fopen($file, 'r')]]
        ]);
        
        $data = json_decode($resp->getBody(), true);
        return $data['result']['guid'];
    }

    public function attach($taskId, $files)
    {
        $items = array_map(function($g) {
            return ['guid' => $g, 'typeAttachId' => 5];
        }, $files);
        
        $this->http->post($this->sdUrl, [
            'headers' => ['Authorization' => "Bearer $this->token"],
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'M4AddTaskAttach',
                'params' => ['taskId' => (int)$taskId, 'files' => $items],
                'id' => 3
            ]
        ]);
        
        return true;
    }

    public function comment($taskId, $text)
    {
        $this->http->post($this->sdUrl, [
            'headers' => ['Authorization' => "Bearer $this->token"],
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'M4AddTaskComment',
                'params' => [
                    'taskId' => (int)$taskId,
                    'comment' => $text,
                    'isPublic' => true
                ],
                'id' => 4
            ]
        ]);
        
        return true;
    }

    public function logout()
    {
        $this->http->post('https://developer-api.m4.systems:4443/api_auth', [
            'headers' => ['Authorization' => "Bearer $this->token"],
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'logout',
                'id' => 5
            ]
        ]);
        
        return true;
    }
}
