const path = require('path');
const { test, expect } = require('@playwright/test');

const appScript = path.resolve(__dirname, '../../public/assets/js/app.js');

async function mountFinancialControls(page) {
  await page.goto('/install');
  await page.setContent(`<!doctype html>
    <html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/css/app.css"></head>
    <body class="p-4">
      <main>
        <button type="button" data-add-line="#lines" data-template="#line-template">Add line</button>
        <div id="lines">
          <div data-line-row>
            <label>Discount type
              <select name="item_discount_type[]" data-discount-type>
                <option value="percent" selected>Percent</option><option value="amount">Amount</option>
              </select>
            </label>
            <label>Discount value <input name="item_discount_value[]" type="number" data-discount-value></label>
            <label>Tax
              <select name="item_tax_rate[]">
                <option value="0">0%</option><option value="20" selected>VAT · 20%</option>
                <option value="5">Reduced · 5% · Inactive</option>
              </select>
            </label>
          </div>
        </div>
        <template id="line-template">
          <div data-line-row>
            <label>Discount type
              <select name="item_discount_type[]" data-discount-type>
                <option value="percent" selected>Percent</option><option value="amount">Amount</option>
              </select>
            </label>
            <label>Discount value <input name="item_discount_value[]" type="number" data-discount-value></label>
            <label>Tax
              <select name="item_tax_rate[]"><option value="0">0%</option><option value="20" selected>VAT · 20%</option></select>
            </label>
          </div>
        </template>
        <div class="table-wrap mt-6">
          <table class="data-table"><thead><tr>
            <th aria-sort="descending"><a href="?sort=invoice_number&amp;direction=asc">Invoice ↓</a></th>
            <th><a href="?sort=client&amp;direction=asc">Client</a></th><th>Status</th><th>Invoice date</th>
            <th>Due date</th><th>Paid</th><th>Balance</th><th>Actions</th>
          </tr></thead><tbody><tr><td>INV-2026-0001</td><td>Northstar Industries</td><td>Paid</td><td>2026-08-26</td><td>2026-09-09</td><td>100.00 GBP</td><td>0.00 GBP</td><td>Edit</td></tr></tbody></table>
        </div>
      </main>
    </body></html>`);
  await page.addScriptTag({ path: appScript });
}

test.describe('financial register controls', () => {
  test('switches discount constraints and applies the default tax to added lines', async ({ page }) => {
    await mountFinancialControls(page);

    const firstRow = page.locator('[data-line-row]').first();
    const discountType = firstRow.locator('[data-discount-type]');
    const discountValue = firstRow.locator('[data-discount-value]');
    await expect(discountValue).toHaveAttribute('max', '100');

    await discountType.selectOption('amount');
    await expect(discountValue).not.toHaveAttribute('max', '100');
    await discountType.selectOption('percent');
    await expect(discountValue).toHaveAttribute('max', '100');

    await page.getByRole('button', { name: 'Add line' }).click();
    const addedRow = page.locator('[data-line-row]').nth(1);
    await expect(addedRow.locator('[name="item_tax_rate[]"]')).toHaveValue('20');
    await expect(addedRow.locator('[data-discount-value]')).toHaveAttribute('max', '100');
    await expect(firstRow.getByRole('option', { name: /Inactive/ })).toHaveCount(1);
  });

  test('keeps wide sortable registers inside their scroll container', async ({ page }) => {
    await mountFinancialControls(page);
    await expect(page.locator('th[aria-sort="descending"] a')).toBeVisible();

    const sizes = await page.evaluate(() => ({
      viewport: document.documentElement.clientWidth,
      documentWidth: document.documentElement.scrollWidth,
      containerWidth: document.querySelector('.table-wrap').clientWidth,
      tableWidth: document.querySelector('.data-table').scrollWidth
    }));
    expect(sizes.documentWidth).toBeLessThanOrEqual(sizes.viewport + 2);
    expect(sizes.tableWidth).toBeGreaterThanOrEqual(sizes.containerWidth);
  });
});
