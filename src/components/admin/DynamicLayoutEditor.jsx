import React, {useCallback, useEffect, useMemo, useState} from 'react';
import {memberAdminApi} from '../../services/memberAdminApi';
import './DynamicLayoutEditor.css';

const clone = value => JSON.parse(JSON.stringify(value));
const getNodes = (nodes, depth = 1) => nodes.flatMap(n => [{...n, depth}, ...getNodes(n.children || [], depth + 1)]);
const patchNodes = (nodes, id, patch) => nodes.map(n => n.id === id ? {...n, ...patch} : {...n, children: patchNodes(n.children || [], id, patch)});
const removeNode = (nodes, id) => nodes.filter(n => n.id !== id).map(n => ({...n, children: removeNode(n.children || [], id)}));
const appendNode = (nodes, parentId, node) => parentId === null ? [...nodes, node] : nodes.map(n => n.id === parentId ? {...n, children: [...(n.children || []), node]} : {...n, children: appendNode(n.children || [], parentId, node)});
const shiftNode = (nodes, id, direction) => {
  const i = nodes.findIndex(n => n.id === id);
  if (i >= 0) {
    const next = [...nodes], target = i + direction;
    if (target >= 0 && target < next.length) [next[i], next[target]] = [next[target], next[i]];
    return next;
  }
  return nodes.map(n => ({...n, children: shiftNode(n.children || [], id, direction)}));
};
const DEFAULT_APPEARANCE = {background:'#ffffff',text:'#10294b',accent:'#175cd3'};
const APPEARANCE_PRESETS = [
  {label:'พื้นขาว',colors:{background:'#ffffff',text:'#10294b',accent:'#175cd3'}},
  {label:'ฟ้าอ่อน',colors:{background:'#eff6ff',text:'#10294b',accent:'#175cd3'}},
  {label:'น้ำเงินกรมท่า',colors:{background:'#0b1c40',text:'#ffffff',accent:'#fbbf24'}},
  {label:'เขียวมิ้นต์',colors:{background:'#ecfdf5',text:'#064e3b',accent:'#047857'}},
  {label:'ครีมอบอุ่น',colors:{background:'#fff7ed',text:'#7c2d12',accent:'#c2410c'}}
];
const initialNode = () => ({
  id: 'section-' + crypto.randomUUID().replaceAll('-', '').slice(0,20),
  title: 'หัวข้อใหม่', description: '', type: 'gallery', layout: 'grid',
  link: '/activities', album_ids: [], slides: [], slide_interval: 6, appearance:{...DEFAULT_APPEARANCE}, visible: true, children: []
});

export default function DynamicLayoutEditor() {
  const [server, setServer] = useState(null);
  const [tree, setTree] = useState([]);
  const [selected, setSelected] = useState(null);
  const [totp, setTotp] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [preview, setPreview] = useState(false);
  const [albumInput, setAlbumInput] = useState('');
  const [availableAlbums, setAvailableAlbums] = useState([]);
  const [loading, setLoading] = useState(true);
  const allNodes = useMemo(() => getNodes(tree), [tree]);
  const current = allNodes.find(n => n.id === selected);
  useEffect(() => setAlbumInput((current?.album_ids || []).join(', ')), [selected]);
  const dirty = server && JSON.stringify(tree) !== JSON.stringify(server.draft ?? server.published);
  const validTotp = /^\d{6}$/.test(totp);

  const load = useCallback(async () => {
    setLoading(true); setError('');
    try {
      const result = await memberAdminApi('/api/admin/dynamic-layout');
      setServer(result.data);
      setTree(clone(result.data.draft ?? result.data.published));
      setSelected(null); setTotp('');
    } catch (e) { setError(e.message || 'โหลดข้อมูลไม่สำเร็จ'); }
    finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);
  useEffect(() => { memberAdminApi('/api/admin/activities').then(r => {
    if (Array.isArray(r.data)) setAvailableAlbums(r.data);
  }).catch(() => {}); }, []);
  useEffect(() => {
    const beforeUnload = e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } };
    window.addEventListener('beforeunload', beforeUnload);
    return () => window.removeEventListener('beforeunload', beforeUnload);
  }, [dirty]);

  const add = parentId => {
    const parent = allNodes.find(n => n.id === parentId);
    if (parent && (parent.depth >= 3 || allNodes.length >= 120)) return;
    if (!parent && tree.length >= 40) return;
    const node = initialNode();
    setTree(prev => appendNode(prev, parentId, node));
    setSelected(node.id); setNotice('');
  };

  const update = patch => setTree(prev => patchNodes(prev, selected, patch));
  const updateSlides = slides => update({slides});
  const emptySlide = () => ({image:'',title:'',caption:'',button_text:'',button_link:'',fit:'cover'});
  const save = async action => {
    if (busy || !validTotp || !server || (action === 'draft' ? !dirty : dirty || !server.draft)) return;
    setBusy(true); setError(''); setNotice('');
    try {
      const data = {version: server.version, totp_code: totp};
      if (action === 'draft') data.sections = tree;
      const result = await memberAdminApi('/api/admin/dynamic-layout/' + action, {data});
      setServer(result.data);
      setTree(clone(result.data.draft ?? result.data.published));
      setTotp('');
      setNotice(action === 'draft' ? 'บันทึกร่างแล้ว เว็บไซต์ยังไม่เปลี่ยน' : 'เผยแพร่หัวข้อหน้าแรกสำเร็จ');
    } catch (e) {
      setTotp('');
      setError(e.status === 409 ? 'มีการแก้ไขจากผู้ใช้อื่น กรุณาโหลดใหม่' : e.message || 'บันทึกไม่สำเร็จ');
    } finally { setBusy(false); }
  };
  const fieldStyle = {};
  const btn = {padding:'8px 12px',marginRight:7,marginBottom:7};
  return <section className="surface-card dynamic-layout-editor" style={{padding:'1.5rem',marginTop:24}}>
    <h2>Dynamic Layout — จัดการบล็อกเว็บไซต์ สไลด์ และรูปกิจกรรม</h2>
    <p>เพิ่ม Section ประเภทข้อความ ลิงก์ แกลเลอรี และ Slideshow พร้อมแก้ไขข้อความ/ภาพ/ลำดับในฉบับร่างก่อนเผยแพร่</p>
    {error && <p role="alert" style={{color:'#b42318'}}>{error}</p>}
    {notice && <p role="status">{notice}</p>}
    {loading ? <p>กำลังโหลดหัวข้อ...</p> : server && <>
      <div style={{display:'flex',gap:8,flexWrap:'wrap',marginBottom:14}}>
        <button type="button" style={btn} disabled={busy} onClick={() => add(null)}>+ เพิ่มหัวข้อหลัก</button>
        <button type="button" style={btn} disabled={busy || !current || current.depth >= 3} onClick={() => add(selected)}>+ เพิ่มหัวข้อย่อย</button>
        <button type="button" style={btn} onClick={() => setPreview(!preview)}>{preview ? 'ปิดตัวอย่าง' : 'ดูตัวอย่างฉบับร่าง'}</button>
        <button type="button" style={btn} disabled={busy} onClick={() => {
          if (dirty && !window.confirm('ยกเลิกการเปลี่ยนแปลงที่ยังไม่ได้บันทึก?')) return;
          load();
        }}>โหลดล่าสุด</button>
      </div>
      <div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(min(100%,300px),1fr))',gap:18}}>
        <div style={{border:'1px solid #d6dae3',borderRadius:9,padding:12}}>
          <h3>ผังหัวข้อเว็บไซต์</h3>
          {allNodes.length === 0 && <p>ยังไม่มีหัวข้อใหม่ กดเพิ่มหัวข้อหลักเพื่อเริ่มต้น</p>}
          {allNodes.map(n => <div key={n.id} style={{display:'flex',alignItems:'center',marginLeft:(n.depth-1)*12,marginBottom:7,gap:5}}>
            <button type="button" aria-pressed={n.id === selected} onClick={() => setSelected(n.id)}
              style={{...btn,flex:1,textAlign:'left',background:n.id===selected?'#e8f1ff':undefined}}>
              {n.depth > 1 ? '↳ ' : '▣ '}{n.title}{!n.visible ? ' (ซ่อน)' : ''}
            </button>
            <button type="button" aria-label={'ย้าย '+n.title+' ขึ้น'} disabled={busy} onClick={() => setTree(p => shiftNode(p,n.id,-1))}>↑</button>
            <button type="button" aria-label={'ย้าย '+n.title+' ลง'} disabled={busy} onClick={() => setTree(p => shiftNode(p,n.id,1))}>↓</button>
          </div>)}
        </div>
        <div className="dynamic-layout-fields" style={{border:'1px solid #d6dae3',borderRadius:9,padding:12}}>
          <h3>แก้ไขหัวข้อ</h3>
          {!current ? <p>เลือกหัวข้อทางซ้ายเพื่อแก้ไข</p> : <>
            <label>ชื่อหัวข้อ<input style={fieldStyle} value={current.title} maxLength={120} onChange={e=>update({title:e.target.value})}/></label>
            <label className="dynamic-layout-field-full">คำอธิบาย<textarea style={fieldStyle} value={current.description} maxLength={1000} rows={3} onChange={e=>update({description:e.target.value})}/></label>
            <label>ประเภท<select style={fieldStyle} value={current.type} onChange={e=>update({type:e.target.value, ...(e.target.value === 'slideshow' ? {link:''} : {slides:[]})})}>
              <option value="gallery">แกลเลอรีกิจกรรม</option><option value="links">ลิงก์</option><option value="text">ข้อความ</option><option value="slideshow">Slide / Slideshow</option>
            </select></label>
            <label>รูปแบบ<select style={fieldStyle} value={current.layout} onChange={e=>update({layout:e.target.value})}>
              <option value="grid">Grid</option><option value="cards">Cards</option><option value="carousel">Carousel</option>
            </select></label>
            <fieldset className="dynamic-layout-appearance">
              <legend>สีและพื้นหลังของหัวข้อนี้</legend>
              <div className="dynamic-layout-presets" role="group" aria-label="ชุดสีสำเร็จรูป">
                {APPEARANCE_PRESETS.map(preset=><button type="button" key={preset.label}
                  className="dynamic-layout-preset" disabled={busy} onClick={()=>update({appearance:{...preset.colors}})}>
                  <span className="dynamic-layout-swatch" style={{background:preset.colors.background,color:preset.colors.text,borderColor:preset.colors.accent}}>Aa</span>
                  {preset.label}
                </button>)}
              </div>
              <div className="dynamic-layout-color-grid">
                {[
                  ['background','สีพื้นหลัง'],
                  ['text','สีข้อความ'],
                  ['accent','สีเน้น / ปุ่ม']
                ].map(([key,label])=><label key={key}>{label}
                  <span className="dynamic-layout-color-input">
                    <input type="color" aria-label={label} value={current.appearance?.[key]||DEFAULT_APPEARANCE[key]}
                      disabled={busy} onChange={e=>update({appearance:{...DEFAULT_APPEARANCE,...current.appearance,[key]:e.target.value}})}/>
                    <code>{current.appearance?.[key]||DEFAULT_APPEARANCE[key]}</code>
                  </span>
                </label>)}
              </div>
              <div className="dynamic-layout-color-preview" style={{backgroundColor:current.appearance?.background||DEFAULT_APPEARANCE.background,color:current.appearance?.text||DEFAULT_APPEARANCE.text}}>
                <strong>{current.title||'หัวข้อตัวอย่าง'}</strong>
                <p>{current.description||'ตัวอย่างข้อความที่จะแสดงบนพื้นหลังที่เลือก'}</p>
                <span style={{backgroundColor:current.appearance?.accent||DEFAULT_APPEARANCE.accent,color:'#ffffff'}}>ตัวอย่างสีปุ่ม/ส่วนเน้น</span>
              </div>
              <p className="dynamic-layout-color-hint">ระบบจะตรวจความชัดเจนของสีข้อความกับพื้นหลังก่อนบันทึก (ขั้นต่ำ 4.5:1)</p>
            </fieldset>
            {current.type === 'slideshow' && <fieldset className="dynamic-slides-editor">
              <legend>จัดการสไลด์ — เพิ่ม / แก้ไข / ลบ / จัดลำดับ</legend>
              <p>เลือกรูปที่อัปโหลดผ่านคลังภาพของหลังบ้าน แล้วนำ URL ภายในเว็บไซต์มาใส่ (เช่น /storage/uploads/.../banner.webp) ไม่อนุญาตรูปจากเว็บไซต์ภายนอก</p>
              <a href="/app/admin/media" target="_blank" rel="noopener noreferrer">เปิดคลังภาพ CMS เพื่อเลือกรูป</a>
              <label className="dynamic-layout-slide-interval">เวลาเปลี่ยนภาพอัตโนมัติ (วินาที)
                <select value={current.slide_interval || 6} onChange={e=>update({slide_interval:Number(e.target.value)})}>
                  {[4,5,6,7,8,9,10,11,12].map(n=><option value={n} key={n}>{n} วินาที</option>)}
                </select>
              </label>
              {(current.slides || []).map((slide,i)=><div className="dynamic-slide-edit-card" key={i}>
                <div className="dynamic-slide-edit-head">
                  <strong>สไลด์ {i+1}</strong>
                  <div className="dynamic-slide-edit-actions">
                    <button type="button" disabled={busy||i===0} onClick={()=>{
                      const slides=[...(current.slides||[])]; [slides[i-1],slides[i]]=[slides[i],slides[i-1]];updateSlides(slides);
                    }}>↑ เลื่อนขึ้น</button>
                    <button type="button" disabled={busy||i===(current.slides||[]).length-1} onClick={()=>{
                      const slides=[...(current.slides||[])]; [slides[i+1],slides[i]]=[slides[i],slides[i+1]];updateSlides(slides);
                    }}>↓ เลื่อนลง</button>
                    <button type="button" disabled={busy} onClick={()=>{
                      if(!window.confirm('ลบสไลด์นี้ออกจากฉบับร่าง?'))return;
                      updateSlides((current.slides||[]).filter((_,pos)=>pos!==i));
                    }}>ลบ</button>
                  </div>
                </div>
                <label>URL รูปภาพ (ภายในเว็บไซต์)
                  <input required value={slide.image||''} placeholder="/storage/uploads/news/gallery/example.webp"
                    onChange={e=>updateSlides((current.slides||[]).map((old,pos)=>pos===i?{...old,image:e.target.value}:old))} />
                </label>
                {slide.image?.startsWith('/') && <img className="dynamic-slide-edit-preview" src={slide.image} alt="ตัวอย่างภาพสไลด์" loading="lazy" />}
                <label>หัวข้อบนสไลด์
                  <input value={slide.title||''} maxLength={120} onChange={e=>updateSlides((current.slides||[]).map((old,pos)=>pos===i?{...old,title:e.target.value}:old))} />
                </label>
                <label>รายละเอียด
                  <textarea rows={3} maxLength={500} value={slide.caption||''} onChange={e=>updateSlides((current.slides||[]).map((old,pos)=>pos===i?{...old,caption:e.target.value}:old))} />
                </label>
                <label>รูปแบบแสดงรูป
                  <select value={slide.fit||'cover'} onChange={e=>updateSlides((current.slides||[]).map((old,pos)=>pos===i?{...old,fit:e.target.value}:old))}>
                    <option value="cover">เต็มกรอบ (Cover)</option><option value="contain">เห็นรูปเต็ม (Contain)</option>
                  </select>
                </label>
                <label>ข้อความปุ่ม
                  <input value={slide.button_text||''} maxLength={60} onChange={e=>updateSlides((current.slides||[]).map((old,pos)=>pos===i?{...old,button_text:e.target.value}:old))} />
                </label>
                <label>ลิงก์ปุ่มภายในเว็บไซต์
                  <input value={slide.button_link||''} maxLength={255} placeholder="/activities" onChange={e=>updateSlides((current.slides||[]).map((old,pos)=>pos===i?{...old,button_link:e.target.value}:old))} />
                </label>
              </div>)}
              <button type="button" disabled={busy||(current.slides||[]).length>=12}
                onClick={()=>updateSlides([...(current.slides||[]),emptySlide()])}>+ เพิ่มสไลด์ (สูงสุด 12 รูป)</button>
            </fieldset>}
            <label className="dynamic-layout-field-full">รหัสอัลบั้มกิจกรรม (คั่นด้วยเครื่องหมายจุลภาค)<input style={fieldStyle} value={albumInput} placeholder="เช่น 1, 3, 5 — เว้นว่าง = ทุกอัลบั้ม" onChange={e=>setAlbumInput(e.target.value)} onBlur={() => {
              const ids = albumInput.split(',').map(v => Number(v.trim())).filter(v => Number.isSafeInteger(v) && v > 0);
              if (albumInput.trim() && (ids.length === 0 || ids.length !== albumInput.split(',').length)) { setError('รหัสอัลบั้มต้องเป็นตัวเลขบวกคั่นด้วยจุลภาค'); return; }
              update({album_ids:[...new Set(ids)]});setError('');
            }}/></label>
            {current.type === 'gallery' && availableAlbums.length > 0 && <fieldset style={{marginBottom:12}}>
              <legend>เลือกอัลบั้มกิจกรรม (ไม่เลือก = ทุกอัลบั้มที่เผยแพร่)</legend>
              {availableAlbums.map(a => <label key={a.id} style={{display:'block',marginBottom:5}}>
                <input type="checkbox" checked={(current.album_ids||[]).includes(Number(a.id))}
                  onChange={e => {
                    const chosen = e.target.checked ? [...new Set([...(current.album_ids||[]), Number(a.id)])]
                      : (current.album_ids||[]).filter(id => id !== Number(a.id));
                    update({album_ids: chosen});setAlbumInput(chosen.join(', '));
                  }}/> {a.title} — {a.status === 'published' ? 'เผยแพร่' : 'ร่าง'}
              </label>)}
            </fieldset>}
            <label className="dynamic-layout-field-full">ลิงก์ภายในเว็บไซต์<input style={fieldStyle} value={current.link} placeholder="/activities" onChange={e=>update({link:e.target.value})}/></label>
            <label className="dynamic-layout-visible"><input type="checkbox" checked={current.visible} onChange={e=>update({visible:e.target.checked})}/> แสดงหัวข้อนี้</label>
            <div className="dynamic-layout-field-actions" style={{marginTop:18}}>
              <button type="button" disabled={busy} style={btn} onClick={() => {
                if (!window.confirm('ลบหัวข้อนี้พร้อมหัวข้อย่อยทั้งหมดจากฉบับร่าง?')) return;
                setTree(p => removeNode(p,current.id));setSelected(null);
              }}>ลบหัวข้อ</button>
              {current.type==='gallery' && <a href="/app/admin/activities" style={{marginLeft:8}}>จัดการอัลบั้มกิจกรรม</a>}
            </div>
          </>}
        </div>
      </div>
      {preview && <div style={{marginTop:18,padding:15,border:'1px dashed #7d92b0'}}>
        <h3>ตัวอย่างร่าง (ไม่แสดงต่อสาธารณะ)</h3>
        {allNodes.filter(n => n.visible).map(n => <div key={n.id} style={{marginLeft:(n.depth-1)*18,padding:7}}>
          <div style={{backgroundColor:n.appearance?.background||DEFAULT_APPEARANCE.background,color:n.appearance?.text||DEFAULT_APPEARANCE.text,padding:16,borderRadius:12}}>
            <strong>{n.title}</strong> <small>({n.layout})</small><p style={{color:'inherit'}}>{n.description}</p>
          </div>
        </div>)}
      </div>}
      <div style={{marginTop:18}}>
        <label className="dynamic-layout-mfa">รหัส MFA 6 หลัก<input style={{...fieldStyle,maxWidth:220}} inputMode="numeric" type="password" maxLength={6} autoComplete="one-time-code" value={totp} onChange={e=>setTotp(e.target.value.replace(/\D/g,'').slice(0,6))}/></label>
        <button type="button" style={btn} disabled={!dirty || !validTotp || busy} onClick={()=>save('draft')}>บันทึกร่าง</button>
        <button type="button" style={btn} disabled={dirty || !server.draft || !validTotp || busy} onClick={()=>{
          if(window.confirm('ยืนยันเผยแพร่หัวข้อฉบับร่างไปยังเว็บไซต์ TEST?')) save('publish');
        }}>อนุมัติเผยแพร่</button>
        <p>สถานะ: {dirty ? 'มีข้อมูลที่ยังไม่ได้บันทึก' : server.draft ? 'มีร่างรอเผยแพร่' : 'ไม่มีร่างค้าง'} · การเผยแพร่ต้องใช้ MFA ใหม่</p>
      </div>
    </>}
  </section>;
}
