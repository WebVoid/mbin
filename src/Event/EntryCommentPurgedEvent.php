<?php declare(strict_types = 1);

namespace App\Event;

use App\Entity\Magazine;

class EntryCommentPurgedEvent
{
    public function __construct(private Magazine $magazine)
    {
    }

    public function getMagazine(): Magazine
    {
        return $this->magazine;
    }
}
