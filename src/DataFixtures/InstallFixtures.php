<?php

namespace Limas\DataFixtures;

use Limas\Entity\FootprintCategory;
use Limas\Entity\PartCategory;
use Limas\Entity\PartMeasurementUnit;
use Limas\Entity\SiPrefix;
use Limas\Entity\StorageLocationCategory;
use Limas\Entity\Unit;
use Limas\Entity\UserProvider;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Limas\Service\UserService;


class InstallFixtures
	extends Fixture
	implements FixtureGroupInterface
{
	public static function getGroups(): array
	{
		return [
			'install'
		];
	}

	public function load(ObjectManager $manager): void
	{
		$manager->persist((new FootprintCategory)
			->setName('Root Category')
			->setRoot(1)
			->setCategoryPath('Root Category'));
		$manager->persist((new PartCategory)
			->setName('Root Category')
			->setRoot(1)
			->setCategoryPath('Root Category'));
		$manager->persist((new StorageLocationCategory)
			->setName('Root Category')
			->setRoot(1)
			->setCategoryPath('Root Category'));

		$manager->persist((new PartMeasurementUnit)
			->setName('Pieces')
			->setShortName('pcs')
			->setDefault(true));

		$manager->persist(new SiPrefix('yotta', 'Y', 24, 10));
		$manager->persist(new SiPrefix('zetta', 'Z', 21, 10));
		$manager->persist(new SiPrefix('exa', 'E', 18, 10));
		$manager->persist(new SiPrefix('peta', 'P', 15, 10));
		$manager->persist($tera = new SiPrefix('tera', 'T', 12, 10));
		$manager->persist($giga = new SiPrefix('giga', 'G', 9, 10));
		$manager->persist($mega = new SiPrefix('mega', 'M', 6, 10));
		$manager->persist($kilo = new SiPrefix('kilo', 'k', 3, 10));
		$manager->persist(new SiPrefix('hecto', 'h', 2, 10));
		$manager->persist(new SiPrefix('deca', 'da', 1, 10));
		$manager->persist($no = new SiPrefix('-', '', 0, 10));
		$manager->persist($deci = new SiPrefix('deci', 'd', -1, 10));
		$manager->persist($centi = new SiPrefix('centi', 'c', -2, 10));
		$manager->persist($milli = new SiPrefix('milli', 'm', -3, 10));
		$manager->persist($micro = new SiPrefix('micro', 'μ', -6, 10));
		$manager->persist($nano = new SiPrefix('nano', 'n', -9, 10));
		$manager->persist($pico = new SiPrefix('pico', 'p', -12, 10));
		$manager->persist(new SiPrefix('femto', 'f', -15, 10));
		$manager->persist(new SiPrefix('atto', 'a', -18, 10));
		$manager->persist(new SiPrefix('zepto', 'z', -21, 10));
		$manager->persist(new SiPrefix('yocto', 'y', -24, 10));
		$manager->persist(new SiPrefix('kibi', 'Ki', 1, 1024));
		$manager->persist(new SiPrefix('mebi', 'Mi', 2, 1024));
		$manager->persist(new SiPrefix('gibi', 'Gi', 3, 1024));
		$manager->persist(new SiPrefix('tebi', 'Ti', 4, 1024));
		$manager->persist(new SiPrefix('pebi', 'Pi', 5, 1024));
		$manager->persist(new SiPrefix('exbi', 'Ei', 6, 1024));
		$manager->persist(new SiPrefix('zebi', 'Zi', 7, 1024));
		$manager->persist(new SiPrefix('yobi', 'Yi', 8, 1024));

		$manager->persist((new Unit('Meter', 'm'))
			->addPrefix($kilo)
			->addPrefix($no)
			->addPrefix($deci)
			->addPrefix($centi)
			->addPrefix($milli)
			->addPrefix($micro)
			->addPrefix($nano)
		);
		$manager->persist((new Unit('Gram', 'g'))
			->addPrefix($kilo)
			->addPrefix($no)
			->addPrefix($milli)
		);
		$manager->persist((new Unit('Second', 's'))
			->addPrefix($no)
			->addPrefix($milli)
		);
		$manager->persist((new Unit('Kelvin', 'K'))
			->addPrefix($milli)
		);
		$manager->persist((new Unit('Mol', 'mol'))
			->addPrefix($milli)
		);
		$manager->persist((new Unit('Candela', 'cd'))
			->addPrefix($milli)
		);
		$manager->persist((new Unit('Ampere', 'A'))
			->addPrefix($kilo)
			->addPrefix($no)
			->addPrefix($milli)
			->addPrefix($micro)
			->addPrefix($nano)
			->addPrefix($pico)
		);
		$manager->persist((new Unit('Ohm', 'Ω'))
			->addPrefix($tera)
			->addPrefix($giga)
			->addPrefix($mega)
			->addPrefix($kilo)
			->addPrefix($no)
			->addPrefix($milli)
			->addPrefix($micro)
		);
		$manager->persist((new Unit('Volt', 'V'))
			->addPrefix($kilo)
			->addPrefix($no)
			->addPrefix($milli)
		);
		$manager->persist((new Unit('Hertz', 'Hz'))
			->addPrefix($tera)
			->addPrefix($giga)
			->addPrefix($kilo)
			->addPrefix($no)
			->addPrefix($milli)
		);
		$manager->persist((new Unit('Newton', 'N'))
			->addPrefix($kilo)
			->addPrefix($no)
		);
		$manager->persist((new Unit('Pascal', 'Pa'))
			->addPrefix($kilo)
			->addPrefix($no)
			->addPrefix($milli)
		);
		$manager->persist((new Unit('Joule', 'J'))
			->addPrefix($kilo)
			->addPrefix($no)
			->addPrefix($milli)
			->addPrefix($micro)
		);
		$manager->persist((new Unit('Watt', 'W'))
			->addPrefix($giga)
			->addPrefix($mega)
			->addPrefix($kilo)
			->addPrefix($no)
			->addPrefix($milli)
			->addPrefix($micro)
		);
		$manager->persist((new Unit('Coulomb', 'C'))
			->addPrefix($kilo)
			->addPrefix($no)
		);
		$manager->persist((new Unit('Farad', 'F'))
			->addPrefix($no)
			->addPrefix($milli)
			->addPrefix($micro)
			->addPrefix($nano)
			->addPrefix($pico)
		);
		$manager->persist((new Unit('Siemens', 'S'))
			->addPrefix($no)
			->addPrefix($milli)
		);
		$manager->persist((new Unit('Weber', 'Wb'))
			->addPrefix($no)
		);
		$manager->persist((new Unit('Tesla', 'T'))
			->addPrefix($no)
		);
		$manager->persist((new Unit('Henry', 'H'))
			->addPrefix($no)
			->addPrefix($milli)
			->addPrefix($micro)
		);
		$manager->persist((new Unit('Celsius', '°C'))
			->addPrefix($no)
		);
		$manager->persist((new Unit('Lumen', 'lm'))
			->addPrefix($no)
		);
		$manager->persist((new Unit('Lux', 'lx'))
			->addPrefix($no)
		);
		$manager->persist((new Unit('Becquerel', 'Bq'))
			->addPrefix($no)
		);
		$manager->persist((new Unit('Gray', 'Gy'))
			->addPrefix($no)
		);
		$manager->persist((new Unit('Sievert', 'Sv'))
			->addPrefix($no)
			->addPrefix($milli)
			->addPrefix($micro)
		);
		$manager->persist((new Unit('Katal', 'kat'))
			->addPrefix($no)
		);
		$manager->persist((new Unit('Ampere Hour', 'Ah'))
			->addPrefix($kilo)
			->addPrefix($no)
			->addPrefix($milli)
		);

		$manager->persist(new UserProvider(UserService::BUILTIN_PROVIDER, true));
		$manager->persist(new UserProvider(UserService::LDAP_PROVIDER, false));

		$manager->flush();
	}
}
