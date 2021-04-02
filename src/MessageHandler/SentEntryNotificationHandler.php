<?php declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\EntryNotificationMessage;
use App\Repository\EntryRepository;
use App\Service\NotificationManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SentEntryNotificationHandler implements MessageHandlerInterface
{
    public function __construct(
        private EntryRepository $entryRepository,
        private NotificationManager $notificationManager
    ) {
    }

    public function __invoke(EntryNotificationMessage $entryCreatedMessage)
    {
        $entry = $this->entryRepository->find($entryCreatedMessage->getEntryId());
        if (!$entry) {
            return;
        }

        $this->notificationManager->sendNewEntryNotification($entry);
    }
}
