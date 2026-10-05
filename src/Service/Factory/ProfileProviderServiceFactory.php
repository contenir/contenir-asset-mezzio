<?php

declare(strict_types=1);

namespace Contenir\Asset\Mezzio\Service\Factory;

use Contenir\Asset\Mezzio\Container\Services;
use Contenir\Asset\Mezzio\Service\ProfileProviderService;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

final class ProfileProviderServiceFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws UnexpectedValueException When a dependency has the wrong type.
     */
    public function __invoke(ContainerInterface $container): ProfileProviderService
    {
        /**
         * Variant definitions are declared once, flat, under storage.variants —
         * the single source the generator also reads.
         */
        return new ProfileProviderService(Services::storageVariants($container));
    }
}
