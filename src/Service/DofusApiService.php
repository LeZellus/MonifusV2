<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DofusApiService
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function fetchItems(int $skip = 0, int $limit = 50): array
    {
        try {
            $url = "https://api.beta.dofusdb.fr/items?\$limit={$limit}&\$skip={$skip}";
            
            $response = $this->client->request('GET', $url, [
                'timeout' => 30
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray();
            return $data['data'] ?? [];

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Recherche d'items par nom
     */
    public function searchItems(string $query, int $limit = 20): array
    {
        try {
            $encodedQuery = urlencode($query);
            $url = "https://api.beta.dofusdb.fr/items?\$limit={$limit}&name.fr[\$regex]={$encodedQuery}";
            
            $response = $this->client->request('GET', $url, [
                'timeout' => 30
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray();
            return $data['data'] ?? [];

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Récupérer un item spécifique par son ankamaId
     */
    public function fetchItem(int $ankamaId): ?array
    {
        try {
            $url = "https://api.beta.dofusdb.fr/items/{$ankamaId}";
            
            $response = $this->client->request('GET', $url, [
                'timeout' => 10
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $response->toArray();

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Récupérer les types d'items depuis l'API
     */
    public function fetchItemTypes(): array
    {
        try {
            $url = "https://api.beta.dofusdb.fr/item-types";
            
            $response = $this->client->request('GET', $url, [
                'timeout' => 30
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray();
            return $data['data'] ?? [];

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Exposer le client HTTP pour usages avancés
     */
    public function getClient(): HttpClientInterface
    {
        return $this->client;
    }
}