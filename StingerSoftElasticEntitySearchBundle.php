<?php

/*
 * This file is part of the Stinger Elastic Entity Search package.
 *
 * (c) Oliver Kotte <oliver.kotte@stinger-soft.net>
 * (c) Florian Meyer <florian.meyer@stinger-soft.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace StingerSoft\ElasticEntitySearchBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use StingerSoft\DoctrineEntitySearchBundle\StingerSoftEntitySearchBundle;

/**
 */
class StingerSoftElasticEntitySearchBundle extends Bundle {

	public static function getRequiredBundles($env) {
		$bundles = array();
		$bundles['StingerSoftElasticEntitySearchBundle'] = '\StingerSoft\ElasticEntitySearchBundle\StingerSoftElasticEntitySearchBundle';
		$bundles = array_merge($bundles, StingerSoftEntitySearchBundle::getRequiredBundles($env));
		return $bundles;
	}
}