<?php
/**
 * Created by PhpStorm.
 * User: FlorianMeyer
 * Date: 16.10.2018
 * Time: 09:46
 */

namespace StingerSoft\ElasticEntitySearchBundle\Services;

use Elastica\Query;
use Elastica\ResultSet;
use Elastica\SearchableInterface;
use Knp\Component\Pager\Event\ItemsEvent;
use StingerSoft\ElasticEntitySearchBundle\Model\Document;

class ElasticaQuerySubscriber extends \Knp\Component\Pager\Event\Subscriber\Paginate\ElasticaQuerySubscriber {

	public function items(ItemsEvent $event) {
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