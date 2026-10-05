<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Command;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Storage\StorageManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

final class VariantsCommandFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): VariantsCommand
    {
        /**
         * What each path is entitled to is resolved inside the backend, from
         * storage.variants and storage.paths — the command needs no config.
         */
        return new VariantsCommand(Services::get($container, StorageManager::class));
    }
}
