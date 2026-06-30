<?php

namespace Limas\Controller\Actions;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use Limas\Entity\PartCategory;
use Limas\Entity\PartCategoryDefaultParameter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;


/**
 * Two related subresources on PartCategory:
 *
 *   GET /api/part_categories/{id}/resolved_defaults
 *     Inheritance-resolved template list. Walks the category tree from leaf to
 *     root, merging each ancestor's defaults; child wins on same name. The
 *     Part editor calls this when a new Part is created in a category to
 *     pre-populate the parameters grid.
 *
 *   GET /api/part_categories/{id}/inherited_defaults
 *     Only the *ancestor-derived* templates (excludes the category's own).
 *     Each entry carries `origin` (the ancestor category's name) so the
 *     PartCategoryEditorWindow can show admins what they would override or
 *     duplicate by adding a same-named template here.
 */
#[AsController]
class PartCategoryActions
	extends AbstractController
{
	use ActionUtilTrait;


	public function __construct(
		private readonly ItemProvider $dataProvider
	)
	{
	}

	public function resolvedDefaults(Request $request, int $id): JsonResponse
	{
		$category = $this->getItem($this->dataProvider, PartCategory::class, $id);

		// Walk root → leaf so children overwrite ancestors on same name
		$chain = [];
		for ($node = $category; $node !== null; $node = $node->getParent()) {
			array_unshift($chain, $node);
		}

		$resolved = [];
		foreach ($chain as $node) {
			foreach ($node->getDefaultParameters() as $tpl) {
				$resolved[$tpl->getName()] = $this->serializeTemplate($tpl);
			}
		}

		return new JsonResponse(array_values($resolved));
	}

	public function inheritedDefaults(Request $request, int $id): JsonResponse
	{
		$category = $this->getItem($this->dataProvider, PartCategory::class, $id);

		// Same walk as resolved_defaults but stop one level *before* the leaf —
		// we want only what the leaf inherits, not what it already owns
		$ancestors = [];
		for ($node = $category->getParent(); $node !== null; $node = $node->getParent()) {
			array_unshift($ancestors, $node);
		}

		$inherited = [];
		foreach ($ancestors as $node) {
			foreach ($node->getDefaultParameters() as $tpl) {
				$entry = $this->serializeTemplate($tpl);
				$entry['origin'] = $node->getName();
				// Child ancestor still wins over earlier ancestors on same name
				$inherited[$tpl->getName()] = $entry;
			}
		}

		return new JsonResponse(array_values($inherited));
	}

	private function serializeTemplate(PartCategoryDefaultParameter $tpl): array
	{
		return [
			'name' => $tpl->getName(),
			'description' => $tpl->getDescription(),
			'valueType' => $tpl->getValueType(),
			'unit' => $tpl->getUnit() !== null
				? ['@id' => '/api/units/' . $tpl->getUnit()->getId(), 'name' => $tpl->getUnit()->getName()]
				: null
		];
	}
}
