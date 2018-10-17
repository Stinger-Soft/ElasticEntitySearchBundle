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

namespace StingerSoft\ElasticEntitySearchBundle\Services;

use Elastica\Query;
use Elastica\ResultSet;
use Elastica\SearchableInterface;
use Knp\Component\Pager\Event\ItemsEvent;
use StingerSoft\ElasticEntitySearchBundle\Model\Document;

class ElasticaQuerySubscriber extends \Knp\Component\Pager\Event\Subscriber\Paginate\ElasticaQuerySubscriber {

	/**
	 * @param ItemsEvent $event
	 */
	public function items(ItemsEvent $event):void {
		if(\is_array($event->target) && 2 === count($event->target) && reset($event->target) instanceof SearchableInterface && end($event->target) instanceof Query) {
			parent::items($event);

			$params = $event->getCustomPaginationParameters();

			/**
			 * @var $results ResultSet
			 */
			$results = $params['resultSet'];

			$event->items = array();
			foreach($results->getResults() as $result) {
				$event->items[] = Document::createFromElasticResult($result);
			}
		}
	}

}