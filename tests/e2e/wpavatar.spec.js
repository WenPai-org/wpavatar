const { test, expect } = require('@playwright/test');
const baseURL = process.env.WPAVATAR_E2E_URL || 'http://127.0.0.1:18993';
const pageId = process.env.WPAVATAR_E2E_PAGE_ID;

test('settings save, avatar route replacement, and cache purge', async ({ page }) => {
  test.setTimeout(120000);
  await page.goto(`${baseURL}/wp-login.php`);
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('admin-pass');
  await Promise.all([page.waitForURL(/wp-admin/), page.locator('#wp-submit').click()]);

  await page.goto(`${baseURL}/wp-admin/options-general.php?page=wpavatar-settings`);
  await expect(page.locator('h1')).toContainText('文派头像设置');
  await page.getByRole('radio', { name: '第三方镜像' }).check({ force: true });
  await page.locator('select[name="wpavatar_third_party_mirror"]').selectOption('libravatar.org');
  await page.locator('input[name="wpavatar_timeout"]').fill('11');
  await Promise.all([
    page.waitForURL(/settings-updated=true/),
    page.locator('#wpavatar-basic-form button[type="submit"]').click(),
  ]);
  await expect(page.getByRole('radio', { name: '第三方镜像' })).toBeChecked();
  await expect(page.locator('select[name="wpavatar_third_party_mirror"]')).toHaveValue('libravatar.org');
  await expect(page.locator('input[name="wpavatar_timeout"]')).toHaveValue('11');

  await page.goto(`${baseURL}/?p=${pageId}`);
  const avatar = page.locator('img.avatar').first();
  await expect(avatar).toBeVisible();
  await expect(avatar).toHaveAttribute('src', /^https:\/\/libravatar\.org\/avatar\//);

  await page.goto(`${baseURL}/wp-admin/options-general.php?page=wpavatar-settings&tab=cache`);
  await expect(page.locator('#wpavatar-section-cache')).toBeVisible();
  page.once('dialog', dialog => dialog.accept());
  await page.locator('#purge-cache').click();
  await expect(page.locator('#wpavatar-status')).toContainText(/已清空 [1-9][0-9]* 个缓存文件/);
  await expect(page.locator('#cache-stats')).toContainText(/缓存文件数:\s*0/);
});
