<?php declare(strict_types=1);

namespace App\Components;

use App\Repository\MagazineRepository;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Twig\Environment;

#[AsTwigComponent('random_magazine')]
class RandomMagazineComponent
{
    public function __construct(private MagazineRepository $repository, private Environment $twig)
    {
    }

    public function getHtml(): string
    {
        $cache      = new FilesystemAdapter();
        $repository = $this->repository;

        return $cache->get('random_magazine', function (ItemInterface $item) use ($repository) {
            $item->expiresAfter(60);

            return $this->twig->render('_layout/_random_magazine.html.twig', [
                'magazine' => $repository->findRandom(),
            ]);
        });
    }
}
