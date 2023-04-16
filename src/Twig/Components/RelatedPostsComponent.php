<?php

namespace App\Twig\Components;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Twig\Runtime\UserExtensionRuntime;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Twig\Environment;

#[AsTwigComponent('related_posts', template: 'components/_cached.html.twig')]
final class RelatedPostsComponent
{
    public const TYPE_TAG = 'tag';
    public const TYPE_MAGAZINE = 'magazine';
    public const TYPE_RANDOM = 'random';

    public int $limit = 4;
    public ?string $tag = null;
    public ?string $magazine = null;
    public ?string $type = self::TYPE_RANDOM;
    public ?Post $post = null;
    public string $title = 'random_posts';

    public function __construct(
        private readonly PostRepository $repository,
        private readonly CacheInterface $cache,
        private readonly Environment $twig
    ) {
    }

    #[PostMount]
    public function postMount(array $attr): array
    {
        if ($this->tag) {
            $this->title = 'related_posts';
            $this->type = self::TYPE_TAG;
        }

        if ($this->magazine) {
            $this->title = 'related_posts';
            $this->type = self::TYPE_MAGAZINE;
        }

        return $attr;
    }

    public function getHtml(ComponentAttributes $attributes): string
    {
        $postId = $this->post?->getId();

        return $this->cache->get(
            "related_posts_{$this->magazine}_{$this->tag}_{$postId}_{$this->type}",
            function (ItemInterface $item) use ($attributes) {
                $item->expiresAfter(60);

                $posts = match ($this->type) {
                    self::TYPE_TAG => $this->repository->findRelatedByMagazine($this->tag, $this->limit + 20),
                    self::TYPE_MAGAZINE => $this->repository->findRelatedByTag(
                        UserExtensionRuntime::username($this->magazine),
                        $this->limit + 20
                    ),
                    default => $this->repository->findLast($this->limit + 50),
                };

                if (count($posts) > $this->limit) {
                    shuffle($posts); // randomize the order
                    $posts = array_slice($posts, 0, $this->limit);
                }

                return $this->twig->render(
                    'components/related_posts.html.twig',
                    [
                        'attributes' => $attributes,
                        'posts' => $posts,
                        'title' => $this->title,
                    ]
                );
            }
        );
    }
}
