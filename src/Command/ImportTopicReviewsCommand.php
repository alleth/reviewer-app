<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Bulk-import topic study/explainer content from a CSV.
 *
 *   bin/cake import_topic_reviews resources/topic-reviews-template.csv --dry-run
 *   bin/cake import_topic_reviews path/to/topic-reviews.csv
 *
 * One row per topic. Columns (header row required, order-independent,
 * case-insensitive): topic, content
 *
 * - the topic must already exist (created via `import_questions`) — this
 *   command never creates topics
 * - one review per topic: re-running the same file *updates* that topic's
 *   content (unlike import_questions, which skips dupes) so revising the
 *   write-up is just a re-run
 * - author is not set by this command; v1 content is site-authored (null)
 *
 * Validation is all-or-nothing: if any row has a problem, nothing is written.
 */
class ImportTopicReviewsCommand extends Command
{
    /**
     * @param \Cake\Console\ConsoleOptionParser $parser Parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Bulk-import topic study/explainer content from a CSV file.')
            ->addArgument('file', ['help' => 'Path to the CSV file.', 'required' => true])
            ->addOption('dry-run', ['boolean' => true, 'help' => 'Parse and validate only; write nothing.']);
    }

    /**
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $path = (string)$args->getArgument('file');
        if (!is_file($path)) {
            $io->error("File not found: $path");

            return static::CODE_ERROR;
        }

        $rows = $this->readCsv($path);
        if ($rows === null) {
            $io->error('Could not read the CSV, or it has no data rows.');

            return static::CODE_ERROR;
        }

        [$reviews, $errors] = $this->parseRows($rows);

        if ($errors !== []) {
            $io->error(count($errors) . ' problem(s) found — nothing was imported:');
            foreach ($errors as $e) {
                $io->out('  • ' . $e);
            }

            return static::CODE_ERROR;
        }

        $io->success(sprintf(
            '%d topic review(s) parsed OK: %s',
            count($reviews),
            implode(', ', array_column($reviews, 'topic')),
        ));

        if ($args->getOption('dry-run')) {
            $io->info('Dry run — nothing written.');

            return static::CODE_SUCCESS;
        }

        $Topics = $this->fetchTable('Topics');
        $TopicReviews = $this->fetchTable('TopicReviews');

        $created = 0;
        $updated = 0;
        $missingTopics = [];

        foreach ($reviews as $r) {
            $topic = $Topics->find()->where(['name' => $r['topic']])->first();
            if (!$topic) {
                $missingTopics[] = "line {$r['line']}: no topic named '{$r['topic']}' (run import_questions first)";
                continue;
            }

            $existing = $TopicReviews->find()->where(['topic_id' => $topic->topic_id])->first();
            $entity = $existing ?? $TopicReviews->newEmptyEntity();
            $entity = $TopicReviews->patchEntity($entity, [
                'topic_id' => $topic->topic_id,
                'content' => $r['content'],
            ]);

            if (!$TopicReviews->save($entity)) {
                $io->error("Save failed (line {$r['line']}): " . json_encode($entity->getErrors()));

                return static::CODE_ERROR;
            }

            $existing ? $updated++ : $created++;
        }

        if ($missingTopics !== []) {
            $io->error(count($missingTopics) . ' row(s) skipped — topic not found:');
            foreach ($missingTopics as $m) {
                $io->out('  • ' . $m);
            }
        }

        $io->success("Imported {$created} new, updated {$updated} existing topic review(s).");

        return $missingTopics !== [] ? static::CODE_ERROR : static::CODE_SUCCESS;
    }

    /**
     * Reads the CSV into a list of associative rows keyed by normalized header.
     *
     * @param string $path File path.
     * @return array<int, array<string, string>>|null
     */
    private function readCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return null;
        }

        $header = fgetcsv($handle);
        if ($header === false || $header === null) {
            fclose($handle);

            return null;
        }
        // Strip a UTF-8 BOM off the first header cell, then normalize.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
        $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);

        $rows = [];
        $line = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            // Skip fully blank lines.
            if (count(array_filter($data, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = trim((string)($data[$i] ?? ''));
            }
            $row['__line'] = (string)$line;
            $rows[] = $row;
        }
        fclose($handle);

        return $rows === [] ? null : $rows;
    }

    /**
     * Validates and structures the rows. Returns [reviews, errors]; when
     * `errors` is non-empty, `reviews` should be ignored.
     *
     * @param array<int, array<string, string>> $rows Parsed CSV rows.
     * @return array{0: array<int, array<string, string>>, 1: array<int, string>}
     */
    private function parseRows(array $rows): array
    {
        $reviews = [];
        $errors = [];
        $seen = [];

        foreach ($rows as $row) {
            $line = $row['__line'] ?? '?';
            $topic = $row['topic'] ?? '';
            $content = $row['content'] ?? '';
            $rowErrors = [];

            if ($topic === '') {
                $rowErrors[] = 'missing topic';
            }
            if ($content === '') {
                $rowErrors[] = 'missing content';
            }

            $key = mb_strtolower($topic);
            if ($topic !== '' && isset($seen[$key])) {
                $rowErrors[] = "duplicate topic, already on line {$seen[$key]}";
            }

            if ($rowErrors !== []) {
                $errors[] = "line $line: " . implode('; ', $rowErrors);
                continue;
            }

            $seen[$key] = $line;

            $reviews[] = [
                'line' => $line,
                'topic' => $topic,
                'content' => $content,
            ];
        }

        return [$reviews, $errors];
    }
}
