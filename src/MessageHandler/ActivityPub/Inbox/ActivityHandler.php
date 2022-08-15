<?php declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Message\ActivityPub\Inbox\AnnounceMessage;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\Message\ActivityPub\Inbox\DeleteMessage;
use App\Message\ActivityPub\Inbox\FollowMessage;
use App\Service\ActivityPub\SignatureValidator;
use App\Service\ActivityPubManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ActivityHandler implements MessageHandlerInterface
{
    public function __construct(private SignatureValidator $signatureValidator, private MessageBusInterface $bus, private ActivityPubManager $manager)
    {
    }

    public function __invoke(ActivityMessage $message): void
    {
        $payload = @json_decode($message->payload, true);

        if ($message->headers) {
            $this->signatureValidator->validate($message->payload, $message->headers);
        }

        $user = $this->manager->findActorOrCreate($payload['actor'] ?? $payload['attributedTo']);
        if ($user->isBanned) {
            return;
        }

        $this->handle($payload);
    }

    private function handle(array $payload)
    {
        switch ($payload['type']) {
            case 'Create':
                $this->bus->dispatch(new CreateMessage($payload['object']));
                break;
            case 'Note':
            case 'Page':
                $this->bus->dispatch(new CreateMessage($payload));
                break;
            case 'Announce':
                $this->bus->dispatch(new AnnounceMessage($payload));
                break;
            case 'Follow':
                $this->bus->dispatch(new FollowMessage($payload));
                break;
            case 'Delete':
                $this->bus->dispatch(new DeleteMessage($payload));
                break;
            case 'Undo':
                $this->handleUndo($payload);
                break;
        }
    }

    private function handleUndo(array $payload)
    {
        if ($payload['object']['type'] === 'Follow') {
            $this->bus->dispatch(new FollowMessage($payload));
        }

        if ($payload['object']['type'] === 'Announce') {
            $this->bus->dispatch(new AnnounceMessage($payload));
        }
    }
}
