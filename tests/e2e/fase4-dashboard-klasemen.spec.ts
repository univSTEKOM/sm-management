import { expect, test, type Page } from '@playwright/test';

/*
 * Dashboard: horizontal bar chart of the klasemen kategori (PRD 13.8, FR-RNK-03). Demo data:
 * Jasa Layanan Bisnis 3, Akuntan Bisnis dan Perpajakan 1, Barbershop 1 (SMAN 1 Semarang is a
 * candidate school and is not counted), the other three categories 0.
 */

async function login(page: Page, panel: 'admin' | 'mentor', email: string): Promise<void> {
    await page.goto(`/${panel}/login`);
    await page.getByRole('textbox', { name: 'Alamat email' }).fill(email);
    await page.getByRole('textbox', { name: 'Kata sandi' }).fill('password');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 90_000 });
}

/**
 * Reads the chart as Filament rendered it: the data and options sit in the wrapper's x-data
 * (Chart.js itself is bundled, not global), and the canvas must really hold drawn pixels.
 */
async function chart(page: Page) {
    const canvas = page.locator('canvas').first();
    await canvas.waitFor({ timeout: 90_000 });
    await page.waitForFunction(() => {
        const c = document.querySelector('canvas') as HTMLCanvasElement | null;
        if (!c || c.width === 0) return false;
        const px = c.getContext('2d')!.getImageData(0, 0, c.width, c.height).data;
        for (let i = 3; i < px.length; i += 4) if (px[i] > 0) return true;

        return false;
    }, null, { timeout: 60_000 });

    return page.evaluate(() => {
        const wrapper = document.querySelector('canvas')!.closest('[data-chart-type]') as HTMLElement;
        const decoded = Array.from(wrapper.getAttribute('x-data')!.matchAll(/JSON\.parse\('(.*?)'\)/gs)).map((m) => JSON.parse(JSON.parse('"' + m[1] + '"')));
        const data = decoded.find((d) => d.datasets)!;
        const options = decoded.find((d) => d.indexAxis || d.scales) ?? {};

        return {
            type: wrapper.getAttribute('data-chart-type'),
            indexAxis: options.indexAxis as string | undefined,
            labels: data.labels.map((l: string | string[]) => (Array.isArray(l) ? l.join(' ') : l)) as string[],
            data: data.datasets[0].data as number[],
            colors: data.datasets[0].backgroundColor as string[],
            ariaLabel: (canvas_aria()),
        };

        function canvas_aria(): string | null {
            return document.querySelector('[role="img"]')?.getAttribute('aria-label') ?? null;
        }
    });
}

const noHorizontalScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);

const EXPECTED = [3, 1, 1, 0, 0, 0];

test.describe('desktop', () => {
    test('Admin Pusat: ranked horizontal bars, jumlah sekolah mitra unik per kategori', async ({ page }) => {
        test.setTimeout(240_000);
        const errors: string[] = [];
        page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
        page.on('pageerror', (e) => errors.push(String(e)));

        await login(page, 'admin', 'pusat@demo.test');
        await page.goto('/admin');
        await expect(page.getByText('Klasemen Kategori Pelatihan')).toBeVisible({ timeout: 60_000 });
        await expect(page.getByText('Satu sekolah dapat terhitung di lebih dari satu kategori.')).toBeVisible();

        const c = await chart(page);
        expect(c.type).toBe('bar');
        expect(c.indexAxis).toBe('y');
        expect(c.data).toEqual(EXPECTED);
        expect(c.labels[0]).toContain('1. Jasa Layanan Bisnis');
        expect(c.labels[0]).toContain('3 sekolah');
        // Dense ranking: Akuntan and Barbershop share rank 2, empty categories rank 3.
        expect(c.labels[1]).toMatch(/^2\. /);
        expect(c.labels[2]).toMatch(/^2\. /);
        expect(c.labels[3]).toMatch(/^3\. /);
        expect(c.ariaLabel).toContain('Klasemen Kategori Pelatihan');
        expect(c.colors[0]).toBe('#7F1D1D');
        expect(c.colors[1]).toBe('#B91C1C');
        expect(await noHorizontalScroll(page)).toBe(true);

        await page.locator('canvas').first().scrollIntoViewIfNeeded();
        await page.screenshot({ path: 'storage/e2e/dashboard-klasemen-desktop.png', fullPage: true });
        expect(errors).toEqual([]);
    });

    test('Admin Cabang and mentor see the same province-wide ranking, not their own branch', async ({ page }) => {
        test.setTimeout(240_000);

        await login(page, 'admin', 'cabang.solo@demo.test');
        await page.goto('/admin');
        expect((await chart(page)).data).toEqual(EXPECTED);

        const mentor = await page.context().browser()!.newContext();
        const m = await mentor.newPage();
        await login(m, 'mentor', 'mentor@demo.test');
        await m.goto('/mentor');
        await expect(m.getByText('Klasemen Kategori Pelatihan')).toBeVisible({ timeout: 60_000 });
        expect((await chart(m)).data).toEqual(EXPECTED);
    });
});

test.describe('phone 360px', () => {
    test.use({ viewport: { width: 360, height: 800 } });

    test('the chart fits the screen without horizontal scroll', async ({ page }) => {
        test.setTimeout(240_000);
        await login(page, 'mentor', 'mentor@demo.test');
        await page.goto('/mentor');
        await expect(page.getByText('Klasemen Kategori Pelatihan')).toBeVisible({ timeout: 60_000 });

        expect((await chart(page)).data).toEqual(EXPECTED);
        expect(await noHorizontalScroll(page)).toBe(true);
        const box = await page.locator('canvas').first().boundingBox();
        expect(box!.width).toBeLessThanOrEqual(360);
        await page.screenshot({ path: 'storage/e2e/dashboard-klasemen-360.png', fullPage: true });
    });
});
