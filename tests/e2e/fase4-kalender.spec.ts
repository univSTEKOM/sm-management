import { expect, test, type Page } from '@playwright/test';

/*
 * T-18 / SP-07: the schedule calendar (read-only) in both panels. Demo data (relative to today):
 *  - SMAN 1 Semarang (Cabang Semarang, Mentor Demo): Pelatihan Awal -2 days, Sesi 1 +3, Sesi 2 +10
 *  - SMKN 2 Surakarta (Cabang Surakarta, Mentor Lain): Sesi 1 +4
 */

async function login(page: Page, panel: 'admin' | 'mentor', email: string): Promise<void> {
    await page.goto(`/${panel}/login`);
    await page.getByRole('textbox', { name: 'Alamat email' }).fill(email);
    await page.getByRole('textbox', { name: 'Kata sandi' }).fill('password');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 90_000 });
}

function watchErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
    page.on('pageerror', (e) => errors.push(String(e)));

    return errors;
}

async function openCalendar(page: Page, panel: 'admin' | 'mentor'): Promise<void> {
    await page.goto(`/${panel}/kalender-jadwal`);
    await page.locator('.fc').first().waitFor({ timeout: 90_000 });
    await page.locator('[data-kegiatan-id]').first().waitFor({ timeout: 90_000 });
}

const noHorizontalScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

test.describe('desktop', () => {
    test('Admin Pusat: month, week and agenda views, event content, detail on click, filters', async ({ page }) => {
        test.setTimeout(240_000);
        const errors = watchErrors(page);
        await login(page, 'admin', 'pusat@demo.test');
        await openCalendar(page, 'admin');

        // Month view (default): all four demo events, colour by stage, status spelled out.
        await expect(page.locator('.fc .fc-dayGridMonth-view, .fc-daygrid').first()).toBeVisible();
        const semarang = page.locator('[data-kegiatan-id]').filter({ hasText: 'SMAN 1 Semarang' });
        const solo = page.locator('[data-kegiatan-id]').filter({ hasText: 'SMKN 2 Surakarta' });
        await expect(semarang).toHaveCount(3);
        await expect(solo).toHaveCount(1);
        await expect(solo.first()).toContainText('Tindak Lanjut');
        await expect(solo.first()).toContainText('Mentor Lain');
        await expect(solo.first()).toContainText('Belum Dimulai');
        const awal = semarang.filter({ hasText: 'Pelatihan Awal' }).first();
        const tl = semarang.filter({ hasText: 'Tindak Lanjut' }).first();
        const warna = (l: ReturnType<Page['locator']>) => l.evaluate((el) => getComputedStyle(el).backgroundColor);
        expect(await warna(awal)).not.toBe(await warna(tl));
        expect(await noHorizontalScroll(page)).toBe(true);

        // Week and agenda views.
        await page.getByRole('button', { name: 'Minggu' }).click();
        await expect(page.locator('.fc-timegrid').first()).toBeVisible();
        await page.getByRole('button', { name: 'Agenda' }).click();
        await expect(page.locator('.fc-list').first()).toBeVisible();
        await expect(page.locator('.fc-list')).toContainText('SMKN 2 Surakarta');
        await page.getByRole('button', { name: 'Bulan' }).click();
        await expect(page.locator('.fc-daygrid').first()).toBeVisible();

        // Click opens the detail of that very activity (read-only: Admin Pusat may reschedule).
        await solo.first().click();
        const detail = page.getByTestId('kalender-detail');
        await expect(detail).toBeVisible({ timeout: 60_000 });
        await expect(page.getByRole('dialog').getByText('SMKN 2 Surakarta').first()).toBeVisible();
        await expect(detail).toContainText('Cabang Surakarta');
        await expect(detail).toContainText('Mentor Lain');
        await expect(detail).toContainText('Belum Dimulai');
        await expect(page.getByRole('button', { name: 'Jadwal Ulang' })).toBeVisible();
        await page.getByRole('dialog').getByRole('button', { name: 'Tutup' }).last().click();
        await expect(detail).toHaveCount(0);

        // Filters: stage, mentor, status, branch.
        await page.getByLabel('Filter jenis').selectOption('awal');
        await expect(page.locator('[data-kegiatan-id]')).toHaveCount(1, { timeout: 60_000 });
        await expect(page.locator('[data-kegiatan-id]').first()).toContainText('Pelatihan Awal');
        await page.getByLabel('Filter jenis').selectOption('');
        await expect(page.locator('[data-kegiatan-id]')).toHaveCount(4, { timeout: 60_000 });

        await page.getByLabel('Filter mentor').selectOption({ label: 'Mentor Lain' });
        await expect(page.locator('[data-kegiatan-id]')).toHaveCount(1, { timeout: 60_000 });
        await page.getByLabel('Filter mentor').selectOption('');

        await page.getByLabel('Filter cabang').selectOption({ label: 'Cabang Semarang' });
        await expect(page.locator('[data-kegiatan-id]')).toHaveCount(3, { timeout: 60_000 });
        await page.getByLabel('Filter status').selectOption('selesai');
        await expect(page.locator('[data-kegiatan-id]')).toHaveCount(0, { timeout: 60_000 });

        expect(errors).toEqual([]);
    });

    test('Admin Cabang sees only its own branch and has no branch filter', async ({ page }) => {
        test.setTimeout(240_000);
        await login(page, 'admin', 'cabang.semarang@demo.test');
        await openCalendar(page, 'admin');

        await expect(page.locator('[data-kegiatan-id]').filter({ hasText: 'SMAN 1 Semarang' })).toHaveCount(3);
        await expect(page.locator('[data-kegiatan-id]').filter({ hasText: 'SMKN 2 Surakarta' })).toHaveCount(0);
        await expect(page.getByLabel('Filter cabang')).toHaveCount(0);
    });

    test('mentor: own activity is rescheduled from the calendar and the change shows immediately', async ({ page }) => {
        test.setTimeout(240_000);
        const errors = watchErrors(page);
        await login(page, 'mentor', 'mentor@demo.test');
        await openCalendar(page, 'mentor');

        // Cross-school view: the other mentor's activity is visible but read-only.
        const lain = page.locator('[data-kegiatan-id]').filter({ hasText: 'SMKN 2 Surakarta' }).first();
        await lain.click();
        await expect(page.getByTestId('kalender-detail')).toBeVisible({ timeout: 60_000 });
        await expect(page.getByRole('button', { name: 'Jadwal Ulang' })).toHaveCount(0);
        await page.getByRole('dialog').getByRole('button', { name: 'Tutup' }).last().click();

        // Own activity: Jadwal Ulang goes through RescheduleKegiatan (reason is mandatory).
        const sesi = page.locator('[data-kegiatan-id]').filter({ hasText: 'SMAN 1 Semarang' }).filter({ hasText: 'Tindak Lanjut' }).first();
        const id = await sesi.getAttribute('data-kegiatan-id');
        await sesi.click();
        await expect(page.getByTestId('kalender-detail')).toBeVisible({ timeout: 60_000 });
        await page.getByRole('button', { name: 'Jadwal Ulang' }).click();
        await expect(page.getByText('Jadwal Ulang Kegiatan')).toBeVisible({ timeout: 60_000 });
        await page.getByLabel('Mulai').fill('10:00');
        await page.getByLabel('Selesai').fill('12:00');
        await page.getByLabel('Alasan perubahan').fill('Sekolah ujian di jam pertama.');
        await page.getByRole('button', { name: 'Simpan' }).click();

        const moved = page.locator(`[data-kegiatan-id="${id}"]`);
        await expect(moved).toContainText(/10[.:]00/, { timeout: 60_000 });
        await expect(moved).toContainText(/12[.:]00/);
        expect(errors).toEqual([]);
    });
});

test.describe('phone 360px', () => {
    test.use({ viewport: { width: 360, height: 800 } });

    test('mentor panel opens on the agenda view without horizontal scroll', async ({ page }) => {
        test.setTimeout(240_000);
        const errors = watchErrors(page);
        await login(page, 'mentor', 'mentor@demo.test');
        await openCalendar(page, 'mentor');

        await expect(page.locator('.fc-list').first()).toBeVisible({ timeout: 30_000 });
        await expect(page.locator('.fc-list')).toContainText('SMAN 1 Semarang');
        expect(await noHorizontalScroll(page)).toBe(true);

        await page.getByRole('button', { name: 'Bulan' }).click();
        await expect(page.locator('.fc-daygrid').first()).toBeVisible();
        expect(await noHorizontalScroll(page)).toBe(true);
        await page.getByRole('button', { name: 'Minggu' }).click();
        await expect(page.locator('.fc-timegrid').first()).toBeVisible();
        expect(await noHorizontalScroll(page)).toBe(true);
        await page.screenshot({ path: 'storage/e2e/kalender-360.png', fullPage: true });

        expect(errors).toEqual([]);
    });
});
