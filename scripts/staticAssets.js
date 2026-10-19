import fs from 'node:fs';
import path from 'node:path';
export function staticAssets() {
 return {name:'public-static-assets',generateBundle(){
  const visit=(dir,prefix)=>{if(!fs.existsSync(dir))return;for(const entry of fs.readdirSync(dir,{withFileTypes:true})){
   const file=path.join(dir,entry.name),name=prefix+'/'+entry.name;
   if(entry.isDirectory())visit(file,name);else if(/\.(?:png|jpe?g|webp|gif|svg|ico|woff2?|ttf|otf|pdf|xlsx|csv|txt)$/i.test(name))this.emitFile({type:'asset',fileName:name,source:fs.readFileSync(file)});
  }};
  for(const folder of ['assets','img','templates'])visit(path.resolve('public',folder),folder);
 }};
}
