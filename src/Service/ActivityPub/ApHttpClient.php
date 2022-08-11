<?php declare(strict_types=1);

namespace App\Service\ActivityPub;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApHttpClient
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    public function getActivityObject(string $url, bool $decoded = true): array|string
    {
        $req = $this->client->request('GET', $url, [
            'headers' => [
                'Accept' => 'application/activity+json,application/ld+json,application/json',
            ],
        ]);

        return $decoded ? json_decode($req->getContent(), true) : $req->getContent();
    }

    public function getActorObject(string $apProfileId)
    {
        $req = $this->client->request('GET', $apProfileId, [
            'headers' => [
                'Accept' => 'application/activity+json,application/ld+json,application/json',
            ],
        ]);

        return json_decode($req->getContent(), true);
    }

    public function post(string $url, array $params): void
    {
        $keyId         = 'key_id';
        $signedHeaders = 'signed_headers';
        $signature     = 'signature';

        $this->client->request('POST', $url, [
            'body'    => $params,
            'headers' => [
                'Signature'    => 'keyId="'.$keyId.'",headers="'.$signedHeaders.'",algorithm="rsa-sha256",signature="'.$signature.'"',
                'Content-Type' => ['application/activity+json'],
                'Accept'       => ['application/activity+json,application/ld+json,application/json'],
                'User-Agent'   => 'kbinBot v0.1 - https://kbin.pub',
            ],
        ]);
    }
}
