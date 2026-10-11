import React, { useEffect, useRef, useState, useId } from 'react';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import RichTextContent from './RichTextContent';
import { safeRichText, textToRichText } from '../../utils/richText';
import './RichTextEditor.css';

const SYMBOL_GROUPS = [
  { name: 'อีโมจิ', items: [['😀','ยิ้ม'],['😊','ยิ้มอบอุ่น'],['🥰','มีความสุข'],['🙏','ขอบคุณ'],['👍','ถูกใจ'],['👏','ปรบมือ'],['🎉','เฉลิมฉลอง'],['❤️','หัวใจ'],['🌟','ดาว'],['💡','แนวคิด'],['📣','ประชาสัมพันธ์'],['📌','ปักหมุด']] },
  { name: 'งานสหกรณ์', items: [['📅','กำหนดการ'],['⏰','เวลา'],['📍','สถานที่'],['☎','โทรศัพท์'],['✉','อีเมล'],['📄','เอกสาร'],['📝','ลงทะเบียน'],['👥','สมาชิก'],['🏦','สหกรณ์'],['💰','เงิน'],['🎓','ทุนการศึกษา'],['🎁','ของขวัญ']] },
  { name: 'สถานะ', items: [['✅','สำเร็จ'],['☑','เลือกแล้ว'],['✔','ถูกต้อง'],['❌','ไม่ผ่าน'],['⚠️','แจ้งเตือน'],['❗','สำคัญ'],['❓','คำถาม'],['ℹ️','ข้อมูล'],['🔔','แจ้งข่าว'],['🔒','ล็อก'],['🟢','สีเขียว'],['🔴','สีแดง']] },
  { name: 'สัญลักษณ์', items: [['•','จุดรายการ'],['▪','สี่เหลี่ยมรายการ'],['★','ดาวทึบ'],['☆','ดาวโปร่ง'],['→','ลูกศรขวา'],['←','ลูกศรซ้าย'],['↑','ลูกศรขึ้น'],['↓','ลูกศรลง'],['✓','เครื่องหมายถูก'],['×','คูณ'],['÷','หาร'],['±','บวกลบ'],['฿','บาท'],['©','ลิขสิทธิ์'],['®','เครื่องหมายจดทะเบียน']] },
];

export default function RichTextEditor({ value = '', onChange, label = 'คำบรรยาย', disabled = false, plainText = false }) {
  const host = useRef(null), editor = useRef(null), callback = useRef(onChange);
  const selectionRef = useRef(null);
  const [showSymbols,setShowSymbols]=useState(false), [symbolSearch,setSymbolSearch]=useState('');
  const [preview, setPreview] = useState(false), [length, setLength] = useState(0);
  const id = useId(); callback.current = onChange;
  useEffect(() => {
    const node = document.createElement('div'); host.current.appendChild(node);
    const quill = new Quill(node, {
      theme: 'snow', placeholder: `พิมพ์${label}...`,
      formats: plainText ? [] : ['header','bold','italic','underline','strike','blockquote','list','link'],
      modules: { toolbar: plainText ? false : [[{header:[2,3,false]}],['bold','italic','underline','strike'],[{list:'ordered'},{list:'bullet'}],['blockquote','link','clean']], history: { userOnly: true } },
    });
    editor.current = quill;
    quill.root.setAttribute('role','textbox');quill.root.setAttribute('aria-multiline','true');quill.root.setAttribute('aria-label',label);
    const initial = plainText || !/<\/?[a-z][\s\S]*>/i.test(value) ? textToRichText(value) : safeRichText(value);
    quill.clipboard.dangerouslyPasteHTML(initial, 'silent'); quill.history.clear();
    setLength(quill.getText().trim().length);
    const changed = () => {
      const text = quill.getText().replace(/\n$/, '');setLength(text.trim().length);
      callback.current?.(plainText ? text : text.trim() ? safeRichText(quill.getSemanticHTML()) : '');
    };
    quill.on('text-change',changed);
    const selected = range => { if(range)selectionRef.current={index:range.index,length:range.length}; };
    quill.on('selection-change',selected);
    host.current.querySelectorAll('.ql-toolbar button').forEach(button => { button.type='button'; const kind=[...button.classList].find(c=>c.startsWith('ql-')); const names={'ql-bold':'ตัวหนา','ql-italic':'ตัวเอียง','ql-underline':'ขีดเส้นใต้','ql-strike':'ขีดฆ่า','ql-list':button.value==='ordered'?'รายการลำดับเลข':'รายการหัวข้อ','ql-blockquote':'ข้อความอ้างอิง','ql-link':'เพิ่มลิงก์','ql-clean':'ล้างรูปแบบ'}; button.setAttribute('aria-label',names[kind] || 'จัดรูปแบบ'); });
    return () => {quill.off('text-change',changed);quill.off('selection-change',selected);selectionRef.current=null;editor.current=null;host.current.replaceChildren();};
  }, [plainText]);
  useEffect(() => { editor.current?.enable(!disabled); }, [disabled]);
  useEffect(() => {
    const quill=editor.current;if(!quill)return;
    const current=plainText?quill.getText().replace(/\n$/,''):quill.getText().trim()?safeRichText(quill.getSemanticHTML()):'';
    if(value!==current){const selection=quill.getSelection();const html=plainText || !/<\/?[a-z][\s\S]*>/i.test(value)?textToRichText(value):safeRichText(value);quill.clipboard.dangerouslyPasteHTML(html,'silent');if(selection)quill.setSelection(Math.min(selection.index,quill.getLength()-1),0,'silent');setLength(quill.getText().trim().length);}
  }, [value,plainText]);
  function insertSymbol(symbol) {
    const quill=editor.current;if(!quill || disabled || preview)return;
    const range=quill.getSelection() || selectionRef.current || {index:quill.getLength()-1,length:0};
    const index=Math.max(0,Math.min(range.index,quill.getLength()-1));
    const selectedLength=Math.min(range.length,quill.getLength()-1-index);
    const formats=plainText?{}:quill.getFormat(index,selectedLength);
    quill.history.cutoff();
    if(selectedLength)quill.deleteText(index,selectedLength,'user');
    quill.insertText(index,symbol,formats,'user');quill.history.cutoff();
    quill.setSelection(index+symbol.length,0,'silent');quill.focus();
    selectionRef.current={index:index+symbol.length,length:0};setShowSymbols(false);
  }
  const filteredGroups=SYMBOL_GROUPS.map(group=>({...group,items:group.items.filter(([symbol,name])=>(symbol+name+group.name).includes(symbolSearch.trim()))})).filter(group=>group.items.length);
  return <div className="rich-text-editor">
    <div className="rich-text-actions"><span id={id}>{label}</span><button type="button" disabled={disabled || preview} aria-expanded={showSymbols && !preview} aria-controls={`${id}-symbols`} onMouseDown={e=>e.preventDefault()} onClick={()=>setShowSymbols(open=>!open)}>😊 อีโมจิ / สัญลักษณ์</button><button type="button" disabled={disabled} onClick={()=>editor.current?.history.undo()}>ย้อนกลับ</button><button type="button" disabled={disabled} onClick={()=>editor.current?.history.redo()}>ทำซ้ำ</button><button type="button" aria-pressed={preview} onClick={()=>{setPreview(p=>!p);setShowSymbols(false);}}>{preview?'กลับไปแก้ไข':'ดูตัวอย่าง'}</button></div>
    {showSymbols && !preview && !disabled && <div id={`${id}-symbols`} className="rich-text-symbols" role="region" aria-label="เลือกอีโมจิและสัญลักษณ์" onKeyDown={e=>{if(e.key==='Escape'){setShowSymbols(false);editor.current?.focus();}}}>
      <div className="rich-text-symbol-search"><label>ค้นหาอีโมจิ / สัญลักษณ์<input type="search" value={symbolSearch} onChange={e=>setSymbolSearch(e.target.value)} placeholder="เช่น ประชาสัมพันธ์ เวลา ลูกศร" /></label><button type="button" onClick={()=>{setShowSymbols(false);editor.current?.focus();}}>ปิด</button></div>
      {filteredGroups.map(group=><section key={group.name}><h4>{group.name}</h4><div className="rich-text-symbol-grid">{group.items.map(([symbol,name])=><button type="button" key={name} title={name} aria-label={`แทรก ${name} ${symbol}`} onMouseDown={e=>e.preventDefault()} onClick={()=>insertSymbol(symbol)}>{symbol}</button>)}</div></section>)}
      {!filteredGroups.length && <p role="status">ไม่พบสัญลักษณ์ที่ค้นหา</p>}
    </div>}
    <div ref={host} hidden={preview} />
    {preview && <div className="rich-text-preview"><RichTextContent value={plainText?textToRichText(value):value} /></div>}
    <small>{length.toLocaleString('th-TH')} ตัวอักษร{plainText?' · เก็บเป็นข้อความธรรมดา':''}</small>
  </div>;
}
