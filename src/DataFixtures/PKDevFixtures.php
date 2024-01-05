<?php

namespace Limas\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Limas\Entity\Footprint;
use Limas\Entity\FootprintCategory;
use Limas\Entity\FootprintImage;
use Limas\Entity\Manufacturer;
use Limas\Entity\ManufacturerICLogo;
use Limas\Entity\User;
use Limas\Entity\UserProvider;
use Nette\Utils\DateTime;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class PKDevFixtures
	extends Fixture
	implements FixtureGroupInterface
{
	private string $pathSeparator;


	public function __construct(
		private readonly UserPasswordHasherInterface $hasher,
		array                                        $limas
	)
	{
		$this->pathSeparator = $limas['category']['path_separator'];
	}

	public static function getGroups(): array
	{
		return [
			'pkdev'
		];
	}

	public function load(ObjectManager $manager): void
	{
		$manager->persist($admin = (new User('admin', $manager->find(UserProvider::class, 1)))->setRoles(['ROLE_SUPER_ADMIN'])->setEmail('foo@bar.com'));
		$admin->setPassword($this->hasher->hashPassword($admin, 'admin'));

		$root = $manager->find(FootprintCategory::class, 1);
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('CBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('CBGA-32')->setCategory($fpcs)->setDescription('32-Lead Ceramic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b614228-517c-11ea-abf0-df07e0119f97')->setOriginalFilename('CBGA-32.png')->setMimetype('image/png')->setSize(23365)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('FCBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('FCBGA-576')->setCategory($fpcs)->setDescription('576-Ball Ball Grid Array, Thermally Enhanced'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b63770a-517c-11ea-8454-92064e7639f6')->setOriginalFilename('FCBGA-576.png')->setMimetype('image/png')->setSize(47861)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-119')->setCategory($fpcs)->setDescription('119-Ball Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b63b0e4-517c-11ea-ba68-6d0f91f0c135')->setOriginalFilename('PBGA-119.png')->setMimetype('image/png')->setSize(32537)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-169')->setCategory($fpcs)->setDescription('169-Ball Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b63f96e-517c-11ea-a0fc-b0836905e28f')->setOriginalFilename('PBGA-169.png')->setMimetype('image/png')->setSize(36699)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-225')->setCategory($fpcs)->setDescription('225-Ball Plastic a Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b643582-517c-11ea-8288-44c7cc4082be')->setOriginalFilename('PBGA-225.png')->setMimetype('image/png')->setSize(39366)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-260')->setCategory($fpcs)->setDescription('260-Ball Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b64666a-517c-11ea-8276-fcf401bad075')->setOriginalFilename('PBGA-260.png')->setMimetype('image/png')->setSize(61202)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-297')->setCategory($fpcs)->setDescription('297-Ball Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b64a288-517c-11ea-a103-2bac72df83f2')->setOriginalFilename('PBGA-297.png')->setMimetype('image/png')->setSize(68013)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-304')->setCategory($fpcs)->setDescription('304-Lead Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b64dc76-517c-11ea-b2f0-af292bdaf294')->setOriginalFilename('PBGA-304.png')->setMimetype('image/png')->setSize(55833)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-316')->setCategory($fpcs)->setDescription('316-Lead Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b6511be-517c-11ea-aeab-598288f4b9f9')->setOriginalFilename('PBGA-316.png')->setMimetype('image/png')->setSize(55996)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-324')->setCategory($fpcs)->setDescription('324-Ball Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b654a30-517c-11ea-b389-0f42afcb7306')->setOriginalFilename('PBGA-324.png')->setMimetype('image/png')->setSize(44882)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-385')->setCategory($fpcs)->setDescription('385-Lead Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b657c1c-517c-11ea-a9a8-e6c551e2818e')->setOriginalFilename('PBGA-385.png')->setMimetype('image/png')->setSize(35146)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-400')->setCategory($fpcs)->setDescription('400-Ball Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b65aa84-517c-11ea-9953-bc48badc789c')->setOriginalFilename('PBGA-400.png')->setMimetype('image/png')->setSize(67933)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-484')->setCategory($fpcs)->setDescription('484-Ball Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b65e1ca-517c-11ea-8c40-59fab99a84a0')->setOriginalFilename('PBGA-484.png')->setMimetype('image/png')->setSize(49851)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-625')->setCategory($fpcs)->setDescription('625-Ball Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b6613c0-517c-11ea-972d-39c41375f445')->setOriginalFilename('PBGA-625.png')->setMimetype('image/png')->setSize(65307)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PBGA-676')->setCategory($fpcs)->setDescription('676-Ball Plastic Ball Grid Array'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b664250-517c-11ea-a3d9-b47a1a2242cb')->setOriginalFilename('PBGA-676.png')->setMimetype('image/png')->setSize(54708)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('SBGA-256')->setCategory($fpcs)->setDescription('256-Ball Ball Grid Array, Thermally Enhanced'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b667838-517c-11ea-8977-5b5865ff9ca0')->setOriginalFilename('SBGA-256.png')->setMimetype('image/png')->setSize(48636)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('SBGA-304')->setCategory($fpcs)->setDescription('304-Ball Ball Grid Array, Thermally Enhanced'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b66adb2-517c-11ea-b7b4-7f7c94b28d87')->setOriginalFilename('SBGA-304.png')->setMimetype('image/png')->setSize(51944)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('BGA')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PBGA')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('SBGA-432')->setCategory($fpcs)->setDescription('432-Ball Ball Grid Array, Thermally Enhanced'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b66e21e-517c-11ea-9c1a-577cc2ece8dc')->setOriginalFilename('SBGA-432.png')->setMimetype('image/png')->setSize(63247)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('CERDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('CerDIP-8')->setCategory($fpcs)->setDescription('8-Lead Ceramic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b6718ce-517c-11ea-921d-b45648294db9')->setOriginalFilename('CERDIP-8.png')->setMimetype('image/png')->setSize(13544)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('CERDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('CerDIP-14')->setCategory($fpcs)->setDescription('14-Lead Ceramic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b6747f4-517c-11ea-a1b9-49ea24d34e37')->setOriginalFilename('CERDIP-14.png')->setMimetype('image/png')->setSize(14226)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('CERDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('CerDIP-16')->setCategory($fpcs)->setDescription('16-Lead Ceramic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b677332-517c-11ea-b3a5-47f1c7fba789')->setOriginalFilename('CERDIP-16.png')->setMimetype('image/png')->setSize(14576)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('CERDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('CerDIP-18')->setCategory($fpcs)->setDescription('18-Lead Ceramic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b679a60-517c-11ea-897c-a85add34303b')->setOriginalFilename('CERDIP-18.png')->setMimetype('image/png')->setSize(9831)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('CERDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('CerDIP-20')->setCategory($fpcs)->setDescription('20-Lead Ceramic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b67c3e6-517c-11ea-9e32-235fc3df1155')->setOriginalFilename('CERDIP-20.png')->setMimetype('image/png')->setSize(10209)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('CERDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('CerDIP-24 Narrow')->setCategory($fpcs)->setDescription('24-Lead Ceramic Dual In-Line Package - Narrow Body'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b67ecae-517c-11ea-b082-45d96b9cd05a')->setOriginalFilename('CERDIP-24-N.png')->setMimetype('image/png')->setSize(11582)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('CERDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('CerDIP-24 Wide')->setCategory($fpcs)->setDescription('24-Lead Ceramic Dual In-Line Package - Wide Body'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b68118e-517c-11ea-bcfb-4eb5975ac9db')->setOriginalFilename('CERDIP-24-W.png')->setMimetype('image/png')->setSize(12407)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('CERDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('CerDIP-28')->setCategory($fpcs)->setDescription('28-Lead Ceramic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b683754-517c-11ea-a0c9-4f8614fa4e5a')->setOriginalFilename('CERDIP-28.png')->setMimetype('image/png')->setSize(12233)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('CERDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('CerDIP-40')->setCategory($fpcs)->setDescription('40-Lead Ceramic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b686152-517c-11ea-92dd-891364b6b3ac')->setOriginalFilename('CERDIP-40.png')->setMimetype('image/png')->setSize(12421)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PDIP-8')->setCategory($fpcs)->setDescription('8-Lead Plastic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b688eac-517c-11ea-b074-23df72f58f66')->setOriginalFilename('PDIP-8.png')->setMimetype('image/png')->setSize(13537)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PDIP-14')->setCategory($fpcs)->setDescription('14-Lead Plastic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b68b9fe-517c-11ea-a238-f95b4fe0544c')->setOriginalFilename('PDIP-14.png')->setMimetype('image/png')->setSize(13779)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PDIP-16')->setCategory($fpcs)->setDescription('16-Lead Plastic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b68e078-517c-11ea-8f25-38997cd97702')->setOriginalFilename('PDIP-16.png')->setMimetype('image/png')->setSize(18305)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PDIP-18')->setCategory($fpcs)->setDescription('18-Lead Plastic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b6908c8-517c-11ea-8831-71c365f054e0')->setOriginalFilename('PDIP-18.png')->setMimetype('image/png')->setSize(14893)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PDIP-20')->setCategory($fpcs)->setDescription('20-Lead Plastic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b692e98-517c-11ea-af51-9c818fee0248')->setOriginalFilename('PDIP-20.png')->setMimetype('image/png')->setSize(14429)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PDIP-24')->setCategory($fpcs)->setDescription('24-Lead Plastic Dual In-Line Package'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b6954f4-517c-11ea-9de4-ae9526322ddf')->setOriginalFilename('PDIP-24.png')->setMimetype('image/png')->setSize(14647)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PDIP-28 Narrow')->setCategory($fpcs)->setDescription('28-Lead Plastic Dual In-Line Package, Narrow Body'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b697b28-517c-11ea-a5f1-27bc161d3dd2')->setOriginalFilename('PDIP-28-N.png')->setMimetype('image/png')->setSize(18703)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist($fpc = (new FootprintCategory)->setName('DIP')->setParent($root));
		$manager->persist($fpcs = (new FootprintCategory)->setName('PDIP')->setParent($fpc));
		$manager->persist($fp = (new Footprint)->setName('PDIP-28 Wide')->setCategory($fpcs)->setDescription('28-Lead Plastic Dual In-Line Package, Wide Body'));
		$manager->persist((new FootprintImage)->setFootprint($fp)->setFilename('1b69a47c-517c-11ea-8c9d-2b8a8acd34e3')->setOriginalFilename('PDIP-28-W.png')->setMimetype('image/png')->setSize(15728)->setCreated(DateTime::from('2020-02-17 11:53:26')));
		$manager->persist((new Footprint)->setName('SOIC-N-EP-8')->setDescription('8-Lead Standard Small Outline Package, with Expose Pad'));

		$root->setCategoryPath($root->generateCategoryPath($this->pathSeparator));

		$manager->persist((new Manufacturer)->setName('Integrated Circuit Designs')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf234c2-517c-11ea-b35d-fce0933e9505')->setOriginalFilename('acer.png')->setMimetype('image/png')->setSize(2195)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ACTEL')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf2ae16-517c-11ea-97d9-6f9707d791fa')->setOriginalFilename('actel.png')->setMimetype('image/png')->setSize(5003)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ALTINC')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf2cacc-517c-11ea-9c25-20eb09bb4805')->setOriginalFilename('advldev.png')->setMimetype('image/png')->setSize(1835)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Aeroflex')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf2e55c-517c-11ea-8e4a-d5b5d8376133')->setOriginalFilename('aeroflex1.png')->setMimetype('image/png')->setSize(9649)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf2f3a8-517c-11ea-9fc3-e855a641c013')->setOriginalFilename('aeroflex2.png')->setMimetype('image/png')->setSize(4562)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Agilent Technologies')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf3125c-517c-11ea-8ad6-7584dd4872e3')->setOriginalFilename('agilent.png')->setMimetype('image/png')->setSize(5264)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('AKM Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf32e22-517c-11ea-ae4b-31942bf0a604')->setOriginalFilename('akm.png')->setMimetype('image/png')->setSize(2204)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Alesis Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf34a60-517c-11ea-a1a2-bf3e0c5eb129')->setOriginalFilename('alesis.png')->setMimetype('image/png')->setSize(1475)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ALi (Acer Laboratories Inc.)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf36504-517c-11ea-b942-de884c035401')->setOriginalFilename('ali1.png')->setMimetype('image/png')->setSize(2462)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf37062-517c-11ea-a769-94c5b8b16606')->setOriginalFilename('ali2.png')->setMimetype('image/png')->setSize(1784)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Allayer Communications')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf38a84-517c-11ea-b777-febc878c93b9')->setOriginalFilename('allayer.png')->setMimetype('image/png')->setSize(1869)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Allegro Microsystems')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf3a4f6-517c-11ea-9354-9dfe667f3ffb')->setOriginalFilename('allegro.png')->setMimetype('image/png')->setSize(1475)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Alliance Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf3bf68-517c-11ea-94ca-3bee1054a469')->setOriginalFilename('alliance.png')->setMimetype('image/png')->setSize(1949)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Alpha Industries')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf3d9d0-517c-11ea-96c3-2f286e2c43a0')->setOriginalFilename('alphaind.png')->setMimetype('image/png')->setSize(1403)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Alpha Microelectronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf3f334-517c-11ea-af41-fdd860f3be55')->setOriginalFilename('alphamic.png')->setMimetype('image/png')->setSize(2989)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf3fe4c-517c-11ea-a72b-0068adddb063')->setOriginalFilename('alpha.png')->setMimetype('image/png')->setSize(1534)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Altera')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf418f0-517c-11ea-ad16-02b4db63573c')->setOriginalFilename('altera.png')->setMimetype('image/png')->setSize(4064)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Advanced Micro Devices (AMD)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf4342a-517c-11ea-9979-9b3ed7ceef28')->setOriginalFilename('amd.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('American Microsystems, Inc. (AMI)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf44e10-517c-11ea-b3ce-a5475ba9f143')->setOriginalFilename('ami1.png')->setMimetype('image/png')->setSize(2399)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf458b0-517c-11ea-ac44-fcd1f1523dfe')->setOriginalFilename('ami2.png')->setMimetype('image/png')->setSize(1706)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Amic Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf46fda-517c-11ea-bdf4-c5c1170ce7c9')->setOriginalFilename('amic.png')->setMimetype('image/png')->setSize(2228)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Amphus')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf48808-517c-11ea-9b81-8d820fae5bbd')->setOriginalFilename('ampus.png')->setMimetype('image/png')->setSize(6150)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Anachip Corp.')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf4a270-517c-11ea-86c5-542d43a8d1ec')->setOriginalFilename('anachip.png')->setMimetype('image/png')->setSize(3549)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ANADIGICs')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf4bc60-517c-11ea-8c03-fab80f6570c4')->setOriginalFilename('anadigic.png')->setMimetype('image/png')->setSize(5147)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Analog Devices')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf4d650-517c-11ea-b630-b2bc58c88656')->setOriginalFilename('analog1.png')->setMimetype('image/png')->setSize(1262)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf4e19a-517c-11ea-842a-b91a0a77f6cd')->setOriginalFilename('analog.png')->setMimetype('image/png')->setSize(1403)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Analog Systems')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf4fa68-517c-11ea-a4a0-a9084394413c')->setOriginalFilename('anasys.png')->setMimetype('image/png')->setSize(3309)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Anchor Chips')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf512fa-517c-11ea-bffc-0c69b625faa2')->setOriginalFilename('anchorch.png')->setMimetype('image/png')->setSize(1475)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Apex Microtechnology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf52cc2-517c-11ea-b02c-919367aa0ba2')->setOriginalFilename('apex1.png')->setMimetype('image/png')->setSize(2627)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf53834-517c-11ea-8391-de106a74aba7')->setOriginalFilename('apex.png')->setMimetype('image/png')->setSize(3974)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ARK Logic')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf54f40-517c-11ea-8d3c-f7c4839de3bb')->setOriginalFilename('ark.png')->setMimetype('image/png')->setSize(2089)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ASD')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf5670a-517c-11ea-b2df-82cfd5ece312')->setOriginalFilename('asd.png')->setMimetype('image/png')->setSize(5024)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Astec Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf5856e-517c-11ea-a6ab-95387f5ec787')->setOriginalFilename('astec.png')->setMimetype('image/png')->setSize(3369)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ATC (Analog Technologie)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf5a198-517c-11ea-9a5b-421f9569f655')->setOriginalFilename('atc.png')->setMimetype('image/png')->setSize(8660)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ATecoM')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf5bd4a-517c-11ea-8fee-4e86d0972f53')->setOriginalFilename('atecom.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ATI Technologies')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf5d6a4-517c-11ea-a217-1842bb5bd149')->setOriginalFilename('ati.png')->setMimetype('image/png')->setSize(2630)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Atmel')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf5f116-517c-11ea-aad9-d71f69b54477')->setOriginalFilename('atmel.png')->setMimetype('image/png')->setSize(2843)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('AT&T')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf609a8-517c-11ea-85c8-138b50f5d7b5')->setOriginalFilename('att.png')->setMimetype('image/png')->setSize(2816)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('AudioCodes')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf621b8-517c-11ea-bfd4-f2c4d3a50749')->setOriginalFilename('audiocod.png')->setMimetype('image/png')->setSize(2429)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Aura Vision')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf63c0c-517c-11ea-8cf1-c65671cbf54d')->setOriginalFilename('auravis.png')->setMimetype('image/png')->setSize(2281)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Aureal')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf6576e-517c-11ea-bd4f-717aa432e8e7')->setOriginalFilename('aureal.png')->setMimetype('image/png')->setSize(2109)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Austin Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf670b4-517c-11ea-b4c9-1b53837dd170')->setOriginalFilename('austin.png')->setMimetype('image/png')->setSize(2464)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Avance Logic')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf688ec-517c-11ea-9657-24b12d0441e7')->setOriginalFilename('averlog.png')->setMimetype('image/png')->setSize(1552)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Bel Fuse')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf6a02a-517c-11ea-85e6-0377c723dd77')->setOriginalFilename('belfuse.png')->setMimetype('image/png')->setSize(2204)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Benchmarq Microelectronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf6b86c-517c-11ea-8f7e-019a2d142c32')->setOriginalFilename('benchmrq.png')->setMimetype('image/png')->setSize(1370)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('BI Technologies')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf6ceec-517c-11ea-ba79-6d72a2c4f46d')->setOriginalFilename('bi.png')->setMimetype('image/png')->setSize(2008)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Bowmar/White')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf6e512-517c-11ea-b0bc-6d136df08bae')->setOriginalFilename('bowmar_white.png')->setMimetype('image/png')->setSize(4652)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Brightflash')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf70092-517c-11ea-9e22-bbd284f7649d')->setOriginalFilename('bright.png')->setMimetype('image/png')->setSize(6839)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Broadcom')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf71cf8-517c-11ea-9d0a-3c376ae135a1')->setOriginalFilename('broadcom.png')->setMimetype('image/png')->setSize(6056)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Brooktree(now Rockwell)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf73832-517c-11ea-97cb-b942028e671a')->setOriginalFilename('brooktre.png')->setMimetype('image/png')->setSize(1364)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Burr Brown')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf75362-517c-11ea-bc6f-80f3acb4a325')->setOriginalFilename('burrbrwn.png')->setMimetype('image/png')->setSize(3563)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('California Micro Devices')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf76e24-517c-11ea-854f-87b093c998fa')->setOriginalFilename('calmicro.png')->setMimetype('image/png')->setSize(2109)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Calogic')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf78738-517c-11ea-81ff-41ad2951bde7')->setOriginalFilename('calogic.png')->setMimetype('image/png')->setSize(3367)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Catalyst Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf79e8a-517c-11ea-bffe-b984ab8fdb7f')->setOriginalFilename('catalys1.png')->setMimetype('image/png')->setSize(1922)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf7a7d6-517c-11ea-ab45-005d289b56bb')->setOriginalFilename('catalyst.png')->setMimetype('image/png')->setSize(2228)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Centon Electronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf7c1bc-517c-11ea-b821-59ea3b1ac07a')->setOriginalFilename('ccube.png')->setMimetype('image/png')->setSize(1309)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Ceramate Technical')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf7d9e0-517c-11ea-809d-d3f3ae05ccb5')->setOriginalFilename('ceramate1.png')->setMimetype('image/png')->setSize(2917)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf7e3e0-517c-11ea-8642-b964a9e4a334')->setOriginalFilename('ceramate2.png')->setMimetype('image/png')->setSize(2917)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Cherry Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf7f9d4-517c-11ea-ba22-63cef6e34ab7')->setOriginalFilename('cherry.png')->setMimetype('image/png')->setSize(2507)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Chipcon AS')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf80ed8-517c-11ea-b815-7ed2d2d5d78d')->setOriginalFilename('chipcon1.png')->setMimetype('image/png')->setSize(8655)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf818d8-517c-11ea-b5b9-d2874e1b0ff8')->setOriginalFilename('chipcon2.png')->setMimetype('image/png')->setSize(2923)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Chips')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf82ea4-517c-11ea-aa02-39e47783e38a')->setOriginalFilename('chips.png')->setMimetype('image/png')->setSize(2864)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Chrontel')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf84394-517c-11ea-b098-e8e85c3d4206')->setOriginalFilename('chrontel.png')->setMimetype('image/png')->setSize(1476)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Cirrus Logic')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf85b7c-517c-11ea-b616-47df838cf0dc')->setOriginalFilename('cirrus.png')->setMimetype('image/png')->setSize(3218)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ComCore Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf87472-517c-11ea-b4fb-6fe8a64387ec')->setOriginalFilename('comcore.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Conexant')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf88d18-517c-11ea-bcff-e2bf3e4e7649')->setOriginalFilename('conexant.png')->setMimetype('image/png')->setSize(2051)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Cosmo Electronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf8a44c-517c-11ea-977e-4d78192c3092')->setOriginalFilename('cosmo.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Chrystal')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf8bab8-517c-11ea-ab3e-4b8a8705546f')->setOriginalFilename('crystal.png')->setMimetype('image/png')->setSize(3605)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Cygnal')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf8d30e-517c-11ea-abba-3238b2c0ea75')->setOriginalFilename('cygnal.png')->setMimetype('image/png')->setSize(2135)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Cypress Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf8ea60-517c-11ea-8c30-e6807cbb2737')->setOriginalFilename('cypres1.png')->setMimetype('image/png')->setSize(2504)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf8f334-517c-11ea-a2e0-d749c5031ab2')->setOriginalFilename('cypress.png')->setMimetype('image/png')->setSize(4275)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Cyrix Corporation')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf90d9c-517c-11ea-a112-69c5a0aa197c')->setOriginalFilename('cyrix.png')->setMimetype('image/png')->setSize(2204)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Daewoo Electronics Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf92890-517c-11ea-97d1-5da2675827f1')->setOriginalFilename('daewoo.png')->setMimetype('image/png')->setSize(1907)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Dallas Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf94186-517c-11ea-86dc-26d3edd8f145')->setOriginalFilename('dallas1.png')->setMimetype('image/png')->setSize(1469)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf94ca8-517c-11ea-9806-96823e02d5ed')->setOriginalFilename('dallas2.png')->setMimetype('image/png')->setSize(1309)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf957c0-517c-11ea-b634-55cc3b2db562')->setOriginalFilename('dallas3.png')->setMimetype('image/png')->setSize(1869)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Davicom Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf97336-517c-11ea-9c7e-80afc2c87a7e')->setOriginalFilename('davicom.png')->setMimetype('image/png')->setSize(4589)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Data Delay Devices')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf98c5e-517c-11ea-abff-9a621015476e')->setOriginalFilename('ddd.png')->setMimetype('image/png')->setSize(3235)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Diamond Technologies')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf9a662-517c-11ea-acf5-db688dccdf07')->setOriginalFilename('diamond.png')->setMimetype('image/png')->setSize(2504)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('DIOTEC')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf9c14c-517c-11ea-808e-c282bbc876ad')->setOriginalFilename('diotec.png')->setMimetype('image/png')->setSize(1454)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('DTC Data Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf9dbaa-517c-11ea-b367-dd80173d488e')->setOriginalFilename('dtc1.png')->setMimetype('image/png')->setSize(2513)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bf9e712-517c-11ea-85ea-a6558bee54c0')->setOriginalFilename('dtc2.png')->setMimetype('image/png')->setSize(1670)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('DVDO')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfa01d4-517c-11ea-823b-03d239269963')->setOriginalFilename('dvdo.png')->setMimetype('image/png')->setSize(2357)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('EG&G')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfa1c14-517c-11ea-b4dc-9e4265231d64')->setOriginalFilename('egg.png')->setMimetype('image/png')->setSize(1628)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Elan Microelectronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfa330c-517c-11ea-8724-27b7465727ee')->setOriginalFilename('elan.png')->setMimetype('image/png')->setSize(13826)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ELANTEC')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfa50b2-517c-11ea-83be-8f0b837351cd')->setOriginalFilename('elantec1.png')->setMimetype('image/png')->setSize(1400)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfa5a26-517c-11ea-81a9-d5d926ac5e69')->setOriginalFilename('elantec.png')->setMimetype('image/png')->setSize(3274)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Electronic Arrays')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfa7524-517c-11ea-b662-f101388794c0')->setOriginalFilename('elec_arrays.png')->setMimetype('image/png')->setSize(5602)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Elite Flash Storage Technology Inc. (EFST)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfa902c-517c-11ea-b316-3cd0b135afd8')->setOriginalFilename('elite[1].png')->setMimetype('image/png')->setSize(8285)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('EM Microelectronik - Marin')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfaab8e-517c-11ea-b19b-57bae656542c')->setOriginalFilename('emmicro.png')->setMimetype('image/png')->setSize(3599)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Enhanced Memory Systems')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfac6a0-517c-11ea-a1c3-79047a53283d')->setOriginalFilename('enhmemsy.png')->setMimetype('image/png')->setSize(1403)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Ensoniq Corp')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfae0ea-517c-11ea-9632-584ec84a88e7')->setOriginalFilename('ensoniq.png')->setMimetype('image/png')->setSize(3557)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('EON Silicon Devices')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfafcec-517c-11ea-ac52-746bcccd59d4')->setOriginalFilename('eon.png')->setMimetype('image/png')->setSize(5393)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Epson')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfb15a6-517c-11ea-902d-29e6ae671f9a')->setOriginalFilename('epson1.png')->setMimetype('image/png')->setSize(2349)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfb1fc4-517c-11ea-8341-8ef9a22eefca')->setOriginalFilename('epson2.png')->setMimetype('image/png')->setSize(2405)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Ericsson')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfb345a-517c-11ea-ad1f-fed1829ccd5f')->setOriginalFilename('ericsson.png')->setMimetype('image/png')->setSize(4184)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ESS Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfb4a58-517c-11ea-ae94-4395a635356d')->setOriginalFilename('ess.png')->setMimetype('image/png')->setSize(3030)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Electronic Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfb63da-517c-11ea-8083-9c1de13bafdf')->setOriginalFilename('etc.png')->setMimetype('image/png')->setSize(2189)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('EXAR')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfb7c30-517c-11ea-bf37-f1c1e1ea7e18')->setOriginalFilename('exar.png')->setMimetype('image/png')->setSize(2771)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Excel Semiconductor Inc.')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfb968e-517c-11ea-86dd-eaf0052b7e7b')->setOriginalFilename('excelsemi1.png')->setMimetype('image/png')->setSize(7632)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfba174-517c-11ea-952a-b55ebce4fb74')->setOriginalFilename('excelsemi2.png')->setMimetype('image/png')->setSize(2339)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfbacaa-517c-11ea-be29-1685ece46cba')->setOriginalFilename('exel.png')->setMimetype('image/png')->setSize(2771)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Fairschild')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfbc5fa-517c-11ea-959d-5c319a41f521')->setOriginalFilename('fairchil.png')->setMimetype('image/png')->setSize(1552)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Freescale Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfbe0c6-517c-11ea-98db-05cfd383483e')->setOriginalFilename('freescale.png')->setMimetype('image/png')->setSize(3840)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Fujitsu')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfbfbce-517c-11ea-9060-c6c9b647d7b4')->setOriginalFilename('fujielec.png')->setMimetype('image/png')->setSize(5048)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfc0858-517c-11ea-95c2-055b6cb1116b')->setOriginalFilename('fujitsu2.png')->setMimetype('image/png')->setSize(1860)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Galileo Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfc2360-517c-11ea-9be3-0f8b4bdcec36')->setOriginalFilename('galileo.png')->setMimetype('image/png')->setSize(3779)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Galvantech')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfc39f4-517c-11ea-a0d7-2359c3171477')->setOriginalFilename('galvant.png')->setMimetype('image/png')->setSize(2669)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('GEC Plessey')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfc548e-517c-11ea-9c95-c21253387d8e')->setOriginalFilename('gecples.png')->setMimetype('image/png')->setSize(2312)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Gennum')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfc6f00-517c-11ea-afe0-b59f0d9790ee')->setOriginalFilename('gennum.png')->setMimetype('image/png')->setSize(2614)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('General Electric (Harris)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfc86a2-517c-11ea-85f3-9287d9be2d26')->setOriginalFilename('ge.png')->setMimetype('image/png')->setSize(2321)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('General Instruments')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfc9caa-517c-11ea-91c4-e5566819f23c')->setOriginalFilename('gi1.png')->setMimetype('image/png')->setSize(1385)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfca5ec-517c-11ea-9727-250bd1d8d03d')->setOriginalFilename('gi.png')->setMimetype('image/png')->setSize(1691)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('G-Link Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfcbbe0-517c-11ea-98e6-b54f366c4785')->setOriginalFilename('glink.png')->setMimetype('image/png')->setSize(1706)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Goal Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfcd15c-517c-11ea-8bbc-89e6e97c734b')->setOriginalFilename('goal1.png')->setMimetype('image/png')->setSize(9092)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfcdb8e-517c-11ea-83df-1bdb667d3784')->setOriginalFilename('goal2.png')->setMimetype('image/png')->setSize(9649)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Goldstar')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfcf29a-517c-11ea-8dca-ce6803b0fa42')->setOriginalFilename('goldstar1.png')->setMimetype('image/png')->setSize(2923)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfcfba0-517c-11ea-9eb7-175b577ae714')->setOriginalFilename('goldstar2.png')->setMimetype('image/png')->setSize(11387)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Gould')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfd13ba-517c-11ea-bf22-05493d42da08')->setOriginalFilename('gould.png')->setMimetype('image/png')->setSize(1549)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Greenwich Instruments')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfd2a9e-517c-11ea-b1b6-046b268735a8')->setOriginalFilename('greenwich.png')->setMimetype('image/png')->setSize(9761)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('General Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfd4326-517c-11ea-99d4-d12f48b5586f')->setOriginalFilename('gsemi.png')->setMimetype('image/png')->setSize(1704)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Harris Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfd5dc0-517c-11ea-b2c3-c45a7273a70f')->setOriginalFilename('harris1.png')->setMimetype('image/png')->setSize(1549)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfd69dc-517c-11ea-bd3b-940b5a317d89')->setOriginalFilename('harris2.png')->setMimetype('image/png')->setSize(1874)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('VEB')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfd8264-517c-11ea-a5f5-e8b2a83b95d8')->setOriginalFilename('hfo.png')->setMimetype('image/png')->setSize(1958)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Hitachi Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfd9a6a-517c-11ea-acbc-5ab4fbe5088e')->setOriginalFilename('hitachi.png')->setMimetype('image/png')->setSize(2611)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Holtek')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfdb572-517c-11ea-8e51-4a6a710fcb37')->setOriginalFilename('holtek.png')->setMimetype('image/png')->setSize(2160)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Hewlett Packard')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfdce2c-517c-11ea-ac3d-ae0eb8a2a8fb')->setOriginalFilename('hp.png')->setMimetype('image/png')->setSize(2464)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Hualon')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfde70e-517c-11ea-9a54-5d0b880141fd')->setOriginalFilename('hualon.png')->setMimetype('image/png')->setSize(2864)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Hynix Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfdff6e-517c-11ea-8f96-52e6914ab4e4')->setOriginalFilename('hynix.png')->setMimetype('image/png')->setSize(8444)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Hyundai')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfe17ba-517c-11ea-bb15-5f7d54a0717c')->setOriginalFilename('hyundai2.png')->setMimetype('image/png')->setSize(2269)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('IC Design')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfe2d72-517c-11ea-8a2a-f6dafbea652a')->setOriginalFilename('icdesign.png')->setMimetype('image/png')->setSize(3014)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Integrated Circuit Systems (ICS)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfe41d6-517c-11ea-a559-ed0946b2e069')->setOriginalFilename('icd.png')->setMimetype('image/png')->setSize(1641)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfe4a14-517c-11ea-9d6e-07c45d9e3672')->setOriginalFilename('ics.png')->setMimetype('image/png')->setSize(2042)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('IC - Haus')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfe6044-517c-11ea-833e-8bcdac840021')->setOriginalFilename('ichaus1.png')->setMimetype('image/png')->setSize(3370)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfe694a-517c-11ea-af58-e981d014fd42')->setOriginalFilename('ichaus.png')->setMimetype('image/png')->setSize(1552)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ICSI (Integrated Circuit Solution Inc.)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfe80e2-517c-11ea-ae64-49d37c814c9a')->setOriginalFilename('icsi.png')->setMimetype('image/png')->setSize(4049)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('I-Cube')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfe9618-517c-11ea-b4eb-0cee245bf283')->setOriginalFilename('icube.png')->setMimetype('image/png')->setSize(1629)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('IC Works')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfeaba8-517c-11ea-af8d-a4e5813116a6')->setOriginalFilename('icworks.png')->setMimetype('image/png')->setSize(1874)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Integrated Device Technology (IDT)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfec21e-517c-11ea-8bc8-30480dc1dea6')->setOriginalFilename('idt1.png')->setMimetype('image/png')->setSize(3995)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfecb6a-517c-11ea-b72b-e176777267ee')->setOriginalFilename('idt.png')->setMimetype('image/png')->setSize(1553)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('IGS Technologies')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfee122-517c-11ea-8530-17141168bd55')->setOriginalFilename('igstech.png')->setMimetype('image/png')->setSize(3832)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('IMPALA Linear')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bfef7ca-517c-11ea-ad5a-3ae067957707')->setOriginalFilename('impala.png')->setMimetype('image/png')->setSize(1628)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('IMP')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bff0fda-517c-11ea-a958-35ac08edcac1')->setOriginalFilename('imp.png')->setMimetype('image/png')->setSize(2175)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Infineon')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bff2790-517c-11ea-abe0-f4d953ce7c7e')->setOriginalFilename('infineon.png')->setMimetype('image/png')->setSize(4511)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('INMOS')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bff3de8-517c-11ea-a1ff-ed0fad37fd43')->setOriginalFilename('inmos.png')->setMimetype('image/png')->setSize(3365)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Intel')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bff52d8-517c-11ea-a777-acdbbfd4fa57')->setOriginalFilename('intel2.png')->setMimetype('image/png')->setSize(2010)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Intersil')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bff66e2-517c-11ea-947e-cdba52b90f3d')->setOriginalFilename('intresil4.png')->setMimetype('image/png')->setSize(2614)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bff6ebc-517c-11ea-8c01-ce54dfb0c7c2')->setOriginalFilename('intrsil1.png')->setMimetype('image/png')->setSize(1874)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bff76e6-517c-11ea-8bbf-709275240ff8')->setOriginalFilename('intrsil2.png')->setMimetype('image/png')->setSize(2520)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bff829e-517c-11ea-9baa-7e486e7dab62')->setOriginalFilename('intrsil3.png')->setMimetype('image/png')->setSize(3295)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('International Rectifier')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bff9d7e-517c-11ea-a26b-0e60ade8fd3e')->setOriginalFilename('ir.png')->setMimetype('image/png')->setSize(2729)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Information Storage Devices')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bffb642-517c-11ea-b506-d2fe916b8b2c')->setOriginalFilename('isd.png')->setMimetype('image/png')->setSize(2554)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ISSI (Integrated Silicon Solution, Inc.)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bffd28a-517c-11ea-ac6d-22001c97b5e5')->setOriginalFilename('issi.png')->setMimetype('image/png')->setSize(3030)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Integrated Technology Express')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1bffec52-517c-11ea-a9cf-401e56c6e1b7')->setOriginalFilename('ite.png')->setMimetype('image/png')->setSize(3302)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ITT Semiconductor (Micronas Intermetall)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c000570-517c-11ea-ab6c-acb5469a8586')->setOriginalFilename('itt.png')->setMimetype('image/png')->setSize(2483)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('IXYS')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c001f74-517c-11ea-b90e-5fc501a2e044')->setOriginalFilename('ixys.png')->setMimetype('image/png')->setSize(3575)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Korea Electronics (KEC)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c003842-517c-11ea-9465-d28775ef9c48')->setOriginalFilename('kec.png')->setMimetype('image/png')->setSize(2567)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Kota Microcircuits')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c004e9a-517c-11ea-af88-922808e4b75b')->setOriginalFilename('kota.png')->setMimetype('image/png')->setSize(1552)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Lattice Semiconductor Corp.')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c006506-517c-11ea-afb6-4c51e1e4a5c3')->setOriginalFilename('lattice1.png')->setMimetype('image/png')->setSize(1768)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c006e66-517c-11ea-8e08-5462bf3c1594')->setOriginalFilename('lattice2.png')->setMimetype('image/png')->setSize(1519)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c007726-517c-11ea-ba4d-bdd30d05962b')->setOriginalFilename('lattice3.png')->setMimetype('image/png')->setSize(1216)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Lansdale Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c008efa-517c-11ea-a6eb-51e332125040')->setOriginalFilename('lds1.png')->setMimetype('image/png')->setSize(2136)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0099b8-517c-11ea-a9fd-4bbf82cc2483')->setOriginalFilename('lds.png')->setMimetype('image/png')->setSize(1959)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Level One Communications')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c00b024-517c-11ea-a9ea-f6612c38b00e')->setOriginalFilename('levone.png')->setMimetype('image/png')->setSize(4189)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('LG Semicon (Lucky Goldstar Electronic Co.)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c00c9ba-517c-11ea-839a-063da728a1c3')->setOriginalFilename('lgs1.png')->setMimetype('image/png')->setSize(2417)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c00d374-517c-11ea-85c2-7d6d32b4f3c3')->setOriginalFilename('lgs.png')->setMimetype('image/png')->setSize(737)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Linear Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c00eda0-517c-11ea-afde-79daf9208531')->setOriginalFilename('linear.png')->setMimetype('image/png')->setSize(2486)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Linfinity Microelectronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0106b4-517c-11ea-a202-fd908f7f22ee')->setOriginalFilename('linfin.png')->setMimetype('image/png')->setSize(4844)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Lite-On')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c012162-517c-11ea-9a81-e995a332bc37')->setOriginalFilename('liteon.png')->setMimetype('image/png')->setSize(2388)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Lucent Technologies (AT&T Microelectronics)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c013b3e-517c-11ea-80a4-2aa3478966ea')->setOriginalFilename('lucent.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Macronix International')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c015416-517c-11ea-bca4-909d0dbe703b')->setOriginalFilename('macronix.png')->setMimetype('image/png')->setSize(2324)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Marvell Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c016d48-517c-11ea-a239-810b41df7b97')->setOriginalFilename('marvell.png')->setMimetype('image/png')->setSize(3131)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Matsushita Panasonic')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c018724-517c-11ea-b67a-0ca1cc0f28c1')->setOriginalFilename('matsush1.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c019250-517c-11ea-b94a-353b6456b126')->setOriginalFilename('matsushi.png')->setMimetype('image/png')->setSize(2029)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Maxim Dallas')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c01a998-517c-11ea-973d-b0655018261d')->setOriginalFilename('maxim.png')->setMimetype('image/png')->setSize(2690)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Media Vision')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c01c068-517c-11ea-81e5-c24385c6515f')->setOriginalFilename('mediavi1.png')->setMimetype('image/png')->setSize(2189)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c01ca0e-517c-11ea-a5ac-f473ef1d085b')->setOriginalFilename('mediavi2.png')->setMimetype('image/png')->setSize(2487)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Microchip (Arizona Michrochip Technology)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c01e386-517c-11ea-a622-c15bf4a48054')->setOriginalFilename('me.png')->setMimetype('image/png')->setSize(2411)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c01ef3e-517c-11ea-b1f1-76021c9e2dd9')->setOriginalFilename('microchp.png')->setMimetype('image/png')->setSize(2814)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Matra MHS')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0208e8-517c-11ea-aaf0-32487cc4aeea')->setOriginalFilename('mhs2.png')->setMimetype('image/png')->setSize(2036)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c021414-517c-11ea-b3ed-c28eb6079886')->setOriginalFilename('mhs.png')->setMimetype('image/png')->setSize(1870)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Micrel Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0229cc-517c-11ea-b83a-000b93a70a0e')->setOriginalFilename('micrel1.png')->setMimetype('image/png')->setSize(9695)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0234bc-517c-11ea-8eb0-93e625e49c31')->setOriginalFilename('micrel2.png')->setMimetype('image/png')->setSize(9695)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Micronas')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c024a4c-517c-11ea-9c27-906076b4ba39')->setOriginalFilename('micronas.png')->setMimetype('image/png')->setSize(1871)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Micronix Integrated Systems')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c026216-517c-11ea-9e86-40aa7b23bf2e')->setOriginalFilename('micronix.png')->setMimetype('image/png')->setSize(1856)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Micron Technology, Inc.')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c027666-517c-11ea-b195-686cbbfc8dce')->setOriginalFilename('micron.png')->setMimetype('image/png')->setSize(1763)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Microsemi')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c028d4a-517c-11ea-b9df-b3fdbcea5d82')->setOriginalFilename('microsemi1.png')->setMimetype('image/png')->setSize(3714)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0298f8-517c-11ea-b218-c45dd384deef')->setOriginalFilename('microsemi2.png')->setMimetype('image/png')->setSize(11992)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Mini-Circuits')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c02b180-517c-11ea-bdb2-d2d6eb049447')->setOriginalFilename('minicirc.png')->setMimetype('image/png')->setSize(1391)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Mitel Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c02c904-517c-11ea-b67d-afeb389800ab')->setOriginalFilename('mitel.png')->setMimetype('image/png')->setSize(2819)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Mitsubishi Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c02e196-517c-11ea-b32a-0ae758ede01b')->setOriginalFilename('mitsubis.png')->setMimetype('image/png')->setSize(2311)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Micro Linear')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c02f94c-517c-11ea-8d31-a90b54774436')->setOriginalFilename('mlinear.png')->setMimetype('image/png')->setSize(3377)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('MMI (Monolithic Memories, Inc.)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c030f4a-517c-11ea-89a3-5ff18e28f89a')->setOriginalFilename('mmi.png')->setMimetype('image/png')->setSize(2692)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Mosaic Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c03264c-517c-11ea-b62e-63ded48d45e3')->setOriginalFilename('mosaic.png')->setMimetype('image/png')->setSize(2959)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Mosel Vitelic')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c033e3e-517c-11ea-aa6e-295a944e6492')->setOriginalFilename('moselvit.png')->setMimetype('image/png')->setSize(2504)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('MOS Technologies')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0355e0-517c-11ea-bf4c-e717b3328fb1')->setOriginalFilename('mos.png')->setMimetype('image/png')->setSize(2857)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Mostek')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c036f08-517c-11ea-bf43-999f653af02d')->setOriginalFilename('mostek1.png')->setMimetype('image/png')->setSize(7502)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0379ee-517c-11ea-9b02-3bf48e6eaab9')->setOriginalFilename('mostek2.png')->setMimetype('image/png')->setSize(7502)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0384f2-517c-11ea-9290-32805a3430f6')->setOriginalFilename('mostek3.png')->setMimetype('image/png')->setSize(2514)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('MoSys')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c039b7c-517c-11ea-8d53-f043c2deb6ca')->setOriginalFilename('mosys.png')->setMimetype('image/png')->setSize(2321)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Motorola')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c03b2e2-517c-11ea-8ebb-01be54fdbdf9')->setOriginalFilename('motorol1.png')->setMimetype('image/png')->setSize(999)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c03bcd8-517c-11ea-afe8-cbb3c5e03c13')->setOriginalFilename('motorol2.png')->setMimetype('image/png')->setSize(2417)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Microtune')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c03d2ea-517c-11ea-a34d-a66320bbd0af')->setOriginalFilename('mpd.png')->setMimetype('image/png')->setSize(2663)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('M-Systems')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c03e974-517c-11ea-b074-f60a7a398d3e')->setOriginalFilename('msystem.png')->setMimetype('image/png')->setSize(1670)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Murata Manufacturing')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c040260-517c-11ea-ac03-3aaf5dc1627d')->setOriginalFilename('murata1.png')->setMimetype('image/png')->setSize(4874)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c040bd4-517c-11ea-8b18-9d21b1cecec6')->setOriginalFilename('murata.png')->setMimetype('image/png')->setSize(4777)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('MWave (IBM)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c042146-517c-11ea-b728-f1af7f5823da')->setOriginalFilename('mwave.png')->setMimetype('image/png')->setSize(3370)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Myson Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0438b6-517c-11ea-91a4-40317701bdee')->setOriginalFilename('myson.png')->setMimetype('image/png')->setSize(1932)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('NEC Electronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c044d56-517c-11ea-a73f-6124c8db30ce')->setOriginalFilename('nec1.png')->setMimetype('image/png')->setSize(3166)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c045530-517c-11ea-9a1e-ace96cd68b31')->setOriginalFilename('nec2.png')->setMimetype('image/png')->setSize(3071)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('NexFlash Technologies')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c04685e-517c-11ea-9361-24d3d2ad1761')->setOriginalFilename('nexflash.png')->setMimetype('image/png')->setSize(7789)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('New Japan Radio')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c047f6a-517c-11ea-9622-6860561ff933')->setOriginalFilename('njr.png')->setMimetype('image/png')->setSize(3419)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('National Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0494e6-517c-11ea-b11c-1b21d20160c4')->setOriginalFilename('ns1.png')->setMimetype('image/png')->setSize(1959)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c049cac-517c-11ea-86c4-0ceab8233d46')->setOriginalFilename('ns2.png')->setMimetype('image/png')->setSize(1952)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('NVidia Corporation')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c04b0ac-517c-11ea-8c67-ada0612fed55')->setOriginalFilename('nvidia.png')->setMimetype('image/png')->setSize(1874)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Oak Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c04cab0-517c-11ea-83b5-0023cd1d89c8')->setOriginalFilename('oak.png')->setMimetype('image/png')->setSize(2614)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Oki Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c04e3ce-517c-11ea-8375-63787777febe')->setOriginalFilename('oki1.png')->setMimetype('image/png')->setSize(2267)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c04ed42-517c-11ea-ac34-8000feba210b')->setOriginalFilename('oki.png')->setMimetype('image/png')->setSize(2546)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Opti')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c050340-517c-11ea-9946-f582cab0e1bd')->setOriginalFilename('opti.png')->setMimetype('image/png')->setSize(1684)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Orbit Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c051e84-517c-11ea-96c8-fafd2105d748')->setOriginalFilename('orbit.png')->setMimetype('image/png')->setSize(3347)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Oren Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c053680-517c-11ea-a6f3-277c109d8a6f')->setOriginalFilename('oren.png')->setMimetype('image/png')->setSize(3497)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Performance Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c054ec2-517c-11ea-844f-270877ad5696')->setOriginalFilename('perform.png')->setMimetype('image/png')->setSize(3284)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Pericom Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c05665a-517c-11ea-99b3-4acaf5fc9854')->setOriginalFilename('pericom.png')->setMimetype('image/png')->setSize(2311)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('PhaseLink Laboratories')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c057bcc-517c-11ea-8089-435334dd563a')->setOriginalFilename('phaslink.png')->setMimetype('image/png')->setSize(2669)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Philips Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0590c6-517c-11ea-926f-34b94eaf1902')->setOriginalFilename('philips.png')->setMimetype('image/png')->setSize(8690)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('PLX Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c05a80e-517c-11ea-9e30-e33221f6f0a4')->setOriginalFilename('plx.png')->setMimetype('image/png')->setSize(4749)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('PMC- Sierra')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c05bff6-517c-11ea-b120-39c4b7530228')->setOriginalFilename('pmc.png')->setMimetype('image/png')->setSize(3497)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Precision Monolithics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c05d7c0-517c-11ea-a6a3-af0f81d6cf9d')->setOriginalFilename('pmi.png')->setMimetype('image/png')->setSize(3807)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Princeton Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c05ef3a-517c-11ea-b73d-ce3eff1ee86c')->setOriginalFilename('ptc.png')->setMimetype('image/png')->setSize(2669)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('PowerSmart')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c06063c-517c-11ea-97fb-feea529c5c72')->setOriginalFilename('pwrsmart.png')->setMimetype('image/png')->setSize(1389)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('QuickLogic')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c061b18-517c-11ea-9647-186c8c43a9e3')->setOriginalFilename('qlogic.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Qlogic')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0630c6-517c-11ea-877b-80a4cb92e8fa')->setOriginalFilename('qualcomm.png')->setMimetype('image/png')->setSize(3326)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Quality Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0645ca-517c-11ea-bb75-12f5658e276e')->setOriginalFilename('quality.png')->setMimetype('image/png')->setSize(1309)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Rabbit Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c065b6e-517c-11ea-bc6c-7cd93ce04c43')->setOriginalFilename('rabbit.png')->setMimetype('image/png')->setSize(2857)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Ramtron International Co.')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c06719e-517c-11ea-aaf4-07313e0e9c74')->setOriginalFilename('ramtron.png')->setMimetype('image/png')->setSize(1573)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Raytheon Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c06874c-517c-11ea-8e1d-51547328f4b5')->setOriginalFilename('raytheon.png')->setMimetype('image/png')->setSize(4303)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('RCA Solid State')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c06a3b2-517c-11ea-8d4d-ff6d78a6c2e9')->setOriginalFilename('rca.png')->setMimetype('image/png')->setSize(1860)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Realtek Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c06bb40-517c-11ea-880a-53bee4296b25')->setOriginalFilename('realtek.png')->setMimetype('image/png')->setSize(2993)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Rectron')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c06d580-517c-11ea-9336-9871318f8422')->setOriginalFilename('rectron.png')->setMimetype('image/png')->setSize(1691)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Rendition')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c06f146-517c-11ea-9989-c14fad4bd6ea')->setOriginalFilename('rendit.png')->setMimetype('image/png')->setSize(1370)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Renesas Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c070bcc-517c-11ea-9689-b9afafa26e25')->setOriginalFilename('renesas.png')->setMimetype('image/png')->setSize(8761)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Rockwell')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0727ec-517c-11ea-b133-74f25d1b7d8d')->setOriginalFilename('rockwell.png')->setMimetype('image/png')->setSize(1704)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Rohm Corp.')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0742f4-517c-11ea-b4ae-338cc7624971')->setOriginalFilename('rohm.png')->setMimetype('image/png')->setSize(2693)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('S3')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c075c30-517c-11ea-926d-226fdf1e4639')->setOriginalFilename('s3.png')->setMimetype('image/png')->setSize(2189)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Sage')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0775da-517c-11ea-8d9a-29d435e9092d')->setOriginalFilename('sage.png')->setMimetype('image/png')->setSize(2735)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Saifun Semiconductors Ltd.')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c078ed0-517c-11ea-88fb-6e511e646f63')->setOriginalFilename('saifun.png')->setMimetype('image/png')->setSize(19242)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Sames')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c07af82-517c-11ea-948c-6ccadf955d15')->setOriginalFilename('sames.png')->setMimetype('image/png')->setSize(2614)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Samsung')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c07c77e-517c-11ea-a083-dfd6889ff8d5')->setOriginalFilename('samsung.png')->setMimetype('image/png')->setSize(1841)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Sanken')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c07e43e-517c-11ea-967c-e9d1e43965c3')->setOriginalFilename('sanken1.png')->setMimetype('image/png')->setSize(2214)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c07f208-517c-11ea-9630-334688b21f8f')->setOriginalFilename('sanken.png')->setMimetype('image/png')->setSize(5309)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Sanyo')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c080eaa-517c-11ea-875b-710ef452ec7f')->setOriginalFilename('sanyo1.png')->setMimetype('image/png')->setSize(2228)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c081c10-517c-11ea-b8b9-97487d91ea85')->setOriginalFilename('sanyo.png')->setMimetype('image/png')->setSize(2455)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Scenix')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0836be-517c-11ea-b122-c02317eaf2d9')->setOriginalFilename('scenix.png')->setMimetype('image/png')->setSize(1869)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Samsung Electronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c085144-517c-11ea-8ddf-f9b7adf9fc7e')->setOriginalFilename('sec1.png')->setMimetype('image/png')->setSize(9392)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c085ffe-517c-11ea-9148-a7a7b72cd1a6')->setOriginalFilename('sec.png')->setMimetype('image/png')->setSize(2051)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('SEEQ Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c087aca-517c-11ea-a535-ad0400d0785f')->setOriginalFilename('seeq.png')->setMimetype('image/png')->setSize(2903)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Seiko Instruments')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c08969a-517c-11ea-bb99-7ed8fdf78efc')->setOriginalFilename('seikoi.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c08a3a6-517c-11ea-aeda-fba30c92bc5f')->setOriginalFilename('semelab.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Semtech')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c08beea-517c-11ea-a35c-96ffa9a7c509')->setOriginalFilename('semtech.png')->setMimetype('image/png')->setSize(1431)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('SGS-Ates')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c08da4c-517c-11ea-97b3-72424c0951d6')->setOriginalFilename('sgs1.png')->setMimetype('image/png')->setSize(2339)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('SGS-Thomson Microelectonics ST-M)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c08f63a-517c-11ea-9d3b-cd6fccb9b34a')->setOriginalFilename('sgs2.png')->setMimetype('image/png')->setSize(1874)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Sharp Microelectronics (USA)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c090ee0-517c-11ea-bc50-298cbcc2f07b')->setOriginalFilename('sharp.png')->setMimetype('image/png')->setSize(2258)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Shindengen')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0926e6-517c-11ea-95c9-a0e63e26801b')->setOriginalFilename('shindgen.png')->setMimetype('image/png')->setSize(1629)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Siemens Microelectronics, Inc.')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c09407c-517c-11ea-bdbf-1b48dfbc3f82')->setOriginalFilename('siemens1.png')->setMimetype('image/png')->setSize(1216)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c094b26-517c-11ea-831c-1cef32a858b2')->setOriginalFilename('siemens2.png')->setMimetype('image/png')->setSize(2916)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Sierra')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c096688-517c-11ea-85cc-db64fd8bb257')->setOriginalFilename('sierra.png')->setMimetype('image/png')->setSize(2321)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Sigma Tel')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c09821c-517c-11ea-9a06-fa89108cd973')->setOriginalFilename('sigmatel.png')->setMimetype('image/png')->setSize(1790)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Signetics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0999c8-517c-11ea-918f-19ee711c3d38')->setOriginalFilename('signetic.png')->setMimetype('image/png')->setSize(1519)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Silicon Laboratories')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c09b37c-517c-11ea-bce2-fd88b95ea8bd')->setOriginalFilename('siliconlabs.png')->setMimetype('image/png')->setSize(5540)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Silicon Magic')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c09d122-517c-11ea-8b37-a171745dc985')->setOriginalFilename('siliconm.png')->setMimetype('image/png')->setSize(3817)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Simtec Corp.')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c09eb1c-517c-11ea-bdf1-4f38bf8dad38')->setOriginalFilename('silicons.png')->setMimetype('image/png')->setSize(2320)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c09f774-517c-11ea-a2d6-864f6a53237f')->setOriginalFilename('simtek.png')->setMimetype('image/png')->setSize(1874)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Siliconix')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0a13f8-517c-11ea-b0fa-062e3cc44d2d')->setOriginalFilename('siliconx.png')->setMimetype('image/png')->setSize(2464)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Siliconians')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0a316c-517c-11ea-a4df-1f96e88298ce')->setOriginalFilename('silnans.png')->setMimetype('image/png')->setSize(1549)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Sipex')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0a4d3c-517c-11ea-9f5b-1ba9d886caa5')->setOriginalFilename('sipex.png')->setMimetype('image/png')->setSize(4029)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Silicon Integrated Systems')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0a6786-517c-11ea-aab3-05026ba5c269')->setOriginalFilename('sis.png')->setMimetype('image/png')->setSize(3608)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('SMC')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0a7ffa-517c-11ea-a1b7-295a2785bec3')->setOriginalFilename('smc1.png')->setMimetype('image/png')->setSize(1763)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Standard Microsystems')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0a99a4-517c-11ea-ac2e-8c558d1fddea')->setOriginalFilename('smsc1.png')->setMimetype('image/png')->setSize(1781)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0aa6ce-517c-11ea-b54b-c444098d7c8d')->setOriginalFilename('smsc.png')->setMimetype('image/png')->setSize(2117)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Sony Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0ac1fe-517c-11ea-9536-9bb2fd9a5b86')->setOriginalFilename('sony.png')->setMimetype('image/png')->setSize(2476)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Space Electronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0adbee-517c-11ea-8f89-3c7e51df0277')->setOriginalFilename('space.png')->setMimetype('image/png')->setSize(3377)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Spectek')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0af7f0-517c-11ea-be3d-536311ad51f8')->setOriginalFilename('spectek.png')->setMimetype('image/png')->setSize(2228)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Signal Processing Technologies')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0b1014-517c-11ea-8c86-726446ba6ae5')->setOriginalFilename('spt.png')->setMimetype('image/png')->setSize(3419)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Solid State Scientific')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0b2a90-517c-11ea-a19a-02d8b32b0ba5')->setOriginalFilename('sss.png')->setMimetype('image/png')->setSize(1871)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Silicon Storage Technology (SST)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0b4462-517c-11ea-b9ed-8388174dcc63')->setOriginalFilename('sst.png')->setMimetype('image/png')->setSize(3072)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('STMicroelectronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0b5d26-517c-11ea-bf6e-a401d8af9e02')->setOriginalFilename('st.png')->setMimetype('image/png')->setSize(1604)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('SUMMIT Microelectronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0b764e-517c-11ea-990d-ce7023a3a859')->setOriginalFilename('summit.png')->setMimetype('image/png')->setSize(11440)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Synergy Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0b8f3a-517c-11ea-92f5-84d33ae2e347')->setOriginalFilename('synergy.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Synertek')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0ba5ba-517c-11ea-9743-08386f480818')->setOriginalFilename('synertek.png')->setMimetype('image/png')->setSize(1789)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Taiwan Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0bbb9a-517c-11ea-8d66-18505514406d')->setOriginalFilename('taiwsemi.png')->setMimetype('image/png')->setSize(1475)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('TDK Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0bd166-517c-11ea-b3a6-c95270af6d2f')->setOriginalFilename('tdk.png')->setMimetype('image/png')->setSize(3687)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Teccor Electronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0be782-517c-11ea-b0ff-90e7b45462fe')->setOriginalFilename('teccor.png')->setMimetype('image/png')->setSize(1869)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('TelCom Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0bfda8-517c-11ea-979f-15d592d8c158')->setOriginalFilename('telcom.png')->setMimetype('image/png')->setSize(2555)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Teledyne')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0c132e-517c-11ea-82b4-078c8c95c31e')->setOriginalFilename('teledyne.png')->setMimetype('image/png')->setSize(1904)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Telefunken')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0c2904-517c-11ea-82fa-0cff1324b488')->setOriginalFilename('telefunk.png')->setMimetype('image/png')->setSize(2715)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Teltone')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0c3eda-517c-11ea-acce-7c4dc69776ca')->setOriginalFilename('teltone.png')->setMimetype('image/png')->setSize(4303)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Thomson-CSF')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0c58b6-517c-11ea-b5fb-3013ce098a99')->setOriginalFilename('thomscsf.png')->setMimetype('image/png')->setSize(1874)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Texas Instruments')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0c7436-517c-11ea-9716-302dada7716a')->setOriginalFilename('ti1.png')->setMimetype('image/png')->setSize(1869)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0c80f2-517c-11ea-ac66-4d136cec08f3')->setOriginalFilename('ti.png')->setMimetype('image/png')->setSize(1789)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Toko Amerika')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0c9cd6-517c-11ea-ac88-58d0882c88f3')->setOriginalFilename('toko.png')->setMimetype('image/png')->setSize(1907)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Toshiba (US)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0cb86a-517c-11ea-9d76-08d6f88fcb34')->setOriginalFilename('toshiba1.png')->setMimetype('image/png')->setSize(1922)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0cc5bc-517c-11ea-900e-275f9183e6b9')->setOriginalFilename('toshiba2.png')->setMimetype('image/png')->setSize(1309)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0cd2e6-517c-11ea-846e-6e6327a714c5')->setOriginalFilename('toshiba3.png')->setMimetype('image/png')->setSize(2269)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Trident')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0cedd0-517c-11ea-9fb7-9e85f317ef6d')->setOriginalFilename('trident.png')->setMimetype('image/png')->setSize(1414)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('TriQuint Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0d0612-517c-11ea-a19d-3e7f543af34f')->setOriginalFilename('triquint.png')->setMimetype('image/png')->setSize(2294)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Triscend')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0d214c-517c-11ea-9be2-102ad0c00d51')->setOriginalFilename('triscend.png')->setMimetype('image/png')->setSize(4521)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Tseng Labs')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0d3cf4-517c-11ea-b115-cb72f949089e')->setOriginalFilename('tseng.png')->setMimetype('image/png')->setSize(1466)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Tundra')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0d5824-517c-11ea-aa67-b349aac71caa')->setOriginalFilename('tundra.png')->setMimetype('image/png')->setSize(1709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Turbo IC')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0d7160-517c-11ea-b55e-7accfc5c477c')->setOriginalFilename('turbo_ic.png')->setMimetype('image/png')->setSize(7784)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Ubicom')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0d8dee-517c-11ea-9088-e015423b4ad5')->setOriginalFilename('ubicom.png')->setMimetype('image/png')->setSize(2047)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('United Microelectronics Corp (UMC)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0da928-517c-11ea-99b1-2dfc4d90ec87')->setOriginalFilename('umc.png')->setMimetype('image/png')->setSize(3032)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Unitrode')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0dc516-517c-11ea-80bb-a9a9739b779a')->setOriginalFilename('unitrode.png')->setMimetype('image/png')->setSize(1309)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('USAR Systems')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0de0c8-517c-11ea-ac5c-8bddf99acce6')->setOriginalFilename('usar1.png')->setMimetype('image/png')->setSize(2771)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0def00-517c-11ea-ad70-0d7666847c17')->setOriginalFilename('usar.png')->setMimetype('image/png')->setSize(2793)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('United Technologies Microelectronics Center (UTMC)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0e0b20-517c-11ea-b2f6-ee77d3ffaf3f')->setOriginalFilename('utmc.png')->setMimetype('image/png')->setSize(2047)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Utron')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0e2678-517c-11ea-bbf4-2b8cc2e082b5')->setOriginalFilename('utron.png')->setMimetype('image/png')->setSize(2047)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('V3 Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0e423e-517c-11ea-b759-9409a88533d7')->setOriginalFilename('v3.png')->setMimetype('image/png')->setSize(3248)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Vadem')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0e5e04-517c-11ea-8a0f-376eea0bee6c')->setOriginalFilename('vadem.png')->setMimetype('image/png')->setSize(1874)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Vanguard International Semiconductor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0e7876-517c-11ea-a914-badcea1efc0f')->setOriginalFilename('vanguard.png')->setMimetype('image/png')->setSize(1454)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Vantis')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0e94c8-517c-11ea-bf66-41221a342775')->setOriginalFilename('vantis.png')->setMimetype('image/png')->setSize(1475)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Via Technologies')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0eaeea-517c-11ea-8d38-cac7c9477d88')->setOriginalFilename('via.png')->setMimetype('image/png')->setSize(1922)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Virata')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0ec8d0-517c-11ea-ac07-98cada6476cc')->setOriginalFilename('virata.png')->setMimetype('image/png')->setSize(3764)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Vishay')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0ee0b8-517c-11ea-a7ff-8e2f79414576')->setOriginalFilename('vishay.png')->setMimetype('image/png')->setSize(4410)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Vision Tech')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0ef7ba-517c-11ea-86ed-4f740cd530ed')->setOriginalFilename('vistech.png')->setMimetype('image/png')->setSize(1942)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Vitelic')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0f0e3a-517c-11ea-b3c5-85841275bc70')->setOriginalFilename('vitelic.png')->setMimetype('image/png')->setSize(1691)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('VLSI Technology')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0f2762-517c-11ea-b2b7-52fadbb768d8')->setOriginalFilename('vlsi.png')->setMimetype('image/png')->setSize(1874)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Volterra')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0f3e32-517c-11ea-acf7-4dcdd4aa1ac1')->setOriginalFilename('volterra.png')->setMimetype('image/png')->setSize(2029)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('VTC')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0f555c-517c-11ea-a6bc-e7f695ee407b')->setOriginalFilename('vtc.png')->setMimetype('image/png')->setSize(2223)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Waferscale Integration (WSI)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0f6bbe-517c-11ea-8a9a-4fb4583341b6')->setOriginalFilename('wafscale.png')->setMimetype('image/png')->setSize(2985)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Western Digital')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0f8004-517c-11ea-8c05-b43d04e5e3f1')->setOriginalFilename('wdc1.png')->setMimetype('image/png')->setSize(1784)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0f88ba-517c-11ea-ad37-90dc024ad52f')->setOriginalFilename('wdc2.png')->setMimetype('image/png')->setSize(1403)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Weitek')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0f9fc6-517c-11ea-a913-9af8c6316c4c')->setOriginalFilename('weitek.png')->setMimetype('image/png')->setSize(1468)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Winbond')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0fb8e4-517c-11ea-8aee-7fce4cfceb26')->setOriginalFilename('winbond.png')->setMimetype('image/png')->setSize(5402)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Wofson Microelectronics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0fd2fc-517c-11ea-a208-3de00791a5d1')->setOriginalFilename('wolf.png')->setMimetype('image/png')->setSize(2343)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Xwmics')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c0febc0-517c-11ea-bc93-3a920704ed1f')->setOriginalFilename('xemics.png')->setMimetype('image/png')->setSize(2029)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Xicor')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c1006a0-517c-11ea-a8c5-3cb725704fee')->setOriginalFilename('xicor1.png')->setMimetype('image/png')->setSize(1259)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c101294-517c-11ea-821f-7b98c1f341b8')->setOriginalFilename('xicor.png')->setMimetype('image/png')->setSize(3389)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Xilinx')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c102e32-517c-11ea-a464-12216c5183b7')->setOriginalFilename('xilinx.png')->setMimetype('image/png')->setSize(4186)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Yamaha')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c104908-517c-11ea-afe2-e2328b8d09e3')->setOriginalFilename('yamaha.png')->setMimetype('image/png')->setSize(1779)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Zetex Semiconductors')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c106168-517c-11ea-ab1c-f405d22f0595')->setOriginalFilename('zetex.png')->setMimetype('image/png')->setSize(1255)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Zilog')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c107658-517c-11ea-831c-70746e0710f8')->setOriginalFilename('zilog1.png')->setMimetype('image/png')->setSize(1958)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c108080-517c-11ea-b6d9-664ee50fe109')->setOriginalFilename('zilog2.png')->setMimetype('image/png')->setSize(2204)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c108a4e-517c-11ea-a62d-706a26744fcc')->setOriginalFilename('zilog3.png')->setMimetype('image/png')->setSize(2614)->setCreated(DateTime::from('2020-02-17 11:53:27')))
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c109430-517c-11ea-a624-80b2229a137f')->setOriginalFilename('zilog4.png')->setMimetype('image/png')->setSize(2405)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('ZMD (Zentrum Mikroelektronik Dresden)')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c10aa60-517c-11ea-8ac5-71d1750e8d1c')->setOriginalFilename('zmda.png')->setMimetype('image/png')->setSize(3709)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);
		$manager->persist((new Manufacturer)->setName('Zoran')
			->addIcLogo((new ManufacturerICLogo)->setFilename('1c10c18a-517c-11ea-a781-aaffe43be5d4')->setOriginalFilename('zoran.png')->setMimetype('image/png')->setSize(2784)->setCreated(DateTime::from('2020-02-17 11:53:27')))
		);

		$manager->flush();
	}
}
