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
namespace StingerSoft\ElasticEntitySearchBundle\Services;

class ClientConfiguration {
	
	
	public function __construct(array $config) {
		
	}

	public $ipAddress;

	public $indexName;

}