<?php

namespace App\Controller;

use App\Entity\ChatConversation;
use App\Entity\ChatMessage;
use App\Entity\User;
use App\Repository\ChatConversationRepository;
use App\Repository\ChatMessageRepository;
use App\Service\ContentModerationService;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Service\MessageRewriteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MessageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageRewriteService $messageRewriteService,
        private ContentModerationService $contentModerationService,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Route('/messages', name: 'app_messages', methods: ['GET'])]
    public function index(Request $request, ChatMessageRepository $chatMessageRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $withId = (int) $request->query->get('with', 0);
            $params = $withId > 0 ? ['with' => $withId] : [];

            return $this->redirectToRoute('app_admin_messages', $params);
        }

        [$contacts, $unreadCounts, $lastMessages] = $this->buildContactSidebarData($currentUser, $chatMessageRepository);
        $selectedContact = null;

        $withId = (int) $request->query->get('with', 0);
        if ($withId > 0) {
            foreach ($contacts as $contact) {
                if ($contact->getId() === $withId) {
                    $selectedContact = $contact;
                    break;
                }
            }
        }

        if ($selectedContact === null && $contacts !== []) {
            $selectedContact = $contacts[0];
        }

        $conversation = [];
        if ($selectedContact instanceof User) {
            $chatMessageRepository->markConversationAsRead($currentUser, $selectedContact);
            $conversation = $chatMessageRepository->findConversation($currentUser, $selectedContact);
        }

        return $this->render('messages/index.html.twig', [
            'contacts' => $contacts,
            'selectedContact' => $selectedContact,
            'conversation' => $conversation,
            'unreadCounts' => $unreadCounts,
            'lastMessages' => $lastMessages,
            'messagesRouteName' => 'app_messages',
            'adminMode' => false,
        ]);
    }

    #[Route('/admin/messages', name: 'app_admin_messages', methods: ['GET'])]
    public function adminIndex(Request $request, ChatMessageRepository $chatMessageRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        [$contacts, $unreadCounts, $lastMessages] = $this->buildContactSidebarData($currentUser, $chatMessageRepository);
        $selectedContact = null;

        $withId = (int) $request->query->get('with', 0);
        if ($withId > 0) {
            foreach ($contacts as $contact) {
                if ($contact->getId() === $withId) {
                    $selectedContact = $contact;
                    break;
                }
            }
        }

        if ($selectedContact === null && $contacts !== []) {
            $selectedContact = $contacts[0];
        }

        $conversation = [];
        if ($selectedContact instanceof User) {
            $chatMessageRepository->markConversationAsRead($currentUser, $selectedContact);
            $conversation = $chatMessageRepository->findConversation($currentUser, $selectedContact);
        }

        return $this->render('messages/index.html.twig', [
            'contacts' => $contacts,
            'selectedContact' => $selectedContact,
            'conversation' => $conversation,
            'unreadCounts' => $unreadCounts,
            'lastMessages' => $lastMessages,
            'messagesRouteName' => 'app_admin_messages',
            'adminMode' => true,
        ]);
    }

    #[Route('/messages/send', name: 'app_messages_send', methods: ['POST'])]
    public function send(Request $request, ChatConversationRepository $chatConversationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('messages_send', $csrfToken)) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Invalid security token.'], Response::HTTP_FORBIDDEN);
            }

            $this->addFlash('error', 'Invalid security token. Please retry.');
            return $this->redirectToRoute('app_messages');
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
            }

            return $this->redirectToRoute('app_login');
        }

        $recipientId = (int) $request->request->get('recipient_id', 0);
        $messageBody = trim((string) $request->request->get('body', ''));

        if ($recipientId <= 0 || $messageBody === '') {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Recipient and message are required.'], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', 'Recipient and message are required.');
            return $this->redirectToRoute('app_messages');
        }

        /** @var User|null $recipient */
        $recipient = $this->entityManager->getRepository(User::class)->find($recipientId);
        if (!$recipient instanceof User || !$this->canMessage($currentUser, $recipient)) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'You are not allowed to message this user.'], Response::HTTP_FORBIDDEN);
            }

            $this->addFlash('error', 'You are not allowed to message this user.');
            return $this->redirectToRoute('app_messages');
        }

        try {
            $moderationResult = $this->contentModerationService->assess($messageBody);
        } catch (\Throwable $exception) {
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'code' => 'moderation_unavailable',
                    'error' => 'La moderation est indisponible pour le moment. Reessayez plus tard.',
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            $this->addFlash('error', 'La moderation est indisponible pour le moment. Reessayez plus tard.');
            return $this->redirectToRoute('app_messages');
        }

        if ($moderationResult['flagged']) {
            $currentUser->incrementModerationWarningCount();
            $warningCount = $currentUser->getModerationWarningCount();
            $now = new \DateTimeImmutable();
            $currentUser->setUpdatedAt($now);

            $blocked = $warningCount >= 2;
            if ($blocked) {
                $currentUser->setIsActive(false);
                $currentUser->setModerationBlockedAt($now);

                // Invalidate server-side session and clear security token immediately
                try {
                    $this->tokenStorage->setToken(null);
                    $session = $request->getSession();
                    if ($session) {
                        $session->invalidate();
                    }
                } catch (\Throwable $e) {
                    // best-effort: do not break the moderation flow if token storage fails
                }
            }

            $this->entityManager->flush();

            $errorMessage = $blocked
                ? 'Votre compte a ete bloque apres une deuxieme violation.'
                : 'Votre message a ete signale. Restez professionnel, sinon votre compte sera bloque au prochain incident.';

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'code' => $blocked ? 'account_blocked' : 'moderation_warning',
                    'error' => $errorMessage,
                    'warningCount' => $warningCount,
                    'blocked' => $blocked,
                    'details' => $moderationResult['reason'],
                ], $blocked ? Response::HTTP_LOCKED : Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('error', $errorMessage);

            return $this->redirectToRoute($blocked ? 'app_login' : 'app_messages', ['with' => $recipient->getId()]);
        }

        $conversation = $chatConversationRepository->findBetweenUsers($currentUser, $recipient);
        if (!$conversation instanceof ChatConversation) {
            $conversation = (new ChatConversation())
                ->setUserA($currentUser)
                ->setUserB($recipient);

            $this->entityManager->persist($conversation);
        }

        $chatMessage = new ChatMessage();
        $chatMessage->setConversation($conversation);
        $chatMessage->setSender($currentUser);
        $chatMessage->setRecipient($recipient);
        $chatMessage->setBody($messageBody);

        $conversation->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($chatMessage);
        $this->entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'message' => $this->serializeMessage($chatMessage, $currentUser),
            ]);
        }

        return $this->redirectToRoute('app_messages', ['with' => $recipient->getId()]);
    }

    #[Route('/messages/thread/{id}', name: 'app_messages_thread', methods: ['GET'])]
    public function thread(int $id, ChatMessageRepository $chatMessageRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['success' => false, 'error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var User|null $contact */
        $contact = $this->entityManager->getRepository(User::class)->find($id);
        if (!$contact instanceof User || !$this->canMessage($currentUser, $contact)) {
            return $this->json(['success' => false, 'error' => 'Forbidden conversation.'], Response::HTTP_FORBIDDEN);
        }

        $conversation = $chatMessageRepository->findConversation($currentUser, $contact);
        $chatMessageRepository->markConversationAsRead($currentUser, $contact);

        return $this->json([
            'success' => true,
            'messages' => array_map(fn (ChatMessage $message) => $this->serializeMessage($message, $currentUser), $conversation),
            'unreadCount' => 0,
        ]);
    }

    #[Route('/messages/unread-counts', name: 'app_messages_unread_counts', methods: ['GET'])]
    public function unreadCounts(ChatMessageRepository $chatMessageRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['success' => false, 'error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $contacts = $this->findAllowedContacts($currentUser);
        $counts = [];

        foreach ($contacts as $contact) {
            if (!$contact instanceof User) {
                continue;
            }

            $counts[(string) $contact->getId()] = $chatMessageRepository->countUnreadFromContact($currentUser, $contact);
        }

        return $this->json([
            'success' => true,
            'counts' => $counts,
        ]);
    }

    #[Route('/messages/rewrite', name: 'app_messages_rewrite', methods: ['POST'])]
    public function rewrite(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $csrfToken = (string) ($request->request->get('_csrf_token') ?: $request->request->get('_rewrite_csrf_token') ?: $request->headers->get('X-CSRF-TOKEN', ''));

        if (!$this->isCsrfTokenValid('messages_rewrite', $csrfToken)) {
            return $this->json(['success' => false, 'error' => 'Invalid security token.'], Response::HTTP_FORBIDDEN);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['success' => false, 'error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $body = trim((string) $request->request->get('body', ''));
        if ($body === '') {
            return $this->json(['success' => false, 'error' => 'Le message ne peut pas etre vide.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($body) > 2000) {
            return $this->json(['success' => false, 'error' => 'Le message depasse 2000 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $rewritten = $this->messageRewriteService->rewrite($body);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage() ?: 'Impossible de reecrire le message pour le moment.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json([
            'success' => true,
            'rewritten' => $rewritten,
        ]);
    }

    #[Route('/messages/toggle-star/{id}', name: 'app_messages_toggle_star', methods: ['POST'])]
    public function toggleStar(int $id, ChatMessageRepository $chatMessageRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['success' => false, 'error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $message = $chatMessageRepository->find($id);
        if (!$message instanceof ChatMessage) {
            return $this->json(['success' => false, 'error' => 'Message not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($message->getSender()?->getId() !== $currentUser->getId() && $message->getRecipient()?->getId() !== $currentUser->getId()) {
            return $this->json(['success' => false, 'error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $newState = !$message->isStarred();
        $message->setIsStarred($newState);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'isStarred' => $newState,
        ]);
    }

    /**
     * @return User[]
     */
    private function findAllowedContacts(User $currentUser): array
    {
        $queryBuilder = $this->entityManager->getRepository(User::class)->createQueryBuilder('u')
            ->andWhere('u.id != :currentUserId')
            ->andWhere('u.isActive = :active')
            ->setParameter('currentUserId', $currentUser->getId())
            ->setParameter('active', true)
            ->orderBy('u.fullName', 'ASC');

        if ($currentUser->getRole() === 'CLIENT') {
            $queryBuilder
                ->andWhere('u.role = :adminRole')
                ->setParameter('adminRole', 'ADMIN');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    private function canMessage(User $sender, User $recipient): bool
    {
        if ($sender->getId() === $recipient->getId()) {
            return false;
        }

        if ($sender->getRole() === 'CLIENT' && $recipient->getRole() === 'CLIENT') {
            return false;
        }

        return true;
    }

    /**
     * @return array{0: User[], 1: array<int, int>, 2: array<int, array{body: string, createdAt: string, timestamp: int, mine: bool}>}
     */
    private function buildContactSidebarData(User $currentUser, ChatMessageRepository $chatMessageRepository): array
    {
        $contacts = $this->findAllowedContacts($currentUser);
        $unreadCounts = [];
        $lastMessages = [];

        foreach ($contacts as $contact) {
            if (!$contact instanceof User) {
                continue;
            }

            $contactId = $contact->getId();
            if ($contactId === null) {
                continue;
            }

            $unreadCounts[$contactId] = $chatMessageRepository->countUnreadFromContact($currentUser, $contact);

            $lastMessage = $chatMessageRepository->findLastMessageWithContact($currentUser, $contact);
            if (!$lastMessage instanceof ChatMessage) {
                continue;
            }

            $createdAt = $lastMessage->getCreatedAt();
            $sender = $lastMessage->getSender();
            $lastMessages[$contactId] = [
                'body' => (string) $lastMessage->getBody(),
                'createdAt' => $createdAt?->format('d/m/Y H:i') ?? '',
                'timestamp' => $createdAt?->getTimestamp() ?? 0,
                'mine' => $sender instanceof User && $sender->getId() === $currentUser->getId(),
            ];
        }

        usort($contacts, function (User $a, User $b) use ($lastMessages): int {
            $aData = $lastMessages[$a->getId()] ?? null;
            $bData = $lastMessages[$b->getId()] ?? null;

            $aTs = $aData['timestamp'] ?? 0;
            $bTs = $bData['timestamp'] ?? 0;

            if ($aTs !== $bTs) {
                return $bTs <=> $aTs;
            }

            $aName = mb_strtolower((string) ($a->getFullName() ?: $a->getEmail()));
            $bName = mb_strtolower((string) ($b->getFullName() ?: $b->getEmail()));

            return $aName <=> $bName;
        });

        return [$contacts, $unreadCounts, $lastMessages];
    }

    private function serializeMessage(ChatMessage $message, User $currentUser): array
    {
        $sender = $message->getSender();

        return [
            'id' => $message->getId(),
            'body' => $message->getBody(),
            'mine' => $sender instanceof User && $sender->getId() === $currentUser->getId(),
            'senderLabel' => $sender instanceof User
                ? ($sender->getId() === $currentUser->getId() ? 'You' : ($sender->getFullName() ?: $sender->getEmail()))
                : 'Unknown',
            'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i') ?? '',
            'isRead' => $message->isRead(),
            'isStarred' => $message->isStarred(),
        ];
    }
}