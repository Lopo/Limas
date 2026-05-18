<?php

namespace Limas\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\Part;
use Limas\Entity\Project;
use Limas\Entity\ProjectAttachment;
use Limas\Entity\ProjectPart;
use Limas\Service\CollectionSynchronizer;


/**
 * Custom processor for Project entity to handle nested collections on PUT
 * Workaround for API Platform 4.x bug with nested collections
 * @see https://github.com/api-platform/api-platform/issues/2855
 */
final readonly class ProjectProcessor
	implements ProcessorInterface
{
	public function __construct(
		private PersistProcessor        $persistProcessor,
		private EntityManagerInterface  $em,
		private CollectionSynchronizer  $collectionSync
	)
	{
	}

	public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
	{
		if (!$data instanceof Project) {
			return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
		}

		if ($operation instanceof Put && isset($uriVariables['id'])) {
			$existingProject = $this->em->find(Project::class, $uriVariables['id']);
			if ($existingProject === null) {
				return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
			}

			// Sync nested collections
			$this->syncParts($existingProject, $data->getParts());
			$this->syncAttachments($existingProject, $data->getAttachments());

			// Update scalar fields
			if ($data->getName() !== null) {
				$existingProject->setName($data->getName());
			}
			if ($data->getDescription() !== null) {
				$existingProject->setDescription($data->getDescription());
			}

			$this->em->flush();

			return $existingProject;
		}

		return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
	}

	/**
	 * @param array<ProjectPart> $newParts
	 */
	private function syncParts(Project $project, array $newParts): void
	{
		$this->collectionSync->syncOneToMany(
			existingItems: $project->getParts(),
			newItems: $newParts,
			getIdCallback: static fn(ProjectPart $pp) => $pp->getId(),
			getRelationIdCallback: static fn(ProjectPart $pp) => $pp->getPart()?->getId(),
			updateCallback: static function(ProjectPart $existing, ProjectPart $new): void {
				$existing->setQuantity($new->getQuantity());
				$existing->setRemarks($new->getRemarks());
				$existing->setOverageType($new->getOverageType());
				$existing->setOverage($new->getOverage());
				$existing->setLotNumber($new->getLotNumber());
			},
			findRelatedCallback: fn(int $partId) => $this->em->find(Part::class, $partId),
			createCallback: static function(Part $part) use ($project): ProjectPart {
				$pp = new ProjectPart();
				$pp->setPart($part);
				$pp->setProject($project);
				return $pp;
			},
			removeCallback: static fn(ProjectPart $pp) => $project->removePart($pp)
		);
	}

	/**
	 * @param iterable<ProjectAttachment> $newAttachments
	 */
	private function syncAttachments(Project $project, iterable $newAttachments): void
	{
		$this->collectionSync->syncAttachments(
			existingItems: $project->getAttachments(),
			newItems: $newAttachments,
			getIdCallback: static fn(ProjectAttachment $a) => $a->getId(),
			setupNewCallback: static fn(ProjectAttachment $a) => $a->setProject($project),
			removeCallback: static fn(ProjectAttachment $a) => $project->removeAttachment($a)
		);
	}
}