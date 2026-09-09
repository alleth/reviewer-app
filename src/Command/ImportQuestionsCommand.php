<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

/**
 * Bulk-import reviewer content from a CSV.
 *
 *   bin/cake import_questions resources/questions-template.csv --dry-run
 *   bin/cake import_questions path/to/questions.csv
 *   bin/cake import_questions path/to/questions.csv --fresh --force
 *
 * One row per question. Columns (header row required, order-independent,
 * case-insensitive):
 *   topic, question, difficulty, explanation,
 *   choice_a, choice_b, choice_c, choice_d, choice_e, answer
 *
 * - difficulty: easy | medium | hard   (1 | 2 | 3 also accepted)
 * - answer: the letter (A–E) of the correct choice
 * - choice_d / choice_e / explanation may be blank; there must be ≥ 2 choices
 * - topics are created if they don't exist
 * - a row whose (topic, question) already exists in the DB is skipped, so
 *   re-running the same file is safe; `--fresh` wipes questions+choices first
 *
 * Validation is all-or-nothing: if any row has a problem, nothing is written.
 */
class ImportQuestionsCommand extends Command
{
    private const DIFFICULTY = [
        'easy' => 1, 'medium' => 2, 'hard' => 3,
        '1' => 1, '2' => 2, '3' => 3,
    ];

    private const CHOICE_COLS = ['choice_a', 'choice_b', 'choice_c', 'choice_d', 'choice_e'];

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser Parser.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Bulk-import reviewer questions from a CSV file.')
            ->addArgument('file', ['help' => 'Path to the CSV file.', 'required' => true])
            ->addOption('dry-run', ['boolean' => true, 'help' => 'Parse and validate only; write nothing.'])
            ->addOption('fresh', [
                'boolean' => true,
                'help' => 'Delete ALL existing questions & choices first (topics are kept).',
            ])
            ->addOption('force', ['boolean' => true, 'help' => 'Skip the --fresh confirmation prompt.']);
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

        [$questions, $errors] = $this->parseRows($rows);

        if ($errors !== []) {
            $io->error(count($errors) . ' problem(s) found — nothing was imported:');
            foreach ($errors as $e) {
                $io->out('  • ' . $e);
            }

            return static::CODE_ERROR;
        }

        $topics = array_values(array_unique(array_map(fn($q) => $q['topic'], $questions)));
        $io->success(sprintf(
            '%d question(s) parsed OK across %d topic(s): %s',
            count($questions),
            count($topics),
            implode(', ', $topics),
        ));

        if ($args->getOption('dry-run')) {
            $io->info('Dry run — nothing written.');

            return static::CODE_SUCCESS;
        }

        if ($args->getOption('fresh') && !$args->getOption('force')) {
            if ($io->askChoice('Delete ALL existing questions and choices first?', ['y', 'n'], 'n') !== 'y') {
                $io->out('Aborted.');

                return static::CODE_ERROR;
            }
        }

        $connection = ConnectionManager::get('default');
        /** @var array{imported: int, skipped: int} $result */
        $result = $connection->transactional(function () use ($questions, $args, $io): array {
            $Topics = $this->fetchTable('Topics');
            $Questions = $this->fetchTable('Questions');
            $Choices = $this->fetchTable('Choices');

            if ($args->getOption('fresh')) {
                $Choices->deleteAll('1=1');
                $Questions->deleteAll('1=1');
                $io->out('Cleared existing questions & choices.');
            }

            $topicId = [];
            $imported = 0;
            $skipped = 0;

            foreach ($questions as $q) {
                if (!isset($topicId[$q['topic']])) {
                    $topic = $Topics->find()->where(['name' => $q['topic']])->first()
                        ?? $Topics->save($Topics->newEntity(['name' => $q['topic'], 'description' => '']));
                    if (!$topic) {
                        throw new RuntimeException("Could not create topic '{$q['topic']}'.");
                    }
                    $topicId[$q['topic']] = $topic->topic_id;
                }

                $duplicate = $Questions->find()
                    ->where(['topic_id' => $topicId[$q['topic']], 'question_text' => $q['question']])
                    ->count() > 0;
                if ($duplicate) {
                    $skipped++;
                    continue;
                }

                $entity = $Questions->newEntity([
                    'topic_id' => $topicId[$q['topic']],
                    'question_text' => $q['question'],
                    'difficulty' => $q['difficulty'],
                    'explanation' => $q['explanation'],
                    'choices' => $q['choices'],
                ], ['associated' => ['Choices']]);

                if (!$Questions->save($entity, ['associated' => ['Choices']])) {
                    throw new RuntimeException(
                        "Save failed (line {$q['line']}): " . json_encode($entity->getErrors()),
                    );
                }
                $imported++;
            }

            return ['imported' => $imported, 'skipped' => $skipped];
        });

        $io->success(
            "Imported {$result['imported']} question(s)." .
            ($result['skipped'] > 0 ? " Skipped {$result['skipped']} already present." : ''),
        );

        return static::CODE_SUCCESS;
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
     * Validates and structures the rows. Returns [questions, errors]; when
     * `errors` is non-empty, `questions` should be ignored.
     *
     * @param array<int, array<string, string>> $rows Parsed CSV rows.
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function parseRows(array $rows): array
    {
        $questions = [];
        $errors = [];
        $seen = [];

        foreach ($rows as $row) {
            $line = $row['__line'] ?? '?';
            $topic = $row['topic'] ?? '';
            $question = $row['question'] ?? '';
            $rowErrors = [];

            if ($topic === '') {
                $rowErrors[] = 'missing topic';
            }
            if ($question === '') {
                $rowErrors[] = 'missing question';
            }

            $difficulty = self::DIFFICULTY[strtolower($row['difficulty'] ?? '')] ?? null;
            if ($difficulty === null) {
                $rowErrors[] = "difficulty must be easy/medium/hard (got '" . ($row['difficulty'] ?? '') . "')";
            }

            $choiceTexts = [];
            foreach (self::CHOICE_COLS as $col) {
                $val = $row[$col] ?? '';
                if ($val !== '') {
                    $choiceTexts[$col] = $val;
                }
            }
            if (count($choiceTexts) < 2) {
                $rowErrors[] = 'need at least 2 non-blank choices';
            }

            $answer = strtolower($row['answer'] ?? '');
            $answerCol = $answer !== '' ? 'choice_' . $answer : '';
            if ($answerCol === '' || !in_array($answerCol, self::CHOICE_COLS, true)) {
                $rowErrors[] = "answer must be a letter A–E (got '" . ($row['answer'] ?? '') . "')";
            } elseif (!isset($choiceTexts[$answerCol])) {
                $rowErrors[] = "answer '$answer' points to a blank choice";
            }

            $key = mb_strtolower($topic . '||' . $question);
            if ($topic !== '' && $question !== '' && isset($seen[$key])) {
                $rowErrors[] = "duplicate of line {$seen[$key]}";
            }

            if ($rowErrors !== []) {
                $errors[] = "line $line: " . implode('; ', $rowErrors);
                continue;
            }

            $seen[$key] = $line;

            $choices = [];
            $order = 0;
            foreach ($choiceTexts as $col => $text) {
                $choices[] = [
                    'choice_text' => $text,
                    'is_correct' => $col === $answerCol,
                    'sort_order' => $order++,
                ];
            }

            $questions[] = [
                'line' => $line,
                'topic' => $topic,
                'question' => $question,
                'difficulty' => $difficulty,
                'explanation' => ($row['explanation'] ?? '') !== '' ? $row['explanation'] : null,
                'choices' => $choices,
            ];
        }

        return [$questions, $errors];
    }
}
