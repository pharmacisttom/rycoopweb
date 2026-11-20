import React,{useEffect,useState} from 'react';
import {Link} from 'react-router-dom';
import './HomeHighlightSlider.css';

export default function HomeHighlightSlider({composition}){
  const slides=Array.isArray(composition?.slides)?composition.slides:[];
  const [index,setIndex]=useState(0);
  const [paused,setPaused]=useState(false);
  const [reduced,setReduced]=useState(false);
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
  return <section className={'home-highlight-slider theme-'+(composition?.theme||'royal-blue')} aria-label="ผลงานและจุดเด่นของสหกรณ์"
    onMouseEnter={()=>setPaused(true)} onMouseLeave={()=>setPaused(false)}
    onFocusCapture={()=>setPaused(true)}
    onBlurCapture={event=>{if(!event.currentTarget.contains(event.relatedTarget))setPaused(false);}}>
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
