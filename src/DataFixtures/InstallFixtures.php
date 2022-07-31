<?php

namespace Limas\DataFixtures;

use Limas\Entity\Footprint;
use Limas\Entity\FootprintCategory;
use Limas\Entity\FootprintImage;
use Limas\Entity\Manufacturer;
use Limas\Entity\ManufacturerICLogo;
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
use Nette\Utils\DateTime;


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
		$root = (new FootprintCategory)
			->setName('Root Category')
			->setRoot(1)
			->setCategoryPath('Root Category');
		$manager->persist($root);
		$bga = (new FootprintCategory)
			->setName('BGA')
			->setParent($root)
			->setCategoryPath('Root Category ➤ BGA');
		$manager->persist($bga);
		$cbga = (new FootprintCategory)
			->setName('CBGA')
			->setParent($bga)
			->setCategoryPath('Root Category ➤ BGA ➤ CBGA');
		$manager->persist($cbga);
		$dip = (new FootprintCategory)
			->setName('DIP')
			->setParent($root)
			->setCategoryPath('Root Category ➤ DIP');
		$manager->persist($dip);
		$cerdip = (new FootprintCategory)
			->setName('CERDIP')
			->setParent($dip)
			->setCategoryPath('Root Category ➤ DIP ➤ CERDIP');
		$manager->persist($cerdip);

		$cbga32 = (new Footprint)
			->setName('CBGA-32')
			->setCategory($cbga)
			->setDescription('32-Lead Ceramic Ball Grid Array');
		$manager->persist($cbga32);
		$cerdip8 = (new Footprint)
			->setName('CerDIP-8')
			->setCategory($cerdip)
			->setDescription('8-Lead Ceramic Dual In-Line Package');
		$manager->persist($cerdip8);

		$cbga32i = (new FootprintImage)
			->setFootprint($cbga32)
			->setFilename('1b614228-517c-11ea-abf0-df07e0119f97')
			->setOriginalFilename('CBGA-32.png')
			->setMimetype('image/png')
			->setSize(23365)
			->setExtension('png')
			->setCreated(DateTime::from('2020-02-17 11:53:26'));
		$manager->persist($cbga32i);
		$cerdip8i = (new FootprintImage)
			->setFootprint($cerdip8)
			->setFilename('1b6718ce-517c-11ea-921d-b45648294db9')
			->setOriginalFilename('CERDIP-8.png')
			->setMimetype('image/png')
			->setSize(13544)
			->setExtension('png')
			->setCreated(DateTime::from('2020-02-17 11:53:26'));
		$manager->persist($cerdip8i);


		$manager->persist($icd = (new Manufacturer)->setName('Integrated Circuit Designs'));
		$manager->persist($actel = (new Manufacturer)->setName('ACTEL'));
		$manager->persist($altinc = (new Manufacturer)->setName('ALTINC'));
		$manager->persist($aeroflex = (new Manufacturer)->setName('Aeroflex'));
		$manager->persist($agilent = (new Manufacturer)->setName('Agilent Technologies'));
		$manager->persist($akm = (new Manufacturer)->setName('AKM Semiconductor'));
		$manager->persist($alesis = (new Manufacturer)->setName('Alesis Semiconductor'));
		$manager->persist($ali = (new Manufacturer)->setName('ALi (Acer Laboratories Inc.)'));
		$manager->persist($allayer = (new Manufacturer)->setName('Allayer Communications'));
		$manager->persist($allegro = (new Manufacturer)->setName('Allegro Microsystems'));
		$manager->persist($alliance = (new Manufacturer)->setName('Alliance Semiconductor'));
		$manager->persist($alpha = (new Manufacturer)->setName('Alpha Industries'));
		$manager->persist($alphamic = (new Manufacturer)->setName('Alpha Microelectronics'));
		$manager->persist($altera = (new Manufacturer)->setName('Altera'));
		$manager->persist($amd = (new Manufacturer)->setName('Advanced Micro Devices (AMD)'));
		$manager->persist($ami = (new Manufacturer)->setName('American Microsystems, Inc. (AMI)'));
		$manager->persist($amic = (new Manufacturer)->setName('Amic Technology'));
		$manager->persist($amphus = (new Manufacturer)->setName('Amphus'));
		$manager->persist($anachip = (new Manufacturer)->setName('Anachip Corp.'));
		$manager->persist($anadigic = (new Manufacturer)->setName('ANADIGICs'));
		$manager->persist($analog = (new Manufacturer)->setName('Analog Devices'));
		$manager->persist($anasys = (new Manufacturer)->setName('Analog Systems'));
		$manager->persist($anchor = (new Manufacturer)->setName('Anchor Chips'));
		$manager->persist($apex = (new Manufacturer)->setName('Apex Microtechnology'));
		$manager->persist($ark = (new Manufacturer)->setName('ARK Logic'));
		$manager->persist($asd = (new Manufacturer)->setName('ASD'));
		$manager->persist($astec = (new Manufacturer)->setName('Astec Semiconductor'));
		$manager->persist($atc = (new Manufacturer)->setName('ATC (Analog Technologie)'));
		$manager->persist($atecom = (new Manufacturer)->setName('ATecoM'));
		$manager->persist($ati = (new Manufacturer)->setName('ATI Technologies'));
		$manager->persist($atmel = (new Manufacturer)->setName('Atmel'));
		$manager->persist($att = (new Manufacturer)->setName('AT&T'));
		$manager->persist($audiocod = (new Manufacturer)->setName('AudioCodes'));
		$manager->persist($aura = (new Manufacturer)->setName('Aura Vision'));
		$manager->persist($aureal = (new Manufacturer)->setName('Aureal'));
		$manager->persist($austin = (new Manufacturer)->setName('Austin Semiconductor'));
		$manager->persist($avance = (new Manufacturer)->setName('Avance Logic'));
		$manager->persist($bel = (new Manufacturer)->setName('Bel Fuse'));
		$manager->persist($benchmarq = (new Manufacturer)->setName('Benchmarq Microelectronics'));
		$manager->persist($bitec = (new Manufacturer)->setName('BI Technologies'));
		$manager->persist($bowmar = (new Manufacturer)->setName('Bowmar/White'));
		$manager->persist($brightflash = (new Manufacturer)->setName('Brightflash'));
		$manager->persist($broadcom = (new Manufacturer)->setName('Broadcom'));
		$manager->persist($brooktree = (new Manufacturer)->setName('Brooktree(now Rockwell)'));
		$manager->persist($burr = (new Manufacturer)->setName('Burr Brown'));
		$manager->persist($cmd = (new Manufacturer)->setName('California Micro Devices'));
		$manager->persist($calogic = (new Manufacturer)->setName('Calogic'));
		$manager->persist($catalyst = (new Manufacturer)->setName('Catalyst Semiconductor'));
		$manager->persist($centon = (new Manufacturer)->setName('Centon Electronics'));
		$manager->persist($ceramate = (new Manufacturer)->setName('Ceramate Technical'));
		$manager->persist($cherry = (new Manufacturer)->setName('Cherry Semiconductor'));
		$manager->persist($chipcon = (new Manufacturer)->setName('Chipcon AS'));
		$manager->persist($chips = (new Manufacturer)->setName('Chips'));
		$manager->persist($chrontel = (new Manufacturer)->setName('Chrontel'));
		$manager->persist($cirrus = (new Manufacturer)->setName('Cirrus Logic'));
		$manager->persist($comcore = (new Manufacturer)->setName('ComCore Semiconductor'));
		$manager->persist($conexant = (new Manufacturer)->setName('Conexant'));
		$manager->persist($cosmo = (new Manufacturer)->setName('Cosmo Electronics'));
		$manager->persist($chrystal = (new Manufacturer)->setName('Chrystal'));
		$manager->persist($cygnal = (new Manufacturer)->setName('Cygnal'));
		$manager->persist($cypress = (new Manufacturer)->setName('Cypress Semiconductor'));
		$manager->persist($cyrix = (new Manufacturer)->setName('Cyrix Corporation'));
		$manager->persist($daewoo = (new Manufacturer)->setName('Daewoo Electronics Semiconductor'));
		$manager->persist($dallas = (new Manufacturer)->setName('Dallas Semiconductor'));
		$manager->persist($davicom = (new Manufacturer)->setName('Davicom Semiconductor'));
		$manager->persist($ddd = (new Manufacturer)->setName('Data Delay Devices'));
		$manager->persist($diamond = (new Manufacturer)->setName('Diamond Technologies'));
		$manager->persist($diotec = (new Manufacturer)->setName('DIOTEC'));
		$manager->persist($dtc = (new Manufacturer)->setName('DTC Data Technology'));
		$manager->persist($dvdo = (new Manufacturer)->setName('DVDO'));
		$manager->persist($egg = (new Manufacturer)->setName('EG&G'));
		$manager->persist($elan = (new Manufacturer)->setName('Elan Microelectronics'));
		$manager->persist($elantec = (new Manufacturer)->setName('ELANTEC'));
		$manager->persist($elarrays = (new Manufacturer)->setName('Electronic Arrays'));
		$manager->persist($efst = (new Manufacturer)->setName('Elite Flash Storage Technology Inc. (EFST)'));
		$manager->persist($emm = (new Manufacturer)->setName('EM Microelectronik - Marin'));
		$manager->persist($ems = (new Manufacturer)->setName('Enhanced Memory Systems'));
		$manager->persist($ensoniq = (new Manufacturer)->setName('Ensoniq Corp'));
		$manager->persist($eon = (new Manufacturer)->setName('EON Silicon Devices'));
		$manager->persist($epson = (new Manufacturer)->setName('Epson'));
		$manager->persist($ericsson = (new Manufacturer)->setName('Ericsson'));
		$manager->persist($ess = (new Manufacturer)->setName('ESS Technology'));
		$manager->persist($eltec = (new Manufacturer)->setName('Electronic Technology'));
		$manager->persist($exar = (new Manufacturer)->setName('EXAR'));
		$manager->persist($excel = (new Manufacturer)->setName('Excel Semiconductor Inc.'));
		$manager->persist($fairschild = (new Manufacturer)->setName('Fairschild'));
		$manager->persist($freescale = (new Manufacturer)->setName('Freescale Semiconductor'));
		$manager->persist($fujitsu = (new Manufacturer)->setName('Fujitsu'));
		$manager->persist($galileo = (new Manufacturer)->setName('Galileo Technology'));
		$manager->persist($galvan = (new Manufacturer)->setName('Galvantech'));
		$manager->persist($gec = (new Manufacturer)->setName('GEC Plessey'));
		$manager->persist($gennum = (new Manufacturer)->setName('Gennum'));
		$manager->persist($general = (new Manufacturer)->setName('General Electric (Harris)'));
		$manager->persist($genins = (new Manufacturer)->setName('General Instruments'));
		$manager->persist($glink = (new Manufacturer)->setName('G-Link Technology'));
		$manager->persist($goal = (new Manufacturer)->setName('Goal Semiconductor'));
		$manager->persist($goldstar = (new Manufacturer)->setName('Goldstar'));
		$manager->persist($gould = (new Manufacturer)->setName('Gould'));
		$manager->persist($greenw = (new Manufacturer)->setName('Greenwich Instruments'));
		$manager->persist($gensemi = (new Manufacturer)->setName('General Semiconductor'));
		$manager->persist($harris = (new Manufacturer)->setName('Harris Semiconductor'));
		$manager->persist($veb = (new Manufacturer)->setName('VEB'));
		$manager->persist($hitachi = (new Manufacturer)->setName('Hitachi Semiconductor'));
		$manager->persist($holtek = (new Manufacturer)->setName('Holtek'));
		$manager->persist($hp = (new Manufacturer)->setName('Hewlett Packard'));
		$manager->persist($hualon = (new Manufacturer)->setName('Hualon'));
		$manager->persist($hynix = (new Manufacturer)->setName('Hynix Semiconductor'));
		$manager->persist($hyundai = (new Manufacturer)->setName('Hyundai'));
		$manager->persist($icd = (new Manufacturer)->setName('IC Design'));
		$manager->persist($ics = (new Manufacturer)->setName('Integrated Circuit Systems (ICS)'));
		$manager->persist($ich = (new Manufacturer)->setName('IC - Haus'));
		$manager->persist($icsi = (new Manufacturer)->setName('ICSI (Integrated Circuit Solution Inc.)'));
		$manager->persist($icube = (new Manufacturer)->setName('I-Cube'));
		$manager->persist($icw = (new Manufacturer)->setName('IC Works'));
		$manager->persist($idt = (new Manufacturer)->setName('Integrated Device Technology (IDT)'));
		$manager->persist($igs = (new Manufacturer)->setName('IGS Technologies'));
		$manager->persist($impala = (new Manufacturer)->setName('IMPALA Linear'));
		$manager->persist($imp = (new Manufacturer)->setName('IMP'));
		$manager->persist($infineon = (new Manufacturer)->setName('Infineon'));
		$manager->persist($inmos = (new Manufacturer)->setName('INMOS'));
		$manager->persist($intel = (new Manufacturer)->setName('Intel'));
		$manager->persist($intersil = (new Manufacturer)->setName('Intersil'));
		$manager->persist($intrect = (new Manufacturer)->setName('International Rectifier'));
		$manager->persist($isd = (new Manufacturer)->setName('Information Storage Devices'));
		$manager->persist($issi = (new Manufacturer)->setName('ISSI (Integrated Silicon Solution, Inc.)'));
		$manager->persist($ite = (new Manufacturer)->setName('Integrated Technology Express'));
		$manager->persist($itt = (new Manufacturer)->setName('ITT Semiconductor (Micronas Intermetall)'));
		$manager->persist($ixys = (new Manufacturer)->setName('IXYS'));
		$manager->persist($kec = (new Manufacturer)->setName('Korea Electronics (KEC)'));
		$manager->persist($kota = (new Manufacturer)->setName('Kota Microcircuits'));
		$manager->persist($lattice = (new Manufacturer)->setName('Lattice Semiconductor Corp.'));
		$manager->persist($lansdale = (new Manufacturer)->setName('Lansdale Semiconductor'));
		$manager->persist($l1 = (new Manufacturer)->setName('Level One Communications'));
		$manager->persist($lgsemi = (new Manufacturer)->setName('LG Semicon (Lucky Goldstar Electronic Co.)'));
		$manager->persist($lintec = (new Manufacturer)->setName('Linear Technology'));
		$manager->persist($linfinity = (new Manufacturer)->setName('Linfinity Microelectronics'));
		$manager->persist($liteon = (new Manufacturer)->setName('Lite-On'));
		$manager->persist($lucent = (new Manufacturer)->setName('Lucent Technologies (AT&T Microelectronics)'));
		$manager->persist($macronix = (new Manufacturer)->setName('Macronix International'));
		$manager->persist($marvell = (new Manufacturer)->setName('Marvell Semiconductor'));
		$manager->persist($matsu = (new Manufacturer)->setName('Matsushita Panasonic'));
		$manager->persist($maxim = (new Manufacturer)->setName('Maxim Dallas'));
		$manager->persist($medvis = (new Manufacturer)->setName('Media Vision'));
		$manager->persist($microchip = (new Manufacturer)->setName('Microchip (Arizona Michrochip Technology)'));
		$manager->persist($matra = (new Manufacturer)->setName('Matra MHS'));
		$manager->persist($micrel = (new Manufacturer)->setName('Micrel Semiconductor'));
		$manager->persist($micronas = (new Manufacturer)->setName('Micronas'));
		$manager->persist($micronix = (new Manufacturer)->setName('Micronix Integrated Systems'));
		$manager->persist($micron = (new Manufacturer)->setName('Micron Technology, Inc.'));
		$manager->persist($microsemi = (new Manufacturer)->setName('Microsemi'));
		$manager->persist($minicirc = (new Manufacturer)->setName('Mini-Circuits'));
		$manager->persist($mitel = (new Manufacturer)->setName('Mitel Semiconductor'));
		$manager->persist($mitsubishi = (new Manufacturer)->setName('Mitsubishi Semiconductor'));
		$manager->persist($microlin = (new Manufacturer)->setName('Micro Linear'));
		$manager->persist($mmi = (new Manufacturer)->setName('MMI (Monolithic Memories, Inc.)'));
		$manager->persist($mosaic = (new Manufacturer)->setName('Mosaic Semiconductor'));
		$manager->persist($mosel = (new Manufacturer)->setName('Mosel Vitelic'));
		$manager->persist($mostec = (new Manufacturer)->setName('MOS Technologies'));
		$manager->persist($mostek = (new Manufacturer)->setName('Mostek'));
		$manager->persist($mosys = (new Manufacturer)->setName('MoSys'));
		$manager->persist($motorola = (new Manufacturer)->setName('Motorola'));
		$manager->persist($microtune = (new Manufacturer)->setName('Microtune'));
		$manager->persist($msys = (new Manufacturer)->setName('M-Systems'));
		$manager->persist($murata = (new Manufacturer)->setName('Murata Manufacturing'));
		$manager->persist($mwave = (new Manufacturer)->setName('MWave (IBM)'));
		$manager->persist($myson = (new Manufacturer)->setName('Myson Technology'));
		$manager->persist($nec = (new Manufacturer)->setName('NEC Electronics'));
		$manager->persist($nexfl = (new Manufacturer)->setName('NexFlash Technologies'));
		$manager->persist($njr = (new Manufacturer)->setName('New Japan Radio'));
		$manager->persist($natsemi = (new Manufacturer)->setName('National Semiconductor'));
		$manager->persist($nvidia = (new Manufacturer)->setName('NVidia Corporation'));
		$manager->persist($oak = (new Manufacturer)->setName('Oak Technology'));
		$manager->persist($oki = (new Manufacturer)->setName('Oki Semiconductor'));
		$manager->persist($opti = (new Manufacturer)->setName('Opti'));
		$manager->persist($orbit = (new Manufacturer)->setName('Orbit Semiconductor'));
		$manager->persist($oren = (new Manufacturer)->setName('Oren Semiconductor'));
		$manager->persist($perfsemi = (new Manufacturer)->setName('Performance Semiconductor'));
		$manager->persist($persemi = (new Manufacturer)->setName('Pericom Semiconductor'));
		$manager->persist($phaselink = (new Manufacturer)->setName('PhaseLink Laboratories'));
		$manager->persist($philips = (new Manufacturer)->setName('Philips Semiconductor'));
		$manager->persist($plx = (new Manufacturer)->setName('PLX Technology'));
		$manager->persist($pmc = (new Manufacturer)->setName('PMC- Sierra'));
		$manager->persist($precis = (new Manufacturer)->setName('Precision Monolithics'));
		$manager->persist($princeton = (new Manufacturer)->setName('Princeton Technology'));
		$manager->persist($powersmart = (new Manufacturer)->setName('PowerSmart'));
		$manager->persist($quickl = (new Manufacturer)->setName('QuickLogic'));
		$manager->persist($qlopgic = (new Manufacturer)->setName('Qlogic'));
		$manager->persist($qsemi = (new Manufacturer)->setName('Quality Semiconductor'));
		$manager->persist($rabbit = (new Manufacturer)->setName('Rabbit Semiconductor'));
		$manager->persist($ramtron = (new Manufacturer)->setName('Ramtron International Co.'));
		$manager->persist($raytheon = (new Manufacturer)->setName('Raytheon Semiconductor'));
		$manager->persist($rca = (new Manufacturer)->setName('RCA Solid State'));
		$manager->persist($realtek = (new Manufacturer)->setName('Realtek Semiconductor'));
		$manager->persist($rectron = (new Manufacturer)->setName('Rectron'));
		$manager->persist($rendition = (new Manufacturer)->setName('Rendition'));
		$manager->persist($renesas = (new Manufacturer)->setName('Renesas Technology'));
		$manager->persist($rockwell = (new Manufacturer)->setName('Rockwell'));
		$manager->persist($rohm = (new Manufacturer)->setName('Rohm Corp.'));
		$manager->persist($s3 = (new Manufacturer)->setName('S3'));
		$manager->persist($sage = (new Manufacturer)->setName('Sage'));
		$manager->persist($saifun = (new Manufacturer)->setName('Saifun Semiconductors Ltd.'));
		$manager->persist($sames = (new Manufacturer)->setName('Sames'));
		$manager->persist($samsung = (new Manufacturer)->setName('Samsung'));
		$manager->persist($sanken = (new Manufacturer)->setName('Sanken'));
		$manager->persist($sanyo = (new Manufacturer)->setName('Sanyo'));
		$manager->persist($scenix = (new Manufacturer)->setName('Scenix'));
		$manager->persist($samele = (new Manufacturer)->setName('Samsung Electronics'));
		$manager->persist($seeq = (new Manufacturer)->setName('SEEQ Technology'));
		$manager->persist($seiko = (new Manufacturer)->setName('Seiko Instruments'));
		$manager->persist($semtech = (new Manufacturer)->setName('Semtech'));
		$manager->persist($sgsa = (new Manufacturer)->setName('SGS-Ates'));
		$manager->persist($sgst = (new Manufacturer)->setName('SGS-Thomson Microelectonics ST-M)'));
		$manager->persist($sharp = (new Manufacturer)->setName('Sharp Microelectronics (USA)'));
		$manager->persist($shindengen = (new Manufacturer)->setName('Shindengen'));
		$manager->persist($siemens = (new Manufacturer)->setName('Siemens Microelectronics, Inc.'));
		$manager->persist($sierra = (new Manufacturer)->setName('Sierra'));
		$manager->persist($sigma = (new Manufacturer)->setName('Sigma Tel'));
		$manager->persist($signetics = (new Manufacturer)->setName('Signetics'));
		$manager->persist($siliconlab = (new Manufacturer)->setName('Silicon Laboratories'));
		$manager->persist($siliconm = (new Manufacturer)->setName('Silicon Magic'));
		$manager->persist($simtec = (new Manufacturer)->setName('Simtec Corp.'));
		$manager->persist($siliconix = (new Manufacturer)->setName('Siliconix'));
		$manager->persist($siliconians = (new Manufacturer)->setName('Siliconians'));
		$manager->persist($sipex = (new Manufacturer)->setName('Sipex'));
		$manager->persist($sis = (new Manufacturer)->setName('Silicon Integrated Systems'));
		$manager->persist($smc = (new Manufacturer)->setName('SMC'));
		$manager->persist($stdmicro = (new Manufacturer)->setName('Standard Microsystems'));
		$manager->persist($sony = (new Manufacturer)->setName('Sony Semiconductor'));
		$manager->persist($space = (new Manufacturer)->setName('Space Electronics'));
		$manager->persist($spectek = (new Manufacturer)->setName('Spectek'));
		$manager->persist($spt = (new Manufacturer)->setName('Signal Processing Technologies'));
		$manager->persist($sss = (new Manufacturer)->setName('Solid State Scientific'));
		$manager->persist($sst = (new Manufacturer)->setName('Silicon Storage Technology (SST)'));
		$manager->persist($stmicro = (new Manufacturer)->setName('STMicroelectronics'));
		$manager->persist($summit = (new Manufacturer)->setName('SUMMIT Microelectronics'));
		$manager->persist($synergy = (new Manufacturer)->setName('Synergy Semiconductor'));
		$manager->persist($synertek = (new Manufacturer)->setName('Synertek'));
		$manager->persist($tsmc = (new Manufacturer)->setName('Taiwan Semiconductor'));
		$manager->persist($tdk = (new Manufacturer)->setName('TDK Semiconductor'));
		$manager->persist($teccor = (new Manufacturer)->setName('Teccor Electronics'));
		$manager->persist($telcom = (new Manufacturer)->setName('TelCom Semiconductor'));
		$manager->persist($teledyne = (new Manufacturer)->setName('Teledyne'));
		$manager->persist($telefunken = (new Manufacturer)->setName('Telefunken'));
		$manager->persist($teltone = (new Manufacturer)->setName('Teltone'));
		$manager->persist($thomson = (new Manufacturer)->setName('Thomson-CSF'));
		$manager->persist($ti = (new Manufacturer)->setName('Texas Instruments'));
		$manager->persist($toko = (new Manufacturer)->setName('Toko Amerika'));
		$manager->persist($toshiba = (new Manufacturer)->setName('Toshiba (US)'));
		$manager->persist($trident = (new Manufacturer)->setName('Trident'));
		$manager->persist($triquint = (new Manufacturer)->setName('TriQuint Semiconductor'));
		$manager->persist($triscend = (new Manufacturer)->setName('Triscend'));
		$manager->persist($tseng = (new Manufacturer)->setName('Tseng Labs'));
		$manager->persist($tundra = (new Manufacturer)->setName('Tundra'));
		$manager->persist($turbo = (new Manufacturer)->setName('Turbo IC'));
		$manager->persist($ubicom = (new Manufacturer)->setName('Ubicom'));
		$manager->persist($umc = (new Manufacturer)->setName('United Microelectronics Corp (UMC)'));
		$manager->persist($unitrode = (new Manufacturer)->setName('Unitrode'));
		$manager->persist($usar = (new Manufacturer)->setName('USAR Systems'));
		$manager->persist($utmc = (new Manufacturer)->setName('United Technologies Microelectronics Center (UTMC)'));
		$manager->persist($utron = (new Manufacturer)->setName('Utron'));
		$manager->persist($v3 = (new Manufacturer)->setName('V3 Semiconductor'));
		$manager->persist($vadem = (new Manufacturer)->setName('Vadem'));
		$manager->persist($vis = (new Manufacturer)->setName('Vanguard International Semiconductor'));
		$manager->persist($vantis = (new Manufacturer)->setName('Vantis'));
		$manager->persist($via = (new Manufacturer)->setName('Via Technologies'));
		$manager->persist($virata = (new Manufacturer)->setName('Virata'));
		$manager->persist($vishay = (new Manufacturer)->setName('Vishay'));
		$manager->persist($vistec = (new Manufacturer)->setName('Vision Tech'));
		$manager->persist($vitelic = (new Manufacturer)->setName('Vitelic'));
		$manager->persist($vlsi = (new Manufacturer)->setName('VLSI Technology'));
		$manager->persist($volterra = (new Manufacturer)->setName('Volterra'));
		$manager->persist($vtc = (new Manufacturer)->setName('VTC'));
		$manager->persist($wsi = (new Manufacturer)->setName('Waferscale Integration (WSI)'));
		$manager->persist($wd = (new Manufacturer)->setName('Western Digital'));
		$manager->persist($weitek = (new Manufacturer)->setName('Weitek'));
		$manager->persist($winbond = (new Manufacturer)->setName('Winbond'));
		$manager->persist($wofson = (new Manufacturer)->setName('Wofson Microelectronics'));
		$manager->persist($xwmics = (new Manufacturer)->setName('Xwmics'));
		$manager->persist($xicor = (new Manufacturer)->setName('Xicor'));
		$manager->persist($xilinx = (new Manufacturer)->setName('Xilinx'));
		$manager->persist($yamaha = (new Manufacturer)->setName('Yamaha'));
		$manager->persist($zetex = (new Manufacturer)->setName('Zetex Semiconductors'));
		$manager->persist($zilog = (new Manufacturer)->setName('Zilog'));
		$manager->persist($zmd = (new Manufacturer)->setName('ZMD (Zentrum Mikroelektronik Dresden)'));
		$manager->persist($zoran = (new Manufacturer)->setName('Zoran'));

		$manager->persist((new ManufacturerICLogo)->setManufacturer($icd)->setFilename('1bf234c2-517c-11ea-b35d-fce0933e9505')->setOriginalFilename('acer.png')->setMimetype('image/png')->setSize(2195)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($actel)->setFilename('d18c2920-e287-11ec-9f7c-18c04d8905ca')->setOriginalFilename('actel.png')->setMimetype('image/png')->setSize(5003)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($altinc)->setFilename('1bf2cacc-517c-11ea-9c25-20eb09bb4805')->setOriginalFilename('advldev.png')->setMimetype('image/png')->setSize(1835)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($aeroflex)->setFilename('1bf2e55c-517c-11ea-8e4a-d5b5d8376133')->setOriginalFilename('aeroflex1.png')->setMimetype('image/png')->setSize(9649)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($aeroflex)->setFilename('1bf2f3a8-517c-11ea-9fc3-e855a641c013')->setOriginalFilename('aeroflex2.png')->setMimetype('image/png')->setSize(4562)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($agilent)->setFilename('d18cdb86-e287-11ec-95d6-18c04d8905ca')->setOriginalFilename('agilent.png')->setMimetype('image/png')->setSize(5264)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($akm)->setFilename('1bf32e22-517c-11ea-ae4b-31942bf0a604')->setOriginalFilename('akm.png')->setMimetype('image/png')->setSize(2204)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($alesis)->setFilename('1bf34a60-517c-11ea-a1a2-bf3e0c5eb129')->setOriginalFilename('alesis.png')->setMimetype('image/png')->setSize(1475)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ali)->setFilename('1bf36504-517c-11ea-b942-de884c035401')->setOriginalFilename('ali1.png')->setMimetype('image/png')->setSize(2462)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ali)->setFilename('1bf37062-517c-11ea-a769-94c5b8b16606')->setOriginalFilename('ali2.png')->setMimetype('image/png')->setSize(1784)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($allayer)->setFilename('1bf38a84-517c-11ea-b777-febc878c93b9')->setOriginalFilename('allayer.png')->setMimetype('image/png')->setSize(1869)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($allegro)->setFilename('1bf3a4f6-517c-11ea-9354-9dfe667f3ffb')->setOriginalFilename('allegro.png')->setMimetype('image/png')->setSize(1475)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($alliance)->setFilename('1bf3bf68-517c-11ea-94ca-3bee1054a469')->setOriginalFilename('alliance.png')->setMimetype('image/png')->setSize(1949)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($alpha)->setFilename('1bf3d9d0-517c-11ea-96c3-2f286e2c43a0')->setOriginalFilename('alphaind.png')->setMimetype('image/png')->setSize(1403)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($alphamic)->setFilename('1bf3f334-517c-11ea-af41-fdd860f3be55')->setOriginalFilename('alphamic.png')->setMimetype('image/png')->setSize(2989)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($alphamic)->setFilename('1bf3fe4c-517c-11ea-a72b-0068adddb063')->setOriginalFilename('alpha.png')->setMimetype('image/png')->setSize(1534)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($altera)->setFilename('d18df3fe-e287-11ec-a279-18c04d8905ca')->setOriginalFilename('altera.png')->setMimetype('image/png')->setSize(4064)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($amd)->setFilename('1bf4342a-517c-11ea-9979-9b3ed7ceef28')->setOriginalFilename('amd.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ami)->setFilename('1bf44e10-517c-11ea-b3ce-a5475ba9f143')->setOriginalFilename('ami1.png')->setMimetype('image/png')->setSize(2399)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ami)->setFilename('1bf458b0-517c-11ea-ac44-fcd1f1523dfe')->setOriginalFilename('ami2.png')->setMimetype('image/png')->setSize(1706)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($amic)->setFilename('1bf46fda-517c-11ea-bdf4-c5c1170ce7c9')->setOriginalFilename('amic.png')->setMimetype('image/png')->setSize(2228)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($amphus)->setFilename('d18e6dac-e287-11ec-bc20-18c04d8905ca')->setOriginalFilename('ampus.png')->setMimetype('image/png')->setSize(6150)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($anachip)->setFilename('1bf4a270-517c-11ea-86c5-542d43a8d1ec')->setOriginalFilename('anachip.png')->setMimetype('image/png')->setSize(3549)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($anadigic)->setFilename('d18ea358-e287-11ec-9ae1-18c04d8905ca')->setOriginalFilename('anadigic.png')->setMimetype('image/png')->setSize(5147)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($analog)->setFilename('1bf4d650-517c-11ea-b630-b2bc58c88656')->setOriginalFilename('analog1.png')->setMimetype('image/png')->setSize(1262)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($analog)->setFilename('1bf4e19a-517c-11ea-842a-b91a0a77f6cd')->setOriginalFilename('analog.png')->setMimetype('image/png')->setSize(1403)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($anasys)->setFilename('1bf4fa68-517c-11ea-a4a0-a9084394413c')->setOriginalFilename('anasys.png')->setMimetype('image/png')->setSize(3309)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($anchor)->setFilename('1bf512fa-517c-11ea-bffc-0c69b625faa2')->setOriginalFilename('anchorch.png')->setMimetype('image/png')->setSize(1475)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($apex)->setFilename('1bf52cc2-517c-11ea-b02c-919367aa0ba2')->setOriginalFilename('apex1.png')->setMimetype('image/png')->setSize(2627)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($apex)->setFilename('1bf53834-517c-11ea-8391-de106a74aba7')->setOriginalFilename('apex.png')->setMimetype('image/png')->setSize(3974)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ark)->setFilename('1bf54f40-517c-11ea-8d3c-f7c4839de3bb')->setOriginalFilename('ark.png')->setMimetype('image/png')->setSize(2089)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($asd)->setFilename('1bf5670a-517c-11ea-b2df-82cfd5ece312')->setOriginalFilename('asd.png')->setMimetype('image/png')->setSize(5024)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($astec)->setFilename('1bf5856e-517c-11ea-a6ab-95387f5ec787')->setOriginalFilename('astec.png')->setMimetype('image/png')->setSize(3369)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($atc)->setFilename('d18f8ee4-e287-11ec-a7c1-18c04d8905ca')->setOriginalFilename('atc.png')->setMimetype('image/png')->setSize(8660)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($atecom)->setFilename('1bf5bd4a-517c-11ea-8fee-4e86d0972f53')->setOriginalFilename('atecom.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ati)->setFilename('d18fc4f4-e287-11ec-ba5a-18c04d8905ca')->setOriginalFilename('ati.png')->setMimetype('image/png')->setSize(2630)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($atmel)->setFilename('d18fe038-e287-11ec-84df-18c04d8905ca')->setOriginalFilename('atmel.png')->setMimetype('image/png')->setSize(2843)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($att)->setFilename('1bf609a8-517c-11ea-85c8-138b50f5d7b5')->setOriginalFilename('att.png')->setMimetype('image/png')->setSize(2816)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($audiocod)->setFilename('1bf621b8-517c-11ea-bfd4-f2c4d3a50749')->setOriginalFilename('audiocod.png')->setMimetype('image/png')->setSize(2429)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($aura)->setFilename('1bf63c0c-517c-11ea-8cf1-c65671cbf54d')->setOriginalFilename('auravis.png')->setMimetype('image/png')->setSize(2281)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($aureal)->setFilename('1bf6576e-517c-11ea-bd4f-717aa432e8e7')->setOriginalFilename('aureal.png')->setMimetype('image/png')->setSize(2109)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($austin)->setFilename('1bf670b4-517c-11ea-b4c9-1b53837dd170')->setOriginalFilename('austin.png')->setMimetype('image/png')->setSize(2464)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($avance)->setFilename('1bf688ec-517c-11ea-9657-24b12d0441e7')->setOriginalFilename('averlog.png')->setMimetype('image/png')->setSize(1552)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($bel)->setFilename('1bf6a02a-517c-11ea-85e6-0377c723dd77')->setOriginalFilename('belfuse.png')->setMimetype('image/png')->setSize(2204)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($benchmarq)->setFilename('1bf6b86c-517c-11ea-8f7e-019a2d142c32')->setOriginalFilename('benchmrq.png')->setMimetype('image/png')->setSize(1370)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($bitec)->setFilename('1bf6ceec-517c-11ea-ba79-6d72a2c4f46d')->setOriginalFilename('bi.png')->setMimetype('image/png')->setSize(2008)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($bowmar)->setFilename('1bf6e512-517c-11ea-b0bc-6d136df08bae')->setOriginalFilename('bowmar_white.png')->setMimetype('image/png')->setSize(4652)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($brightflash)->setFilename('1bf70092-517c-11ea-9e22-bbd284f7649d')->setOriginalFilename('bright.png')->setMimetype('image/png')->setSize(6839)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($broadcom)->setFilename('d1911fa2-e287-11ec-abe0-18c04d8905ca')->setOriginalFilename('broadcom.png')->setMimetype('image/png')->setSize(6056)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($brooktree)->setFilename('1bf73832-517c-11ea-97cb-b942028e671a')->setOriginalFilename('brooktre.png')->setMimetype('image/png')->setSize(1364)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($burr)->setFilename('1bf75362-517c-11ea-bc6f-80f3acb4a325')->setOriginalFilename('burrbrwn.png')->setMimetype('image/png')->setSize(3563)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($cmd)->setFilename('1bf76e24-517c-11ea-854f-87b093c998fa')->setOriginalFilename('calmicro.png')->setMimetype('image/png')->setSize(2109)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($calogic)->setFilename('1bf78738-517c-11ea-81ff-41ad2951bde7')->setOriginalFilename('calogic.png')->setMimetype('image/png')->setSize(3367)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($catalyst)->setFilename('1bf79e8a-517c-11ea-bffe-b984ab8fdb7f')->setOriginalFilename('catalys1.png')->setMimetype('image/png')->setSize(1922)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($catalyst)->setFilename('1bf7a7d6-517c-11ea-ab45-005d289b56bb')->setOriginalFilename('catalyst.png')->setMimetype('image/png')->setSize(2228)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($centon)->setFilename('1bf7c1bc-517c-11ea-b821-59ea3b1ac07a')->setOriginalFilename('ccube.png')->setMimetype('image/png')->setSize(1309)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ceramate)->setFilename('1bf7d9e0-517c-11ea-809d-d3f3ae05ccb5')->setOriginalFilename('ceramate1.png')->setMimetype('image/png')->setSize(2917)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ceramate)->setFilename('1bf7e3e0-517c-11ea-8642-b964a9e4a334')->setOriginalFilename('ceramate2.png')->setMimetype('image/png')->setSize(2917)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($cherry)->setFilename('1bf7f9d4-517c-11ea-ba22-63cef6e34ab7')->setOriginalFilename('cherry.png')->setMimetype('image/png')->setSize(2507)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($chipcon)->setFilename('1bf80ed8-517c-11ea-b815-7ed2d2d5d78d')->setOriginalFilename('chipcon1.png')->setMimetype('image/png')->setSize(8655)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($chipcon)->setFilename('1bf818d8-517c-11ea-b5b9-d2874e1b0ff8')->setOriginalFilename('chipcon2.png')->setMimetype('image/png')->setSize(2923)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($chips)->setFilename('1bf82ea4-517c-11ea-aa02-39e47783e38a')->setOriginalFilename('chips.png')->setMimetype('image/png')->setSize(2864)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($chrontel)->setFilename('1bf84394-517c-11ea-b098-e8e85c3d4206')->setOriginalFilename('chrontel.png')->setMimetype('image/png')->setSize(1476)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($cirrus)->setFilename('1bf85b7c-517c-11ea-b616-47df838cf0dc')->setOriginalFilename('cirrus.png')->setMimetype('image/png')->setSize(3218)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($comcore)->setFilename('1bf87472-517c-11ea-b4fb-6fe8a64387ec')->setOriginalFilename('comcore.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($conexant)->setFilename('1bf88d18-517c-11ea-bcff-e2bf3e4e7649')->setOriginalFilename('conexant.png')->setMimetype('image/png')->setSize(2051)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($cosmo)->setFilename('1bf8a44c-517c-11ea-977e-4d78192c3092')->setOriginalFilename('cosmo.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($chrystal)->setFilename('1bf8bab8-517c-11ea-ab3e-4b8a8705546f')->setOriginalFilename('crystal.png')->setMimetype('image/png')->setSize(3605)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($cygnal)->setFilename('1bf8d30e-517c-11ea-abba-3238b2c0ea75')->setOriginalFilename('cygnal.png')->setMimetype('image/png')->setSize(2135)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($cypress)->setFilename('1bf8ea60-517c-11ea-8c30-e6807cbb2737')->setOriginalFilename('cypres1.png')->setMimetype('image/png')->setSize(2504)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($cypress)->setFilename('1bf8f334-517c-11ea-a2e0-d749c5031ab2')->setOriginalFilename('cypress.png')->setMimetype('image/png')->setSize(4275)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($cyrix)->setFilename('d1934a20-e287-11ec-bace-18c04d8905ca')->setOriginalFilename('cyrix.png')->setMimetype('image/png')->setSize(2204)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($daewoo)->setFilename('1bf92890-517c-11ea-97d1-5da2675827f1')->setOriginalFilename('daewoo.png')->setMimetype('image/png')->setSize(1907)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($dallas)->setFilename('1bf94186-517c-11ea-86dc-26d3edd8f145')->setOriginalFilename('dallas1.png')->setMimetype('image/png')->setSize(1469)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($dallas)->setFilename('1bf94ca8-517c-11ea-9806-96823e02d5ed')->setOriginalFilename('dallas2.png')->setMimetype('image/png')->setSize(1309)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($dallas)->setFilename('1bf957c0-517c-11ea-b634-55cc3b2db562')->setOriginalFilename('dallas3.png')->setMimetype('image/png')->setSize(1869)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($davicom)->setFilename('1bf97336-517c-11ea-9c7e-80afc2c87a7e')->setOriginalFilename('davicom.png')->setMimetype('image/png')->setSize(4589)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ddd)->setFilename('1bf98c5e-517c-11ea-abff-9a621015476e')->setOriginalFilename('ddd.png')->setMimetype('image/png')->setSize(3235)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($diamond)->setFilename('1bf9a662-517c-11ea-acf5-db688dccdf07')->setOriginalFilename('diamond.png')->setMimetype('image/png')->setSize(2504)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($diotec)->setFilename('1bf9c14c-517c-11ea-808e-c282bbc876ad')->setOriginalFilename('diotec.png')->setMimetype('image/png')->setSize(1454)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($dtc)->setFilename('1bf9dbaa-517c-11ea-b367-dd80173d488e')->setOriginalFilename('dtc1.png')->setMimetype('image/png')->setSize(2513)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($dtc)->setFilename('1bf9e712-517c-11ea-85ea-a6558bee54c0')->setOriginalFilename('dtc2.png')->setMimetype('image/png')->setSize(1670)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($dvdo)->setFilename('d1944a88-e287-11ec-90da-18c04d8905ca')->setOriginalFilename('dvdo.png')->setMimetype('image/png')->setSize(2357)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($egg)->setFilename('1bfa1c14-517c-11ea-b4dc-9e4265231d64')->setOriginalFilename('egg.png')->setMimetype('image/png')->setSize(1628)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($elan)->setFilename('1bfa330c-517c-11ea-8724-27b7465727ee')->setOriginalFilename('elan.png')->setMimetype('image/png')->setSize(13826)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($elantec)->setFilename('1bfa50b2-517c-11ea-83be-8f0b837351cd')->setOriginalFilename('elantec1.png')->setMimetype('image/png')->setSize(1400)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($elantec)->setFilename('1bfa5a26-517c-11ea-81a9-d5d926ac5e69')->setOriginalFilename('elantec.png')->setMimetype('image/png')->setSize(3274)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($elarrays)->setFilename('1bfa7524-517c-11ea-b662-f101388794c0')->setOriginalFilename('elec_arrays.png')->setMimetype('image/png')->setSize(5602)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($efst)->setFilename('1bfa902c-517c-11ea-b316-3cd0b135afd8')->setOriginalFilename('elite[1].png')->setMimetype('image/png')->setSize(8285)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($emm)->setFilename('1bfaab8e-517c-11ea-b19b-57bae656542c')->setOriginalFilename('emmicro.png')->setMimetype('image/png')->setSize(3599)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ems)->setFilename('1bfac6a0-517c-11ea-a1c3-79047a53283d')->setOriginalFilename('enhmemsy.png')->setMimetype('image/png')->setSize(1403)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ensoniq)->setFilename('d19590be-e287-11ec-a463-18c04d8905ca')->setOriginalFilename('ensoniq.png')->setMimetype('image/png')->setSize(3557)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($eon)->setFilename('d195aa36-e287-11ec-acb1-18c04d8905ca')->setOriginalFilename('eon.png')->setMimetype('image/png')->setSize(5393)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($epson)->setFilename('1bfb15a6-517c-11ea-902d-29e6ae671f9a')->setOriginalFilename('epson1.png')->setMimetype('image/png')->setSize(2349)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($epson)->setFilename('1bfb1fc4-517c-11ea-8341-8ef9a22eefca')->setOriginalFilename('epson2.png')->setMimetype('image/png')->setSize(2405)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ericsson)->setFilename('d195e8a2-e287-11ec-bcbe-18c04d8905ca')->setOriginalFilename('ericsson.png')->setMimetype('image/png')->setSize(4184)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ess)->setFilename('1bfb4a58-517c-11ea-ae94-4395a635356d')->setOriginalFilename('ess.png')->setMimetype('image/png')->setSize(3030)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($eltec)->setFilename('1bfb63da-517c-11ea-8083-9c1de13bafdf')->setOriginalFilename('etc.png')->setMimetype('image/png')->setSize(2189)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($exar)->setFilename('1bfb7c30-517c-11ea-bf37-f1c1e1ea7e18')->setOriginalFilename('exar.png')->setMimetype('image/png')->setSize(2771)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($excel)->setFilename('1bfb968e-517c-11ea-86dd-eaf0052b7e7b')->setOriginalFilename('excelsemi1.png')->setMimetype('image/png')->setSize(7632)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($excel)->setFilename('1bfba174-517c-11ea-952a-b55ebce4fb74')->setOriginalFilename('excelsemi2.png')->setMimetype('image/png')->setSize(2339)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($excel)->setFilename('1bfbacaa-517c-11ea-be29-1685ece46cba')->setOriginalFilename('exel.png')->setMimetype('image/png')->setSize(2771)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($fairschild)->setFilename('1bfbc5fa-517c-11ea-959d-5c319a41f521')->setOriginalFilename('fairchil.png')->setMimetype('image/png')->setSize(1552)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($freescale)->setFilename('1bfbe0c6-517c-11ea-98db-05cfd383483e')->setOriginalFilename('freescale.png')->setMimetype('image/png')->setSize(3840)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($fujitsu)->setFilename('1bfbfbce-517c-11ea-9060-c6c9b647d7b4')->setOriginalFilename('fujielec.png')->setMimetype('image/png')->setSize(5048)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($fujitsu)->setFilename('1bfc0858-517c-11ea-95c2-055b6cb1116b')->setOriginalFilename('fujitsu2.png')->setMimetype('image/png')->setSize(1860)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($galileo)->setFilename('d196da14-e287-11ec-bd04-18c04d8905ca')->setOriginalFilename('galileo.png')->setMimetype('image/png')->setSize(3779)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($galvan)->setFilename('1bfc39f4-517c-11ea-a0d7-2359c3171477')->setOriginalFilename('galvant.png')->setMimetype('image/png')->setSize(2669)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($gec)->setFilename('1bfc548e-517c-11ea-9c95-c21253387d8e')->setOriginalFilename('gecples.png')->setMimetype('image/png')->setSize(2312)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($gennum)->setFilename('1bfc6f00-517c-11ea-afe0-b59f0d9790ee')->setOriginalFilename('gennum.png')->setMimetype('image/png')->setSize(2614)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($general)->setFilename('1bfc86a2-517c-11ea-85f3-9287d9be2d26')->setOriginalFilename('ge.png')->setMimetype('image/png')->setSize(2321)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($genins)->setFilename('1bfc9caa-517c-11ea-91c4-e5566819f23c')->setOriginalFilename('gi1.png')->setMimetype('image/png')->setSize(1385)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($genins)->setFilename('1bfca5ec-517c-11ea-9727-250bd1d8d03d')->setOriginalFilename('gi.png')->setMimetype('image/png')->setSize(1691)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($glink)->setFilename('1bfcbbe0-517c-11ea-98e6-b54f366c4785')->setOriginalFilename('glink.png')->setMimetype('image/png')->setSize(1706)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($goal)->setFilename('1bfcd15c-517c-11ea-8bbc-89e6e97c734b')->setOriginalFilename('goal1.png')->setMimetype('image/png')->setSize(9092)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($goal)->setFilename('1bfcdb8e-517c-11ea-83df-1bdb667d3784')->setOriginalFilename('goal2.png')->setMimetype('image/png')->setSize(9649)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($goldstar)->setFilename('1bfcf29a-517c-11ea-8dca-ce6803b0fa42')->setOriginalFilename('goldstar1.png')->setMimetype('image/png')->setSize(2923)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($goldstar)->setFilename('1bfcfba0-517c-11ea-9eb7-175b577ae714')->setOriginalFilename('goldstar2.png')->setMimetype('image/png')->setSize(11387)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($gould)->setFilename('1bfd13ba-517c-11ea-bf22-05493d42da08')->setOriginalFilename('gould.png')->setMimetype('image/png')->setSize(1549)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($greenw)->setFilename('d198054c-e287-11ec-bd70-18c04d8905ca')->setOriginalFilename('greenwich.png')->setMimetype('image/png')->setSize(9761)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($gensemi)->setFilename('1bfd4326-517c-11ea-99d4-d12f48b5586f')->setOriginalFilename('gsemi.png')->setMimetype('image/png')->setSize(1704)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($harris)->setFilename('1bfd5dc0-517c-11ea-b2c3-c45a7273a70f')->setOriginalFilename('harris1.png')->setMimetype('image/png')->setSize(1549)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($harris)->setFilename('1bfd69dc-517c-11ea-bd3b-940b5a317d89')->setOriginalFilename('harris2.png')->setMimetype('image/png')->setSize(1874)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($veb)->setFilename('1bfd8264-517c-11ea-a5f5-e8b2a83b95d8')->setOriginalFilename('hfo.png')->setMimetype('image/png')->setSize(1958)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($hitachi)->setFilename('1bfd9a6a-517c-11ea-acbc-5ab4fbe5088e')->setOriginalFilename('hitachi.png')->setMimetype('image/png')->setSize(2611)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($holtek)->setFilename('1bfdb572-517c-11ea-8e51-4a6a710fcb37')->setOriginalFilename('holtek.png')->setMimetype('image/png')->setSize(2160)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($hp)->setFilename('1bfdce2c-517c-11ea-ac3d-ae0eb8a2a8fb')->setOriginalFilename('hp.png')->setMimetype('image/png')->setSize(2464)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($hualon)->setFilename('1bfde70e-517c-11ea-9a54-5d0b880141fd')->setOriginalFilename('hualon.png')->setMimetype('image/png')->setSize(2864)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($hynix)->setFilename('d198e17e-e287-11ec-960e-18c04d8905ca')->setOriginalFilename('hynix.png')->setMimetype('image/png')->setSize(8444)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($hyundai)->setFilename('1bfe17ba-517c-11ea-bb15-5f7d54a0717c')->setOriginalFilename('hyundai2.png')->setMimetype('image/png')->setSize(2269)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($icd)->setFilename('d19915fe-e287-11ec-b980-18c04d8905ca')->setOriginalFilename('icdesign.png')->setMimetype('image/png')->setSize(3014)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ics)->setFilename('1bfe41d6-517c-11ea-a559-ed0946b2e069')->setOriginalFilename('icd.png')->setMimetype('image/png')->setSize(1641)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ics)->setFilename('1bfe4a14-517c-11ea-9d6e-07c45d9e3672')->setOriginalFilename('ics.png')->setMimetype('image/png')->setSize(2042)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ich)->setFilename('1bfe6044-517c-11ea-833e-8bcdac840021')->setOriginalFilename('ichaus1.png')->setMimetype('image/png')->setSize(3370)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ich)->setFilename('1bfe694a-517c-11ea-af58-e981d014fd42')->setOriginalFilename('ichaus.png')->setMimetype('image/png')->setSize(1552)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($icsi)->setFilename('d19978aa-e287-11ec-9100-18c04d8905ca')->setOriginalFilename('icsi.png')->setMimetype('image/png')->setSize(4049)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($icube)->setFilename('1bfe9618-517c-11ea-b4eb-0cee245bf283')->setOriginalFilename('icube.png')->setMimetype('image/png')->setSize(1629)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($icw)->setFilename('1bfeaba8-517c-11ea-af8d-a4e5813116a6')->setOriginalFilename('icworks.png')->setMimetype('image/png')->setSize(1874)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($idt)->setFilename('1bfec21e-517c-11ea-8bc8-30480dc1dea6')->setOriginalFilename('idt1.png')->setMimetype('image/png')->setSize(3995)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($idt)->setFilename('1bfecb6a-517c-11ea-b72b-e176777267ee')->setOriginalFilename('idt.png')->setMimetype('image/png')->setSize(1553)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($igs)->setFilename('1bfee122-517c-11ea-8530-17141168bd55')->setOriginalFilename('igstech.png')->setMimetype('image/png')->setSize(3832)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($impala)->setFilename('1bfef7ca-517c-11ea-ad5a-3ae067957707')->setOriginalFilename('impala.png')->setMimetype('image/png')->setSize(1628)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($imp)->setFilename('1bff0fda-517c-11ea-a958-35ac08edcac1')->setOriginalFilename('imp.png')->setMimetype('image/png')->setSize(2175)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($infineon)->setFilename('d19a39ac-e287-11ec-864d-18c04d8905ca')->setOriginalFilename('infineon.png')->setMimetype('image/png')->setSize(4511)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($inmos)->setFilename('1bff3de8-517c-11ea-a1ff-ed0fad37fd43')->setOriginalFilename('inmos.png')->setMimetype('image/png')->setSize(3365)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($intel)->setFilename('d19a6df0-e287-11ec-b453-18c04d8905ca')->setOriginalFilename('intel2.png')->setMimetype('image/png')->setSize(2010)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($intersil)->setFilename('1bff66e2-517c-11ea-947e-cdba52b90f3d')->setOriginalFilename('intresil4.png')->setMimetype('image/png')->setSize(2614)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($intersil)->setFilename('1bff6ebc-517c-11ea-8c01-ce54dfb0c7c2')->setOriginalFilename('intrsil1.png')->setMimetype('image/png')->setSize(1874)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($intersil)->setFilename('1bff76e6-517c-11ea-8bbf-709275240ff8')->setOriginalFilename('intrsil2.png')->setMimetype('image/png')->setSize(2520)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($intersil)->setFilename('1bff829e-517c-11ea-9baa-7e486e7dab62')->setOriginalFilename('intrsil3.png')->setMimetype('image/png')->setSize(3295)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($intrect)->setFilename('1bff9d7e-517c-11ea-a26b-0e60ade8fd3e')->setOriginalFilename('ir.png')->setMimetype('image/png')->setSize(2729)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($isd)->setFilename('1bffb642-517c-11ea-b506-d2fe916b8b2c')->setOriginalFilename('isd.png')->setMimetype('image/png')->setSize(2554)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($issi)->setFilename('d19af478-e287-11ec-af53-18c04d8905ca')->setOriginalFilename('issi.png')->setMimetype('image/png')->setSize(3030)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ite)->setFilename('d19b0f3a-e287-11ec-94f7-18c04d8905ca')->setOriginalFilename('ite.png')->setMimetype('image/png')->setSize(3302)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($itt)->setFilename('d19b2a24-e287-11ec-880b-18c04d8905ca')->setOriginalFilename('itt.png')->setMimetype('image/png')->setSize(2483)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ixys)->setFilename('d19b4428-e287-11ec-8ea4-18c04d8905ca')->setOriginalFilename('ixys.png')->setMimetype('image/png')->setSize(3575)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($kec)->setFilename('d19b5e90-e287-11ec-9aca-18c04d8905ca')->setOriginalFilename('kec.png')->setMimetype('image/png')->setSize(2567)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($kota)->setFilename('1c004e9a-517c-11ea-af88-922808e4b75b')->setOriginalFilename('kota.png')->setMimetype('image/png')->setSize(1552)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($lattice)->setFilename('1c006506-517c-11ea-afb6-4c51e1e4a5c3')->setOriginalFilename('lattice1.png')->setMimetype('image/png')->setSize(1768)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($lattice)->setFilename('1c006e66-517c-11ea-8e08-5462bf3c1594')->setOriginalFilename('lattice2.png')->setMimetype('image/png')->setSize(1519)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($lattice)->setFilename('1c007726-517c-11ea-ba4d-bdd30d05962b')->setOriginalFilename('lattice3.png')->setMimetype('image/png')->setSize(1216)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($lansdale)->setFilename('1c008efa-517c-11ea-a6eb-51e332125040')->setOriginalFilename('lds1.png')->setMimetype('image/png')->setSize(2136)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($lansdale)->setFilename('1c0099b8-517c-11ea-a9fd-4bbf82cc2483')->setOriginalFilename('lds.png')->setMimetype('image/png')->setSize(1959)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($l1)->setFilename('d19be5ea-e287-11ec-b924-18c04d8905ca')->setOriginalFilename('levone.png')->setMimetype('image/png')->setSize(4189)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($lgsemi)->setFilename('1c00c9ba-517c-11ea-839a-063da728a1c3')->setOriginalFilename('lgs1.png')->setMimetype('image/png')->setSize(2417)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($lgsemi)->setFilename('1c00d374-517c-11ea-85c2-7d6d32b4f3c3')->setOriginalFilename('lgs.png')->setMimetype('image/png')->setSize(737)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($lintec)->setFilename('1c00eda0-517c-11ea-afde-79daf9208531')->setOriginalFilename('linear.png')->setMimetype('image/png')->setSize(2486)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($linfinity)->setFilename('1c0106b4-517c-11ea-a202-fd908f7f22ee')->setOriginalFilename('linfin.png')->setMimetype('image/png')->setSize(4844)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($liteon)->setFilename('d19c5bd8-e287-11ec-a4bc-18c04d8905ca')->setOriginalFilename('liteon.png')->setMimetype('image/png')->setSize(2388)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($lucent)->setFilename('1c013b3e-517c-11ea-80a4-2aa3478966ea')->setOriginalFilename('lucent.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($macronix)->setFilename('1c015416-517c-11ea-bca4-909d0dbe703b')->setOriginalFilename('macronix.png')->setMimetype('image/png')->setSize(2324)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($marvell)->setFilename('1c016d48-517c-11ea-a239-810b41df7b97')->setOriginalFilename('marvell.png')->setMimetype('image/png')->setSize(3131)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($matsu)->setFilename('1c018724-517c-11ea-b67a-0ca1cc0f28c1')->setOriginalFilename('matsush1.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($matsu)->setFilename('1c019250-517c-11ea-b94a-353b6456b126')->setOriginalFilename('matsushi.png')->setMimetype('image/png')->setSize(2029)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($maxim)->setFilename('d19ceb66-e287-11ec-949a-18c04d8905ca')->setOriginalFilename('maxim.png')->setMimetype('image/png')->setSize(2690)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($medvis)->setFilename('1c01c068-517c-11ea-81e5-c24385c6515f')->setOriginalFilename('mediavi1.png')->setMimetype('image/png')->setSize(2189)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($medvis)->setFilename('1c01ca0e-517c-11ea-a5ac-f473ef1d085b')->setOriginalFilename('mediavi2.png')->setMimetype('image/png')->setSize(2487)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($microchip)->setFilename('1c01e386-517c-11ea-a622-c15bf4a48054')->setOriginalFilename('me.png')->setMimetype('image/png')->setSize(2411)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($microchip)->setFilename('1c01ef3e-517c-11ea-b1f1-76021c9e2dd9')->setOriginalFilename('microchp.png')->setMimetype('image/png')->setSize(2814)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($matra)->setFilename('1c0208e8-517c-11ea-aaf0-32487cc4aeea')->setOriginalFilename('mhs2.png')->setMimetype('image/png')->setSize(2036)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($matra)->setFilename('1c021414-517c-11ea-b3ed-c28eb6079886')->setOriginalFilename('mhs.png')->setMimetype('image/png')->setSize(1870)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($micrel)->setFilename('d19d7ffe-e287-11ec-beb8-18c04d8905ca')->setOriginalFilename('micrel1.png')->setMimetype('image/png')->setSize(9695)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($micrel)->setFilename('d19d768a-e287-11ec-b5e7-18c04d8905ca')->setOriginalFilename('micrel2.png')->setMimetype('image/png')->setSize(9695)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($micronas)->setFilename('1c024a4c-517c-11ea-9c27-906076b4ba39')->setOriginalFilename('micronas.png')->setMimetype('image/png')->setSize(1871)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($micronix)->setFilename('1c026216-517c-11ea-9e86-40aa7b23bf2e')->setOriginalFilename('micronix.png')->setMimetype('image/png')->setSize(1856)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($micron)->setFilename('1c027666-517c-11ea-b195-686cbbfc8dce')->setOriginalFilename('micron.png')->setMimetype('image/png')->setSize(1763)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($microsemi)->setFilename('1c028d4a-517c-11ea-b9df-b3fdbcea5d82')->setOriginalFilename('microsemi1.png')->setMimetype('image/png')->setSize(3714)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($microsemi)->setFilename('1c0298f8-517c-11ea-b218-c45dd384deef')->setOriginalFilename('microsemi2.png')->setMimetype('image/png')->setSize(11992)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($minicirc)->setFilename('1c02b180-517c-11ea-bdb2-d2d6eb049447')->setOriginalFilename('minicirc.png')->setMimetype('image/png')->setSize(1391)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mitel)->setFilename('1c02c904-517c-11ea-b67d-afeb389800ab')->setOriginalFilename('mitel.png')->setMimetype('image/png')->setSize(2819)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mitsubishi)->setFilename('1c02e196-517c-11ea-b32a-0ae758ede01b')->setOriginalFilename('mitsubis.png')->setMimetype('image/png')->setSize(2311)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($microlin)->setFilename('1c02f94c-517c-11ea-8d31-a90b54774436')->setOriginalFilename('mlinear.png')->setMimetype('image/png')->setSize(3377)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mmi)->setFilename('1c030f4a-517c-11ea-89a3-5ff18e28f89a')->setOriginalFilename('mmi.png')->setMimetype('image/png')->setSize(2692)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mosaic)->setFilename('1c03264c-517c-11ea-b62e-63ded48d45e3')->setOriginalFilename('mosaic.png')->setMimetype('image/png')->setSize(2959)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mosel)->setFilename('1c033e3e-517c-11ea-aa6e-295a944e6492')->setOriginalFilename('moselvit.png')->setMimetype('image/png')->setSize(2504)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mostec)->setFilename('1c0355e0-517c-11ea-bf4c-e717b3328fb1')->setOriginalFilename('mos.png')->setMimetype('image/png')->setSize(2857)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mostek)->setFilename('1c036f08-517c-11ea-bf43-999f653af02d')->setOriginalFilename('mostek1.png')->setMimetype('image/png')->setSize(7502)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mostek)->setFilename('1c0379ee-517c-11ea-9b02-3bf48e6eaab9')->setOriginalFilename('mostek2.png')->setMimetype('image/png')->setSize(7502)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mostek)->setFilename('1c0384f2-517c-11ea-9290-32805a3430f6')->setOriginalFilename('mostek3.png')->setMimetype('image/png')->setSize(2514)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mosys)->setFilename('1c039b7c-517c-11ea-8d53-f043c2deb6ca')->setOriginalFilename('mosys.png')->setMimetype('image/png')->setSize(2321)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($motorola)->setFilename('1c03b2e2-517c-11ea-8ebb-01be54fdbdf9')->setOriginalFilename('motorol1.png')->setMimetype('image/png')->setSize(999)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($motorola)->setFilename('1c03bcd8-517c-11ea-afe8-cbb3c5e03c13')->setOriginalFilename('motorol2.png')->setMimetype('image/png')->setSize(2417)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($microtune)->setFilename('1c03d2ea-517c-11ea-a34d-a66320bbd0af')->setOriginalFilename('mpd.png')->setMimetype('image/png')->setSize(2663)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($msys)->setFilename('1c03e974-517c-11ea-b074-f60a7a398d3e')->setOriginalFilename('msystem.png')->setMimetype('image/png')->setSize(1670)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($murata)->setFilename('1c040260-517c-11ea-ac03-3aaf5dc1627d')->setOriginalFilename('murata1.png')->setMimetype('image/png')->setSize(4874)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($murata)->setFilename('1c040bd4-517c-11ea-8b18-9d21b1cecec6')->setOriginalFilename('murata.png')->setMimetype('image/png')->setSize(4777)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($mwave)->setFilename('1c042146-517c-11ea-b728-f1af7f5823da')->setOriginalFilename('mwave.png')->setMimetype('image/png')->setSize(3370)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($myson)->setFilename('1c0438b6-517c-11ea-91a4-40317701bdee')->setOriginalFilename('myson.png')->setMimetype('image/png')->setSize(1932)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($nec)->setFilename('d19fe956-e287-11ec-a6f7-18c04d8905ca')->setOriginalFilename('nec1.png')->setMimetype('image/png')->setSize(3166)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($nec)->setFilename('d19ff20c-e287-11ec-ad4e-18c04d8905ca')->setOriginalFilename('nec2.png')->setMimetype('image/png')->setSize(3071)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($nexfl)->setFilename('1c04685e-517c-11ea-9361-24d3d2ad1761')->setOriginalFilename('nexflash.png')->setMimetype('image/png')->setSize(7789)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($njr)->setFilename('1c047f6a-517c-11ea-9622-6860561ff933')->setOriginalFilename('njr.png')->setMimetype('image/png')->setSize(3419)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($natsemi)->setFilename('1c0494e6-517c-11ea-b11c-1b21d20160c4')->setOriginalFilename('ns1.png')->setMimetype('image/png')->setSize(1959)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($natsemi)->setFilename('1c049cac-517c-11ea-86c4-0ceab8233d46')->setOriginalFilename('ns2.png')->setMimetype('image/png')->setSize(1952)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($nvidia)->setFilename('d1a066a6-e287-11ec-925f-18c04d8905ca')->setOriginalFilename('nvidia.png')->setMimetype('image/png')->setSize(1874)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($oak)->setFilename('1c04cab0-517c-11ea-83b5-0023cd1d89c8')->setOriginalFilename('oak.png')->setMimetype('image/png')->setSize(2614)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($oki)->setFilename('1c04e3ce-517c-11ea-8375-63787777febe')->setOriginalFilename('oki1.png')->setMimetype('image/png')->setSize(2267)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($oki)->setFilename('1c04ed42-517c-11ea-ac34-8000feba210b')->setOriginalFilename('oki.png')->setMimetype('image/png')->setSize(2546)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($opti)->setFilename('d1a0c326-e287-11ec-a40f-18c04d8905ca')->setOriginalFilename('opti.png')->setMimetype('image/png')->setSize(1684)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($orbit)->setFilename('d1a0dd16-e287-11ec-8e2e-18c04d8905ca')->setOriginalFilename('orbit.png')->setMimetype('image/png')->setSize(3347)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($oren)->setFilename('1c053680-517c-11ea-a6f3-277c109d8a6f')->setOriginalFilename('oren.png')->setMimetype('image/png')->setSize(3497)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($perfsemi)->setFilename('1c054ec2-517c-11ea-844f-270877ad5696')->setOriginalFilename('perform.png')->setMimetype('image/png')->setSize(3284)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($persemi)->setFilename('1c05665a-517c-11ea-99b3-4acaf5fc9854')->setOriginalFilename('pericom.png')->setMimetype('image/png')->setSize(2311)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($phaselink)->setFilename('1c057bcc-517c-11ea-8089-435334dd563a')->setOriginalFilename('phaslink.png')->setMimetype('image/png')->setSize(2669)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($philips)->setFilename('d1a15fe8-e287-11ec-a824-18c04d8905ca')->setOriginalFilename('philips.png')->setMimetype('image/png')->setSize(8690)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($plx)->setFilename('d1a17aaa-e287-11ec-a53c-18c04d8905ca')->setOriginalFilename('plx.png')->setMimetype('image/png')->setSize(4749)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($pmc)->setFilename('d1a1954e-e287-11ec-9bfe-18c04d8905ca')->setOriginalFilename('pmc.png')->setMimetype('image/png')->setSize(3497)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($precis)->setFilename('1c05d7c0-517c-11ea-a6a3-af0f81d6cf9d')->setOriginalFilename('pmi.png')->setMimetype('image/png')->setSize(3807)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($princeton)->setFilename('1c05ef3a-517c-11ea-b73d-ce3eff1ee86c')->setOriginalFilename('ptc.png')->setMimetype('image/png')->setSize(2669)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($powersmart)->setFilename('1c06063c-517c-11ea-97fb-feea529c5c72')->setOriginalFilename('pwrsmart.png')->setMimetype('image/png')->setSize(1389)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($quickl)->setFilename('1c061b18-517c-11ea-9647-186c8c43a9e3')->setOriginalFilename('qlogic.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($qlopgic)->setFilename('d1a21938-e287-11ec-a654-18c04d8905ca')->setOriginalFilename('qualcomm.png')->setMimetype('image/png')->setSize(3326)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($qsemi)->setFilename('1c0645ca-517c-11ea-bb75-12f5658e276e')->setOriginalFilename('quality.png')->setMimetype('image/png')->setSize(1309)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($rabbit)->setFilename('1c065b6e-517c-11ea-bc6c-7cd93ce04c43')->setOriginalFilename('rabbit.png')->setMimetype('image/png')->setSize(2857)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ramtron)->setFilename('1c06719e-517c-11ea-aaf4-07313e0e9c74')->setOriginalFilename('ramtron.png')->setMimetype('image/png')->setSize(1573)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($raytheon)->setFilename('d1a282c4-e287-11ec-8d1e-18c04d8905ca')->setOriginalFilename('raytheon.png')->setMimetype('image/png')->setSize(4303)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($rca)->setFilename('d1a29d54-e287-11ec-a886-18c04d8905ca')->setOriginalFilename('rca.png')->setMimetype('image/png')->setSize(1860)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($realtek)->setFilename('d1a2b870-e287-11ec-a9a8-18c04d8905ca')->setOriginalFilename('realtek.png')->setMimetype('image/png')->setSize(2993)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($rectron)->setFilename('1c06d580-517c-11ea-9336-9871318f8422')->setOriginalFilename('rectron.png')->setMimetype('image/png')->setSize(1691)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($rendition)->setFilename('1c06f146-517c-11ea-9989-c14fad4bd6ea')->setOriginalFilename('rendit.png')->setMimetype('image/png')->setSize(1370)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($renesas)->setFilename('d1a30726-e287-11ec-955f-18c04d8905ca')->setOriginalFilename('renesas.png')->setMimetype('image/png')->setSize(8761)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($rockwell)->setFilename('1c0727ec-517c-11ea-b133-74f25d1b7d8d')->setOriginalFilename('rockwell.png')->setMimetype('image/png')->setSize(1704)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($rohm)->setFilename('d1a33df4-e287-11ec-a6e3-18c04d8905ca')->setOriginalFilename('rohm.png')->setMimetype('image/png')->setSize(2693)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($s3)->setFilename('d1a35848-e287-11ec-a82d-18c04d8905ca')->setOriginalFilename('s3.png')->setMimetype('image/png')->setSize(2189)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sage)->setFilename('1c0775da-517c-11ea-8d9a-29d435e9092d')->setOriginalFilename('sage.png')->setMimetype('image/png')->setSize(2735)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($saifun)->setFilename('d1a38ca0-e287-11ec-916a-18c04d8905ca')->setOriginalFilename('saifun.png')->setMimetype('image/png')->setSize(19242)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sames)->setFilename('1c07af82-517c-11ea-948c-6ccadf955d15')->setOriginalFilename('sames.png')->setMimetype('image/png')->setSize(2614)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($samsung)->setFilename('d1a441b8-e287-11ec-8fdf-18c04d8905ca')->setOriginalFilename('samsung.png')->setMimetype('image/png')->setSize(1841)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sanken)->setFilename('1c07e43e-517c-11ea-967c-e9d1e43965c3')->setOriginalFilename('sanken1.png')->setMimetype('image/png')->setSize(2214)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sanken)->setFilename('1c07f208-517c-11ea-9630-334688b21f8f')->setOriginalFilename('sanken.png')->setMimetype('image/png')->setSize(5309)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sanyo)->setFilename('1c080eaa-517c-11ea-875b-710ef452ec7f')->setOriginalFilename('sanyo1.png')->setMimetype('image/png')->setSize(2228)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sanyo)->setFilename('1c081c10-517c-11ea-b8b9-97487d91ea85')->setOriginalFilename('sanyo.png')->setMimetype('image/png')->setSize(2455)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($scenix)->setFilename('d1a42732-e287-11ec-bf35-18c04d8905ca')->setOriginalFilename('scenix.png')->setMimetype('image/png')->setSize(1869)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($samele)->setFilename('1c085144-517c-11ea-8ddf-f9b7adf9fc7e')->setOriginalFilename('sec1.png')->setMimetype('image/png')->setSize(9392)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($samele)->setFilename('1c085ffe-517c-11ea-9148-a7a7b72cd1a6')->setOriginalFilename('sec.png')->setMimetype('image/png')->setSize(2051)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($seeq)->setFilename('d1a46774-e287-11ec-9b63-18c04d8905ca')->setOriginalFilename('seeq.png')->setMimetype('image/png')->setSize(2903)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($seiko)->setFilename('1c08969a-517c-11ea-bb99-7ed8fdf78efc')->setOriginalFilename('seikoi.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($seiko)->setFilename('1c08a3a6-517c-11ea-aeda-fba30c92bc5f')->setOriginalFilename('semelab.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($semtech)->setFilename('1c08beea-517c-11ea-a35c-96ffa9a7c509')->setOriginalFilename('semtech.png')->setMimetype('image/png')->setSize(1431)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sgsa)->setFilename('1c08da4c-517c-11ea-97b3-72424c0951d6')->setOriginalFilename('sgs1.png')->setMimetype('image/png')->setSize(2339)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sgst)->setFilename('1c08f63a-517c-11ea-9d3b-cd6fccb9b34a')->setOriginalFilename('sgs2.png')->setMimetype('image/png')->setSize(1874)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sharp)->setFilename('d1a4f5cc-e287-11ec-a9fb-18c04d8905ca')->setOriginalFilename('sharp.png')->setMimetype('image/png')->setSize(2258)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($shindengen)->setFilename('1c0926e6-517c-11ea-95c9-a0e63e26801b')->setOriginalFilename('shindgen.png')->setMimetype('image/png')->setSize(1629)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($siemens)->setFilename('1c09407c-517c-11ea-bdbf-1b48dfbc3f82')->setOriginalFilename('siemens1.png')->setMimetype('image/png')->setSize(1216)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($siemens)->setFilename('1c094b26-517c-11ea-831c-1cef32a858b2')->setOriginalFilename('siemens2.png')->setMimetype('image/png')->setSize(2916)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sierra)->setFilename('1c096688-517c-11ea-85cc-db64fd8bb257')->setOriginalFilename('sierra.png')->setMimetype('image/png')->setSize(2321)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sigma)->setFilename('1c09821c-517c-11ea-9a06-fa89108cd973')->setOriginalFilename('sigmatel.png')->setMimetype('image/png')->setSize(1790)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($signetics)->setFilename('1c0999c8-517c-11ea-918f-19ee711c3d38')->setOriginalFilename('signetic.png')->setMimetype('image/png')->setSize(1519)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($siliconlab)->setFilename('1c09b37c-517c-11ea-bce2-fd88b95ea8bd')->setOriginalFilename('siliconlabs.png')->setMimetype('image/png')->setSize(5540)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($siliconm)->setFilename('d1a5be12-e287-11ec-9a95-18c04d8905ca')->setOriginalFilename('siliconm.png')->setMimetype('image/png')->setSize(3817)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($simtec)->setFilename('1c09eb1c-517c-11ea-bdf1-4f38bf8dad38')->setOriginalFilename('silicons.png')->setMimetype('image/png')->setSize(2320)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($simtec)->setFilename('1c09f774-517c-11ea-a2d6-864f6a53237f')->setOriginalFilename('simtek.png')->setMimetype('image/png')->setSize(1874)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($siliconix)->setFilename('1c0a13f8-517c-11ea-b0fa-062e3cc44d2d')->setOriginalFilename('siliconx.png')->setMimetype('image/png')->setSize(2464)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($siliconians)->setFilename('1c0a316c-517c-11ea-a4df-1f96e88298ce')->setOriginalFilename('silnans.png')->setMimetype('image/png')->setSize(1549)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sipex)->setFilename('d1a63612-e287-11ec-9b3b-18c04d8905ca')->setOriginalFilename('sipex.png')->setMimetype('image/png')->setSize(4029)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sis)->setFilename('d1a6514c-e287-11ec-ab1a-18c04d8905ca')->setOriginalFilename('sis.png')->setMimetype('image/png')->setSize(3608)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($smc)->setFilename('1c0a7ffa-517c-11ea-a1b7-295a2785bec3')->setOriginalFilename('smc1.png')->setMimetype('image/png')->setSize(1763)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($stdmicro)->setFilename('1c0a99a4-517c-11ea-ac2e-8c558d1fddea')->setOriginalFilename('smsc1.png')->setMimetype('image/png')->setSize(1781)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($stdmicro)->setFilename('1c0aa6ce-517c-11ea-b54b-c444098d7c8d')->setOriginalFilename('smsc.png')->setMimetype('image/png')->setSize(2117)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sony)->setFilename('d1a6b16e-e287-11ec-b568-18c04d8905ca')->setOriginalFilename('sony.png')->setMimetype('image/png')->setSize(2476)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($space)->setFilename('1c0adbee-517c-11ea-8f89-3c7e51df0277')->setOriginalFilename('space.png')->setMimetype('image/png')->setSize(3377)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($spectek)->setFilename('1c0af7f0-517c-11ea-be3d-536311ad51f8')->setOriginalFilename('spectek.png')->setMimetype('image/png')->setSize(2228)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($spt)->setFilename('d1a702d6-e287-11ec-aa6d-18c04d8905ca')->setOriginalFilename('spt.png')->setMimetype('image/png')->setSize(3419)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sss)->setFilename('1c0b2a90-517c-11ea-a19a-02d8b32b0ba5')->setOriginalFilename('sss.png')->setMimetype('image/png')->setSize(1871)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($sst)->setFilename('d1a73a30-e287-11ec-8548-18c04d8905ca')->setOriginalFilename('sst.png')->setMimetype('image/png')->setSize(3072)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($stmicro)->setFilename('1c0b5d26-517c-11ea-bf6e-a401d8af9e02')->setOriginalFilename('st.png')->setMimetype('image/png')->setSize(1604)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($summit)->setFilename('d1a772a2-e287-11ec-a352-18c04d8905ca')->setOriginalFilename('summit.png')->setMimetype('image/png')->setSize(11440)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($synergy)->setFilename('1c0b8f3a-517c-11ea-92f5-84d33ae2e347')->setOriginalFilename('synergy.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($synertek)->setFilename('1c0ba5ba-517c-11ea-9743-08386f480818')->setOriginalFilename('synertek.png')->setMimetype('image/png')->setSize(1789)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($tsmc)->setFilename('1c0bbb9a-517c-11ea-8d66-18505514406d')->setOriginalFilename('taiwsemi.png')->setMimetype('image/png')->setSize(1475)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($tdk)->setFilename('d1a80ec4-e287-11ec-b54b-18c04d8905ca')->setOriginalFilename('tdk.png')->setMimetype('image/png')->setSize(3687)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($teccor)->setFilename('1c0be782-517c-11ea-b0ff-90e7b45462fe')->setOriginalFilename('teccor.png')->setMimetype('image/png')->setSize(1869)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($telcom)->setFilename('1c0bfda8-517c-11ea-979f-15d592d8c158')->setOriginalFilename('telcom.png')->setMimetype('image/png')->setSize(2555)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($teledyne)->setFilename('1c0c132e-517c-11ea-82b4-078c8c95c31e')->setOriginalFilename('teledyne.png')->setMimetype('image/png')->setSize(1904)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($telefunken)->setFilename('1c0c2904-517c-11ea-82fa-0cff1324b488')->setOriginalFilename('telefunk.png')->setMimetype('image/png')->setSize(2715)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($teltone)->setFilename('d1a8a866-e287-11ec-8cbd-18c04d8905ca')->setOriginalFilename('teltone.png')->setMimetype('image/png')->setSize(4303)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($thomson)->setFilename('1c0c58b6-517c-11ea-b5fb-3013ce098a99')->setOriginalFilename('thomscsf.png')->setMimetype('image/png')->setSize(1874)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ti)->setFilename('1c0c7436-517c-11ea-9716-302dada7716a')->setOriginalFilename('ti1.png')->setMimetype('image/png')->setSize(1869)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ti)->setFilename('1c0c80f2-517c-11ea-ac66-4d136cec08f3')->setOriginalFilename('ti.png')->setMimetype('image/png')->setSize(1789)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($toko)->setFilename('1c0c9cd6-517c-11ea-ac88-58d0882c88f3')->setOriginalFilename('toko.png')->setMimetype('image/png')->setSize(1907)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($toshiba)->setFilename('1c0cb86a-517c-11ea-9d76-08d6f88fcb34')->setOriginalFilename('toshiba1.png')->setMimetype('image/png')->setSize(1922)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($toshiba)->setFilename('1c0cc5bc-517c-11ea-900e-275f9183e6b9')->setOriginalFilename('toshiba2.png')->setMimetype('image/png')->setSize(1309)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($toshiba)->setFilename('1c0cd2e6-517c-11ea-846e-6e6327a714c5')->setOriginalFilename('toshiba3.png')->setMimetype('image/png')->setSize(2269)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($trident)->setFilename('1c0cedd0-517c-11ea-9fb7-9e85f317ef6d')->setOriginalFilename('trident.png')->setMimetype('image/png')->setSize(1414)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($triquint)->setFilename('1c0d0612-517c-11ea-a19d-3e7f543af34f')->setOriginalFilename('triquint.png')->setMimetype('image/png')->setSize(2294)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($triscend)->setFilename('d1a96fda-e287-11ec-9f99-18c04d8905ca')->setOriginalFilename('triscend.png')->setMimetype('image/png')->setSize(4521)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($tseng)->setFilename('1c0d3cf4-517c-11ea-b115-cb72f949089e')->setOriginalFilename('tseng.png')->setMimetype('image/png')->setSize(1466)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($tundra)->setFilename('1c0d5824-517c-11ea-aa67-b349aac71caa')->setOriginalFilename('tundra.png')->setMimetype('image/png')->setSize(1709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($turbo)->setFilename('d1a9bcd8-e287-11ec-b412-18c04d8905ca')->setOriginalFilename('turbo_ic.png')->setMimetype('image/png')->setSize(7784)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($ubicom)->setFilename('1c0d8dee-517c-11ea-9088-e015423b4ad5')->setOriginalFilename('ubicom.png')->setMimetype('image/png')->setSize(2047)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($umc)->setFilename('1c0da928-517c-11ea-99b1-2dfc4d90ec87')->setOriginalFilename('umc.png')->setMimetype('image/png')->setSize(3032)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($unitrode)->setFilename('1c0dc516-517c-11ea-80bb-a9a9739b779a')->setOriginalFilename('unitrode.png')->setMimetype('image/png')->setSize(1309)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($usar)->setFilename('d1aa25ec-e287-11ec-b4bd-18c04d8905ca')->setOriginalFilename('usar1.png')->setMimetype('image/png')->setSize(2771)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($usar)->setFilename('d1aa3064-e287-11ec-a0c7-18c04d8905ca')->setOriginalFilename('usar.png')->setMimetype('image/png')->setSize(2793)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($utmc)->setFilename('1c0e0b20-517c-11ea-b2f6-ee77d3ffaf3f')->setOriginalFilename('utmc.png')->setMimetype('image/png')->setSize(2047)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($utron)->setFilename('1c0e2678-517c-11ea-bbf4-2b8cc2e082b5')->setOriginalFilename('utron.png')->setMimetype('image/png')->setSize(2047)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($v3)->setFilename('1c0e423e-517c-11ea-b759-9409a88533d7')->setOriginalFilename('v3.png')->setMimetype('image/png')->setSize(3248)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($vadem)->setFilename('1c0e5e04-517c-11ea-8a0f-376eea0bee6c')->setOriginalFilename('vadem.png')->setMimetype('image/png')->setSize(1874)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($vis)->setFilename('1c0e7876-517c-11ea-a914-badcea1efc0f')->setOriginalFilename('vanguard.png')->setMimetype('image/png')->setSize(1454)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($vantis)->setFilename('1c0e94c8-517c-11ea-bf66-41221a342775')->setOriginalFilename('vantis.png')->setMimetype('image/png')->setSize(1475)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($via)->setFilename('d1aae914-e287-11ec-95b0-18c04d8905ca')->setOriginalFilename('via.png')->setMimetype('image/png')->setSize(1922)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($virata)->setFilename('d1ab00c0-e287-11ec-948a-18c04d8905ca')->setOriginalFilename('virata.png')->setMimetype('image/png')->setSize(3764)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($vishay)->setFilename('d1ab19d4-e287-11ec-92c2-18c04d8905ca')->setOriginalFilename('vishay.png')->setMimetype('image/png')->setSize(4410)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($vistec)->setFilename('1c0ef7ba-517c-11ea-86ed-4f740cd530ed')->setOriginalFilename('vistech.png')->setMimetype('image/png')->setSize(1942)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($vitelic)->setFilename('1c0f0e3a-517c-11ea-b3c5-85841275bc70')->setOriginalFilename('vitelic.png')->setMimetype('image/png')->setSize(1691)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($vlsi)->setFilename('1c0f2762-517c-11ea-b2b7-52fadbb768d8')->setOriginalFilename('vlsi.png')->setMimetype('image/png')->setSize(1874)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($volterra)->setFilename('1c0f3e32-517c-11ea-acf7-4dcdd4aa1ac1')->setOriginalFilename('volterra.png')->setMimetype('image/png')->setSize(2029)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($vtc)->setFilename('1c0f555c-517c-11ea-a6bc-e7f695ee407b')->setOriginalFilename('vtc.png')->setMimetype('image/png')->setSize(2223)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($wsi)->setFilename('d1abb1f0-e287-11ec-8dd7-18c04d8905ca')->setOriginalFilename('wafscale.png')->setMimetype('image/png')->setSize(2985)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($wd)->setFilename('1c0f8004-517c-11ea-8c05-b43d04e5e3f1')->setOriginalFilename('wdc1.png')->setMimetype('image/png')->setSize(1784)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($wd)->setFilename('1c0f88ba-517c-11ea-ad37-90dc024ad52f')->setOriginalFilename('wdc2.png')->setMimetype('image/png')->setSize(1403)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($weitek)->setFilename('1c0f9fc6-517c-11ea-a913-9af8c6316c4c')->setOriginalFilename('weitek.png')->setMimetype('image/png')->setSize(1468)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($winbond)->setFilename('d1ac0a74-e287-11ec-a6c1-18c04d8905ca')->setOriginalFilename('winbond.png')->setMimetype('image/png')->setSize(5402)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($wofson)->setFilename('1c0fd2fc-517c-11ea-a208-3de00791a5d1')->setOriginalFilename('wolf.png')->setMimetype('image/png')->setSize(2343)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($xwmics)->setFilename('1c0febc0-517c-11ea-bc93-3a920704ed1f')->setOriginalFilename('xemics.png')->setMimetype('image/png')->setSize(2029)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($xicor)->setFilename('1c1006a0-517c-11ea-a8c5-3cb725704fee')->setOriginalFilename('xicor1.png')->setMimetype('image/png')->setSize(1259)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($xicor)->setFilename('1c101294-517c-11ea-821f-7b98c1f341b8')->setOriginalFilename('xicor.png')->setMimetype('image/png')->setSize(3389)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($xilinx)->setFilename('d1ac80b2-e287-11ec-8663-18c04d8905ca')->setOriginalFilename('xilinx.png')->setMimetype('image/png')->setSize(4186)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($yamaha)->setFilename('d1ac9b2e-e287-11ec-9841-18c04d8905ca')->setOriginalFilename('yamaha.png')->setMimetype('image/png')->setSize(1779)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($zetex)->setFilename('1c106168-517c-11ea-ab1c-f405d22f0595')->setOriginalFilename('zetex.png')->setMimetype('image/png')->setSize(1255)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($zilog)->setFilename('1c107658-517c-11ea-831c-70746e0710f8')->setOriginalFilename('zilog1.png')->setMimetype('image/png')->setSize(1958)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($zilog)->setFilename('1c108080-517c-11ea-b6d9-664ee50fe109')->setOriginalFilename('zilog2.png')->setMimetype('image/png')->setSize(2204)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($zilog)->setFilename('1c108a4e-517c-11ea-a62d-706a26744fcc')->setOriginalFilename('zilog3.png')->setMimetype('image/png')->setSize(2614)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($zilog)->setFilename('1c109430-517c-11ea-a624-80b2229a137f')->setOriginalFilename('zilog4.png')->setMimetype('image/png')->setSize(2405)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($zmd)->setFilename('1c10aa60-517c-11ea-8ac5-71d1750e8d1c')->setOriginalFilename('zmda.png')->setMimetype('image/png')->setSize(3709)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));
		$manager->persist((new ManufacturerICLogo)->setManufacturer($zoran)->setFilename('1c10c18a-517c-11ea-a781-aaffe43be5d4')->setOriginalFilename('zoran.png')->setMimetype('image/png')->setSize(2784)->setExtension('png')->setCreated(DateTime::from('2020-02-17 11:53:27')));

		$root = (new PartCategory)
			->setName('Root Category')
			->setRoot(1)
			->setCategoryPath('Root Category');
		$manager->persist($root);

		$pcs = (new PartMeasurementUnit)
			->setName('Pieces')
			->setShortName('pcs')
			->setDefault(true);
		$manager->persist($pcs);

		$manager->persist(new SiPrefix('yotta', 'Y', 24, 10));
		$manager->persist(new SiPrefix('zetta', 'Z', 21, 10));
		$manager->persist(new SiPrefix('exa', 'E', 18, 10));
		$manager->persist(new SiPrefix('peta', 'P', 15, 10));
		$manager->persist($tera = new SiPrefix('tera', 'T', 12, 10)); //5
		$manager->persist($giga = new SiPrefix('giga', 'G', 9, 10)); //6
		$manager->persist($mega = new SiPrefix('mega', 'M', 6, 10)); //7
		$manager->persist($kilo = new SiPrefix('kilo', 'k', 3, 10)); //8
		$manager->persist(new SiPrefix('hecto', 'h', 2, 10));
		$manager->persist(new SiPrefix('deca', 'da', 1, 10));
		$manager->persist($no = new SiPrefix('-', '', 0, 10)); //11
		$manager->persist($deci = new SiPrefix('deci', 'd', -1, 10));
		$manager->persist($centi = new SiPrefix('centi', 'c', -2, 10));
		$manager->persist($milli = new SiPrefix('milli', 'm', -3, 10)); //14
		$manager->persist($micro = new SiPrefix('micro', 'μ', -6, 10));
		$manager->persist($nano = new SiPrefix('nano', 'n', -9, 10)); //16
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

		$root = (new StorageLocationCategory)
			->setName('Root Category')
			->setRoot(1)
			->setCategoryPath('Root Category');
		$manager->persist($root);

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

		$manager->flush();
	}
}
