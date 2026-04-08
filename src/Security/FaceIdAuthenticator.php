<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class FaceIdAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const LOGIN_ROUTE = 'app_login';
    private const CSRF_TOKEN_ID = 'face_id_login';
    private const COSINE_THRESHOLD = 0.82;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->getPathInfo() === '/face-id/login' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, 'face-id');

        $token = (string) $request->request->get('_csrf_token', '');
        if ($token === '') {
            throw new CustomUserMessageAuthenticationException('Missing Face ID security token.');
        }

        $descriptorJson = trim((string) $request->request->get('face_descriptor', ''));
        if ($descriptorJson === '') {
            throw new CustomUserMessageAuthenticationException('No face descriptor was provided.');
        }

        $descriptor = json_decode($descriptorJson, true);
        if (!is_array($descriptor) || count($descriptor) < 64) {
            throw new CustomUserMessageAuthenticationException('Invalid face data.');
        }

        $email = strtolower(trim((string) $request->request->get('face_email', '')));
        $normalizedDescriptor = array_values(array_map(static fn ($value) => (float) $value, $descriptor));
        [$matchedUser, $bestSimilarity, $reason] = $this->findBestMatch($normalizedDescriptor, $email !== '' ? $email : null);
        if (!$matchedUser instanceof User) {
            if ($reason === 'template_mismatch') {
                throw new CustomUserMessageAuthenticationException('Your Face ID template is outdated. Log in with password and click Update Face ID.');
            }

            if ($reason === 'template_missing') {
                throw new CustomUserMessageAuthenticationException('No Face ID enrolled for this email. Please enroll it from profile.');
            }

            if (is_finite($bestSimilarity)) {
                throw new CustomUserMessageAuthenticationException(sprintf('Face not recognized (similarity %.3f). Re-enroll Face ID with OpenCV capture.', $bestSimilarity));
            }

            throw new CustomUserMessageAuthenticationException('Face not recognized. Ensure this email has Face ID enrolled.');
        }

        return new SelfValidatingPassport(
            new UserBadge($matchedUser->getUserIdentifier(), static fn () => $matchedUser),
            [new CsrfTokenBadge(self::CSRF_TOKEN_ID, $token)]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();

        if ($user->getRole() === 'ADMIN') {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    private function findBestMatch(array $descriptor, ?string $email = null): array
    {
        if ($email !== null && $email !== '') {
            $targetUser = $this->entityManager->getRepository(User::class)->findByEmail(strtolower($email));
            if (!$targetUser instanceof User || !$targetUser->isActive() || !$targetUser->hasFaceTemplate()) {
                return [null, INF, 'template_missing'];
            }

            $storedDescriptor = $targetUser->getFaceDescriptor();
            if ($storedDescriptor === [] || count($storedDescriptor) !== count($descriptor)) {
                return [null, INF, 'template_mismatch'];
            }

            $similarity = $this->cosineSimilarity($storedDescriptor, $descriptor);
            if ($similarity >= self::COSINE_THRESHOLD) {
                return [$targetUser, $similarity, 'ok'];
            }

            return [null, $similarity, 'low_similarity'];
        }

        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->andWhere('u.faceTemplate IS NOT NULL')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $bestUser = null;
        $bestSimilarity = -1.0;

        foreach ($users as $user) {
            if (!$user instanceof User || !$user->hasFaceTemplate()) {
                continue;
            }

            $storedDescriptor = $user->getFaceDescriptor();
            if (count($storedDescriptor) !== count($descriptor) || $storedDescriptor === []) {
                continue;
            }

            $similarity = $this->cosineSimilarity($storedDescriptor, $descriptor);
            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestUser = $user;
            }
        }

        if ($bestUser instanceof User && $bestSimilarity >= self::COSINE_THRESHOLD) {
            return [$bestUser, $bestSimilarity, 'ok'];
        }

        return [null, $bestSimilarity, 'low_similarity'];
    }

    private function cosineSimilarity(array $left, array $right): float
    {
        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;
        $length = min(count($left), count($right));

        for ($index = 0; $index < $length; $index++) {
            $leftValue = (float) $left[$index];
            $rightValue = (float) $right[$index];
            $dot += $leftValue * $rightValue;
            $leftNorm += $leftValue * $leftValue;
            $rightNorm += $rightValue * $rightValue;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return -1.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }
}
