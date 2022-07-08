<?php declare(strict_types=1);

namespace App\Components;

use App\Entity\Entry;
use App\Service\SearchManager;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Twig\Environment;

#[AsTwigComponent('related_entries')]
class RelatedEntriesComponent
{
    public Entry $entry;

    public function __construct(private SearchManager $manager, private Environment $twig, private Security $security, private CacheInterface $cache)
    {
    }

    public function getHtml(): string
    {
        $id = $this->entry->getId();

        return $this->cache->get('related_'.$id.'_'.$this->security->getUser()?->getId(), function (ItemInterface $item) {
            $item->expiresAfter(3600);

            $entries = $this->manager->findRelated($this->entry->title.' '.$this->entry->magazine->name);
            $entries = array_filter($entries, fn($e) => $e->getId() !== $this->entry->getId());

            if (!count($entries)) {
                return '';
            }

            return $this->twig->render('entry/_related.html.twig', ['entries' => $entries]);
        });
    }
}
