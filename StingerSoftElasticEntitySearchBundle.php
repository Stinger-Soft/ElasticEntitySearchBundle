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
use StingerSoft\EntitySearchBundle\StingerSoftEntitySearchBundle;

/**
 */
class StingerSoftElasticEntitySearchBundle extends Bundle {

	public static function getRequiredBundles(string $env, array &$requiredBundles = []): array {

		if(isset($requiredBundles['StingerSoftElasticEntitySearchBundle'])) {
			return $requiredBundles;
		}

		$requiredBundles['StingerSoftElasticEntitySearchBundle'] = '\StingerSoft\ElasticEntitySearchBundle\StingerSoftElasticEntitySearchBundle';
		StingerSoftEntitySearchBundle::getRequiredBundles($env, $requiredBundles);
		return $requiredBundles;
	}
}
