<?php

namespace Limas\Tests;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Limas\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Attribute\Groups;


/**
 * Default-deny guard. Every write operation of the API must carry an explicit
 * authorization decision: either a `security`/`securityPostDenormalize`
 * expression (or `#[IsGranted]` on a custom route), or an entry in the lists
 * below that says "open to every logged-in user on purpose" — shared
 * inventory content. A new entity or endpoint therefore cannot ship without
 * someone deciding who may write it. Relations to User must be
 * reference-only.
 */
class ApiAuthorizationGuardTest
	extends KernelTestCase
{
	/**
	 * Write operations intentionally open to any authenticated user:
	 * "ShortName METHOD uriTemplate" (uriTemplate only for custom ones).
	 */
	private const array INTENTIONALLY_OPEN_OPERATIONS = [
		// Shared inventory content (parts, categories, stock movements via the
		// part actions, projects, reports, aliases, presets, batch jobs —
		// whose execute validates its target itself), per-user actions scoped
		// server-side (markTipRead, own preferences) and temp uploads.
		// SiPrefix/Unit are deliberately still open; candidates for admin-only.
		'BatchJob DELETE',
		'BatchJob POST',
		'BatchJob PUT',
		'BatchJob PUT batch_jobs/{id}/execute',
		'BatchJobQueryField DELETE',
		'BatchJobQueryField POST',
		'BatchJobQueryField PUT',
		'BatchJobUpdateField DELETE',
		'BatchJobUpdateField POST',
		'BatchJobUpdateField PUT',
		'Distributor DELETE',
		'Distributor POST',
		'Distributor PUT',
		'Footprint DELETE',
		'Footprint POST',
		'Footprint PUT',
		'FootprintAlias DELETE',
		'FootprintAlias PATCH',
		'FootprintAlias PUT',
		'FootprintAttachment POST',
		'FootprintCategory DELETE',
		'FootprintCategory PATCH',
		'FootprintCategory POST',
		'FootprintCategory PUT footprint_categories/{id}/move',
		'FootprintImage POST',
		'GridPreset DELETE',
		'GridPreset POST',
		'GridPreset PUT',
		'GridPreset PUT /grid_presets/{id}/markAsDefault',
		'ImportPreset DELETE',
		'ImportPreset POST',
		'ImportPreset PUT',
		'Manufacturer DELETE',
		'Manufacturer POST',
		'Manufacturer POST /manufacturers/{id}/merge',
		'Manufacturer PUT',
		'ManufacturerAlias DELETE',
		'ManufacturerAlias PATCH',
		'ManufacturerAlias PUT',
		'ManufacturerICLogo POST',
		'ManufacturerICLogo PUT',
		'MetaPartParameterCriteria DELETE',
		'MetaPartParameterCriteria POST',
		'MetaPartParameterCriteria PUT',
		'ParameterAlias DELETE',
		'ParameterAlias PATCH',
		'Part DELETE',
		'Part POST /parts{._format}',
		'Part POST parts/bulkMove',
		'Part PUT /parts/{id}{._format}',
		'Part PUT parts/{id}/addStock',
		'Part PUT parts/{id}/removeStock',
		'Part PUT parts/{id}/setStock',
		'PartAttachment POST',
		'PartCategory DELETE',
		'PartCategory PATCH',
		'PartCategory POST',
		'PartCategory PUT part_categories/{id}/move',
		'PartCategoryDefaultParameter DELETE',
		'PartCategoryDefaultParameter PATCH',
		'PartCategoryDefaultParameter POST',
		'PartDistributor DELETE',
		'PartDistributor POST',
		'PartDistributor PUT',
		'PartManufacturer DELETE',
		'PartManufacturer POST',
		'PartManufacturer PUT',
		'PartMeasurementUnit POST',
		'PartMeasurementUnit PUT',
		'PartMeasurementUnit PUT part_measurement_units/{id}/setDefault',
		'PartParameter DELETE',
		'PartParameter POST',
		'PartParameter PUT',
		'Project DELETE',
		'Project POST',
		'Project PUT',
		'ProjectAttachment POST',
		'ProjectPart DELETE',
		'ProjectPart POST',
		'ProjectPart PUT',
		'ProjectRun DELETE',
		'ProjectRun POST',
		'ProjectRun PUT',
		'ProjectRunPart DELETE',
		'ProjectRunPart POST',
		'ProjectRunPart PUT',
		'Report DELETE',
		'Report POST /reports{._format}',
		'Report PUT',
		'ReportPart DELETE',
		'ReportPart POST',
		'ReportPart PUT',
		'ReportProject DELETE',
		'ReportProject POST',
		'ReportProject PUT',
		'SiPrefix DELETE',
		'SiPrefix POST',
		'SiPrefix PUT',
		'StorageLocation DELETE',
		'StorageLocation POST',
		'StorageLocation PUT',
		'StorageLocationCategory DELETE',
		'StorageLocationCategory PATCH',
		'StorageLocationCategory POST',
		'StorageLocationCategory PUT storage_location_categories/{id}/move',
		'StorageLocationImage POST',
		'TempImage DELETE /temp_images/{id}{._format}',
		'TempImage POST temp_images/upload',
		'TempImage POST temp_images/webcamUpload',
		'TempUploadedFile DELETE /temp_uploaded_files/{id}{._format}',
		'TempUploadedFile POST temp_uploaded_files/upload',
		'TempUploadedFile POST temp_uploaded_files/webcamUpload',
		'TipOfTheDay POST',
		'TipOfTheDay POST tip_of_the_days/markAllTipsAsUnread',
		'TipOfTheDay PUT tip_of_the_days/{id}/markTipRead',
		'Unit DELETE',
		'Unit POST',
		'Unit PUT',
		'UserPreference DELETE user_preferences'
	];

	/** Custom `/api` routes (non-API-Platform) intentionally open to any authenticated user */
	private const array INTENTIONALLY_OPEN_ROUTES = [
		// Read endpoints without an explicit `methods:` restriction, label
		// rendering, statistics, imports/aggregator into the shared inventory,
		// and the caller's own login/logout/preferences.
		'UserPreferenceSet',
		'api_export',
		'getcurrencies',
		'getcurrentstatistic',
		'getdiskfreespace',
		'getpreview',
		'getsampledstatistic',
		'getstatisticrange',
		'getsysteminformation',
		'getsystemstatus',
		'import',
		'labels_part',
		'labels_preview',
		'labels_sheet',
		'labels_storage_location',
		'limas_actions_bulkimportactions_createjob',
		'limas_actions_infoprovideraggregatoractions_deepen',
		'limas_actions_infoprovideraggregatoractions_import',
		'limas_actions_partactions_checkduplicate',
		'limas_actions_partactions_massremovestock',
		'limas_actions_useractions_login',
		'limas_actions_useractions_logout'
	];


	private function operationKey(string $shortName, HttpOperation $op, bool $custom): string
	{
		return trim(sprintf('%s %s %s', $shortName, $op->getMethod(), $custom ? (string)$op->getUriTemplate() : ''));
	}

	public function testEveryWriteOperationHasAnAuthorizationDecision(): void
	{
		$names = self::getContainer()->get(ResourceNameCollectionFactoryInterface::class)->create();
		$metadataFactory = self::getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);

		$undecided = [];
		foreach ($names as $class) {
			foreach ($metadataFactory->create($class) as $resource) {
				foreach ($resource->getOperations() ?? [] as $op) {
					if (in_array($op->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
						continue;
					}
					if ($op->getSecurity() !== null || $op->getSecurityPostDenormalize() !== null) {
						continue;
					}
					$custom = $op->getController() !== null || !preg_match('#^/[a-z_]+(/\{id\})?\{\._format\}$#', (string)$op->getUriTemplate());
					$key = $this->operationKey((string)$resource->getShortName(), $op, $custom);
					if (!in_array($key, self::INTENTIONALLY_OPEN_OPERATIONS, true)) {
						$undecided[] = $key;
					}
				}
			}
		}
		sort($undecided);
		self::assertSame([], array_values(array_unique($undecided)), "Write operations without an authorization decision — add `security:` or list them as intentionally open:\n" . var_export(array_values(array_unique($undecided)), true));
	}

	public function testEveryCustomApiRouteHasAnAuthorizationDecision(): void
	{
		$routes = self::getContainer()->get(RouterInterface::class)->getRouteCollection();
		$undecided = [];
		foreach ($routes as $name => $route) {
			if (!str_starts_with($route->getPath(), '/api') || $route->getDefault('_api_resource_class') !== null) {
				continue;
			}
			$controller = $route->getDefault('_controller');
			if (!is_string($controller) || !str_starts_with($controller, 'Limas\\')) {
				continue;
			}
			$methods = $route->getMethods();
			if ($methods !== [] && array_diff($methods, ['GET', 'HEAD']) === []) {
				continue; // read-only
			}
			[$class, $method] = array_pad(explode('::', $controller, 2), 2, '__invoke');
			$refMethod = new \ReflectionMethod($class, $method);
			if ($refMethod->getAttributes(IsGranted::class) !== [] || (new \ReflectionClass($class))->getAttributes(IsGranted::class) !== []) {
				continue;
			}
			if (!in_array($name, self::INTENTIONALLY_OPEN_ROUTES, true)) {
				$undecided[] = $name;
			}
		}
		sort($undecided);
		self::assertSame([], $undecided, "Custom /api routes that can write without an authorization decision — add #[IsGranted] or list them as intentionally open:\n" . var_export($undecided, true));
	}

	/**
	 * A relation that points at User and is writable in its resource's
	 * denormalization groups must be reference-only (IRI).
	 */
	public function testNoResourceCanWriteThroughToUser(): void
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);
		$offenders = [];
		foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
			$class = $metadata->getReflectionClass();
			$resource = $class->getAttributes(ApiResource::class);
			if ($resource === [] || $metadata->getName() === User::class) {
				continue;
			}
			$writeGroups = $resource[0]->newInstance()->getDenormalizationContext()['groups'] ?? [];
			foreach ($metadata->getAssociationNames() as $association) {
				if ($metadata->getAssociationTargetClass($association) !== User::class || !$class->hasProperty($association)) {
					continue;
				}
				$property = $class->getProperty($association);
				$groups = [];
				foreach ($property->getAttributes(Groups::class) as $attribute) {
					$groups = [...$groups, ...$attribute->newInstance()->groups];
				}
				if (array_intersect($groups, $writeGroups) === []) {
					continue; // not writable through this resource at all
				}
				$apiProperty = $property->getAttributes(ApiProperty::class);
				if ($apiProperty === [] || $apiProperty[0]->newInstance()->isWritableLink() !== false) {
					$offenders[] = $metadata->getName() . '::$' . $association;
				}
			}
		}
		self::assertSame([], $offenders, 'Relations to User must be #[ApiProperty(writableLink: false)]');
	}
}
