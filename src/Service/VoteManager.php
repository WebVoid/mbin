<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Contracts\VoteInterface;
use App\Entity\User;
use App\Entity\Vote;
use App\Event\VoteEvent;
use App\Factory\VoteFactory;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class VoteManager
{
    public function __construct(
        private VoteFactory $factory,
        private RateLimiterFactory $voteLimiter,
        private EventDispatcherInterface $dispatcher,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function vote(int $choice, VoteInterface $votable, User $user, $limiter = true): Vote
    {
        if ($limiter) {
            $limiter = $this->voteLimiter->create($user->username);
            if (false === $limiter->consume()->isAccepted()) {
                throw new TooManyRequestsHttpException();
            }
        }

        $vote = $votable->getUserVote($user);

        if ($vote) {
            $choice       = $this->guessUserChoice($choice, $votable->getUserChoice($user));
            $vote->choice = $choice;
        } else {
            $vote = $this->factory->create($choice, $votable, $user);
            $this->entityManager->persist($vote);
        }

        $votable->updateVoteCounts();

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new VoteEvent($votable));

        return $vote;
    }

    private function guessUserChoice(int $choice, int $vote): int
    {
        if ($choice === VoteInterface::VOTE_NONE) {
            return $choice;
        }

        if ($vote === VoteInterface::VOTE_UP) {
            return match ($choice) {
                VoteInterface::VOTE_UP => VoteInterface::VOTE_NONE,
                VoteInterface::VOTE_DOWN => VoteInterface::VOTE_DOWN,
                default => throw new LogicException(),
            };
        }

        if ($vote === VoteInterface::VOTE_DOWN) {
            return match ($choice) {
                VoteInterface::VOTE_UP => VoteInterface::VOTE_UP,
                VoteInterface::VOTE_DOWN => VoteInterface::VOTE_NONE,
                default => throw new LogicException(),
            };
        }

        return $choice;
    }

    public function upvote(VoteInterface $votable, User $user): ?Vote
    {
        // @todo save activity pub object id
        $vote = $votable->getUserVote($user);

        if ($vote) {
            return null;
        }

        $votable->updateVoteCounts();

        // @todo interfaces
        $votable->entry?->updateLastActive();
        $votable->post?->updateLastActive();

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new VoteEvent($votable));

        return $vote;
    }
}
