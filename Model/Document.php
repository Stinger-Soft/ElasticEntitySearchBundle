<?php
declare(strict_types=1);

/*
 * This file is part of the Stinger Entity Search package.
 *
 * (c) Oliver Kotte <oliver.kotte@stinger-soft.net>
 * (c) Florian Meyer <florian.meyer@stinger-soft.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace StingerSoft\ElasticEntitySearchBundle\Model;

use Elastica\Result;
use StingerSoft\EntitySearchBundle\Model\DocumentAdapter;

class Document extends DocumentAdapter {

	/**
	 * @var Result
	 */
	protected $internalResult;

	public static function createFromElasticResult(Result $result): self {
		$document = new self();
		$document->internalResult = $result;
		foreach($result->getSource() as $key => $value) {
			$document->addField($key, $value);
		}
		$document->setEntityType($document->getFieldValue('entityType'));
		$document->setEntityClass($document->getFieldValue('clazz'));
		$document->setEntityId($document->getFieldValue('internalId'));

		// Map solr extractor properties to the document
		$contentType = $document->getFieldValue('attachment');
		if($contentType !== null && $document->getFieldValue(\StingerSoft\EntitySearchBundle\Model\Document::FIELD_CONTENT_TYPE) === null) {
			$document->addField(\StingerSoft\EntitySearchBundle\Model\Document::FIELD_CONTENT_TYPE, $contentType['content_type']);
		}

		return $document;
	}

	/**
	 * @return Result
	 */
	public function getInternalResult(): Result {
		return $this->internalResult;
	}

}

