const { chromium } = require('playwright');
const { pathToFileURL } = require('url');
const path = require('path');
(async () => {
 const base = path.resolve(__dirname, '..');
 const browser = await chromium.launch({executablePath: process.env.CHROMIUM_PATH || '/Applications/Opera.app/Contents/MacOS/Opera', headless:true});
 for (const [scale,name] of [[1,'banner-772x250.png'],[2,'banner-1544x500.png']]) {
  const context=await browser.newContext({viewport:{width:772,height:250},deviceScaleFactor:scale});
  const page=await context.newPage();
  await page.goto(pathToFileURL(path.join(base,'tools/banner-source.html')).href);
  await page.screenshot({path:path.join(base,'.wordpress-org',name)});
  await context.close();
 }
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
