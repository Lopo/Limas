<?php

namespace Limas\Tests\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Limas\Entity\PartCategory;
use Limas\Entity\StorageLocation;
use Limas\Filter\Filter;
use Limas\Entity\MetaPartParameterCriteria;
use Limas\Entity\Part;
use Limas\Entity\PartMeasurementUnit;
use Limas\Entity\PartParameter;
use Limas\Entity\Unit;


class PartDataLoader
	extends AbstractFixture
{
	public function load(ObjectManager $manager)
	{
		$partUnit = (new PartMeasurementUnit)
			->setName('pieces')
			->setShortName('pcs')
			->setDefault(true);

		$this->addReference('partunit.default', $partUnit);

		$part = (new Part)
			->setName('FOOBAR')
			->setPartUnit($partUnit)
			->setCategory($this->getReference('partcategory.first', PartCategory::class))
			->setStorageLocation($this->getReference('storagelocation.first', StorageLocation::class));

		$category = $this->getReference('partcategory.first', PartCategory::class);
		$storageLocation = $this->getReference('storagelocation.second', StorageLocation::class);

		$part2 = (new Part)
			->setName('FOOBAR2')
			->setCategory($category)
			->setStorageLocation($storageLocation)
			->setPartUnit($partUnit);

		$manager->persist($partUnit);
		$manager->persist($part);
		$manager->persist($part2);

		$this->addReference('part.1', $part);
		$this->addReference('part.2', $part2);

		$ohms = (new Unit('Ohm', 'O'));
		$manager->persist($ohms);

		$partParameterR1 = (new PartParameter)
			->setName('Resistance')
			->setValueType(PartParameter::VALUE_TYPE_NUMERIC)
			->setUnit($ohms)
			->setValue(100);

		$partParameterR2 = (new PartParameter)
			->setName('Resistance')
			->setValueType(PartParameter::VALUE_TYPE_NUMERIC)
			->setUnit($ohms)
			->setValue(100);

		$partParameterR3 = (new PartParameter)
			->setName('Resistance')
			->setValueType(PartParameter::VALUE_TYPE_NUMERIC)
			->setUnit($ohms)
			->setValue(101);

		$partParameterP1 = (new PartParameter)
			->setName('Case')
			->setValueType(PartParameter::VALUE_TYPE_STRING)
			->setStringValue('1206');

		$partParameterP2 = (new PartParameter)
			->setName('Case')
			->setValueType(PartParameter::VALUE_TYPE_STRING)
			->setStringValue('0805');

		$partParameterP3 = (new PartParameter)
			->setName('Case')
			->setValueType(PartParameter::VALUE_TYPE_STRING)
			->setStringValue('0805');

		$metaSourcePart1 = (new Part)
			->setPartUnit($partUnit)
			->setName('100 Ohms 1206 FIRST')
			->setPartUnit($partUnit)
			->setCategory($category)
			->setStorageLocation($storageLocation)
			->addParameter($partParameterR1)
			->addParameter($partParameterP1);

		$metaSourcePart2 = (new Part)
			->setPartUnit($partUnit)
			->setName('100 Ohms 0805 SECOND')
			->setPartUnit($partUnit)
			->setCategory($category)
			->setStorageLocation($storageLocation)
			->addParameter($partParameterR2)
			->addParameter($partParameterP2);

		$metaSourcePart3 = (new Part)
			->setPartUnit($partUnit)
			->setName('100 Ohms 0805 THIRD')
			->setPartUnit($partUnit)
			->setCategory($category)
			->setStorageLocation($storageLocation)
			->addParameter($partParameterP3)
			->addParameter($partParameterR3);

		$manager->persist($metaSourcePart1);
		$manager->persist($metaSourcePart2);
		$manager->persist($metaSourcePart3);

		$this->addReference('metapart.source.1', $metaSourcePart1);
		$this->addReference('metapart.source.2', $metaSourcePart2);
		$this->addReference('metapart.source.3', $metaSourcePart3);

		$metaPartParameterCriteria1 = (new MetaPartParameterCriteria)
			->setPartParameterName('Resistance')
			->setValueType(PartParameter::VALUE_TYPE_NUMERIC)
			->setOperator(Filter::OPERATOR_EQUALS)
			->setValue(100);

		$metaPartParameterCriteria2 = (new MetaPartParameterCriteria)
			->setPartParameterName('Resistance')
			->setValueType(PartParameter::VALUE_TYPE_NUMERIC)
			->setOperator(Filter::OPERATOR_EQUALS)
			->setValue(100);

		$metaPartParameterCriteria3 = (new MetaPartParameterCriteria)
			->setValueType(PartParameter::VALUE_TYPE_STRING)
			->setPartParameterName('Case')
			->setOperator(Filter::OPERATOR_EQUALS)
			->setStringValue('0805');

		$metaPart1 = (new Part)
			->setMetaPart(true)
			->setName('all 100 ohms resistors')
			->setCategory($category)
			->setPartUnit($partUnit)
			->addMetaPartParameterCriteria($metaPartParameterCriteria1);

		$metaPart2 = (new Part)
			->setMetaPart(true)
			->setName('all 100 ohms 0805 resistors')
			->setCategory($category)
			->setPartUnit($partUnit)
			->addMetaPartParameterCriteria($metaPartParameterCriteria2)
			->addMetaPartParameterCriteria($metaPartParameterCriteria3);

		$manager->persist($metaPart2);
		$manager->persist($metaPart1);

		$this->addReference('metapart.1', $metaPart1);
		$this->addReference('metapart.2', $metaPart2);

		$manager->flush();
	}
}
