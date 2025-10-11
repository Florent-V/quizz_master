<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MistralApiService
{
    private string $apiKey;
    private string $mistralAgentId;
    private HttpClientInterface $httpClient;

    public function __construct(
        string $mistralApiKey,
        string $mistralAgentId,
        HttpClientInterface $httpClient,
    ) {
        $this->apiKey         = $mistralApiKey;
        $this->mistralAgentId = $mistralAgentId;
        $this->httpClient     = $httpClient;
    }

    /**
     * Pose une question texte à l'API Mistral et retourne la réponse.
     */
    public function askQuestion(string $question, string $model = 'mistral-large-latest'): string
    {
        $url = 'https://api.mistral.ai/v1/chat/completions';

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'    => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $question],
                ],
            ],
        ]);

        $data = $response->toArray();

        return $data['choices'][0]['message']['content'] ?? 'Aucune réponse valide reçue.';
    }

    /**
     * ÉTAPE 1 : Crée un agent Mistral avec la capacité de génération d'images.
     *
     * @param string $name        Nom de l'agent
     * @param string $description Description de l'agent
     * @param string $model       Modèle à utiliser (par défaut: mistral-medium-2505)
     *
     * @return array{id: string, name: string, model: string, created_at: string, full_data: array<string, mixed>}
     */
    public function createImageGenerationAgent(
        string $name = 'Image Generation Agent',
        string $description = 'Agent used to generate images.',
        string $model = 'mistral-large-latest',
    ): array {
        $url = 'https://api.mistral.ai/v1/agents';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'model'        => $model,
                    'name'         => $name,
                    'description'  => $description,
                    'instructions' => 'Use the image generation tool when you have to create images.',
                    'tools'        => [
                        [
                            'type' => 'image_generation',
                        ],
                    ],
                    'completion_args' => [
                        'temperature' => 0.3,
                        'top_p'       => 0.95,
                    ],
                ],
            ]);



            $data = $response->toArray();

            return [
                'id'         => $data['id'],
                'name'       => $data['name'],
                'model'      => $data['model'],
                'created_at' => $data['created_at'],
                'full_data'  => $data,
            ];
        } catch (ClientExceptionInterface $e) {
            throw new \RuntimeException('Erreur lors de la création de l\'agent : ' . $e->getMessage());
        }
    }

    /**
     * ÉTAPE 2 : Génère une image via l'API Conversations de Mistral.
     *
     * @param string $prompt  Description de l'image à générer
     * @param string $agentId ID de l'agent Mistral avec image_generation activé
     *
     * @return array{
     *     conversation_id: string|null,
     *     file_id: string|null,
     *     text: string|null,
     *     file_name: string|null,
     *     file_type: string|null,
     *     usage: array<string, mixed>|null
     * }|null
     */
    public function generateImage(string $prompt, string $agentId): ?array
    {
        $url = 'https://api.mistral.ai/v1/conversations';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'inputs'   => $prompt,
                    'stream'   => false,
                    'agent_id' => $agentId,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['outputs'])) {
                return null;
            }

            return $this->parseImageGenerationResponse($data);
        } catch (ClientExceptionInterface $e) {
            throw new \RuntimeException('Erreur lors de la génération de l\'image : ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \RuntimeException('Erreur inattendue : ' . $e->getMessage());
        } catch (\Throwable $e) {
            throw new \RuntimeException('Erreur critique : ' . $e->getMessage());
        }
    }

    /**
     * Parse la réponse de l'API de génération d'image.
     *
     * @param array<string, mixed> $data
     *
     * @return array{
     *     conversation_id: string|null,
     *     file_id: string|null,
     *     text: string|null,
     *     file_name: string|null,
     *     file_type: string|null,
     *     usage: array<string, mixed>|null
     * }
     */
    private function parseImageGenerationResponse(array $data): array
    {
        $result = [
            'conversation_id' => $data['conversation_id'] ?? null,
            'file_id'         => null,
            'text'            => null,
            'file_name'       => null,
            'file_type'       => null,
            'usage'           => $data['usage'] ?? null,
        ];

        foreach ($data['outputs'] as $output) {
            $this->extractOutputContent($output, $result);
        }

        return $result;
    }

    /**
     * Extrait le contenu d'un output (texte et fichier image).
     *
     * @param array<string, mixed> $output
     * @param array<string, mixed> $result
     */
    private function extractOutputContent(array $output, array &$result): void
    {
        if ('message.output' !== $output['type'] || !isset($output['content'])) {
            return;
        }

        foreach ($output['content'] as $contentItem) {
            $this->extractTextContent($contentItem, $result);
            $this->extractImageFileContent($contentItem, $result);
        }
    }

    /**
     * Extrait le contenu texte d'un item.
     *
     * @param array<string, mixed> $contentItem
     * @param array<string, mixed> $result
     */
    private function extractTextContent(array $contentItem, array &$result): void
    {
        if ('text' === $contentItem['type']) {
            $result['text'] = $contentItem['text'];
        }
    }

    /**
     * Extrait les informations du fichier image d'un item.
     *
     * @param array<string, mixed> $contentItem
     * @param array<string, mixed> $result
     */
    private function extractImageFileContent(array $contentItem, array &$result): void
    {
        if ('tool_file' !== $contentItem['type'] || 'image_generation' !== $contentItem['tool']) {
            return;
        }

        $result['file_id']   = $contentItem['file_id'];
        $result['file_name'] = $contentItem['file_name'];
        $result['file_type'] = $contentItem['file_type'];
    }

    /**
     * ÉTAPE 3 : Télécharge le contenu d'un fichier image généré par Mistral.
     *
     * @param string $fileId ID du fichier à télécharger
     *
     * @return string Contenu binaire de l'image
     */
    public function downloadImageFile(string $fileId): string
    {
        $url = sprintf('https://api.mistral.ai/v1/files/%s/content', $fileId);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization'   => 'Bearer ' . $this->apiKey,
                    'Accept'          => 'application/octet-stream',
                    'Accept-Encoding' => 'gzip, deflate, zstd',
                ],
            ]);

            return $response->getContent();
        } catch (ClientExceptionInterface $e) {
            throw new \RuntimeException('Erreur lors du téléchargement du fichier : ' . $e->getMessage());
        }
    }

    /**
     * WORKFLOW COMPLET : Crée un agent, génère une image et la télécharge en une seule fois.
     *
     * @param string $prompt Description de l'image à générer
     *
     * @return array{
     *     agent_id: string,
     *     conversation_id: string,
     *     file_id: string,
     *     file_name: string,
     *     file_type: string,
     *     description: string,
     *     image_content: string,
     *     usage: array<string, mixed>
     * }
     */
    public function generateImageWorkflow(
        string $prompt,
    ): array {

        $agentId = 'YOUR_MISTRAL_AGENT_ID' === $this->mistralAgentId ? null : $this->mistralAgentId;

        // ÉTAPE 1 : Créer l'agent si nécessaire
        if (!$agentId) {
            $agentData = $this->createImageGenerationAgent();
            $agentId   = $agentData['id'];
        }

        // ÉTAPE 2 : Générer l'image
        $imageData = $this->generateImage($prompt, $agentId);

        if (!$imageData || !$imageData['file_id']) {
            throw new \RuntimeException('Échec de la génération de l\'image');
        }

        // ÉTAPE 3 : Télécharger l'image
        $imageContent = $this->downloadImageFile($imageData['file_id']);

        return [
            'agent_id'        => $agentId,
            'conversation_id' => $imageData['conversation_id'],
            'file_id'         => $imageData['file_id'],
            'file_name'       => $imageData['file_name'],
            'file_type'       => $imageData['file_type'],
            'description'     => $imageData['text'],
            'image_content'   => $imageContent,
            'usage'           => $imageData['usage'],
        ];
    }
}
