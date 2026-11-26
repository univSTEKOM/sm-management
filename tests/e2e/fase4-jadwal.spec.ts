import { expect, test, type Page } from '@playwright/test';

/*
 * Fase 4: Admin Pusat schedules the initial training of a school that has none (AC-JDA-01, AC-JDA-04),
 * and the cross-school schedule shows it, read-only, in the Pelatihan Awal tab.
 * SMKN 2 Surakarta has no initial training in the demo data.
 */

async function login(page: Page, panel: 'admin' | 'mentor', email: string): Promise<void> {
    await page.goto(`/${panel}/login`);
    await page.getByRole('textbox', { name: 'Alamat email' }).fill(email);
    await page.getByRole('textbox', { name: 'Kata sandi' }).fill('password');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

test('admin pusat schedules an initial training and sees it in the cross-school schedule', async ({ page }) => {
    test.setTimeout(180_000);
    await login(page, 'admin', 'pusat@demo.test');

    await page.goto('/admin/sekolah');
    await page.getByRole('link', { name: 'SMKN 2 Surakarta' }).first().click();

    // The relation manager tabs start on Narahubung; each manager loads lazily when shown.
    await page.getByRole('tab', { name: 'Pelatihan Awal' }).click();
    const panel = page.getByRole('tabpanel', { name: 'Pelatihan Awal' });
    await panel.scrollIntoViewIfNeeded();
    await panel.getByRole('button', { name: 'Jadwalkan Pelatihan Awal' }).click({ timeout: 60_000 });

    // The method and location are fixed, shown as information only.
    await expect(page.getByText('Offline —')).toBeVisible();

    // Filament's dialog wrapper is not reported as visible; target the fields directly.
    await page.getByLabel('Tanggal').fill('2027-03-15');
    await page.getByLabel('Mulai').fill('09:00');
    await page.getByLabel('Selesai').fill('12:00');
    await page.getByLabel('Jenis kegiatan').selectOption({ label: 'Pelatihan' }).catch(async () => {
        await page.getByLabel('Jenis kegiatan').click();
        await page.getByRole('option', { name: 'Pelatihan' }).click();
    });
    await page.getByRole('button', { name: 'Kirim' }).click();
    await expect(page.getByLabel('Tanggal')).toHaveCount(0, { timeout: 30_000 });

    // Cross-school schedule: the new activity is in the Pelatihan Awal tab and has no actions.
    await page.goto('/admin/jadwal');
    // The list tabs are a button group; Pelatihan Awal is the first and selected by default.
    await expect(page.getByRole('button', { name: 'Pelatihan Awal' })).toHaveAttribute('aria-pressed', 'true');
    const row = page.getByRole('row').filter({ hasText: 'SMKN 2 Surakarta' });
    await expect(row).toBeVisible({ timeout: 30_000 });
    await expect(row.getByText('Belum Dimulai')).toBeVisible();
    await expect(row.getByRole('button')).toHaveCount(0);

    // Initial training happens once: the action is gone from the school page.
    await page.getByRole('row').filter({ hasText: 'SMKN 2 Surakarta' }).click();
    await page.getByRole('tab', { name: 'Pelatihan Awal' }).click();
    const again = page.getByRole('tabpanel', { name: 'Pelatihan Awal' });
    await again.scrollIntoViewIfNeeded();
    await expect(again.getByText('Belum Dimulai')).toBeVisible({ timeout: 60_000 });
    await expect(again.getByRole('button', { name: 'Jadwalkan Pelatihan Awal' })).toHaveCount(0);
});
