import React, { useState } from 'react';
import SafeImage from './SafeImage';
import { mediaLibraryApi } from '../../services/mediaLibraryApi';
import './ActivitySlideshow.css';
import './NewsGalleryMediaPicker.css';

export default function NewsGalleryEditor({ images, newsId, onChange, disabled, onBusyChange }) {
  const [error, setError] = useState('');
  const [reading, setReading] = useState(false);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [catalog, setCatalog] = useState([]);
  const [catalogError, setCatalogError] = useState('');
  const [catalogLoading, setCatalogLoading] = useState(false);
  const [catalogPage, setCatalogPage] = useState(1);
  const [catalogPages, setCatalogPages] = useState(1);
  async function openCatalog(page = 1) {
    setCatalogLoading(true); setCatalogError(''); setPickerOpen(true);
    try {
      const result = await mediaLibraryApi.list({ page, perPage: 12 });
      setCatalog(result.data || []);setCatalogPage(page);setCatalogPages(result.pagination?.pages || 1);
    } catch (e) { setCatalogError(e.message || 'ไม่สามารถโหลดคลังรูปภาพ'); }
    finally { setCatalogLoading(false); }
  }
  function chooseCatalog(image) {
    if (images.length >= 20) { setCatalogError('อัลบั้มมีได้ไม่เกิน 20 ภาพ'); return; }
    if (images.some(item => item.src === image.url)) { setCatalogError('ภาพนี้อยู่ในอัลบั้มแล้ว'); return; }
    onChange([...images, { media_id: image.id, src: image.url, thumbnail: image.preview_url || image.url, alt: image.alt_text || '', caption: image.caption || '' }]);
    setPickerOpen(false);
  }
  async function add(event) {
    const files = Array.from(event.target.files || []); event.target.value = '';
    setError('');
    if (!files.length) return;
    if (files.length + images.length > 20) { setError('อัลบั้มมีได้ไม่เกิน 20 ภาพ'); return; }
    const pendingBytes = images.filter(image => image.src.startsWith('data:')).reduce((sum, image) => sum + image.src.length * .75, 0);
    if (files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)
      || pendingBytes + files.reduce((sum, file) => sum + file.size, 0) > 12 * 1024 * 1024) {
      setError('รองรับ JPEG, PNG, WebP ภาพละไม่เกิน 5 MB และภาพใหม่รวมไม่เกิน 12 MB'); return;
    }
    setReading(true); onBusyChange?.(true);
    try {
      const added = await Promise.all(files.map(file => new Promise((resolve, reject) => {
        const reader = new FileReader(); reader.onerror = () => reject(new Error('อ่านภาพไม่ได้ กรุณาเลือกไฟล์ใหม่'));
        reader.onload = () => resolve({ src: String(reader.result), alt: '', caption: '' }); reader.readAsDataURL(file);
      })));
      onChange([...images, ...added]);
    } catch (exception) { setError(exception.message); }
    finally { setReading(false); onBusyChange?.(false); }
  }
  function imagePreviewUrl(image) {
    const source = image.thumbnail || image.src || '';
    const match = typeof source === 'string' && source.match(/\/news\/gallery\/([a-f0-9]{32}-(?:src|thumbnail)\.webp)(?:$|[?#])/i);
    if (newsId && match) return `/api/admin/news/${encodeURIComponent(newsId)}/gallery/${match[1]}`;
    return source;
  }
  function move(index, offset) {
    const next = [...images]; [next[index], next[index + offset]] = [next[index + offset], next[index]]; onChange(next);
  }
  return <fieldset className="gallery-editor" disabled={disabled || reading}>
    <legend>อัลบั้มภาพกิจกรรม</legend>
    <p>ภาพแรกเป็นภาพปก เรียงลำดับและระบุคำบรรยายก่อนส่งตรวจ (สูงสุด 20 ภาพ)</p>
    <label>เพิ่มภาพหลายภาพ<input type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={add} /></label>
    <p>ภาพละไม่เกิน 5 MB ภาพใหม่รวมไม่เกิน 12 MB ต่อการบันทึก ระบบย่อภาพและนำพิกัด EXIF ออกก่อนเผยแพร่</p>
    <button type="button" onClick={() => openCatalog()} disabled={disabled || reading || images.length >= 20}>เลือกภาพจากคลังรูปภาพ</button>
    {pickerOpen && <div className="ry-media-picker-backdrop" role="presentation"><section className="ry-media-picker" role="dialog" aria-modal="true" aria-label="เลือกรูปภาพจากคลัง">
      <h3>คลังรูปภาพ</h3><button type="button" onClick={() => setPickerOpen(false)}>ปิดหน้าต่าง</button>
      {catalogError && <p role="alert">{catalogError}</p>}
      {catalogLoading ? <p role="status">กำลังโหลด...</p> : <div className="ry-media-picker-grid">{catalog.map(image => <button key={image.id} type="button" onClick={() => chooseCatalog(image)} title={image.alt_text || image.original_name}><img src={image.preview_url || image.url} alt={image.alt_text || image.original_name} loading="lazy" /><span>{image.original_name}</span></button>)}</div>}
      <nav aria-label="หน้าคลังภาพ"><button type="button" disabled={catalogLoading || catalogPage <= 1} onClick={() => openCatalog(catalogPage - 1)}>ก่อนหน้า</button><span> หน้า {catalogPage} / {Math.max(1,catalogPages)} </span><button type="button" disabled={catalogLoading || catalogPage >= catalogPages} onClick={() => openCatalog(catalogPage + 1)}>ถัดไป</button></nav>
    </section></div>}
    {reading && <p role="status">กำลังอ่านไฟล์ภาพ…</p>}{error && <p role="alert">{error}</p>}
    <ol>{images.map((image, index) => <li key={index}>
      {/^data:image\/(?:jpeg|png|webp);base64,[a-z0-9+/=]+$/i.test(image.src) ? <img src={image.src} alt={image.alt || `ภาพที่ ${index + 1}`} loading="lazy" /> : <SafeImage src={imagePreviewUrl(image)} alt={image.alt || `ภาพที่ ${index + 1}`} loading="lazy" />}
      <strong>{index === 0 ? 'ภาพปก' : `ภาพที่ ${index + 1}`}</strong>
      <label>ข้อความทดแทนภาพ<input className="form-control" maxLength={255} value={image.alt} onChange={event => onChange(images.map((item, position) => position === index ? { ...item, alt: event.target.value } : item))} /></label>
      <label>คำบรรยาย<textarea className="form-control" maxLength={1000} value={image.caption} onChange={event => onChange(images.map((item, position) => position === index ? { ...item, caption: event.target.value } : item))} /></label>
      <div className="gallery-editor-actions">
        <button type="button" disabled={index === 0} onClick={() => move(index, -1)}>เลื่อนขึ้น</button>
        <button type="button" disabled={index === images.length - 1} onClick={() => move(index, 1)}>เลื่อนลง</button>
        <button type="button" disabled={index === 0} onClick={() => onChange([image, ...images.filter((_, position) => position !== index)])}>ใช้เป็นภาพปก</button>
        <button type="button" onClick={() => {
          if (window.confirm('นำภาพออกจากอัลบั้มนี้? ไฟล์เดิมจะยังเก็บไว้เพื่อไม่กระทบข่าวอื่นและประวัติการแก้ไข')) onChange(images.filter((_, position) => position !== index));
        }}>นำภาพออก</button>
      </div>
    </li>)}</ol>
  </fieldset>;
}
