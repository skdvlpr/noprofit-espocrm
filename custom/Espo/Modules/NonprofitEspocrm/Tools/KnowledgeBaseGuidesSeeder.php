<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\ORM\EntityManager;

/**
 * Upsert Italian Knowledge Base category + articles from module Resources.
 * Idempotent by article name. Safe for DDEV seed and approved prod oneshots.
 */
class KnowledgeBaseGuidesSeeder
{
    private const CATEGORY_NAME = 'Guide operative';
    private const MANIFEST = __DIR__ . '/../Resources/knowledge-base/articles.it_IT.json';

    public function __construct(private EntityManager $entityManager) {}

    /**
     * @return array{categoryId: string, created: list<string>, updated: list<string>}
     */
    public function run(): array
    {
        $manifestPath = realpath(self::MANIFEST) ?: self::MANIFEST;

        if (!is_readable($manifestPath)) {
            throw new \RuntimeException("KB manifest not readable: {$manifestPath}");
        }

        $raw = json_decode((string) file_get_contents($manifestPath), true);

        if (!is_array($raw) || !isset($raw['articles']) || !is_array($raw['articles'])) {
            throw new \RuntimeException('KB manifest must contain an articles array.');
        }

        $category = $this->entityManager
            ->getRDBRepository('KnowledgeBaseCategory')
            ->where(['name' => self::CATEGORY_NAME])
            ->findOne();

        if (!$category) {
            $category = $this->entityManager->getNewEntity('KnowledgeBaseCategory');
            $category->set([
                'name' => self::CATEGORY_NAME,
                'order' => 10,
            ]);
            $this->entityManager->saveEntity($category);
        }

        $created = [];
        $updated = [];

        foreach ($raw['articles'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $body = (string) ($item['body'] ?? '');
            $order = (int) ($item['order'] ?? 10);

            if ($name === '' || $body === '') {
                continue;
            }

            $article = $this->entityManager
                ->getRDBRepository('KnowledgeBaseArticle')
                ->where(['name' => $name])
                ->findOne();

            $isNew = $article === null;

            if ($isNew) {
                $article = $this->entityManager->getNewEntity('KnowledgeBaseArticle');
            }

            $article->set([
                'name' => $name,
                'status' => 'Published',
                'language' => 'it_IT',
                'type' => 'Article',
                'body' => $body,
                'order' => $order,
                'publishDate' => date('Y-m-d'),
            ]);

            $this->entityManager->saveEntity($article);
            $this->entityManager
                ->getRDBRepository('KnowledgeBaseArticle')
                ->getRelation($article, 'categories')
                ->relate($category);

            if ($isNew) {
                $created[] = $name;
            } else {
                $updated[] = $name;
            }
        }

        return [
            'categoryId' => (string) $category->getId(),
            'created' => $created,
            'updated' => $updated,
        ];
    }
}
