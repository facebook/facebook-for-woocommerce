/**
 * WordPress theme helpers for E2E tests
 */

const { execSync } = require('child_process');
const fs = require('fs/promises');
const path = require('path');

const THEME_LOCK_FILE = path.join(process.cwd(), 'tests/e2e/.theme-switch.lock');

function runWpCli(command, opts = {}) {
  const wpPath = process.env.WORDPRESS_PATH;

  if (!wpPath) {
    throw new Error('WORDPRESS_PATH is not set');
  }

  const skipFlags = opts.skipPluginsAndThemes === false ? '' : ' --skip-plugins --skip-themes';

  return execSync(`wp ${command} --path="${wpPath}" --allow-root${skipFlags}`, {
    encoding: 'utf8',
    stdio: ['pipe', 'pipe', 'pipe'],
  });
}

function isThemeInstalled(slug) {
  try {
    runWpCli(`theme is-installed ${slug}`);
    return true;
  } catch (_) {
    return false;
  }
}

async function getActiveThemeStatus() {
  const activeStylesheet = runWpCli('option get stylesheet').trim();
  const activeTemplate = runWpCli('option get template').trim();

  return { activeStylesheet, activeTemplate };
}

async function switchThemeBySlug(slug) {
  try {
    if (!isThemeInstalled(slug)) {
      runWpCli(`theme install ${slug}`);
    }

    const activateOutput = runWpCli(`theme activate ${slug}`);
    const status = await getActiveThemeStatus();

    if (status.activeStylesheet !== slug) {
      return {
        success: false,
        slug,
        activeStylesheet: status.activeStylesheet,
        activeTemplate: status.activeTemplate,
        error: `Activation did not stick (expected: ${slug}, actual: ${status.activeStylesheet}). wp output: ${activateOutput.trim()}`,
      };
    }

    return {
      success: true,
      slug,
      activeStylesheet: status.activeStylesheet,
      activeTemplate: status.activeTemplate,
    };
  } catch (error) {
    return {
      success: false,
      slug,
      error: error?.stderr?.toString?.().trim() || error?.message || 'Unknown WP-CLI error',
    };
  }
}

async function acquireThemeLock(timeoutMs = 45000) {
  const start = Date.now();
  const token = `${process.pid}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  const staleMs = 120000;

  while (Date.now() - start < timeoutMs) {
    try {
      await fs.writeFile(THEME_LOCK_FILE, token, { flag: 'wx' });
      return token;
    } catch (error) {
      if (error.code !== 'EEXIST') {
        throw error;
      }

      try {
        const current = (await fs.readFile(THEME_LOCK_FILE, 'utf8')).trim();
        const [pidRaw, createdRaw] = current.split('-');
        const lockPid = Number(pidRaw);
        const createdAt = Number(createdRaw);
        const lockAgeMs = Number.isFinite(createdAt) ? Date.now() - createdAt : Number.MAX_SAFE_INTEGER;
        const processAlive = Number.isFinite(lockPid) && lockPid > 0 ? isProcessAlive(lockPid) : false;

        if (!processAlive || lockAgeMs > staleMs) {
          await fs.unlink(THEME_LOCK_FILE).catch(() => {});
          await new Promise((resolve) => setTimeout(resolve, 25));
          continue;
        }
      } catch {
        // If lock disappeared or can't be parsed, retry shortly.
      }

      await new Promise((resolve) => setTimeout(resolve, 120));
    }
  }

  throw new Error('Timed out waiting for theme lock');
}

function isProcessAlive(pid) {
  try {
    process.kill(pid, 0);
    return true;
  } catch {
    return false;
  }
}

async function releaseThemeLock(token) {
  if (!token) {
    return;
  }

  try {
    const current = await fs.readFile(THEME_LOCK_FILE, 'utf8');
    if (current.trim() === token) {
      await fs.unlink(THEME_LOCK_FILE);
    }
  } catch {
    // lock already released
  }
}

module.exports = {
  runWpCli,
  getActiveThemeStatus,
  switchThemeBySlug,
  acquireThemeLock,
  releaseThemeLock,
};
