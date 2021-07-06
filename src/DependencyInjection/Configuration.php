<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\LdapBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const ROOT_ID = 'huh_ldap';

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder(static::ROOT_ID);

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('user')
                    ->children()
                        ->scalarNode('host')->defaultValue('localhost')->end()
                        ->integerNode('port')->defaultValue(389)->end()
                        ->enumNode('encryption')->values(['plain', 'ssl'])->defaultValue('ssl')->end()
                        ->scalarNode('bind_dn')->isRequired()->end()
                        ->scalarNode('bind_password')->defaultValue('')->end()
                        ->scalarNode('person_username_ldap_field')->end()
                        ->arrayNode('person')
                            ->children()
                                ->scalarNode('base_dn')->isRequired()->end()
                                ->scalarNode('filter')->end()
                                ->arrayNode('skip_uids')->defaultValue([])
                                    ->scalarPrototype()->end()
                                ->end()
                                ->arrayNode('skip_uid_numbers')->defaultValue([])
                                    ->scalarPrototype()->end()
                                ->end()
                                ->arrayNode('field_mapping')->defaultValue([])
                                    ->arrayPrototype()
                                        ->children()
                                            ->scalarNode('ldap_field')->isRequired()->end()
                                            ->scalarNode('contao_field')->isRequired()->end()
                                        ->end()
                                    ->end()
                                ->end()
                                ->arrayNode('default_values')->defaultValue([])
                                    ->arrayPrototype()
                                        ->children()
                                            ->scalarNode('field')->isRequired()->end()
                                            ->scalarNode('value')->isRequired()->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('group')
                            ->children()
                                ->scalarNode('base_dn')->isRequired()->end()
                                ->scalarNode('filter')->end()
                                ->arrayNode('skip_gid_numbers')->defaultValue([])
                                    ->scalarPrototype()->end()
                                ->end()
                                ->arrayNode('field_mapping')->defaultValue([])
                                    ->arrayPrototype()
                                        ->children()
                                            ->scalarNode('ldap_field')->isRequired()->end()
                                            ->scalarNode('contao_field')->isRequired()->end()
                                        ->end()
                                    ->end()
                                ->end()
                                ->arrayNode('default_values')->defaultValue([])
                                    ->arrayPrototype()
                                        ->children()
                                            ->scalarNode('field')->isRequired()->end()
                                            ->scalarNode('value')->isRequired()->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
