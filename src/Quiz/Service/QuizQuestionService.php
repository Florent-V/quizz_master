<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\DTO\QuizConfigurationDTO;
use App\Entity\Question;
use App\Entity\QuizSession;
use App\Entity\QuizSessionAnswer;
use App\Quiz\Exception\NoMoreQuestionsException;
use App\Repository\QuestionRepository;
use App\Repository\QuizSessionAnswerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class QuizQuestionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionRepository $questionRepository,
        private QuizSessionAnswerRepository $quizSessionAnswerRepository,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * @return array<int, Question>
     */
    public function getNextQuestions(QuizSession $quizSession, int $limit): array
    {
        // Récupérer les IDs des questions déjà posées
        $alreadyAskedQuestionIds = $this->quizSessionAnswerRepository
            ->findQuestionIdsByQuizSessionId($quizSession->getId());

        return  $this->questionRepository->findQuizSessionQuestions(
            $quizSession->getCategory(),
            $quizSession->getSubCategory(),
            $quizSession->getDifficulties()->toArray(),
            $limit,
            $alreadyAskedQuestionIds
        );
    }

    /**
     * @throws NoMoreQuestionsException
     */
    public function getQuizQuestion(?int $questionId, QuizConfigurationDTO $quizDto): Question
    {
        $question = $questionId
            ? $this->questionRepository->find($questionId)
            : $this->questionRepository->findQuestionsForQuiz($quizDto, $quizDto->gameMode->getQuestionLimit())[0];

        if (!$question) {
            throw new NoMoreQuestionsException();
        }

        return $question;
    }

    /**
     * @throws NoMoreQuestionsException
     *
     * @return array<array{
     *     id: int,
     *     content: string,
     *     explanation: string|null,
     *     hint: string|null,
     *     imageName: string|null,
     *     category: array{id: int, name: string},
     *     difficulty: array{id: int, name: string},
     *     proposals: array<array{
     *         id: int,
     *         content: string,
     *         isCorrect: bool,
     *         imageName: string|null
     *     }>
     * }>
     */
    public function getNormalizedQuizQuestions(QuizConfigurationDTO $quizDto): array
    {
        $limit     = $quizDto->gameMode->getQuestionLimit();
        $questions = $this->questionRepository->findQuestionsForQuiz($quizDto, $limit);

        return $this->normalizeQuizQuestions($questions);
    }

    /**
     * @throws NoMoreQuestionsException
     *
     * @return array<array{
     *     id: int,
     *     content: string,
     *     explanation: string|null,
     *     hint: string|null,
     *     imageName: string|null,
     *     category: array{id: int, name: string},
     *     difficulty: array{id: int, name: string},
     *     proposals: array<array{
     *         id: int,
     *         content: string,
     *         isCorrect: bool,
     *         imageName: string|null
     *     }>
     * }>
     */
    public function getRandomNormalizedQuizQuestions(int $limit): array
    {
        $questions = $this->questionRepository->findRandomQuestionsForQuiz($limit);

        return $this->normalizeQuizQuestions($questions);
    }

    /**
     * @throws NoMoreQuestionsException
     *
     * @return array<array{
     *     id: int,
     *     content: string,
     *     explanation: string|null,
     *     hint: string|null,
     *     imageName: string|null,
     *     category: array{id: int, name: string},
     *     difficulty: array{id: int, name: string},
     *     proposals: array<array{
     *         id: int,
     *         content: string,
     *         isCorrect: bool,
     *         imageName: string|null
     *     }>
     * }>
     */
    public function getQuestionsForRelativeSession(QuizSession $quizSession): array
    {
        $limit     = $quizSession->getGameMode()->getQuestionLimit();
        $questions = $this->questionRepository->findQuizSessionQuestions(
            $quizSession->getCategory(),
            $quizSession->getSubCategory(),
            $quizSession->getDifficulties()->toArray(),
            $limit
        );

        return $this->normalizeQuizQuestions($questions);
    }

    /**
     * Gets the next unanswered question from the list of question IDs for the session.
     *
     * @param QuizSession $quizSession the current quiz session
     * @param int[]       $questionIds the list of all question IDs for this quiz
     *
     * @return Question|null the next question entity, or null if all questions have been answered
     */
    public function getNextQuestion(QuizSession $quizSession, array $questionIds): ?Question
    {
        $answeredQuestionIds = $this->getAnsweredQuestionIds($quizSession);

        foreach ($questionIds as $questionId) {
            if (!in_array($questionId, $answeredQuestionIds, true)) {
                return $this->entityManager->getRepository(Question::class)->find($questionId);
            }
        }

        return null;
    }

    /**
     * Gets the IDs of all questions that have been answered in a quiz session.
     *
     * @param QuizSession $quizSession the quiz session
     *
     * @return int[] an array of answered question IDs
     */
    private function getAnsweredQuestionIds(QuizSession $quizSession): array
    {
        return array_map(
            fn (QuizSessionAnswer $answer) => $answer->getQuestion()->getId(),
            $quizSession->getQuizSessionAnswers()->toArray()
        );
    }

    /**
     * Normalise un tableau de questions pour l'API et mélange l'ordre.
     *
     * @param array<int, Question> $questions
     *
     * @throws NoMoreQuestionsException
     *
     * @return array<array{
     *     id: int,
     *     content: string,
     *     explanation: string|null,
     *     hint: string|null,
     *     imageName: string|null,
     *     category: array{id: int, name: string},
     *     difficulty: array{id: int, name: string},
     *     proposals: array<array{
     *         id: int,
     *         content: string,
     *         isCorrect: bool,
     *         imageName: string|null
     *     }>
     * }>
     */
    public function normalizeQuizQuestions(array $questions): array
    {
        if (!count($questions)) {
            throw new NoMoreQuestionsException();
        }

        // @phpstan-ignore-next-line
        $questionsArray = $this->serializer->normalize($questions, 'json', [
            'groups' => ['quiz:question:read'],
        ]);

        shuffle($questionsArray);

        return $questionsArray;
    }

    public function getQuestionNumber(QuizSession $quizSession): int
    {
        return $this->quizSessionAnswerRepository->countAnsweredQuestions($quizSession) + 1;
    }
}
