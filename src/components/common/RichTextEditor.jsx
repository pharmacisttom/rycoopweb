import React, { useEffect, useRef, useState, useId } from 'react';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import RichTextContent from './RichTextContent';
import { safeRichText, textToRichText } from '../../utils/richText';
import './RichTextEditor.css';

export default function RichTextEditor({ value = '', onChange, label = 'คำบรรยาย', disabled = false, plainText = false }) {
  const host = useRef(null), editor = useRef(null), callback = useRef(onChange);
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
    host.current.querySelectorAll('.ql-toolbar button').forEach(button => { button.type='button'; const kind=[...button.classList].find(c=>c.startsWith('ql-')); const names={'ql-bold':'ตัวหนา','ql-italic':'ตัวเอียง','ql-underline':'ขีดเส้นใต้','ql-strike':'ขีดฆ่า','ql-list':button.value==='ordered'?'รายการลำดับเลข':'รายการหัวข้อ','ql-blockquote':'ข้อความอ้างอิง','ql-link':'เพิ่มลิงก์','ql-clean':'ล้างรูปแบบ'}; button.setAttribute('aria-label',names[kind] || 'จัดรูปแบบ'); });
    return () => {quill.off('text-change',changed);editor.current=null;host.current.replaceChildren();};
  }, [plainText]);
  useEffect(() => { editor.current?.enable(!disabled); }, [disabled]);
  useEffect(() => {
    const quill=editor.current;if(!quill)return;
    const current=plainText?quill.getText().replace(/\n$/,''):quill.getText().trim()?safeRichText(quill.getSemanticHTML()):'';
    if(value!==current){const selection=quill.getSelection();const html=plainText || !/<\/?[a-z][\s\S]*>/i.test(value)?textToRichText(value):safeRichText(value);quill.clipboard.dangerouslyPasteHTML(html,'silent');if(selection)quill.setSelection(Math.min(selection.index,quill.getLength()-1),0,'silent');setLength(quill.getText().trim().length);}
  }, [value,plainText]);
  return <div className="rich-text-editor">
    <div className="rich-text-actions"><span id={id}>{label}</span><button type="button" disabled={disabled} onClick={()=>editor.current?.history.undo()}>ย้อนกลับ</button><button type="button" disabled={disabled} onClick={()=>editor.current?.history.redo()}>ทำซ้ำ</button><button type="button" aria-pressed={preview} onClick={()=>setPreview(p=>!p)}>{preview?'กลับไปแก้ไข':'ดูตัวอย่าง'}</button></div>
    <div ref={host} hidden={preview} />
    {preview && <div className="rich-text-preview"><RichTextContent value={plainText?textToRichText(value):value} /></div>}
    <small>{length.toLocaleString('th-TH')} ตัวอักษร{plainText?' · เก็บเป็นข้อความธรรมดา':''}</small>
  </div>;
}
