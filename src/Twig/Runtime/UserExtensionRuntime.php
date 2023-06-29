<?php

namespace App\Twig\Runtime;

use App\Entity\User;
use App\Repository\ReputationRepository;
use App\Service\MentionManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\RuntimeExtensionInterface;

class UserExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ReputationRepository $reputationRepository,
        private readonly CacheInterface $cache
    ) {
    }

    public function isFollowed(User $followed)
    {
        if (!$this->security->getUser()) {
            return false;
        }

        return $this->security->getUser()->isFollower($followed);
    }

    public function isBlocked(User $blocked)
    {
        if (!$this->security->getUser()) {
            return false;
        }

        return $this->security->getUser()->isBlocked($blocked);
    }

    public static function username(string $value, ?bool $withApPostfix = false): string
    {
        $value = MentionManager::addHandle([$value])[0];

        if (true === $withApPostfix) {
            return $value;
        }

        return explode('@', $value)[1];
    }

    public function getReputationTotal(User $user): int
    {
        return $this->cache->get(
            "user_reputation_{$user->getId()}",
            function (ItemInterface $item) use ($user) {
                $item->expiresAfter(60);

                return $this->reputationRepository->getUserReputationTotal($user);
            }
        );
    }
}
