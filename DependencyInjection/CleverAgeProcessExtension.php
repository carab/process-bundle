<?php
/*
 * This file is part of the CleverAge/ProcessBundle package.
 *
 * Copyright (C) 2017-2018 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\ProcessBundle\DependencyInjection;

use Sidus\BaseBundle\DependencyInjection\SidusBaseExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @see http://symfony.com/doc/current/cookbook/bundles/extension.html
 *
 * @author Valentin Clavreul <vclavreul@clever-age.com>
 * @author Vincent Chalnot <vchalnot@clever-age.com>
 */
class CleverAgeProcessExtension extends SidusBaseExtension
{
    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        parent::load($configs, $container);

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $processConfigurationRegistry = $container->getDefinition(ProcessConfigurationRegistry::class);
        $processConfigurationRegistry->replaceArgument(0, $config['configurations']);
    }
}
