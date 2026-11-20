import React, {useCallback, useEffect, useMemo, useState, useId, useRef} from 'react';
import {memberAdminApi} from '../../services/memberAdminApi';
import {mediaLibraryApi} from '../../services/mediaLibraryApi';
import './HomeCompositionEditor.css';

const NAMES = {
  hero: 'Hero / ป้ายต้อนรับ',
  highlights: 'Highlight Slider (5–6 ข้อความ)',
  rates_news: 'อัตราดอกเบี้ยและข่าวสาร',
  quick_services: 'บริการด่วน',
  statistics: 'ข้อมูลสถิติ',
  products: 'ผลิตภัณฑ์สินเชื่อ',
  calculator: 'เครื่องคำนวณ',
  welfare: 'สวัสดิการ'
};
const copy = value => JSON.parse(JSON.stringify(value));
const makeSlide = () => ({id:'slide-'+crypto.randomUUID().replaceAll('-','').slice(0,16),title:'',body:'',link:'',image_url:'',mobile_image_url:''});
const normalBackground = value => ({image_url:value?.image_url||'', mobile_image_url:value?.mobile_image_url||'', overlay_opacity:value?.overlay_opacity??60, position:value?.position||'center'});
const allowedImage = path => !path || (['/storage/uploads/','/uploads/','/assets/'].some(prefix=>path.startsWith(prefix)) && /^[a-zA-Z0-9_./-]+$/.test(path) && ['.jpg','.jpeg','.png','.webp'].some(ext=>path.toLowerCase().endsWith(ext)) && !path.includes('..') && !path.includes('//'));
const prepare = value => ({...copy(value),background:normalBackground(value.background),slides:value.slides.map(item=>({...item,image_url:item.image_url||'',mobile_image_url:item.mobile_image_url||''}))});
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
function UploadImageField({label,description,value,onUpload,onClear,disabled,uploading}){
  const inputId=useId();
  const inputRef=useRef(null);
  return <div className="hc-upload-field">
    <div className="hc-upload-heading"><strong>{label}</strong><small>{description}</small></div>
    <input ref={inputRef} id={inputId} className="hc-upload-native" type="file"
      accept="image/jpeg,image/png,image/webp" disabled={disabled}
      onChange={async event=>{
        const file=event.currentTarget.files?.[0];
        event.currentTarget.value='';
        if(file)await onUpload(file);
      }} aria-label={'เลือกไฟล์'+label}/>
    <button type="button" className="hc-upload-button" disabled={disabled}
      onClick={event=>{event.stopPropagation();inputRef.current?.click();}}>
      {uploading?'กำลังอัปโหลด…':'เลือกรูปภาพจากเครื่อง'}
    </button>
    {value&&allowedImage(value)&&<div className="hc-upload-existing">
      <img src={value} alt={'ตัวอย่าง'+label} loading="lazy"/>
      <button type="button" disabled={disabled} onClick={onClear}>นำรูปออก</button>
    </div>}
    {!value&&<small className="hc-upload-empty">ยังไม่ได้เลือกรูปภาพ — ใช้พื้นหลังหลักหรือสีเดิม</small>}
  </div>;
}
function Preview({composition}) {
  return <div className="hc-preview" aria-label="ตัวอย่างการจัดลำดับหน้าแรก">
    {composition.order.filter(id=>composition.visible[id]).map(id=><section key={id} className={'hc-preview-section hc-preview-'+id}>
      <strong>{NAMES[id]}</strong>
      {id==='highlights'&&<div>{composition.slides.map((slide,i)=><p key={slide.id}>{i+1}. {slide.title||'ยังไม่ได้กรอกหัวข้อ'}</p>)}</div>}
    </section>)}
  </div>;
}
export default function HomeCompositionEditor(){
  const [server,setServer]=useState(null);
  const [model,setModel]=useState(null);
  const [error,setError]=useState('');
  const [notice,setNotice]=useState('');
  const [totp,setTotp]=useState('');
  const [busy,setBusy]=useState(false);
  const [uploading,setUploading]=useState('');
  const [showPreview,setShowPreview]=useState(true);
  const [dragged,setDragged]=useState(null);
  const [loading,setLoading]=useState(true);
  const dirty=useMemo(()=>!!server&&!!model&&JSON.stringify(model)!==JSON.stringify(prepare(server.draft??server.published)),[server,model]);
  const mfa=/^\d{6}$/.test(totp);
  const load=useCallback(async()=>{
    setLoading(true);setError('');
    try{
      const response=await memberAdminApi('/api/admin/home-composition');
      if(!response.data?.published?.order)throw new Error('ข้อมูล Layout ไม่ครบถ้วน');
      setServer(response.data);setModel(prepare(response.data.draft??response.data.published));setTotp('');
    }catch(e){setError(e.message||'ไม่สามารถโหลดผังหน้าแรก');}
    finally{setLoading(false);}
  },[]);
  useEffect(()=>{load();},[load]);
  useEffect(()=>{
    const onBeforeUnload=e=>{if(dirty){e.preventDefault();e.returnValue='';}};
    window.addEventListener('beforeunload',onBeforeUnload);
    return()=>window.removeEventListener('beforeunload',onBeforeUnload);
  },[dirty]);
  const update=fn=>setModel(prev=>fn(copy(prev)));
  const uploadImage=async (file,slot,key,slideId=null)=>{
    if(busy||uploading)return;
    if(file.size<1||file.size>MAX_UPLOAD_BYTES||!['image/jpeg','image/png','image/webp'].includes(file.type)){
      setError('รองรับภาพ JPG, PNG และ WebP ขนาดไม่เกิน 10 MB');return;
    }
    const uploadKey=slot+':'+key+':'+(slideId||'section');
    setUploading(uploadKey);setError('');setNotice('');
    try{
      const result=await mediaLibraryApi.upload(file,{folder:'highlights',altText:slideId?'ภาพพื้นหลัง Highlight':'ภาพพื้นหลังหลัก Highlight'});
      const url=result?.data?.url||'';
      if(!allowedImage(url)||!url){
        throw new Error('ไฟล์อัปโหลดแล้ว แต่ระบบไม่ส่งที่อยู่ภาพสาธารณะที่ใช้ได้ กรุณาตรวจ Media Library');
      }
      // Media Library admin preview endpoints cannot be used on the public homepage.
      // Verify the actual shared image URL before referencing it in a draft.
      const check=await fetch(url,{method:'GET',credentials:'same-origin',cache:'no-store'});
      if(!check.ok || !(check.headers.get('content-type')||'').startsWith('image/')){
        throw new Error('อัปโหลดสำเร็จ แต่เปิดรูปจากหน้าเว็บไซต์สาธารณะไม่ได้ โปรดตรวจการตั้งค่า /storage/uploads/ (HTTP '+check.status+')');
      }
      update(m=>{
        if(slot==='section'){
          m.background=normalBackground(m.background);
          m.background[key]=url;
        }else{
          const item=m.slides.find(row=>row.id===slideId);
          if(item)item[key]=url;
        }
        return m;
      });
      setNotice('อัปโหลดภาพสำเร็จแล้ว กรุณาบันทึกร่างและอนุมัติเผยแพร่ด้วย MFA เพื่อให้หน้าแรกเปลี่ยน');
    }catch(e){setError(e.message||'อัปโหลดภาพไม่สำเร็จ');}
    finally{setUploading('');}
  };
  const move=(key,direction)=>update(current=>{
    const index=current.order.indexOf(key),next=index+direction;
    if(next>=0&&next<current.order.length)[current.order[index],current.order[next]]=[current.order[next],current.order[index]];
    return current;
  });
  const moveSlide=(index,direction)=>update(current=>{
    const next=index+direction;
    if(next>=0&&next<current.slides.length)[current.slides[index],current.slides[next]]=[current.slides[next],current.slides[index]];
    return current;
  });
  const submit=async action=>{
    if(!mfa||busy||!server||!model||(action==='draft'?!dirty:dirty||!server.draft))return;
    if(!allowedImage(model.background?.image_url)||!allowedImage(model.background?.mobile_image_url)||model.slides.some(item=>!allowedImage(item.image_url)||!allowedImage(item.mobile_image_url))){setError('กรุณาใช้ที่อยู่รูป JPG, PNG หรือ WEBP ภายในเว็บไซต์ เช่น /storage/uploads/...');return;}
    if(action==='publish'&&!window.confirm('ยืนยันเผยแพร่ผังหน้าแรกและ Highlight Slider ไปยังเว็บไซต์จริง?'))return;
    setBusy(true);setError('');setNotice('');
    try{
      const body={version:server.version,totp_code:totp};
      if(action==='draft')body.composition=model;
      const result=await memberAdminApi('/api/admin/home-composition/'+action,{data:body});
      setServer(result.data);setModel(prepare(result.data.draft??result.data.published));setTotp('');
      setNotice(action==='draft'?'บันทึกร่างแล้ว หน้าเว็บไซต์ยังไม่เปลี่ยน':'เผยแพร่เรียบร้อย');
    }catch(e){setTotp('');setError(e.message||'บันทึกไม่สำเร็จ');}
    finally{setBusy(false);}
  };
  return <section className="surface-card hc-editor">
    <header><h2>RC15 — Homepage Visual Layout & Layer Manager</h2>
      <p>จัดลำดับส่วนหน้าแรกผ่าน Drag & Drop หรือปุ่มขึ้น–ลง ควบคุมการแสดงผล เพิ่มข้อความ Highlight และดูตัวอย่างก่อนบันทึกร่าง</p></header>
    {error&&<p role="alert" className="hc-error">{error}</p>}
    {notice&&<p role="status">{notice}</p>}
    {loading&&<p>กำลังโหลดข้อมูล Layout...</p>}
    {model&&<>
      <div className="hc-columns">
        <div>
          <h3>ลำดับและ Layer ของส่วนหน้าเว็บ</h3>
          <ol className="hc-sortlist">{model.order.map((id,index)=><li key={id} draggable={!busy}
            onDragStart={e=>{setDragged(id);e.dataTransfer.effectAllowed='move';}}
            onDragOver={e=>e.preventDefault()}
            onDrop={e=>{e.preventDefault();if(dragged&&dragged!==id)update(m=>{
              const from=m.order.indexOf(dragged),to=m.order.indexOf(id);
              m.order.splice(from,1);m.order.splice(to,0,dragged);return m;
            });setDragged(null);}}
            onDragEnd={()=>setDragged(null)}>
            <span className="hc-handle" aria-hidden="true">☰</span>
            <strong>{index+1}. {NAMES[id]}</strong>
            <label><input type="checkbox" checked={model.visible[id]} onChange={e=>update(m=>{m.visible[id]=e.target.checked;return m;})}/> แสดง</label>
            <label>พื้นหลัง <select aria-label={'พื้นหลัง '+NAMES[id]} value={model.layers?.[id]?.surface||'original'}
              onChange={e=>update(m=>{m.layers=m.layers||{};m.layers[id]={...(m.layers[id]||{spacing:'comfortable'}),surface:e.target.value};return m;})}>
              <option value="original">ตามต้นฉบับ</option><option value="light">สว่าง</option>
              <option value="navy">น้ำเงินกรมท่า</option><option value="ocean">ฟ้าทะเล</option>
            </select></label>
            <label>ระยะห่าง <select aria-label={'ระยะห่าง '+NAMES[id]} value={model.layers?.[id]?.spacing||'comfortable'}
              onChange={e=>update(m=>{m.layers=m.layers||{};m.layers[id]={...(m.layers[id]||{surface:'original'}),spacing:e.target.value};return m;})}>
              <option value="compact">กระชับ</option><option value="comfortable">มาตรฐาน</option><option value="spacious">โปร่ง</option>
            </select></label>
            <div className="hc-actions"><button type="button" disabled={busy||index===0} onClick={()=>move(id,-1)} aria-label={'เลื่อน '+NAMES[id]+' ขึ้น'}>↑</button>
              <button type="button" disabled={busy||index===model.order.length-1} onClick={()=>move(id,1)} aria-label={'เลื่อน '+NAMES[id]+' ลง'}>↓</button></div>
          </li>)}</ol>
          <h3>การตกแต่งและ Animation</h3>
          <label>ธีมสีหลัก <select value={model.theme} onChange={e=>update(m=>{m.theme=e.target.value;return m;})}>
            <option value="royal-blue">น้ำเงินสหกรณ์</option><option value="ocean">ฟ้าทะเล</option><option value="midnight">กรมท่าทางการ</option>
          </select></label>
          <label>ระยะเวลาเลื่อนสไลด์ (วินาที) <input type="number" min="4" max="15" value={model.autoplay_seconds}
            onChange={e=>update(m=>{m.autoplay_seconds=Number(e.target.value);return m;})}/></label>
        </div>
        <div>
          <section className="hc-background-settings" aria-label="ภาพพื้นหลัง Highlight Slider">
            <h3>ภาพพื้นหลัง Highlight Slider</h3>
            <p>ขนาดภาพแนะนำ 1920 × 640 พิกเซล (สัดส่วน 3:1), WebP ประมาณ 150–400 KB เว้นพื้นที่ด้านซ้ายไว้สำหรับข้อความ และหลีกเลี่ยงการใส่ตัวหนังสือลงในรูป ภาพจะถูกครอบตัดอัตโนมัติบนมือถือ เลือกรูปจาก Media Library แล้วนำที่อยู่ภายในเว็บไซต์มาใส่ รองรับ JPG, PNG และ WEBP เท่านั้น</p>
            <div className="hc-upload-pair">
              <UploadImageField label="ภาพพื้นหลังหลัก Desktop" description="แนะนำ 1920 × 640 px (3:1)" value={model.background?.image_url||''}
                disabled={busy||!!uploading} uploading={uploading==='section:image_url:section'}
                onUpload={file=>uploadImage(file,'section','image_url')}
                onClear={()=>update(m=>{m.background=normalBackground(m.background);m.background.image_url='';return m;})}/>
              <UploadImageField label="ภาพพื้นหลังหลัก Mobile" description="แนะนำ 1080 × 1350 px (4:5)" value={model.background?.mobile_image_url||''}
                disabled={busy||!!uploading} uploading={uploading==='section:mobile_image_url:section'}
                onUpload={file=>uploadImage(file,'section','mobile_image_url')}
                onClear={()=>update(m=>{m.background=normalBackground(m.background);m.background.mobile_image_url='';return m;})}/>
            </div>
            <p className="hc-image-guidance">Desktop: 1920 × 640 px (3:1) · Mobile: 1080 × 1350 px (4:5) · แนะนำ WebP และเก็บวัตถุสำคัญไว้กลางภาพ</p>
            <div className="hc-art-direction-preview" aria-label="ตัวอย่างพื้นหลัง Desktop และ Mobile">
              {['desktop','mobile'].map(view=>{
                const url=view==='mobile'?(model.background?.mobile_image_url||model.background?.image_url):model.background?.image_url;
                return <div key={view} className={'hc-device-preview hc-device-'+view}>
                  <strong>{view==='mobile'?'Mobile · 4:5':'Desktop · 3:1'}</strong>
                  <div className="hc-device-canvas" style={allowedImage(url)?{
                    backgroundImage:`linear-gradient(rgba(8,22,48,${(model.background?.overlay_opacity??60)/100}),rgba(8,22,48,${(model.background?.overlay_opacity??60)/100})),url("${url}")`,
                    backgroundPosition:model.background?.position||'center'}:{}}>
                    <span>Highlight Slider</span><small>ตัวอย่างการครอบตัด · {view}</small>
                  </div>
                </div>;
              })}
            </div>
            <div className="hc-background-row">
              <label>ความเข้ม Overlay ({model.background?.overlay_opacity??60}%)
                <input type="range" min="35" max="85" step="5" value={model.background?.overlay_opacity??60}
                  onChange={e=>update(m=>{m.background=normalBackground(m.background);m.background.overlay_opacity=Number(e.target.value);return m;})}/>
              </label>
              <label>จุดโฟกัสภาพ
                <select value={model.background?.position||'center'}
                  onChange={e=>update(m=>{m.background=normalBackground(m.background);m.background.position=e.target.value;return m;})}>
                  <option value="center">กึ่งกลาง</option><option value="top">ด้านบน</option>
                  <option value="bottom">ด้านล่าง</option><option value="left">ด้านซ้าย</option><option value="right">ด้านขวา</option>
                </select>
              </label>
            </div>
            {!!model.background?.image_url && allowedImage(model.background.image_url) && <div className="hc-background-preview" style={{backgroundImage:`linear-gradient(rgba(8,22,48,${model.background.overlay_opacity/100}),rgba(8,22,48,${model.background.overlay_opacity/100})),url("${model.background.image_url}")`,backgroundPosition:model.background.position||'center'}}>
              <strong>ตัวอย่างภาพพื้นหลังหลัก</strong>
              <button type="button" onClick={()=>update(m=>{m.background.image_url='';return m;})}>นำรูปพื้นหลังออก</button>
            </div>}
          </section>
          <div className="hc-heading-row"><h3>Highlight Slider ({model.slides.length}/6)</h3>
            <button type="button" disabled={busy||model.slides.length>=6} onClick={()=>update(m=>{m.slides.push(makeSlide());return m;})}>+ เพิ่มข้อความ</button></div>
          <p>ต้องกรอกอย่างน้อย 5 รายการก่อนเผยแพร่ ห้ามใช้ข้อมูลที่ยังไม่ได้ตรวจสอบเป็นข้อเท็จจริง</p>
          <div className="hc-slide-list">{model.slides.map((slide,index)=><fieldset key={slide.id}>
            <legend>ข้อความที่ {index+1}</legend>
            <label>หัวข้อ <input maxLength="100" value={slide.title} onChange={e=>update(m=>{m.slides[index].title=e.target.value;return m;})}/></label>
            <label>รายละเอียด <textarea maxLength="320" rows={3} value={slide.body}
              onChange={e=>update(m=>{m.slides[index].body=e.target.value;return m;})}/></label>
            <div className="hc-upload-pair">
              <UploadImageField label="ภาพสไลด์ Desktop" description="เว้นว่างเพื่อใช้ภาพหลัก" value={slide.image_url||''}
                disabled={busy||!!uploading} uploading={uploading==='slide:image_url:'+slide.id}
                onUpload={file=>uploadImage(file,'slide','image_url',slide.id)}
                onClear={()=>update(m=>{m.slides[index].image_url='';return m;})}/>
              <UploadImageField label="ภาพสไลด์ Mobile" description="เว้นว่างเพื่อใช้ภาพหลัก" value={slide.mobile_image_url||''}
                disabled={busy||!!uploading} uploading={uploading==='slide:mobile_image_url:'+slide.id}
                onUpload={file=>uploadImage(file,'slide','mobile_image_url',slide.id)}
                onClear={()=>update(m=>{m.slides[index].mobile_image_url='';return m;})}/>
            </div>
            <label>ลิงก์ภายใน (ไม่บังคับ) <input placeholder="/about" value={slide.link}
              onChange={e=>update(m=>{m.slides[index].link=e.target.value;return m;})}/></label>
            <div className="hc-actions"><button type="button" disabled={index===0} onClick={()=>moveSlide(index,-1)}>↑</button>
              <button type="button" disabled={index===model.slides.length-1} onClick={()=>moveSlide(index,1)}>↓</button>
              <button type="button" onClick={()=>update(m=>{m.slides.splice(index,1);return m;})}>นำออกจากร่าง</button></div>
          </fieldset>)}</div>
        </div>
      </div>
      <button type="button" onClick={()=>setShowPreview(x=>!x)}>{showPreview?'ซ่อนตัวอย่าง':'แสดงตัวอย่าง'}</button>
      {showPreview&&<Preview composition={model}/>}
      <div className="hc-submit">
        <p role="status">{dirty?'มีการแก้ไขที่ยังไม่บันทึก':server?.draft?'มีร่างรอเผยแพร่':'ใช้ Layout ที่เผยแพร่แล้ว'}</p>
        <label>รหัส MFA 6 หลัก <input type="password" inputMode="numeric" autoComplete="one-time-code" maxLength={6}
          value={totp} onChange={e=>setTotp(e.target.value.replace(/\D/g,'').slice(0,6))}/></label>
        <button type="button" disabled={!dirty||!mfa||busy||!!uploading} onClick={()=>submit('draft')}>1. บันทึกร่าง</button>
        <button type="button" disabled={!server?.draft||dirty||!mfa||busy||!!uploading} onClick={()=>submit('publish')}>2. อนุมัติเผยแพร่</button>
        <button type="button" disabled={busy} onClick={()=>{if(!dirty||window.confirm('ทิ้งการแก้ไขที่ยังไม่บันทึก?'))load();}}>โหลดล่าสุด / ยกเลิก</button>
      </div>
    </>}
  </section>;
}
