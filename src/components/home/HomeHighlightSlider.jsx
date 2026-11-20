import React,{useEffect,useState} from 'react';
import {Link} from 'react-router-dom';
import './HomeHighlightSlider.css';

export default function HomeHighlightSlider({composition,appearance}){
  const slides=Array.isArray(composition?.slides)?composition.slides:[];
  const [index,setIndex]=useState(0);
  const [paused,setPaused]=useState(false);
  const [reduced,setReduced]=useState(false);
  const [failedImages,setFailedImages]=useState({});
  useEffect(()=>{
    const mq=window.matchMedia('(prefers-reduced-motion: reduce)');
    const update=()=>setReduced(mq.matches);
    update();mq.addEventListener('change',update);
    return()=>mq.removeEventListener('change',update);
  },[]);
  useEffect(()=>setIndex(value=>Math.min(value,Math.max(0,slides.length-1))),[slides.length]);
  useEffect(()=>{
    if(paused||reduced||slides.length<2)return undefined;
    const timer=window.setInterval(()=>setIndex(i=>(i+1)%slides.length),(composition?.autoplay_seconds||6)*1000);
    return()=>window.clearInterval(timer);
  },[paused,reduced,slides.length,composition?.autoplay_seconds]);
  if(!slides.length)return null;
  const slide=slides[index];
  const background=composition?.background||{};
  const isAllowedImage=value=>typeof value==='string' && ['/storage/uploads/','/uploads/','/assets/'].some(prefix=>value.startsWith(prefix)) && /^[a-zA-Z0-9_./-]+$/.test(value) && ['.jpg','.jpeg','.png','.webp'].some(ext=>value.toLowerCase().endsWith(ext)) && !value.includes('..') && !value.includes('//');
  const desktopCandidate=slide?.image_url || background.image_url || '';
  const mobileCandidate=slide?.mobile_image_url || background.mobile_image_url || '';
  const desktopUrl=isAllowedImage(desktopCandidate)?desktopCandidate:'';
  const mobileUrl=isAllowedImage(mobileCandidate)?mobileCandidate:'';
  const showDesktop=Boolean(desktopUrl && !failedImages[desktopUrl]);
  const showMobile=Boolean(mobileUrl && !failedImages[mobileUrl]);
  const showImage=showDesktop || showMobile;
  const overlayOpacity=Number.isInteger(background.overlay_opacity)?Math.max(35,Math.min(85,background.overlay_opacity))/100:0.6;
  const positions={center:'center center',top:'center top',bottom:'center bottom',left:'left center',right:'right center'};
  const surfaces={
    light:'linear-gradient(115deg,#365f9b,#6286c4)',
    navy:'linear-gradient(115deg,#0e2145,#23365c)',
    ocean:'linear-gradient(115deg,#065267,#0b7c92)'
  };
  const customBackground=surfaces[appearance?.surface];
  const customPadding=appearance?.spacing==='compact'?'1.5rem':appearance?.spacing==='spacious'?'5rem':null;
  return <section className={'home-highlight-slider theme-'+(composition?.theme||'royal-blue')}
    style={{...(customBackground?{background:customBackground}:{}),...(customPadding?{paddingBlock:customPadding}:{})}}
    aria-label="ผลงานและจุดเด่นของสหกรณ์"
    onMouseEnter={()=>setPaused(true)} onMouseLeave={()=>setPaused(false)}
    onFocusCapture={()=>setPaused(true)}
    onBlurCapture={event=>{if(!event.currentTarget.contains(event.relatedTarget))setPaused(false);}}>
    {showImage && <picture className="home-highlight-picture" aria-hidden="true">
      {showMobile && <source media="(max-width: 767px)" srcSet={mobileUrl}/>}
      <img className="home-highlight-photo" src={showDesktop?desktopUrl:mobileUrl} alt="" decoding="async"
        style={{objectPosition:positions[background.position]||positions.center}}
        onError={event=>{
          const current=event.currentTarget.currentSrc || event.currentTarget.src;
          const path=new URL(current,window.location.href).pathname;
          setFailedImages(previous=>({...previous,[path]:true}));
        }}/>
    </picture>}
    {showImage && <div className="home-highlight-image-overlay" aria-hidden="true"
      style={{background:`linear-gradient(90deg,rgba(8,22,48,${Math.min(0.95,overlayOpacity+0.12)}) 0%,rgba(8,22,48,${overlayOpacity}) 70%,rgba(8,22,48,${Math.max(0.35,overlayOpacity-0.08)}) 100%)`}}/>}
    <div className="container home-highlight-inner">
      <div className="home-highlight-copy" aria-live="off">
        <span className="home-highlight-kicker">ผลงานและจุดเด่นของสหกรณ์ · {index+1}/{slides.length}</span>
        <h2 key={'title-'+slide.id}>{slide.title}</h2>
        <p key={'body-'+slide.id}>{slide.body}</p>
        {slide.link&&<Link to={slide.link} className="home-highlight-link">อ่านรายละเอียดเพิ่มเติม →</Link>}
      </div>
      <div className="home-highlight-controls">
        <button type="button" onClick={()=>{setPaused(true);setIndex(i=>(i-1+slides.length)%slides.length);}} aria-label="ข้อความก่อนหน้า">‹</button>
        <div className="home-highlight-dots" aria-label="เลือกข้อความ Highlight">
          {slides.map((item,i)=><button key={item.id} type="button" aria-label={'ข้อความที่ '+(i+1)+': '+item.title}
            aria-current={i===index?'true':undefined} className={i===index?'is-active':''}
            onClick={()=>{setIndex(i);setPaused(true);}}/>)}
        </div>
        <button type="button" onClick={()=>{setPaused(true);setIndex(i=>(i+1)%slides.length);}} aria-label="ข้อความถัดไป">›</button>
        {!reduced&&<button type="button" onClick={()=>setPaused(v=>!v)} aria-label={paused?'เล่นสไลด์อัตโนมัติ':'หยุดสไลด์อัตโนมัติ'}>{paused?'▶':'Ⅱ'}</button>}
      </div>
    </div>
  </section>;
}
