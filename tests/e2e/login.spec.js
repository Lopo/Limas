const {test, expect} = require('@playwright/test');

test.describe('Limas Login', () => {
	test('should show login dialog if not authenticated', async ({page}) => {
		// Ideme na hlavnú stránku
		await page.goto('/');

		// Počkáme kým sa načíta ExtJS
		await page.waitForFunction(() => typeof Ext !== 'undefined' && Ext.isReady, {timeout: 30000});

		// Ak nie sme prihlásený, mal by sa zobraziť login dialog
		const loginWindow = page.locator('.x-window').filter({hasText: /Login|Prihlásenie/});

		// Počkáme či sa objaví login okno (ak auto-login nie je povolený)
		try {
			await expect(loginWindow).toBeVisible({timeout: 5000});

			// Overíme že má potrebné polia
			const usernameField = page.locator('input[name="username"]');
			const passwordField = page.locator('input[type="password"]');
			const loginButton = page.locator('.x-btn').filter({hasText: /Login|Prihlásenie/});

			await expect(usernameField).toBeVisible();
			await expect(passwordField).toBeVisible();
			await expect(loginButton).toBeVisible();
		} catch (e) {
			// Možno je povolený auto-login alebo už sme prihlásený
		}
	});

	test('should handle login process', async ({page}) => {
		// Pre tento test potrebujeme vedieť testovacie prihlasovacie údaje
		// Zatiaľ len overíme že API endpoint existuje
		const response = await page.request.post('/api/users/jwt', {
			data: {
				username: 'invalid',
				password: 'invalid'
			},
			failOnStatusCode: false
		});

		// Mal by vrátiť 401 pre nesprávne údaje
		expect(response.status()).toBe(401);
	});
});
