<?php

declare(strict_types=1);

namespace App\Security;

use App\DTO\UserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\IpResolver;
use App\Service\SettingsManager;
use App\Service\UserManager;
use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use TheNetworg\OAuth2\Client\Provider\AzureResourceOwner;

class AzureAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly RouterInterface $router,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserManager $userManager,
        private readonly IpResolver $ipResolver,
        private readonly Slugger $slugger,
        private readonly UserRepository $userRepository,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'oauth_azure_verify' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('azure');
        $slugger = $this->slugger;

        $provider = $client->getOAuth2Provider();

        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $request->query->get('code'),
        ]);

        $rememberBadge = new RememberMeBadge();
        $rememberBadge = $rememberBadge->enable();

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $slugger) {
                /** @var AzureResourceOwner $azureUser */
                $azureUser = $client->fetchUserFromToken($accessToken);

                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(
                    ['oauthAzureId' => $azureUser->getUpn()]
                );

                if ($existingUser) {
                    return $existingUser;
                }

                $user = $this->userRepository->findOneBy(['email' => $azureUser->getUpn()]);

                if ($user) {
                    $user->oauthAzureId = $azureUser->getUpn();

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    return $user;
                }

                if (false === $this->settingsManager->get('MBIN_SSO_REGISTRATIONS_ENABLED')) {
                    throw new CustomUserMessageAuthenticationException('MBIN_SSO_REGISTRATIONS_ENABLED');
                }

                $username = $slugger->slug($azureUser->toArray()['name']);

                if ($this->userRepository->count(['username' => $username]) > 0) {
                    $username .= rand(1, 999);
                }

                $dto = (new UserDto())->create(
                    $username,
                    $azureUser->getUpn()
                );

                $dto->plainPassword = bin2hex(random_bytes(20));
                $dto->ip = $this->ipResolver->resolve();

                $user = $this->userManager->create($dto, false);
                $user->oauthAzureId = $azureUser->getUpn();
                $user->isVerified = true;

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            }),
            [
                $rememberBadge,
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetUrl = $this->router->generate('user_settings_profile');

        return new RedirectResponse($targetUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        if ('MBIN_SSO_REGISTRATIONS_ENABLED' === $message) {
            $session = $request->getSession();
            $session->getFlashBag()->add('error', 'sso_registrations_enabled.error');

            return new RedirectResponse($this->router->generate('app_login'));
        }

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
}
