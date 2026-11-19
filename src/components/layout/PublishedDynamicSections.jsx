import React, {useEffect, useState} from 'react';
import {Link} from 'react-router-dom';
import DynamicSlideshow from './DynamicSlideshow';

const DEFAULT_APPEARANCE = {background:'#ffffff',text:'#10294b',accent:'#175cd3'};
const allowedColor = (value, fallback) => typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value) ? value : fallback;
/** Published dynamic sections only; no draft content is fetched by public visitors. */
export default function PublishedDynamicSections() {
  const [sections, setSections] = useState([]);
  const [albums, setAlbums] = useState([]);
  useEffect(() => {
    const controller = new AbortController();
    Promise.all([
      fetch('/api/public/dynamic-layout', {signal:controller.signal,cache:'no-cache'}).then(r=>r.ok?r.json():null),
      fetch('/api/public/activities', {signal:controller.signal,cache:'no-cache'}).then(r=>r.ok?r.json():null)
    ]).then(([layoutResult,albumResult])=>{
      if (layoutResult?.success && Array.isArray(layoutResult.data)) setSections(layoutResult.data);
      if (albumResult?.success && Array.isArray(albumResult.data)) setAlbums(albumResult.data);
    }).catch(()=>{});
    return ()=>controller.abort();
  },[]);
  const renderNodes = (items, depth=1) => items.filter(s=>s.visible).map(s => (
    <div key={s.id} className="dynamic-published-section" style={{marginTop: depth > 1 ? 16 : 0,padding:'clamp(20px,3vw,44px)',borderRadius:16,
      backgroundColor:allowedColor(s.appearance?.background,DEFAULT_APPEARANCE.background),
      color:allowedColor(s.appearance?.text,DEFAULT_APPEARANCE.text),
      '--section-accent':allowedColor(s.appearance?.accent,DEFAULT_APPEARANCE.accent)}}>
      {depth === 1 ? <h2 className="section-title" style={{color:"inherit"}}>{s.title}</h2> : <h3 style={{color:"inherit"}}>{s.title}</h3>}
      {s.description && <p className="section-subtitle" style={{color:"inherit"}}>{s.description}</p>}
      {s.type === 'slideshow' && <DynamicSlideshow slides={s.slides || []} title={s.title} interval={s.slide_interval || 6} />}
      {s.type === 'gallery' && albums.length > 0 && <div style={{
        display:'grid',gridTemplateColumns:s.layout==='carousel'?'repeat(auto-fit,minmax(min(100%,250px),1fr))':'repeat(auto-fit,minmax(min(100%,240px),1fr))',
        gap:18,marginTop:22
      }}>
        {albums.filter(album => !s.album_ids?.length || s.album_ids.includes(Number(album.id))).slice(0,8).map(album=><article className="surface-card" key={album.id} style={{overflow:'hidden'}}>
          <Link to="/activities" aria-label={'ดูอัลบั้ม '+album.title}>
            {album.cover_url && <img src={album.cover_url} alt={album.title} loading="lazy" style={{width:'100%',aspectRatio:'4/3',objectFit:'cover'}}/>}
            <div style={{padding:14}}><strong>{album.title}</strong><p>{album.event_date||''}</p></div>
          </Link>
        </article>)}
      </div>}
      {s.link && <Link to={s.link} className="btn btn-outline" style={{marginTop:12,borderColor:"var(--section-accent)",color:"inherit",backgroundColor:"transparent"}}>
        {s.type === 'gallery' ? 'ดูภาพกิจกรรมทั้งหมด' : 'ดูรายละเอียด'}
      </Link>}
      {(s.children?.length > 0) && <div style={{marginLeft:depth*12,marginTop:14}}>{renderNodes(s.children, depth+1)}</div>}
    </div>
  ));
  if (sections.length === 0) return null;
  return <section className="section" aria-label="ส่วนประกอบเว็บไซต์ที่เผยแพร่">
    <div className="container">{renderNodes(sections)}</div>
  </section>;
}
