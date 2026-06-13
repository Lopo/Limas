const {execSync} = require('child_process');

module.exports = async () => {
	console.log('\n🔧 Reinitializing test database...');

	const env = {
		...process.env,
		APP_ENV: process.env.APP_ENV || 'test'
	};

	const opts = {
		cwd: process.cwd(),
		env,
		stdio: 'inherit'
	};

	try {
		// Drop all tables including migration versions
		execSync('php bin/console doctrine:schema:drop --force --full-database', opts);
		execSync('php bin/console dbal:run-sql "DROP TABLE IF EXISTS doctrine_migration_versions"', opts);

		// Run migrations (creates tables)
		execSync('php bin/console doctrine:migrations:migrate --no-interaction', opts);

		// Load base fixtures
		execSync('php bin/console doctrine:fixtures:load --group=setup --append --no-interaction', opts);

		// Create admin user for tests and protect it
		execSync('php bin/console limas:user:create --role super_admin admin admin@example.com admin', opts);
		execSync('php bin/console limas:user:protect admin', opts);

		// Create test storage location (required for part creation tests)
		execSync('php bin/console dbal:run-sql "INSERT INTO StorageLocation (name, category_id) SELECT \'Test Location\', id FROM StorageLocationCategory LIMIT 1"', opts);

		console.log('✅ Database ready\n');
	} catch (error) {
		console.error('❌ Database setup failed:', error.message);
		throw error;
	}
};
