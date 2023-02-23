<?php

declare(strict_types=1);

/**
 * Hugin and Munin
 *
 * @copyright     Copyright (c) 2023, Plenta.io
 * @author        Plenta.io <https://plenta.io>
 * @link          https://jobboerse-software.de
 * @license       proprietary
 */

namespace Plenta\HuginMunin\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Plenta\HuginMunin\HuginMuninBundle;

/**
 * Class ContaoManagerPlugin.
 */
class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(HuginMuninBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
