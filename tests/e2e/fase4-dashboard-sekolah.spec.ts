import { expect, test, type Page } from '@playwright/test';

/* Dashboard Sekolah Mitra: section order, one row per school, search, sticky header, detail link. */

async function login(page: Page, panel: 'admin' | 'mentor', email: string): Promise<void> {
    await page.goto(`/${panel}/login`);
    await page.getByRole('textbox', { name: 'Alamat email' }).fill(email);
    await page.getByRole('textbox', { name: 'Kata sandi' }).fill('password');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 90_000 });
}

const noHorizontalScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

test.describe('desktop', () => {
    test('Admin Pusat: header, statistics, klasemen, then the table; search and detail work', async ({ page }) => {
        test.setTimeout(240_000);
        await login(page, 'admin', 'pusat@demo.test');
        await page.goto('/admin');

        await expect(page.getByRole('heading', { name: 'Dashboard Sekolah Mitra' })).toBeVisible({ timeout: 60_000 });
        const klasemen = page.getByRole('heading', { name: 'Klasemen Kategori Pelatihan' });
        const tabel = page.getByRole('heading', { name: 'Daftar Sekolah Mitra' });
        await expect(klasemen).toBeVisible();
        await expect(tabel).toBeVisible();

        const y = async (l: import('@playwright/test').Locator) => (await l.boundingBox())!.y;
        const stat = page.getByText('Mitra resmi', { exact: true });
        expect(await y(stat)).toBeLessThan(await y(klasemen));
        expect(await y(klasemen)).toBeLessThan(await y(tabel));

        for (const kolom of ['Nama sekolah', 'Jenjang', 'Kabupaten/Kota', 'Status program', 'Mentor PJ', 'Narahubung', 'Sertifikat', 'Status MOU', 'Progres tindak lanjut', 'Update mentor']) {
            await expect(page.getByRole('columnheader', { name: kolom })).toBeVisible();
        }
        // One school per row, never cards.
        await expect(page.getByTestId('kartu-sekolah')).toHaveCount(0);
        await expect(page.getByRole('row').filter({ hasText: 'SMAN 1 Semarang' })).toHaveCount(1);

        await page.getByPlaceholder('Cari nama sekolah').fill('Surakarta');
        await expect(page.getByRole('row').filter({ hasText: 'SMAN 1 Surakarta' })).toBeVisible({ timeout: 30_000 });
        await expect(page.getByRole('row').filter({ hasText: 'SMAN 1 Semarang' })).toHaveCount(0);

        await page.screenshot({ path: 'storage/e2e/dashboard-sekolah-desktop.png', fullPage: true });
        await page.getByRole('row').filter({ hasText: 'SMAN 1 Surakarta' }).getByRole('link', { name: 'Detail' }).click();
        await expect(page).toHaveURL(/\/admin\/sekolah\/\d+/);
    });

    test('table header stays visible while the table body scrolls', async ({ page }) => {
        test.setTimeout(240_000);
        await login(page, 'admin', 'pusat@demo.test');
        await page.goto('/admin');
        const th = page.getByRole('columnheader', { name: 'Nama sekolah' });
        await expect(th).toBeVisible({ timeout: 60_000 });
        expect(await th.evaluate((el) => getComputedStyle(el).position)).toBe('sticky');
    });
});

test.describe('phone 360px', () => {
    test.use({ viewport: { width: 360, height: 800 } });

    test('page has no horizontal scroll; the table scrolls inside its own box', async ({ page }) => {
        test.setTimeout(240_000);
        await login(page, 'mentor', 'mentor@demo.test');
        await page.goto('/mentor');
        await expect(page.getByRole('heading', { name: 'Dashboard Sekolah Mitra' })).toBeVisible({ timeout: 60_000 });
        await expect(page.getByRole('columnheader', { name: 'Nama sekolah' })).toBeAttached({ timeout: 60_000 });
        expect(await noHorizontalScroll(page)).toBe(true);
        await page.screenshot({ path: 'storage/e2e/dashboard-sekolah-360.png', fullPage: true });
    });
});
