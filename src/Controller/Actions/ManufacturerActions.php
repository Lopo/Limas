<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use Limas\Entity\Manufacturer;
use Limas\Service\ManufacturerCanonicalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


#[AsController]
class ManufacturerActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly ManufacturerCanonicalizer $canonicalizer,
		private readonly ItemProvider              $dataProvider
	)
	{
	}

	/**
	 * Merge `$source` (path id) into `$target` (body `target` IRI or id).
	 * Reassigns every PartManufacturer FK, migrates source's existing aliases,
	 * caches source's name as a verified alias of target, then deletes source.
	 *
	 * Wired in src/Entity/Manufacturer.php as a Post operation with
	 * name='ManufacturerMerge'.
	 */
	public function __invoke(Request $request, int $id): Manufacturer
	{
		$source = $this->getItem($this->dataProvider, Manufacturer::class, $id);

		$body = json_decode($request->getContent(), true);
		$targetRaw = $body['target'] ?? null;
		if ($targetRaw === null) {
			throw new \InvalidArgumentException('Missing "target" in request body.');
		}

		// Accept either a numeric id or an API Platform IRI like "/api/manufacturers/42"
		if (is_string($targetRaw) && preg_match('#/(\d+)$#', $targetRaw, $m)) {
			$targetId = (int)$m[1];
		} else {
			$targetId = (int)$targetRaw;
		}
		$target = $this->getItem($this->dataProvider, Manufacturer::class, $targetId);

		$this->canonicalizer->mergeInto($source, $target);
		return $target;
	}
}
