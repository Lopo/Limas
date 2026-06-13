const { test, expect } = require('@playwright/test');

test.describe('Limas Homepage', () => {
  test('should load ExtJS application', async ({ page }) => {
    await page.goto('/');

    // Overíme že má správny title
    await expect(page).toHaveTitle(/Limas/);

    // Počkáme kým ExtJS je ready (loader už môže byť hidden)
    await page.waitForFunction(() => typeof Ext !== 'undefined' && Ext.isReady, { timeout: 30000 });

    // Overíme že existuje hlavný viewport
    const viewport = page.locator('.x-viewport');
    await expect(viewport).toBeVisible();
  });

  test('should have navigation elements', async ({ page }) => {
    await page.goto('/');
    
    // Počkáme na načítanie aplikácie
    await page.waitForSelector('.x-viewport', { timeout: 30000 });

    // Overíme že existuje hlavné menu
    const menuBar = page.locator('.x-toolbar').first();
    await expect(menuBar).toBeVisible();

    // Overíme že existuje status bar
    const statusBar = page.locator('.x-statusbar');
    await expect(statusBar).toBeVisible();
  });
});
