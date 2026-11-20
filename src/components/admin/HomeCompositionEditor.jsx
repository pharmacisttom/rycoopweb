import React, {useCallback, useEffect, useMemo, useState} from 'react';
import {memberAdminApi} from '../../services/memberAdminApi';
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
const makeSlide = () => ({id:'slide-'+crypto.randomUUID().replaceAll('-','').slice(0,16),title:'',body:'',link:'',image_url:''});
const normalBackground = value => ({image_url:value?.image_url||'', overlay_opacity:value?.overlay_opacity??60, position:value?.position||'center'});
const allowedImage = path => !path || (/^\\/(?:storage\\/uploads|uploads|assets)\\/[a-zA-Z0-9_./-]+\\.(?:webp|png|jpe?g)$/.test(path) && !path.includes('..') && !path.includes('//'));
const prepare = value => ({...copy(value),background:normalBackground(value.background),slides:value.slides.map(item=>({...item,image_url:item.image_url||''}))});
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
    if(!allowedImage(model.background?.image_url)||model.slides.some(item=>!allowedImage(item.image_url))){setError('กรุณาใช้ที่อยู่รูป JPG, PNG หรือ WEBP ภายในเว็บไซต์ เช่น /storage/uploads/...');return;}
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
            <p>เลือกรูปที่อัปโหลดไว้ใน Media Library แล้วคัดลอกที่อยู่ไฟล์ภายในเว็บไซต์มาใส่ รองรับ JPG, PNG และ WEBP ไม่รับ URL ภายนอก เพื่อป้องกันการติดตามผู้เข้าชม</p>
            <label>ที่อยู่ภาพพื้นหลังหลัก
              <input type="text" placeholder="/storage/uploads/..." value={model.background?.image_url||''}
                onChange={e=>update(m=>{m.background=normalBackground(m.background);m.background.image_url=e.target.value.trim();return m;})}/>
            </label>
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
            <label>ภาพพื้นหลังเฉพาะข้อความ (ไม่บังคับ)
              <input type="text" placeholder="เว้นว่างเพื่อใช้ภาพหลัก เช่น /storage/uploads/..."
                value={slide.image_url||''}
                onChange={e=>update(m=>{m.slides[index].image_url=e.target.value.trim();return m;})}/>
            </label>
            {!!slide.image_url && allowedImage(slide.image_url) && <div className="hc-mini-image" style={{backgroundImage:`linear-gradient(#08163077,#08163077),url("${slide.image_url}")`}}>
              <span>ตัวอย่างรูปของสไลด์นี้</span>
              <button type="button" onClick={()=>update(m=>{m.slides[index].image_url='';return m;})}>ลบรูป</button>
            </div>}
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
        <button type="button" disabled={!dirty||!mfa||busy} onClick={()=>submit('draft')}>1. บันทึกร่าง</button>
        <button type="button" disabled={!server?.draft||dirty||!mfa||busy} onClick={()=>submit('publish')}>2. อนุมัติเผยแพร่</button>
        <button type="button" disabled={busy} onClick={()=>{if(!dirty||window.confirm('ทิ้งการแก้ไขที่ยังไม่บันทึก?'))load();}}>โหลดล่าสุด / ยกเลิก</button>
      </div>
    </>}
  </section>;
}
