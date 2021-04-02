<?php declare(strict_types = 1);

namespace App\Event;

use App\Entity\Magazine;
use App\Entity\MagazineBan;
use App\Entity\User;

class MagazineBanEvent
{
    public function __construct(private MagazineBan $ban)
    {
    }

    public function getBan(): MagazineBan
    {
        return $this->ban;
    }
}
