const {defineConfig, devices} = require('@playwright/test');
const {execSync} = require('child_process');

// Use different port for tests to avoid conflicts with dev server
const TEST_PORT = 8001;

// Detect available browsers
function getAvailableBrowsers() {
	const browsers = [];

	// Chromium is always available
	browsers.push({
		name: 'chromium',
		use: {...devices['Desktop Chrome']},
	});

	// Firefox is always available
	browsers.push({
		name: 'firefox',
		use: {...devices['Desktop Firefox']},
	});

	// Check if webkit dependencies are available
	try {
		execSync('which wpe-webkit-2.44-driver 2>/dev/null || ldconfig -p | grep -q libwpe', {stdio: 'ignore'});
		browsers.push({
			name: 'webkit',
			use: {...devices['Desktop Safari']},
		});
	} catch {
		// Webkit dependencies not available, skip
		console.log('Webkit dependencies not found, skipping Safari tests');
	}

	return browsers;
}

module.exports = defineConfig({
	globalSetup: './tests/e2e/global-setup.js',
	testDir: './tests/e2e',
	timeout: 30 * 1000,
	expect: {
		timeout: 10000
	},
	fullyParallel: false,
	workers: 1,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 1,
	reporter: 'html',

	use: {
		// Nastav cez env: PLAYWRIGHT_BASE_URL=https://limas npm run test:e2e
		baseURL: process.env.PLAYWRIGHT_BASE_URL || `http://localhost:${TEST_PORT}`,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		ignoreHTTPSErrors: true,
	},

	projects: getAvailableBrowsers(),

	webServer: process.env.PLAYWRIGHT_BASE_URL ? undefined : {
		command: `symfony server:start --port=${TEST_PORT} --no-tls --daemon=false 2>/dev/null`,
		port: TEST_PORT,
		reuseExistingServer: false, // Always start fresh test server
		timeout: 120 * 1000,
		env: {
			...process.env,
			APP_ENV: process.env.APP_ENV || 'test',
		},
	},
});
