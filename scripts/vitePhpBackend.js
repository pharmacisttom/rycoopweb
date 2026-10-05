import { spawn } from 'node:child_process';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

// Development only: keep PHP sessions inside the writable project directory.
export function phpBackendPlugin(target, external) {
  return {
    name: 'local-php-backend',
    apply: 'serve',
    async configureServer(server) {
      if (external) return;
      const root = server.config.root;
      const sessions = path.join(root, 'storage', 'sessions');
      await mkdir(sessions, { recursive: true });
      const address = new URL(target);
      const child = spawn(process.env.PHP_BINARY || 'php', ['-S', `${address.hostname}:${address.port}`, 'public/index.php'], {
        cwd: root,
        windowsHide: true,
        stdio: ['ignore', 'ignore', 'pipe'],
        env: { ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: 'http://localhost:3000', SESSION_SECURE: 'false', SESSION_SAVE_PATH: sessions },
      });
      let failure;
      child.on('error', error => { failure = error; });
      child.on('exit', code => { if (code && !failure) failure = new Error(`PHP backend exited (${code})`); });
      child.stderr.on('data', data => { if (String(data).includes('Failed to listen')) failure = new Error(`PHP backend port ${address.port} is already in use. Set VITE_PHP_BACKEND to your existing PHP server.`); });
      const stop = () => { if (child.exitCode === null) child.kill(); };
      process.once('exit', stop);
      server.httpServer?.once('close', () => { stop(); process.removeListener('exit', stop); });
      for (let attempt = 0; attempt < 40; attempt++) {
        if (failure) { stop(); throw failure; }
        try {
          const response = await fetch(`${target}/csrf-token`, { signal: AbortSignal.timeout(500) });
          if (response.ok && (await response.json()).token) {
            server.config.logger.info(`PHP backend ready: ${target}`);
            return;
          }
        } catch { /* PHP may still be starting. */ }
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      stop();
      throw new Error('PHP backend did not start. Check PHP_BINARY and the local PHP installation.');
    },
  };
}
