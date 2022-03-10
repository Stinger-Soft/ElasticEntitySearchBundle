<?php
declare(strict_types=1);

/*
 * This file is part of the Stinger Elastic Entity Search package.
 *
 * (c) Oliver Kotte <oliver.kotte@stinger-soft.net>
 * (c) Florian Meyer <florian.meyer@stinger-soft.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace StingerSoft\ElasticEntitySearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface {

	/**
	 *
	 * {@inheritDoc}
	 *
	 */
	public function getConfigTreeBuilder(): void {
		$treeBuilder = new TreeBuilder('stinger_soft_elastic_entity_search');

		// @formatter:off
		$treeBuilder->getRootNode()->children()
			->scalarNode('ipaddress')->defaultValue('127.0.0.1')->end()
			->scalarNode('indexname')->defaultValue('stinger_search2')->end()
		->end();
		// @formatter:on

		return $treeBuilder;
	}
}
