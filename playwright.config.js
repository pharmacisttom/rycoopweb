import { defineConfig } from '@playwright/test';
export default defineConfig({
 testDir:'tests/browser',workers:1,timeout:45000,
 use:{baseURL:'http://127.0.0.1:4175',headless:true,...(process.env.PLAYWRIGHT_CHROME_PATH?{launchOptions:{executablePath:process.env.PLAYWRIGHT_CHROME_PATH}}:{})},
 webServer:{command:'node scripts/staticPreviewServer.mjs',url:'http://127.0.0.1:4175',reuseExistingServer:false},
 reporter:'list'
});
