import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const sourcePath = join(projectRoot, 'resources/css/filament/admin/theme.css');
const tokenPath = join(projectRoot, 'resources/css/design-tokens.css');
const filamentBasePath = join(projectRoot, 'vendor/filament/filament/resources/css/base.css');
const outputPath = join(projectRoot, 'public/css/filament/admin/theme.css');
const configPath = join(projectRoot, 'resources/css/filament/admin/tailwind.config.js');
const cliPath = join(projectRoot, 'node_modules/tailwindcss3/lib/cli.js');

const temporaryDirectory = mkdtempSync(join(tmpdir(), 'inventori-q-filament-theme-'));
const temporaryInput = join(temporaryDirectory, 'theme.css');

try {
    const source = readFileSync(sourcePath, 'utf8')
        .replace("@import '../../../../vendor/filament/filament/resources/css/base.css';", readFileSync(filamentBasePath, 'utf8'))
        .replace("@import '../../design-tokens.css';", readFileSync(tokenPath, 'utf8'))
        .replace("@config 'tailwind.config.js';", '');

    writeFileSync(temporaryInput, source);
    mkdirSync(dirname(outputPath), { recursive: true });

    const result = spawnSync(process.execPath, [
        cliPath,
        '--input', temporaryInput,
        '--output', outputPath,
        '--config', configPath,
        '--minify',
    ], {
        cwd: projectRoot,
        stdio: 'inherit',
        env: { ...process.env, BROWSERSLIST_IGNORE_OLD_DATA: 'true' },
    });

    if (result.status !== 0) {
        process.exitCode = result.status ?? 1;
    }
} finally {
    rmSync(temporaryDirectory, { recursive: true, force: true });
}
