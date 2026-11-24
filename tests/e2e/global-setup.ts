import { execFileSync } from 'node:child_process';

/** Fresh local demo data before each E2E run (local Docker only). */
export default function globalSetup(): void {
    if (process.env.E2E_SKIP_RESEED === '1') {
        return;
    }

    const docker = process.env.DOCKER_BIN ?? '/mnt/c/Program Files/Docker/Docker/resources/bin/docker.exe';

    execFileSync(docker, ['compose', 'exec', '-T', '-u', '1000:1000', '-e', 'HOME=/tmp', 'app', 'php', 'artisan', 'migrate:fresh', '--seed', '--force'], {
        stdio: 'inherit',
    });
}
