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
const initialNode = () => ({
  id: 'section-' + crypto.randomUUID().replaceAll('-', '').slice(0,20),
  title: 'หัวข้อใหม่', description: '', type: 'gallery', layout: 'grid',
  link: '/activities', album_ids: [], visible: true, children: []
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
    <h2>Dynamic Layout & รูปกิจกรรมของสหกรณ์</h2>
    <p>เพิ่มหัวข้อหลัก หัวข้อย่อย และอัลบั้มกิจกรรม โดยไม่กระทบส่วนหน้าแรก 6 รายการเดิม</p>
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
            <label>ประเภท<select style={fieldStyle} value={current.type} onChange={e=>update({type:e.target.value})}>
              <option value="gallery">แกลเลอรีกิจกรรม</option><option value="links">ลิงก์</option><option value="text">ข้อความ</option>
            </select></label>
            <label>รูปแบบ<select style={fieldStyle} value={current.layout} onChange={e=>update({layout:e.target.value})}>
              <option value="grid">Grid</option><option value="cards">Cards</option><option value="carousel">Carousel</option>
            </select></label>
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
          <strong>{n.title}</strong> <small>({n.layout})</small><p>{n.description}</p>
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
