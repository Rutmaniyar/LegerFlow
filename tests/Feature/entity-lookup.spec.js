const path = require('path');
const { test, expect } = require('@playwright/test');

const appScript = path.resolve(__dirname, '../../public/assets/js/app.js');

async function mountClientLookup(page) {
  await page.goto('/install');
  await page.setContent(`
    <form>
      <label>
        <span>Client</span>
        <select name="client_id" required data-entity-select="client" data-allow-create>
          <option value="">Select client</option>
          <option value="__new__">Create new client</option>
          <option value="7">Acme Ltd</option>
        </select>
      </label>
      <div class="hidden" data-new-client-panel>
        <input name="new_client_name" data-new-client-required>
        <input name="new_client_email" type="email">
      </div>
      <button type="submit">Save</button>
    </form>
  `);
  await page.addScriptTag({ path: appScript });
}

test.describe('client and vendor lookup', () => {
  test('requires confirmation before reusing an existing client or creating a new one', async ({ page }) => {
    await page.route('**/clients/search?**', async (route) => {
      const query = new URL(route.request().url()).searchParams.get('q');
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          results: query === 'Acme'
            ? [{ id: 7, name: 'Acme Ltd', contact_name: 'Jane Smith', email: 'accounts@acme.test', currency: 'GBP' }]
            : []
        })
      });
    });
    await mountClientLookup(page);

    const combobox = page.getByRole('combobox');
    const select = page.locator('select[name="client_id"]');
    const panel = page.locator('[data-new-client-panel]');

    await combobox.fill('Acme');
    await page.getByRole('option', { name: /Acme Ltd/ }).click();
    await expect(select).toHaveValue('7');
    await expect(combobox).toHaveValue('Acme Ltd');
    await expect(panel).toHaveClass(/hidden/);

    await combobox.fill('Northstar');
    await page.getByRole('option', { name: /Create new client “Northstar”/ }).click();
    await expect(select).toHaveValue('__new__');
    await expect(panel).not.toHaveClass(/hidden/);
    await expect(page.locator('[name="new_client_name"]')).toHaveValue('Northstar');
    await expect(page.locator('[name="new_client_name"]')).toHaveAttribute('required', '');
  });

  test('supports keyboard selection and restores the native select on lookup failure', async ({ page }) => {
    let fail = false;
    await page.route('**/clients/search?**', async (route) => {
      if (fail) {
        await route.fulfill({ status: 500, body: 'Unavailable' });
        return;
      }
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({ results: [{ id: 7, name: 'Acme Ltd', contact_name: null, email: null, currency: 'GBP' }] })
      });
    });
    await mountClientLookup(page);

    const combobox = page.getByRole('combobox');
    const select = page.locator('select[name="client_id"]');
    await combobox.fill('Acme');
    await page.getByRole('option', { name: /Acme Ltd/ }).waitFor();
    await combobox.press('ArrowDown');
    await combobox.press('Enter');
    await expect(select).toHaveValue('7');

    fail = true;
    await combobox.fill('Broken');
    await expect(page.getByText('Search is unavailable. Choose a client from the list.')).toBeVisible();
    await expect(select).toBeVisible();
    await expect(select).toHaveAttribute('required', '');
  });

  test('opens an existing vendor from the add-vendor name field', async ({ page }) => {
    await page.route('**/vendors/search?**', async (route) => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          results: [{ id: 12, name: 'Northstar Supplies', contact_name: 'Sam Lee', email: 'sam@northstar.test' }]
        })
      });
    });
    await page.route('**/vendors/12', async (route) => {
      await route.fulfill({ contentType: 'text/html', body: '<h1>Northstar Supplies</h1>' });
    });
    await page.goto('/install');
    await page.setContent(`
      <form>
        <label><span>Vendor name</span><input name="name" required data-entity-name-lookup="vendor"></label>
        <input name="email" type="email">
      </form>
    `);
    await page.addScriptTag({ path: appScript });

    await page.getByRole('combobox').fill('Northstar');
    await page.getByRole('option', { name: /Northstar Supplies/ }).click();
    await expect(page).toHaveURL(/\/vendors\/12$/);
  });
});
