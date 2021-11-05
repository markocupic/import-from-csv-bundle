<?php

declare(strict_types=1);

namespace Markocupic\ImportFromCsvBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const ROOT_KEY = 'markocupic_import_from_csv';

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder(self::ROOT_KEY);

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('per_request')
                    ->defaultValue(25)
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
