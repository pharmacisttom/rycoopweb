import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import jsQR from 'jsqr';

const root = fileURLToPath(new URL('../', import.meta.url));
const cases = JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', ['tests/QrDecodeFixtures.php'], { cwd: root, encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 }));

// Rasterize the SVG's filled unit squares, then decode pixels with an independent reader.
// No OTP URI is sent over the network, written to a file or included in success logs.
function decodeSvg(svg, margin, nativeSize = false) {
  assert.ok(svg.startsWith('<svg') && svg.endsWith('</svg>'));
  assert.ok(!/<(?:script|image|foreignObject)\b/i.test(svg), 'SVG must contain no active or external content');
  const view = svg.match(/viewBox="0 0 (\d+) (\d+)"/);
  assert.ok(view, 'SVG has a square viewBox');
  const units = Number(view[1]); assert.equal(units, Number(view[2]));
  const naturalWidth=Number(svg.match(/width="(\d+)"/)[1]);
  assert.ok(naturalWidth>=units*4 && naturalWidth%units===0, 'intrinsic image uses whole module pixels');
  const width = nativeSize ? naturalWidth : units * 6;
  const pixels = new Uint8ClampedArray(width * width * 4); pixels.fill(255);
  const modules = [...svg.matchAll(/M(\d+),(\d+)h1v1h-1z/g)];
  assert.ok(modules.length > 0);
  for (const [, rawX, rawY] of modules) {
    const x = Number(rawX), y = Number(rawY);
    assert.ok(x >= margin && y >= margin && x < units-margin && y < units-margin, 'quiet zone is empty');
    for (let py=Math.round(y*width/units); py<Math.round((y+1)*width/units); py++) {
      for (let px=Math.round(x*width/units); px<Math.round((x+1)*width/units); px++) {
        const i=(py*width+px)*4; pixels[i]=pixels[i+1]=pixels[i+2]=0;
      }
    }
  }
  const version=(units-margin*2-17)/4;
  assert.ok(Number.isInteger(version) && version>=1 && version<=40);
  return { value: jsQR(pixels,width,width)?.data, version };
}

let largeVersion=false;
for (const test of cases) {
  const decoded=decodeSvg(test.svg,test.margin);
  assert.equal(decoded.value,test.payload,`${test.name}: independent QR round trip`);
  largeVersion ||= decoded.version>10;
  assert.equal(decodeSvg(test.svg,test.margin,true).value,test.payload,`${test.name}: native display size`);
  if (['standard-otp-uri','controller-enrollment'].includes(test.name)) {
    const uri=new URL(decoded.value);
    assert.equal(uri.protocol,'otpauth:'); assert.equal(uri.hostname,'totp');
    assert.equal(uri.searchParams.get('algorithm'),'SHA1'); assert.equal(uri.searchParams.get('digits'),'6'); assert.equal(uri.searchParams.get('period'),'30');
  }
  console.log(`PASS: ${test.name} (QR version ${decoded.version})`);
}
assert.ok(largeVersion,'test data covers versions above the old encoder limit');
console.log(`QR decode round trips passed (${cases.length} cases, including actual enrollment response).`);
