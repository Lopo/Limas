<?php

namespace Limas\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Limas\Entity\Part;
use Limas\Entity\PartCategory;
use Limas\Entity\StockEntry;
use Limas\Entity\StorageLocation;
use Limas\Entity\User;
use Limas\Service\UserService;
use Limas\Tests\DataFixtures\PartCategoryDataLoader;
use Limas\Tests\DataFixtures\PartDataLoader;
use Limas\Tests\DataFixtures\StorageLocationCategoryDataLoader;
use Limas\Tests\DataFixtures\StorageLocationDataLoader;
use Limas\Tests\DataFixtures\UserDataLoader;
use Nette\Utils\Json;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;


/**
 * GHSA-8883-2whw-gwhm: the StockEntry API had no authorization on its write
 * operations, so any ROLE_USER account could rewrite, re-attribute, forge or
 * delete other users' stock history (the rules only lived in the UI grid), and
 * every embedded user carried its e-mail address although the user directory
 * is admin-only. Reproduces the reporter's lab scenario: admin books +100,
 * carol takes 40, bob (ROLE_USER) attacks.
 */
class StockEntrySecurityTest
	extends WebTestCase
{
	private int $partId;
	private int $adminId;
	private int $carolId;
	private int $adminEntryId;
	private int $carolEntryId;


	protected function setUp(): void
	{
		parent::setUp();
		$fixtures = self::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
			UserDataLoader::class,
			StorageLocationCategoryDataLoader::class,
			StorageLocationDataLoader::class,
			PartCategoryDataLoader::class,
			PartDataLoader::class
		])->getReferenceRepository();

		$em = self::getContainer()->get(EntityManagerInterface::class);
		$provider = self::getContainer()->get(UserService::class)->getBuiltinProvider();
		$admin = $em->find(User::class, $fixtures->getReference('user.admin', User::class)->getId());
		$part = $em->find(Part::class, $fixtures->getReference('part.1', Part::class)->getId());

		$bob = (new User('bob', $provider))->setPassword('bob')->setRoles(['ROLE_USER']);
		$carol = (new User('carol', $provider))->setPassword('carol')->setRoles(['ROLE_USER'])->setEmail('carol@lab.test');
		$em->persist($bob);
		$em->persist($carol);

		$adminEntry = (new StockEntry)->setPart($part)->setUser($admin)->setStockLevel(100)->setPrice('2.5');
		$carolEntry = (new StockEntry)->setPart($part)->setUser($carol)->setStockLevel(-40)->setComment('carol took 40 for project X');
		$em->persist($adminEntry);
		$em->persist($carolEntry);
		$em->flush();

		$this->partId = $part->getId();
		$this->adminId = $admin->getId();
		$this->carolId = $carol->getId();
		$this->adminEntryId = $adminEntry->getId();
		$this->carolEntryId = $carolEntry->getId();
	}

	private function bob(): KernelBrowser
	{
		return $this->makeClientWithCredentials('bob', 'bob');
	}

	private function em(): EntityManagerInterface
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->clear();
		return $em;
	}

	private function entry(int $id): ?StockEntry
	{
		return $this->em()->find(StockEntry::class, $id);
	}

	private function putEntry(KernelBrowser $client, int $id, array $body): int
	{
		$client->request('PUT', '/api/stock_entries/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], Json::encode($body));
		return $client->getResponse()->getStatusCode();
	}

	/** Book +5 at 1.5 as bob through the normal flow and return that entry's id */
	private function bobsOwnEntryId(KernelBrowser $client): int
	{
		$client->request('PUT', '/api/parts/' . $this->partId . '/addStock', [], [], ['CONTENT_TYPE' => 'application/json'], Json::encode(['quantity' => 5, 'price' => 1.5]));
		self::assertSame(200, $client->getResponse()->getStatusCode());
		$entry = $this->em()->getRepository(StockEntry::class)->findOneBy(['stockLevel' => 5]);
		self::assertNotNull($entry);
		self::assertSame('bob', $entry->getUser()?->getUsername());
		return $entry->getId();
	}

	public function testRegularUserCannotRewriteAnotherUsersEntry(): void
	{
		$client = $this->bob();

		// The reporter's step 1: turn carol's -40 into a -1 booked on the admin
		self::assertSame(403, $this->putEntry($client, $this->carolEntryId, [
			'stockLevel' => -1,
			'comment' => 'edited by bob',
			'user' => '/api/users/' . $this->adminId
		]));
		// Even a comment-only edit is not bob's to make
		self::assertSame(403, $this->putEntry($client, $this->carolEntryId, ['comment' => 'edited by bob']));

		$entry = $this->entry($this->carolEntryId);
		self::assertSame(-40, $entry->getStockLevel());
		self::assertSame('carol took 40 for project X', $entry->getComment());
		self::assertSame($this->carolId, $entry->getUser()?->getId());
	}

	public function testRegularUserMayFixOwnPriceAndComment(): void
	{
		$client = $this->bob();
		$ownId = $this->bobsOwnEntryId($client);

		self::assertSame(200, $this->putEntry($client, $ownId, ['comment' => 'fixed note', 'price' => '1.25']));

		$entry = $this->entry($ownId);
		self::assertSame('fixed note', $entry->getComment());
		self::assertSame(1.25, (float)$entry->getPrice());
		self::assertSame(5, $entry->getStockLevel());
		self::assertSame('bob', $entry->getUser()?->getUsername());
	}

	public function testRegularUserCannotChangeOwnAmountOrUser(): void
	{
		$client = $this->bob();
		$ownId = $this->bobsOwnEntryId($client);

		self::assertSame(403, $this->putEntry($client, $ownId, ['stockLevel' => 500]));
		self::assertSame(403, $this->putEntry($client, $ownId, ['user' => '/api/users/' . $this->carolId]));

		$entry = $this->entry($ownId);
		self::assertSame(5, $entry->getStockLevel());
		self::assertSame('bob', $entry->getUser()?->getUsername());
	}

	public function testRegularUserCannotForgeOrDeleteEntries(): void
	{
		$client = $this->bob();

		// Step 2: forge a withdrawal attributed to carol
		$client->request('POST', '/api/stock_entries', [], [], ['CONTENT_TYPE' => 'application/json'], Json::encode([
			'part' => '/api/parts/' . $this->partId,
			'stockLevel' => -5,
			'user' => '/api/users/' . $this->carolId,
			'comment' => 'forged as carol'
		]));
		self::assertSame(403, $client->getResponse()->getStatusCode());
		self::assertNull($this->em()->getRepository(StockEntry::class)->findOneBy(['comment' => 'forged as carol']));

		// Step 3: delete the admin's +100
		$client->request('DELETE', '/api/stock_entries/' . $this->adminEntryId);
		self::assertSame(403, $client->getResponse()->getStatusCode());
		self::assertNotNull($this->entry($this->adminEntryId));
	}

	public function testAdminKeepsFullControl(): void
	{
		$client = $this->makeAuthenticatedClient();

		self::assertSame(200, $this->putEntry($client, $this->carolEntryId, ['stockLevel' => -41, 'comment' => 'corrected by admin']));
		$entry = $this->entry($this->carolEntryId);
		self::assertSame(-41, $entry->getStockLevel());
		self::assertSame('corrected by admin', $entry->getComment());

		$client->request('DELETE', '/api/stock_entries/' . $this->carolEntryId);
		self::assertSame(204, $client->getResponse()->getStatusCode());
		self::assertNull($this->entry($this->carolEntryId));
	}

	public function testEmbeddedUserDoesNotExposeEmail(): void
	{
		$client = $this->bob();

		// Step 4: the history listing embeds every author
		$client->request('GET', '/api/stock_entries');
		self::assertSame(200, $client->getResponse()->getStatusCode());
		$members = Json::decode((string)$client->getResponse()->getContent(), true)['hydra:member'];
		self::assertNotEmpty($members);
		foreach ($members as $member) {
			self::assertArrayHasKey('username', $member['user']);
			self::assertArrayNotHasKey('email', $member['user'], 'embedded user must not carry the e-mail');
		}
		self::assertStringNotContainsString('carol@lab.test', (string)$client->getResponse()->getContent());

		// The directory itself stays admin-only…
		$client->request('GET', '/api/users/' . $this->carolId);
		self::assertSame(403, $client->getResponse()->getStatusCode());

		// …and still serves the e-mail to the admin
		$admin = $this->makeAuthenticatedClient();
		$admin->request('GET', '/api/users/' . $this->carolId);
		self::assertSame(200, $admin->getResponse()->getStatusCode());
		self::assertSame('carol@lab.test', Json::decode((string)$admin->getResponse()->getContent(), true)['email']);
	}

	public function testInitialStockOnPartCreateIsBookedUnderCaller(): void
	{
		$client = $this->bob();
		$em = $this->em();
		$category = $em->getRepository(PartCategory::class)->findOneBy([]);
		$location = $em->getRepository(StorageLocation::class)->findOneBy([]);

		$client->request('POST', '/api/parts', [], [], ['CONTENT_TYPE' => 'application/json'], Json::encode([
			'name' => 'bob initial stock',
			'category' => '/api/part_categories/' . $category->getId(),
			'storageLocation' => '/api/storage_locations/' . $location->getId(),
			'stockLevels' => [
				['stockLevel' => 7, 'user' => '/api/users/' . $this->adminId, 'comment' => 'pinned on admin']
			]
		]));
		self::assertSame(201, $client->getResponse()->getStatusCode());

		preg_match('#/api/parts/(\d+)#', Json::decode((string)$client->getResponse()->getContent(), true)['@id'], $m);
		$part = $this->em()->find(Part::class, (int)$m[1]);
		self::assertCount(1, $part->getStockLevels());
		self::assertSame('bob', $part->getStockLevels()->first()->getUser()?->getUsername());
	}
}
