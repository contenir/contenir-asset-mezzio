<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Command;

use Contenir\Storage\Entry;
use Contenir\Storage\Exception\InvalidPathException;
use Contenir\Storage\Exception\NotFoundException;
use Contenir\Storage\ListOptions;
use Contenir\Storage\MissingVariantsReporterInterface;
use Contenir\Storage\StorageInterface;
use Contenir\Storage\StorageManager;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function count;
use function implode;
use function is_array;
use function is_string;
use function pathinfo;
use function sprintf;
use function str_contains;

use const PATHINFO_BASENAME;

/**
 * Audit — and optionally backfill — the storage variants for every original in
 * a backend.
 *
 * What a path is entitled to comes entirely from config: `storage.variants`
 * declares the families, `storage.paths` declares which paths own them, and the
 * backend applies both. The command asks the backend rather than re-deriving
 * the expected keys, so there is one source of truth and no way for tooling and
 * rendering to disagree about what should exist.
 *
 * Reporting uses {@see MissingVariantsReporterInterface::missingVariants()} —
 * no writes, no source download. Generation uses
 * {@see StorageInterface::regenerateMissingVariants()}, which fetches each
 * original once and derives every missing variant from that single copy.
 *
 * Idempotent and re-runnable; safe to re-run while uploads continue.
 *
 * @mago-expect lint:cyclomatic-complexity One branch per option and per reported outcome; already split by step.
 */
final class VariantsCommand extends Command
{
    public function __construct(
        private readonly StorageManager $manager,
    ) {
        parent::__construct();
    }

    /**
     * @mago-expect analysis:mixed-assignment Console options are untyped input; checked here.
     */
    private static function option(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return is_string($value) ? $value : '';
    }

    #[Override]
    protected function configure(): void
    {
        $this->setName('storage:variants')
            ->setDescription('Report and optionally backfill missing storage variants for a backend.')
            ->addOption(
                'backend',
                'b',
                InputOption::VALUE_REQUIRED,
                'Storage backend to operate on (default: the primary backend).',
                '',
            )
            ->addOption(
                'prefix',
                null,
                InputOption::VALUE_REQUIRED,
                'Only process originals under this key prefix.',
                '',
            )
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Process at most N originals.', '0')
            ->addOption('generate', 'g', InputOption::VALUE_NONE, 'Generate the missing variants (default: report).');
    }

    /**
     * @throws InvalidArgumentException If no backend is registered.
     * @throws NotFoundException        If the --prefix does not exist.
     * @throws InvalidPathException     If the --prefix is unsafe (traversal, null byte).
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $prefix   = self::option($input, 'prefix');
        $generate = true === $input->getOption('generate');
        $storage  = $this->backend($io, self::option($input, 'backend'));
        if (null === $storage) {
            return Command::FAILURE;
        }
        if (! $generate && null === $storage['reporter']) {
            $io->error(sprintf('Backend "%s" cannot report missing variants.', $storage['name']));

            return Command::FAILURE;
        }

        $io->title(sprintf(
            'storage:variants — backend=%s%s%s',
            $storage['name'],
            '' === $prefix ? '' : " prefix={$prefix}",
            $generate ? ' [GENERATE]' : ' [report only]',
        ));

        $tally = $this->walk(
            $io,
            $storage['backend'],
            $generate ? null : $storage['reporter'],
            $prefix,
            (int) self::option(
                $input,
                'limit',
            ),
        );

        $io->newLine();
        $io->table(
            ['originals', 'complete', $generate ? 'generated' : 'missing', 'errors'],
            [[$tally['originals'], $tally['complete'], $tally['keys'], $tally['errors']]],
        );
        if (! $generate && $tally['keys'] > 0) {
            $io->note('Re-run with --generate to create the missing variants.');
        }

        return $tally['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * The named backend (the primary when $name is empty), or null after
     * reporting why it cannot be used.
     *
     * @return array{name: string, backend: StorageInterface, reporter: ?MissingVariantsReporterInterface}|null
     *
     * @throws InvalidArgumentException If no backend is registered.
     */
    private function backend(SymfonyStyle $io, string $name): ?array
    {
        $name = '' === $name ? $this->manager->primaryKey() : $name;
        if (! $this->manager->has($name)) {
            $io->error(sprintf(
                'Unknown backend "%s". Available: %s.',
                $name,
                implode(', ', $this->manager->profiles()),
            ));

            return null;
        }

        $backend = $this->manager->get($name);

        return [
            'name'     => $name,
            'backend'  => $backend,
            'reporter' => $backend instanceof MissingVariantsReporterInterface ? $backend : null,
        ];
    }

    /**
     * Walk the backend recursively, yielding image-file originals (keys without
     * a `__variant` suffix). list() is one level deep, so recurse directories.
     *
     * @return iterable<Entry>
     *
     * @throws NotFoundException    If $path does not exist.
     * @throws InvalidPathException If $path is unsafe (traversal, null byte).
     */
    private function eachOriginal(StorageInterface $storage, string $path): iterable
    {
        foreach ($storage->list($path, new ListOptions(includeDirectories: true)) as $entry) {
            if ($entry->isDir) {
                yield from $this->eachOriginal($storage, $entry->path);
                continue;
            }
            if ($entry->isImage() && ! str_contains(pathinfo($entry->path, PATHINFO_BASENAME), '__')) {
                yield $entry;
            }
        }
    }

    /**
     * Generate the variants one original is missing, or with a $reporter only
     * report them. Returns the keys, false when the original vanished, or null
     * on failure.
     *
     * @return list<string>|false|null
     */
    private function process(
        SymfonyStyle $io,
        Entry $entry,
        StorageInterface $storage,
        ?MissingVariantsReporterInterface $reporter,
    ): array|false|null {
        try {
            $keys = null === $reporter
                ? $storage->regenerateMissingVariants($entry->path)
                : $reporter->missingVariants($entry->path);
        } catch (NotFoundException) {
            /**
             * Listed a moment ago and gone now — a concurrent delete, not a fault.
             */
            $io->writeln("  <comment>vanished</comment> {$entry->path}", OutputInterface::VERBOSITY_VERBOSE);

            return false;
        } catch (Throwable $e) {
            $io->writeln(sprintf('  <error>fail</error> %s: %s', $entry->path, $e->getMessage()));

            return null;
        }

        $label = null === $reporter ? '<info>made</info>' : '<comment>missing</comment>';
        foreach ($keys as $key) {
            $io->writeln("  {$label} {$key}", OutputInterface::VERBOSITY_VERBOSE);
        }

        return $keys;
    }

    /**
     * Process every original under $prefix, up to $limit (0 for no limit).
     *
     * @return array{originals: int, complete: int, keys: int, errors: int}
     *
     * @throws NotFoundException    If $prefix does not exist.
     * @throws InvalidPathException If $prefix is unsafe (traversal, null byte).
     */
    private function walk(
        SymfonyStyle $io,
        StorageInterface $storage,
        ?MissingVariantsReporterInterface $reporter,
        string $prefix,
        int $limit,
    ): array {
        $tally = ['originals' => 0, 'complete' => 0, 'keys' => 0, 'errors' => 0];
        foreach ($this->eachOriginal($storage, $prefix) as $entry) {
            if ($limit > 0 && $tally['originals'] >= $limit) {
                break;
            }
            ++$tally['originals'];

            $keys              = $this->process($io, $entry, $storage, $reporter);
            $tally['errors']   += null === $keys ? 1 : 0;
            $tally['complete'] += [] === $keys ? 1 : 0;
            $tally['keys']     += is_array($keys) ? count($keys) : 0;

            if (0 === ($tally['originals'] % 100)) {
                $io->writeln(sprintf('  … %d originals', $tally['originals']), OutputInterface::VERBOSITY_VERBOSE);
            }
        }

        return $tally;
    }
}
