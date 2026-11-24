import { expect, test, type Browser, type Page } from '@playwright/test';

/*
 * Fase 2 (T-11): Superadmin creates a mentor account, the mentor must change the
 * initial password at first login (FR-AUTH-03), and a deactivated account can no
 * longer use its session (AC-AUTH-03). Runs against the reseeded local database.
 *
 * Keep the number of logins in the whole E2E suite at 5 or fewer: Filament rate-limits
 * login to 5 attempts per minute per IP.
 */

async function login(page: Page, panel: 'admin' | 'mentor', email: string, password: string): Promise<void> {
    await page.goto(`/${panel}/login`);
    await page.getByRole('textbox', { name: 'Alamat email' }).fill(email);
    await page.getByRole('textbox', { name: 'Kata sandi' }).fill(password);
    await page.getByRole('button', { name: 'Masuk' }).click();
}

async function newPage(browser: Browser): Promise<Page> {
    return (await browser.newContext()).newPage();
}

test('superadmin creates a mentor who must change the initial password, then deactivates it', async ({ browser }) => {
    const email = `mentor.e2e.${Date.now()}@demo.test`;

    // Superadmin creates the account.
    const admin = await newPage(browser);
    await login(admin, 'admin', 'superadmin@demo.test', 'password');
    await admin.waitForURL((url) => !url.pathname.endsWith('/login'));
    await admin.goto('/admin/pengguna');
    await admin.getByRole('button', { name: 'Buat Pengguna' }).click();

    const createModal = admin.getByRole('dialog');
    await createModal.getByLabel('Nama').fill('Mentor E2E');
    await createModal.getByLabel('Email').fill(email);
    await createModal.getByLabel('Role').selectOption({ label: 'Mentor' }).catch(async () => {
        // Filament renders a searchable select; fall back to clicking the option.
        await createModal.getByLabel('Role').click();
        await admin.getByRole('option', { name: 'Mentor' }).click();
    });
    await createModal.getByRole('textbox', { name: 'Password awal' }).fill('awalan123');
    await createModal.getByRole('button', { name: 'Buat', exact: true }).click();
    await expect(admin.getByRole('cell', { name: email })).toBeVisible();
    await admin.screenshot({ path: 'storage/e2e/fase2-pengguna-list.png', fullPage: true });

    // First login is forced to the profile page.
    const mentor = await newPage(browser);
    await login(mentor, 'mentor', email, 'awalan123');
    await mentor.waitForURL(/\/mentor\/profile$/);
    await expect(mentor.getByText('wajib mengganti password awal')).toBeVisible();

    await mentor.getByRole('textbox', { name: 'Password saat ini' }).fill('awalan123');
    await mentor.getByRole('textbox', { name: /^Password baru/ }).fill('rahasiaBaru9');
    await mentor.getByRole('textbox', { name: 'Konfirmasi password baru' }).fill('rahasiaBaru9');
    await mentor.getByRole('button', { name: 'Simpan' }).click();
    await mentor.waitForURL((url) => !url.pathname.endsWith('/profile'));

    // The obligation is gone and the session is still valid.
    await mentor.goto('/mentor/kegiatan-saya');
    await expect(mentor).toHaveURL(/\/mentor\/kegiatan-saya$/);
    await expect(mentor.getByRole('heading', { name: 'Perlu Pembaruan' })).toBeVisible();

    // Superadmin deactivates the account; the mentor session ends and login is refused.
    const row = admin.getByRole('row').filter({ hasText: email });
    await row.getByRole('button', { name: 'Nonaktifkan' }).click();
    await admin.getByRole('alertdialog').getByRole('button', { name: 'Konfirmasi' }).click();
    await expect(admin.getByText('Akun dinonaktifkan')).toBeVisible();

    await mentor.goto('/mentor/kegiatan-saya');
    await expect(mentor).toHaveURL(/\/mentor\/login$/);
    // Refused re-login of the deactivated account is covered by Pest (AC-AUTH-03): Filament
    // allows 5 login attempts per minute per IP and the whole suite shares one IP.
});

test('admin pusat does not see master data or account menus', async ({ page }) => {
    await login(page, 'admin', 'pusat@demo.test', 'password');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));

    await expect(page.getByRole('link', { name: 'Pengguna' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Cabang' })).toHaveCount(0);

    const response = await page.goto('/admin/pengguna');
    expect(response?.status()).toBe(403);
});
