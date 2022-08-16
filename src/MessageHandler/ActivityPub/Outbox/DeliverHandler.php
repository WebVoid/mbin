<?php declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPubManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class DeliverHandler implements MessageHandlerInterface
{
    public function __construct(private ApHttpClient $client, private ActivityPubManager $manager)
    {
    }

    public function __invoke(DeliverMessage $message): void
    {
        $user = $this->manager->findActorOrCreate($message->payload['object']['attributedTo'] ?? $message->payload['actor']);
        if ($user->isBanned) {
            return;
        }

        $this->client->post($this->client->getInboxUrl($message->apProfileId), $user, $message->payload);
    }
}