import { expect, test, type Browser, type Page } from '@playwright/test';

/*
 * Fase 3: a mentor proposes a new contact for an assigned school (AC-SCH-21), Admin Pusat
 * approves it from the proposal queue (AC-SCH-22) and the contact appears on the school.
 */

async function login(page: Page, panel: 'admin' | 'mentor', email: string): Promise<void> {
    await page.goto(`/${panel}/login`);
    await page.getByRole('textbox', { name: 'Alamat email' }).fill(email);
    await page.getByRole('textbox', { name: 'Kata sandi' }).fill('password');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

async function newPage(browser: Browser): Promise<Page> {
    return (await browser.newContext()).newPage();
}

test('mentor proposes a contact, admin pusat approves it', async ({ browser }) => {
    test.setTimeout(240_000);

    const kontak = `Bu E2E ${Date.now()}`;

    // Mentor: "Sekolah" shows assigned schools; open the detail and propose.
    const mentor = await newPage(browser);
    await login(mentor, 'mentor', 'mentor@demo.test');
    await mentor.goto('/mentor/sekolah');
    await expect(mentor.getByText('SMAN 1 Semarang')).toBeVisible();
    await expect(mentor.getByText('SMKN 2 Surakarta')).toHaveCount(0);
    await mentor.getByText('SMAN 1 Semarang').click();
    await mentor.getByRole('button', { name: 'Usulkan Perubahan Narahubung' }).click();

    // Filament's dialog wrapper is not reported as visible; target the fields directly.
    await mentor.getByRole('textbox', { name: /^Nama/ }).fill(kontak);
    await mentor.getByRole('textbox', { name: /^Jabatan/ }).fill('Guru BK');
    await mentor.getByRole('textbox', { name: /^Nomor WhatsApp/ }).fill('0812 5555 6666');
    await mentor.getByRole('button', { name: 'Kirim' }).click();
    // Toasts are short-lived on the slow dev server; the queue below proves the submission.
    await expect(mentor.getByRole('textbox', { name: /^Nama/ })).toHaveCount(0, { timeout: 30_000 });

    // Admin Pusat: proposal queue shows it; approve.
    const admin = await newPage(browser);
    await login(admin, 'admin', 'pusat@demo.test');
    await admin.goto('/admin/usulan-narahubung');
    const row = admin.getByRole('row').filter({ hasText: kontak });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: 'Setujui' }).click();
    await admin.getByRole('button', { name: 'Konfirmasi' }).click();
    // The default filter shows open proposals only, so the approved row leaves the list.
    await expect(admin.getByRole('row').filter({ hasText: kontak })).toHaveCount(0, { timeout: 30_000 });

    // The contact is now on the school detail page.
    await admin.goto('/admin/sekolah');
    await admin.getByRole('link', { name: 'SMAN 1 Semarang' }).first().click();
    // Relation managers load lazily once scrolled into view.
    await admin.getByRole('tabpanel', { name: 'Narahubung' }).scrollIntoViewIfNeeded();
    await expect(admin.getByRole('tabpanel', { name: 'Narahubung' }).getByText(kontak, { exact: true })).toBeVisible({ timeout: 30_000 });
});
