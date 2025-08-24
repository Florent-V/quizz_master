<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\DTO\QuizConfigurationDTO;
use App\Quiz\Exception\InvalidSessionException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

readonly class SessionManager
{
    private SessionInterface $session;

    public function __construct(
        RequestStack $requestStack,
        private QuizConfigurationService $quizConfigurationService,
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
     * @throws InvalidSessionException
     */
    public function getQuizConfiguration(): QuizConfigurationDTO
    {
        // Get the configuration and remove it from the session
        // @TODO Remove from session
        $quizDto = $this->quizConfigurationService
            ->fromSession($this->session)
            // ->clearSession($this->session)
            ->build();

        if (!$quizDto) {
            throw new InvalidSessionException('Configuration du quiz invalide ou inexistante. Veuillez recommencer.');
        }

        return $quizDto;
    }

    /**
     * @throws InvalidSessionException
     */
    public function getSessionConfig(): QuizConfigurationDTO
    {
        $quizDto = $this->session->get('quiz_session_config');
        if (!($quizDto instanceof QuizConfigurationDTO)) {
            throw new InvalidSessionException('Configuration du quiz invalide ou inexistante. Veuillez recommencer.');
        }

        return $quizDto;
    }

    /**
     * @throws InvalidSessionException
     */
    public function getQuizSessionId(): int
    {
        $quizSessionId = $this->session->get('quiz_session_id');
        if (null === $quizSessionId) {
            throw new InvalidSessionException('Session de quiz invalide ou inexistante. Veuillez recommencer.');
        }

        return $quizSessionId;
    }

    /**
     * @throws InvalidSessionException
     */
    public function getCurrentQuestionId(): int
    {
        $currentQuestionId = $this->session->get('quiz_current_question_id');
        if (null === $currentQuestionId) {
            throw new InvalidSessionException('Question inexistante. Veuillez recommencer.');
        }

        return $currentQuestionId;
    }

    /**
     * @throws InvalidSessionException
     */
    public function getSessionAnswerId(): int
    {
        $currentQuestionId = $this->session->get('quiz_current_answer_id');
        if (null === $currentQuestionId) {
            throw new InvalidSessionException('Réponse inexistante. Veuillez recommencer.');
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
