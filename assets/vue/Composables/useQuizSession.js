/**
 * Composable pour gérer les actions liées à une session de quiz.
 * @returns {object} - Un objet contenant les fonctions mutualisées.
 * @param quizSessionId
 */
export function useQuizSession(quizSessionId) {
  // Pour s'assurer que les props restent réactives si elles sont passées à un composable
  // const { quizSessionId } = toRefs(props); // Peut être utile si quizSessionId est mis à jour

  const finishQuiz = () => {
    // Route: FinishController.php
    window.location.href = `/quiz/${quizSessionId}/finish`
  }

  const abortQuiz = () => {
    if (confirm('Êtes-vous sûr de vouloir abandonner le quiz ?')) {
      window.location.href = `/quiz/${quizSessionId}/abort`
    }
  }

  return {
    finishQuiz,
    abortQuiz,
  }
}
