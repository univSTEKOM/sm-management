import { expect, test, type Page } from '@playwright/test';

/*
 * SP-04: the mentor work page is comfortable on a 360px phone.
 * Criteria: no horizontal scroll, touch targets >= 44px, status update in
 * at most 3 taps (plus typing the mandatory note), no foreign data shown.
 */

async function loginAsMentor(page: Page): Promise<void> {
    await page.goto('/mentor/login');
    await page.getByRole('textbox', { name: 'Alamat email' }).fill('mentor@demo.test');
    await page.getByRole('textbox', { name: 'Kata sandi' }).fill('password');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

async function hasNoHorizontalScroll(page: Page): Promise<boolean> {
    return page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);
}

test('Kegiatan Saya is usable on a 360px phone', async ({ page }) => {
    await loginAsMentor(page);
    await page.goto('/mentor/kegiatan-saya');

    await expect(page.getByRole('heading', { name: 'Perlu Pembaruan' })).toBeVisible();
    const cards = page.getByTestId('kartu-kegiatan');
    await expect(cards).toHaveCount(3);
    expect(await hasNoHorizontalScroll(page)).toBe(true);

    // Only assigned activities; another mentor's meeting link never appears.
    await expect(page.getByText('SMKN 2 Surakarta')).toHaveCount(0);
    expect(await page.content()).not.toContain('rahasia-solo');

    const firstCard = cards.first();
    const statusButton = firstCard.getByRole('button', { name: 'Perbarui Status' });
    const rescheduleButton = firstCard.getByRole('button', { name: 'Jadwal Ulang' });

    for (const button of [statusButton, rescheduleButton]) {
        const box = await button.boundingBox();
        expect(box?.height ?? 0).toBeGreaterThanOrEqual(44);
    }

    await page.screenshot({ path: 'storage/e2e/sp04-kegiatan-saya-360.png', fullPage: true });

    // Tap 1: open the modal.
    await statusButton.click();
    const modal = page.getByRole('dialog');
    await expect(modal.getByText('Perbarui Status Kegiatan')).toBeVisible();
    expect(await hasNoHorizontalScroll(page)).toBe(true);

    // Tap 2: choose the status.
    await modal.getByText('Sedang Berlangsung', { exact: true }).click();
    await modal.getByLabel('Catatan').fill('Pelatihan sedang berlangsung di kampus pusat.');
    await page.screenshot({ path: 'storage/e2e/sp04-modal-status-360.png' });

    // Tap 3: save.
    await modal.getByRole('button', { name: 'Simpan' }).click();

    await expect(page.getByText('Status kegiatan diperbarui')).toBeVisible();
    await expect(firstCard.getByText('Sedang Berlangsung')).toBeVisible();
});

test('reschedule from the phone records the change', async ({ page }) => {
    await loginAsMentor(page);
    await page.goto('/mentor/kegiatan-saya');

    const card = page.getByTestId('kartu-kegiatan').filter({ hasText: 'Sesi 1' });
    await card.getByRole('button', { name: 'Jadwal Ulang' }).click();

    const modal = page.getByRole('dialog');
    await expect(modal.getByText('Jadwal Ulang Kegiatan')).toBeVisible();
    expect(await hasNoHorizontalScroll(page)).toBe(true);

    await modal.getByLabel('Lokasi').fill('Ruang Aula');
    await modal.getByLabel('Alasan perubahan').fill('Sekolah meminta pindah ruangan.');
    await modal.getByRole('button', { name: 'Simpan' }).click();

    await expect(page.getByText('Jadwal diperbarui')).toBeVisible();
    await expect(card.getByText('Ruang Aula')).toBeVisible();
});
