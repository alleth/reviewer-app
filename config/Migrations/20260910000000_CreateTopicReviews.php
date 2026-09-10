<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Detailed study/explainer content for a topic — distinct from the practice
 * question bank. One row per topic in v1 (all site-authored); `author_id` is
 * nullable now so attribution to individual (future professional) authors
 * doesn't need another migration later.
 *
 * NOTE: docker-start.sh does not run migrations, so on Railway apply the
 * equivalent SQL by hand (see CLAUDE.md).
 */
class CreateTopicReviews extends AbstractMigration
{
    public function change(): void
    {
        $this->table('topic_reviews', ['id' => false, 'primary_key' => ['topic_review_id']])
            ->addColumn('topic_review_id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('topic_id', 'integer', ['signed' => false])
            // Signed (not unsigned) to match users.user_id, which is `int(11)` signed.
            ->addColumn('author_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('content', 'text', [])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['topic_id'], ['unique' => true])
            ->addForeignKey('topic_id', 'topics', 'topic_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('author_id', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'NO_ACTION'])
            ->create();
    }
}
