<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\DTO\AIQuizDTO;
use App\DTO\QuizConfigurationDTO;
use App\Quiz\Exception\QuizBadRequestException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Uid\Uuid;

readonly class SessionManager
{
    private SessionInterface $session;

    public function __construct(
        RequestStack $requestStack,
    ) {
        $this->session = $requestStack->getSession();
    }

    /**
     * Adds multiple key/value pairs to the session.
     *
     * @param array<string, mixed> $data Associative array of data to add to the session.
     *                                   Example: ['quiz_current_question_id' => 123, 'score' => 10]
     */
    public function setMultiple(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->session->set($key, $value);
        }
    }

    /**
     * Adds a key/value pair to the session.
     *
     * @param string $key   the key under which to store the value
     * @param mixed  $value the value to store
     */
    public function setKey(string $key, mixed $value): void
    {
        $this->session->set($key, $value);
    }

    /**
     * Gets a value from the session by its key.
     *
     * @param string $key the key of the value to retrieve
     *
     * @return mixed the stored value, or null if the key does not exist
     */
    public function getKey(string $key): mixed
    {
        return $this->session->get($key);
    }

    /**
     * Delete key from session.
     *
     * @param string[] $keys
     */
    public function removeKeys(array $keys): void
    {
        foreach ($keys as $key) {
            $this->session->remove($key);
        }
    }

    /**
     * Store Quiz Configuration DTO in session.
     */
    public function setQuizConfigurationDto(QuizConfigurationDTO $dto): void
    {
        $this->session->set('quiz_configuration_dto', $dto);
    }

    /**
     * Store Quiz Configuration for AI session.
     */
    public function setAIQuizConfiguration(AIQuizDTO $dto): void
    {
        $this->session->set('quiz_ai_configuration', $dto);
    }

    /**
     * Retrieve Quiz Configuration DTO from session.
     */
    public function getQuizConfigurationDto(): ?QuizConfigurationDTO
    {
        $quizConfigurationDto = $this->session->get('quiz_configuration_dto');

        if (!($quizConfigurationDto instanceof QuizConfigurationDTO)) {
            throw new QuizBadRequestException(
                'Configuration du quiz invalide ou inexistante. Veuillez recommencer.'
            );
        }

        return $quizConfigurationDto;
    }

    /**
     * Retrieve AI Quiz Configuration DTO from session.
     */
    public function getAIQuizConfiguration(): ?AIQuizDTO
    {
        $quizConfigurationDto = $this->session->get('quiz_ai_configuration');

        if (!($quizConfigurationDto instanceof AIQuizDTO)) {
            throw new QuizBadRequestException(
                'Configuration du quiz invalide ou inexistante. Veuillez recommencer.'
            );
        }

        return $quizConfigurationDto;
    }

    /**
     * @throws QuizBadRequestException
     */
    public function getSessionConfig(): QuizConfigurationDTO
    {
        $quizDto = $this->session->get('quiz_session_config');
        if (!($quizDto instanceof QuizConfigurationDTO)) {
            throw new QuizBadRequestException(
                'Configuration du quiz invalide ou inexistante. Veuillez recommencer.'
            );
        }

        return $quizDto;
    }

    /**
     * @throws QuizBadRequestException
     */
    public function getQuizSessionId(): Uuid
    {
        $quizSessionId = $this->session->get('quiz_session_id');
        if (null === $quizSessionId) {
            throw new QuizBadRequestException(
                'Session de quiz invalide ou inexistante. Veuillez recommencer.'
            );
        }

        return $quizSessionId;
    }

    /**
     * @throws QuizBadRequestException
     */
    public function getCurrentQuestionId(): int
    {
        $currentQuestionId = $this->session->get('quiz_current_question_id');
        if (null === $currentQuestionId) {
            throw new QuizBadRequestException(
                'Question inexistante. Veuillez recommencer.'
            );
        }

        return $currentQuestionId;
    }

    /**
     * @throws QuizBadRequestException
     */
    public function getSessionAnswerId(): int
    {
        $currentQuestionId = $this->session->get('quiz_current_answer_id');
        if (null === $currentQuestionId) {
            throw new QuizBadRequestException(
                'Réponse inexistante. Veuillez recommencer.'
            );
        }

        return $currentQuestionId;
    }

    /**
     * Clears the session by removing keys matching a prefix or a regular expression.
     *
     * @param string|null $prefix the prefix of the keys to remove
     * @param string|null $regex  the regular expression for the keys to remove
     */
    public function clear(?string $prefix = null, ?string $regex = null): void
    {
        foreach (array_keys($this->session->all()) as $key) {
            if (
                (null !== $prefix && str_starts_with($key, $prefix))
                || (null !== $regex && preg_match($regex, $key))
            ) {
                $this->session->remove($key);
            }
        }
    }
}
