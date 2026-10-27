import { usePublicInformation } from '../../context/PublicContentContext';
import React, { useState, useEffect, useRef } from 'react';
import { 
  Bot, Sparkles, Send, MessageCircle, X, RefreshCw, 
  Copy, Check, Coffee, ShieldAlert, Smile, Zap, 
  Award, TrendingUp, HelpCircle, ChevronRight, Laptop,
  Lightbulb, HeartHandshake, FileText, AlertCircle, Play
} from 'lucide-react';

import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';

// Pre-defined Fun AI Excuse Database
const EXCUSES = [
  "ขออนุญาตลาช่วงบ่ายครับ เนื่องจากระบบไฟฟ้าที่บ้านขัดข้อง ช่างการไฟฟ้ากำลังเข้ามาแก้ไขเกรงว่าจะกระทบอุปกรณ์ไอที",
  "วันนี้มีอาการปวดศีรษะไมเกรนฉับพลัน แพทย์แนะนำให้พักสายตาจากหน้าจอ 4-6 ชั่วโมงครับ",
  "ขออนุญาตเข้าสาย 1 ชั่วโมงครับ เนื่องจากติดภารกิจต้องไปทำธุรกรรมสวัสดิการสหกรณ์และยืนยันตัวตนเอกสาร",
  "ขออนุญาตสลับไป WFH ช่วงบ่ายครับ ติดตามเอกสารและวิเคราะห์ข้อมูลรายงานทางการเงินเพื่อเตรียมส่งผู้บริหาร",
  "ขออภัยครับ กำลังติดประชุมออนไลน์เร่งด่วนกับคณะทำงานขับเคลื่อนยุทธศาสตร์ จะรีบตอบกลับทันทีที่เสร็จสิ้นครับ",
  "ช่วงเช้าขออนุญาตพบแพทย์ตามนัดหมายตรวจสุขภาพประจำปีครับ เอกสารรับรองแพทย์จะนำมาส่งวันพรุ่งนี้",
  "ระบบอินเทอร์เน็ตสายหลักในพื้นที่เกิดเหตุขัดข้อง กำลังรีบูตเราเตอร์และประสานผู้ให้บริการครับ"
];

// Pre-defined Fake Corporate Status
const CORPORATE_JARGONS = [
  "กำลังอยู่ระหว่างการขับเคลื่อน Roadmap เชิงกลยุทธ์เพื่อยกระดับความพึงพอใจของสมาชิกอย่างยั่งยืน",
  "กำลังทำการวิเคราะห์ข้อมูลเชิงลึก (Deep-dive Analytics) สำหรับงบดุลและสภาพคล่องไตรมาสถัดไป",
  "อยู่ระหว่างการ Sync-up ข้ามสายงานเพื่อ Re-align กระบวนการเอกสารสินเชื่อให้เป็น Zero-touch Workflow",
  "กำลังจัดทำร่าง Framework เพื่อ Optimize กระบวนการบริหารความเสี่ยงและ Capital Adequacy Ratio",
  "กำลังตรวจสอบ Cross-validation ของสมุดบัญชีแยกประเภทเพื่อเตรียมสรุป Executive Dashboard"
];

// Pre-defined Lunch Recommendations in Rayong
const RAYONG_FOODS = [
  { name: "ข้าวผัดปูก้อน & ต้มยำกุ้งน้ำข้น", place: "ร้านป้าหวาน หาดแสงจันทร์ (เลียบชายหาด)", mood: "🌊 อิ่มอร่อยริมทะเล ลมเย็นๆ สบายใจ" },
  { name: "ส้มตำปูม้าดอง & ไก่ย่างเขาสวนกวาง", place: "ร้านเจ๊ยินดี หาดแม่รำพึง", mood: "🌶️ แซ่บจัดจ้าน เรียกพลังยามบ่าย" },
  { name: "ก๋วยเตี๋ยวกั้ง & ทะเลรวมมิตรต้มยำ", place: "ร้านก๋วยเตี๋ยวเรือนแก้ว ถนนสุขุมวิท", mood: "🦐 ซดน้ำร้อนๆ คล่องคอ วัตถุดิบสดจากทะเล" },
  { name: "ข้าวมันไก่ตอนสูตรเบตง", place: "ร้านโกข้าวมันไก่ ใกล้ศูนย์ราชการระยอง", mood: "🍗 เนื้อไก่นุ่มฉ่ำ น้ำจิ้มเต้าเจี้ยวเด็ด" },
  { name: "แกงป่าปลาเห็ดโคน & ผัดเผ็ดหมูป่า", place: "ร้านครัวแกงป่าระยอง", mood: "🔥 รสชาติจัดจ้านฉบับพื้นบ้านระยองแท้ๆ" },
  { name: "กาแฟ Dirty & ขนมปังปิ้งเนยนม", place: "คาเฟ่ริมหาดแหลมเจริญ", mood: "☕ นั่งจิบชิลๆ แอบเช็คอีเมลแบบสโลว์ไลฟ์" }
];

// Pre-defined Lucky Fortunes
const FORTUNES = [
  { stick: "ใบที่ ๑ - มหาเศรษฐีปันผล", text: "ดวงการเงินพุ่งแรง ปันผลหุ้น 5.25% กำลังจะเข้ากระเป๋า การงานราบรื่น มีเกณฑ์ได้ลาพักผ่อนแบบไม่มีใครตาม!", luckyNo: "88, 52" },
  { stick: "ใบที่ ๒ - โชคลาภลอยมา", text: "วันนี้มีผู้ใหญ่เมตตา เลี้ยงชานมไข่มุกฟรี การเจรจาขอกู้สหกรณ์ผ่านฉลุยไร้อุปสรรค", luckyNo: "19, 91" },
  { stick: "ใบที่ ๓ - สบายใจไร้งานด่วน", text: "วันนี้คอมพิวเตอร์ทำงานเสถียร หัวหน้าไม่อยู่ อารมณ์แจ่มใสตลอดวัน เหมาะแก่การวางแผนเที่ยว", luckyNo: "36, 63" },
  { stick: "ใบที่ ๔ - รวยเงียบๆ", text: "เงินฝากงอกเงย ดอกเบี้ยทบต้น แนะนำสะสมหุ้นเพิ่มเดือนละนิด อนาคตเศรษฐีวัยเกษียณแน่นอน", luckyNo: "48, 92" }
];

export default function AIChatWidget() {
  const { INTEREST_RATES } = usePublicInformation();
  const { user } = useAuth();
  const { confirmDialog, toast } = useToast();
  const [isOpen, setIsOpen] = useState(false);
  const [activeTab, setActiveTab] = useState('chat'); // 'chat' | 'generators' | 'fortune' | 'game'
  const [bossKeyActive, setBossKeyActive] = useState(false);

  // Chat State
  const [inputMsg, setInputMsg] = useState('');
  const [messages, setMessages] = useState([
    {
      sender: 'bot',
      text: `สวัสดีครับคุณ ${user?.name || 'สมาชิก'}! 🤖✨ ผมคือ **RayongCoop AI Copilot** ผู้ช่วยอัจฉริยะ (และผู้สมรู้ร่วมคิดในการแอบอู้งาน 555)\n\nสอบถามข้อมูลสหกรณ์ ดอกเบี้ย เงินกู้ หรือใช้ **เครื่องมือ Gen ข้ออ้าง / สุ่มเซียมซี / เมนูมื้อเที่ยง** ได้เลยครับ!`,
      time: 'เมื่อสักครู่'
    }
  ]);
  const [isTyping, setIsTyping] = useState(false);
  const chatBottomRef = useRef(null);

  // Generator States
  const [generatedExcuse, setGeneratedExcuse] = useState('');
  const [generatedJargon, setGeneratedJargon] = useState('');
  const [generatedFood, setGeneratedFood] = useState(null);
  const [generatedFortune, setGeneratedFortune] = useState(null);
  const [copiedId, setCopiedId] = useState(null);

  // Slacker Clicker Mini-game state
  const [slackerCoins, setSlackerCoins] = useState(() => {
    try {
      return parseInt(localStorage.getItem('coop_slacker_coins') || '100', 10);
    } catch (e) {
      return 100;
    }
  });
  const [slackerLevel, setSlackerLevel] = useState('พนักงานฝึกงานแอบจิบกาแฟ');

  useEffect(() => {
    if (chatBottomRef.current && isOpen) {
      chatBottomRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [messages, isOpen, isTyping]);

  useEffect(() => {
    if (slackerCoins >= 1000) setSlackerLevel('👑 ประธานสหกรณ์สายชิลล์ระดับตำนาน');
    else if (slackerCoins >= 500) setSlackerLevel('💼 ผู้เชี่ยวชาญการเปิด Excel บังหน้าจอ');
    else if (slackerCoins >= 300) setSlackerLevel('☕ รองหัวหน้าแผนกจิบชา 2 ชั่วโมง');
    else if (slackerCoins >= 150) setSlackerLevel('💻 เจ้าหน้าที่กดคีย์บอร์ดให้ดูเหมือนพิมพ์งาน');
    else setSlackerLevel('🌱 พนักงานฝึกงานแอบจิบกาแฟ');
    try {
      localStorage.setItem('coop_slacker_coins', slackerCoins.toString());
    } catch (e) {}
  }, [slackerCoins]);

  // Handle Send Message in Chatbot
  const handleSendMessage = (textToSend) => {
    const query = textToSend || inputMsg;
    if (!query.trim()) return;

    const userMessage = {
      sender: 'user',
      text: query,
      time: new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })
    };

    setMessages((prev) => [...prev, userMessage]);
    setInputMsg('');
    setIsTyping(true);

    // Simulate smart bot response
    setTimeout(() => {
      let reply = '';
      const lower = query.toLowerCase();

      if (lower.includes('กู้') || lower.includes('ดอกเบี้ยกู้')) {
        reply = "ข้อมูลวงเงิน อัตราดอกเบี้ย และสิทธิเงินเฉลี่ยคืนขึ้นอยู่กับประเภทสมาชิกและประกาศที่มีผล กรุณาติดต่อสหกรณ์ 038-967-409 เพื่อตรวจสอบก่อนยื่นคำขอ";
      } else if (lower.includes('ฝาก') || lower.includes('ดอกเบี้ยฝาก')) {
        reply = INTEREST_RATES.deposits.map(item => `${item.type}: ${item.rate} ต่อปี เปิดบัญชีขั้นต่ำ ${item.minDeposit}`).join('\n') + '\n' + INTEREST_RATES.depositSource.note + '\nแหล่งข้อมูล: ' + INTEREST_RATES.depositSource.url;
      } else if (lower.includes('ปันผล') || lower.includes('เฉลี่ยคืน')) {
        reply = "ยอดปันผลและเงินเฉลี่ยคืนต้องตรวจสอบตามปีบัญชีและข้อมูลของสมาชิก กรุณาเข้าสู่ระบบสมาชิกเพื่อตรวจสอบยอดจริง หรือติดต่อสหกรณ์ 038-967-409";
      } else if (lower.includes('อู้') || lower.includes('ขี้เกียจ') || lower.includes('เบื่อ') || lower.includes('หนีงาน')) {
        reply = `🤫 **คู่มือการอู้งานฉบับมืออาชีพ:**\n1. กดปุ่ม **"🚨 หัวหน้ามา!"** ด้านบนเพื่อแปลงร่างหน้าจอเป็น Excel ทันที!\n2. ไปที่แท็บ **"🪄 Gen ข้ออ้าง"** เพื่อก๊อปข้ออ้างลางานสุดเนียนไปส่ง\n3. จิ้มแท็บ **"🍱 กินไรดี"** หาของอร่อยกินย้อมใจ\n\nจำไว้ครับ: *"งานคือเงิน แต่พักผ่อนคือชีวิต"* 5555`;
      } else if (lower.includes('มุก') || lower.includes('ตลก') || lower.includes('ขำ')) {
        const jokes = [
          "ทำงานเหมือนทำบุญ... ได้ผลบุญชาติหน้า แต่ได้ความเหนื่อยชาตินี้! 😂",
          "เงินเดือนเหมือนน้ำแข็ง... เพิ่งเปิดตู้เย็นออกมา ยังไม่ทันตั้งโต๊ะก็ละลายหมดแล้ว 💸",
          "สหกรณ์บอกให้ 'ออมวันละนิด'... ผมเลยออมวันละ 5 บาท แต่กินชาบูวันละ 500! 🍲",
          "ถาม: ทำไมเจ้าหน้าที่สหกรณ์ใจดีจัง?\nตอบ: เพราะถ้าใจร้าย สมาชิกจะหนีไปกู้นอกระบบหมดจ้า 555"
        ];
        reply = jokes[Math.floor(Math.random() * jokes.length)];
      } else if (lower.includes('หวย') || lower.includes('เลข') || lower.includes('ดวง')) {
        reply = `🔮 **เซียมซี AI แนะนำเลขมงคลสหกรณ์:**\nเลขเด็ดประจำวัน: **${Math.floor(10 + Math.random() * 89)}** และ **${Math.floor(100 + Math.random() * 899)}**\nเคล็ดลับ: ซื้อสลากแต่พอดี แล้วเอาเงินที่เหลือมาฝากสหกรณ์ได้ดอกเบี้ยแน่นอน 100% ครับ!`;
      } else {
        reply = `รับทราบครับ! 🤖 คุณกำลังสนใจเรื่อง **"${query}"** ใช่ไหมครับ\n\nหากต้องการคำนวณเงินกู้, ยื่นสวัสดิการ, เช็คใบเสร็จ e-Receipt หรือต้องการให้ Gen ข้ออ้างลางาน เลือกใช้งานแถบเมนูด้านบนได้เลยครับผม! ✨`;
      }

      setMessages((prev) => [
        ...prev,
        {
          sender: 'bot',
          text: reply,
          time: new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })
        }
      ]);
      setIsTyping(false);
    }, 600);
  };

  // Generator Trigger Handlers
  const handleGenExcuse = () => {
    const item = EXCUSES[Math.floor(Math.random() * EXCUSES.length)];
    setGeneratedExcuse(item);
  };

  const handleGenJargon = () => {
    const item = CORPORATE_JARGONS[Math.floor(Math.random() * CORPORATE_JARGONS.length)];
    setGeneratedJargon(item);
  };

  const handleGenFood = () => {
    const item = RAYONG_FOODS[Math.floor(Math.random() * RAYONG_FOODS.length)];
    setGeneratedFood(item);
  };

  const handleGenFortune = () => {
    const item = FORTUNES[Math.floor(Math.random() * FORTUNES.length)];
    setGeneratedFortune(item);
  };

  const copyToClipboard = (text, id) => {
    navigator.clipboard.writeText(text);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  return (
    <>
      {/* Floating Toggle Button (Bottom Left or Next to Dock) */}
      {!isOpen && (
        <button
          onClick={() => setIsOpen(true)}
          style={{
            position: 'fixed',
            left: '1.25rem',
            bottom: '1.5rem',
            zIndex: 995,
            background: 'linear-gradient(135deg, #0284c7 0%, #0d9488 100%)',
            color: '#ffffff',
            border: 'none',
            borderRadius: '50px',
            padding: '0.75rem 1.25rem',
            display: 'flex',
            alignItems: 'center',
            gap: '0.65rem',
            boxShadow: '0 8px 25px rgba(2, 132, 199, 0.45)',
            cursor: 'pointer',
            transition: 'all 0.25s cubic-bezier(0.4, 0, 0.2, 1)',
            fontWeight: 700,
            fontSize: '0.9rem'
          }}
          className="ai-chat-fab hover-scale"
          title="เปิด RayongCoop AI Copilot & สารพัด Gen"
        >
          <div style={{
            background: '#ffffff',
            color: '#0284c7',
            width: '28px',
            height: '28px',
            borderRadius: '50%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            boxShadow: '0 2px 8px rgba(0,0,0,0.15)'
          }}>
            <Bot size={18} />
          </div>
          <span>Coop AI & Gen Hub</span>
          <span style={{
            background: 'rgba(255, 255, 255, 0.25)',
            padding: '0.15rem 0.45rem',
            borderRadius: '10px',
            fontSize: '0.7rem',
            fontWeight: 800
          }}>
            AI ✨
          </span>
        </button>
      )}

      {/* Main AI Chat & Generator Modal Box */}
      {isOpen && (
        <div style={{
          position: 'fixed',
          left: '1.25rem',
          bottom: '1.5rem',
          width: 'calc(100vw - 2.5rem)',
          maxWidth: '430px',
          height: '560px',
          maxHeight: 'calc(100vh - 3rem)',
          zIndex: 1000,
          background: 'var(--bg-surface)',
          borderRadius: 'var(--radius-xl)',
          border: '1px solid var(--border-subtle)',
          boxShadow: '0 20px 40px rgba(0, 0, 0, 0.25)',
          display: 'flex',
          flexDirection: 'column',
          overflow: 'hidden',
          animation: 'fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1)'
        }}>

          {/* =========================================================
              BOSS KEY: FAKE SPREADSHEET / EXCEL OVERLAY (แอบอู้งาน)
              ========================================================= */}
          {bossKeyActive ? (
            <div style={{
              flex: 1,
              background: '#ffffff',
              color: '#1e293b',
              display: 'flex',
              flexDirection: 'column',
              fontFamily: 'Segoe UI, Tahoma, sans-serif'
            }}>
              {/* Fake Excel Header */}
              <div style={{ background: '#107c41', color: '#ffffff', padding: '0.5rem 1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.85rem', fontWeight: 600 }}>
                  <Laptop size={16} />
                  <span>รายงานสรุปงบการเงิน_สหกรณ์ออมทรัพย์ระยอง_Q1_2567.xlsx - Excel</span>
                </div>
                <button
                  onClick={() => setBossKeyActive(false)}
                  style={{ background: 'rgba(255,255,255,0.2)', border: 'none', color: '#fff', padding: '0.2rem 0.5rem', borderRadius: '4px', fontSize: '0.75rem', cursor: 'pointer' }}
                >
                  🟢 ปลอดภัยแล้ว (กลับสู่แชท)
                </button>
              </div>

              {/* Fake Spreadsheet Data */}
              <div style={{ padding: '0.75rem', fontSize: '0.78rem', overflowX: 'auto', flex: 1, background: '#f8fafc' }}>
                <div style={{ fontWeight: 700, marginBottom: '0.5rem', color: '#0f172a' }}>
                  ตารางที่ 1.4 การวิเคราะห์ความเสี่ยงและอัตราส่วนทุนต่อสินทรัพย์เสี่ยง (BIS Ratio)
                </div>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.75rem', background: '#ffffff' }}>
                  <thead>
                    <tr style={{ background: '#e2e8f0', textAlign: 'left' }}>
                      <th style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>หมวดหมู่</th>
                      <th style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>เป้าหมาย (M฿)</th>
                      <th style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>ผลงานจริง</th>
                      <th style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>สถานะ</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>ทุนเรือนหุ้น</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>1,850.00</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>1,894.20</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px', color: 'green', fontWeight: 700 }}>102.4% Pass</td>
                    </tr>
                    <tr>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>เงินรับฝากรวม</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>1,200.00</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>1,245.80</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px', color: 'green', fontWeight: 700 }}>103.8% Pass</td>
                    </tr>
                    <tr>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>สินเชื่อคงเหลือ</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>2,100.00</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>2,080.50</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>99.1% Normal</td>
                    </tr>
                    <tr>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>NPL Ratio</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>&lt; 0.50%</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px' }}>0.12%</td>
                      <td style={{ border: '1px solid #cbd5e1', padding: '4px 6px', color: 'green', fontWeight: 700 }}>Excellent</td>
                    </tr>
                  </tbody>
                </table>
                <div style={{ marginTop: '0.75rem', color: '#64748b', fontSize: '0.72rem' }}>
                  * สูตรคำนวณ = SUM(Assets) * RiskWeightFactor / Tier1Capital
                </div>
              </div>
            </div>
          ) : (
            <>
              {/* Normal Header */}
              <div style={{
                background: 'linear-gradient(135deg, #0284c7 0%, #0d9488 100%)',
                color: '#ffffff',
                padding: '0.9rem 1.15rem',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center'
              }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                  <div style={{
                    width: '32px',
                    height: '32px',
                    borderRadius: '50%',
                    background: '#ffffff',
                    color: '#0284c7',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    boxShadow: '0 2px 6px rgba(0,0,0,0.2)'
                  }}>
                    <Bot size={20} />
                  </div>
                  <div>
                    <div style={{ fontWeight: 800, fontSize: '0.95rem', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
                      <span>RayongCoop AI Copilot</span>
                    </div>
                    <div style={{ fontSize: '0.72rem', opacity: 0.9 }}>
                      ผู้ช่วยอัจฉริยะ & สารพัด Gen คลายเครียด
                    </div>
                  </div>
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                  {/* Boss Alarm Button */}
                  <button
                    onClick={() => setBossKeyActive(true)}
                    className="btn btn-sm"
                    style={{
                      background: 'rgba(239, 68, 68, 0.9)',
                      color: '#ffffff',
                      border: 'none',
                      padding: '0.25rem 0.5rem',
                      borderRadius: '6px',
                      fontSize: '0.72rem',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '0.25rem',
                      fontWeight: 700
                    }}
                    title="หัวหน้าเดินมา! สลับเป็นหน้าจอ Excel ทันที"
                  >
                    <ShieldAlert size={13} />
                    <span>หัวหน้ามา!</span>
                  </button>

                  <button
                    onClick={() => setIsOpen(false)}
                    style={{
                      background: 'rgba(255,255,255,0.2)',
                      border: 'none',
                      color: '#ffffff',
                      width: '28px',
                      height: '28px',
                      borderRadius: '50%',
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center'
                    }}
                  >
                    <X size={16} />
                  </button>
                </div>
              </div>

              {/* Navigation Tabs Bar */}
              <div style={{
                display: 'flex',
                background: 'var(--bg-subtle)',
                borderBottom: '1px solid var(--border-subtle)',
                padding: '0.35rem 0.5rem',
                gap: '0.25rem'
              }}>
                <button
                  onClick={() => setActiveTab('chat')}
                  style={{
                    flex: 1,
                    padding: '0.4rem 0.5rem',
                    fontSize: '0.78rem',
                    fontWeight: activeTab === 'chat' ? 700 : 500,
                    borderRadius: '6px',
                    border: 'none',
                    background: activeTab === 'chat' ? 'var(--primary-600)' : 'transparent',
                    color: activeTab === 'chat' ? '#ffffff' : 'var(--text-main)',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '0.3rem'
                  }}
                >
                  <MessageCircle size={13} />
                  <span>แชท AI</span>
                </button>

                <button
                  onClick={() => setActiveTab('generators')}
                  style={{
                    flex: 1,
                    padding: '0.4rem 0.5rem',
                    fontSize: '0.78rem',
                    fontWeight: activeTab === 'generators' ? 700 : 500,
                    borderRadius: '6px',
                    border: 'none',
                    background: activeTab === 'generators' ? 'var(--primary-600)' : 'transparent',
                    color: activeTab === 'generators' ? '#ffffff' : 'var(--text-main)',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '0.3rem'
                  }}
                >
                  <Sparkles size={13} />
                  <span>🪄 Gen ข้ออ้าง</span>
                </button>

                <button
                  onClick={() => setActiveTab('fortune')}
                  style={{
                    flex: 1,
                    padding: '0.4rem 0.5rem',
                    fontSize: '0.78rem',
                    fontWeight: activeTab === 'fortune' ? 700 : 500,
                    borderRadius: '6px',
                    border: 'none',
                    background: activeTab === 'fortune' ? 'var(--primary-600)' : 'transparent',
                    color: activeTab === 'fortune' ? '#ffffff' : 'var(--text-main)',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '0.3rem'
                  }}
                >
                  <Award size={13} />
                  <span>เซียมซี/หวย</span>
                </button>

                <button
                  onClick={() => setActiveTab('game')}
                  style={{
                    flex: 1,
                    padding: '0.4rem 0.5rem',
                    fontSize: '0.78rem',
                    fontWeight: activeTab === 'game' ? 700 : 500,
                    borderRadius: '6px',
                    border: 'none',
                    background: activeTab === 'game' ? 'var(--primary-600)' : 'transparent',
                    color: activeTab === 'game' ? '#ffffff' : 'var(--text-main)',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '0.3rem'
                  }}
                >
                  <Coffee size={13} />
                  <span>กดเก็บหุ้น</span>
                </button>
              </div>

              {/* TAB 1: CHATBOT */}
              {activeTab === 'chat' && (
                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
                  {/* Messages Area */}
                  <div style={{
                    flex: 1,
                    overflowY: 'auto',
                    padding: '1rem',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: '0.85rem'
                  }}>
                    {messages.map((msg, idx) => {
                      const isBot = msg.sender === 'bot';
                      return (
                        <div
                          key={idx}
                          style={{
                            display: 'flex',
                            justifyContent: isBot ? 'flex-start' : 'flex-end',
                            gap: '0.5rem'
                          }}
                        >
                          {isBot && (
                            <div style={{
                              width: '26px',
                              height: '26px',
                              borderRadius: '50%',
                              background: 'var(--primary-600)',
                              color: '#fff',
                              display: 'flex',
                              alignItems: 'center',
                              justifyContent: 'center',
                              flexShrink: 0,
                              marginTop: '2px'
                            }}>
                              <Bot size={15} />
                            </div>
                          )}

                          <div style={{
                            maxWidth: '82%',
                            background: isBot ? 'var(--bg-subtle)' : 'linear-gradient(135deg, var(--primary-600) 0%, var(--accent-teal-dark) 100%)',
                            color: isBot ? 'var(--text-main)' : '#ffffff',
                            padding: '0.75rem 0.95rem',
                            borderRadius: isBot ? '4px 14px 14px 14px' : '14px 4px 14px 14px',
                            fontSize: '0.86rem',
                            lineHeight: 1.5,
                            border: isBot ? '1px solid var(--border-subtle)' : 'none',
                            whiteSpace: 'pre-line'
                          }}>
                            {msg.text}
                            <div style={{
                              fontSize: '0.68rem',
                              opacity: 0.65,
                              marginTop: '0.3rem',
                              textAlign: isBot ? 'left' : 'right'
                            }}>
                              {msg.time}
                            </div>
                          </div>
                        </div>
                      );
                    })}

                    {isTyping && (
                      <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', color: 'var(--text-muted)', fontSize: '0.78rem' }}>
                        <Bot size={16} style={{ color: 'var(--primary-600)' }} />
                        <span>AI กำลังประมวลผลคำตอบ...</span>
                      </div>
                    )}
                    <div ref={chatBottomRef} />
                  </div>

                  {/* Quick Suggested Prompts */}
                  <div style={{
                    display: 'flex',
                    gap: '0.35rem',
                    overflowX: 'auto',
                    padding: '0.4rem 0.75rem',
                    background: 'var(--bg-subtle)',
                    borderTop: '1px solid var(--border-subtle)'
                  }}>
                    {[
                      '💰 ดอกเบี้ยเงินกู้',
                      '📈 เงินปันผลล่าสุด',
                      '😂 เล่ามุกตลกการเงิน',
                      '🤫 ทริคอู้งานเนียนๆ',
                      '🔮 เลขเด็ดวันนี้'
                    ].map((p, i) => (
                      <button
                        key={i}
                        onClick={() => handleSendMessage(p)}
                        style={{
                          whiteSpace: 'nowrap',
                          background: 'var(--bg-surface)',
                          border: '1px solid var(--border-subtle)',
                          padding: '0.25rem 0.6rem',
                          borderRadius: '12px',
                          fontSize: '0.72rem',
                          color: 'var(--text-main)',
                          cursor: 'pointer'
                        }}
                      >
                        {p}
                      </button>
                    ))}
                  </div>

                  {/* Input Form */}
                  <form
                    onSubmit={(e) => {
                      e.preventDefault();
                      handleSendMessage();
                    }}
                    style={{
                      display: 'flex',
                      padding: '0.65rem 0.75rem',
                      background: 'var(--bg-surface)',
                      borderTop: '1px solid var(--border-subtle)',
                      gap: '0.5rem'
                    }}
                  >
                    <input
                      type="text"
                      className="form-control"
                      value={inputMsg}
                      onChange={(e) => setInputMsg(e.target.value)}
                      placeholder="พิมพ์คำถาม หรือคุยกับ AI..."
                      style={{ fontSize: '0.85rem', padding: '0.5rem 0.75rem' }}
                    />
                    <button
                      type="submit"
                      className="btn btn-primary"
                      style={{ padding: '0.5rem 0.85rem', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                    >
                      <Send size={15} />
                    </button>
                  </form>
                </div>
              )}

              {/* TAB 2: GENERATORS (ข้ออ้าง / ศัพท์หรู / เมนูอาหาร) */}
              {activeTab === 'generators' && (
                <div style={{ flex: 1, overflowY: 'auto', padding: '1rem', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                  
                  {/* Excuse Generator Card */}
                  <div className="surface-card" style={{ padding: '1rem', borderRadius: '12px', border: '1px solid var(--border-subtle)' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                      <span style={{ fontWeight: 700, fontSize: '0.88rem', display: 'flex', alignItems: 'center', gap: '0.35rem', color: 'var(--accent-rose)' }}>
                        <ShieldAlert size={16} />
                        <span>AI สุ่มข้ออ้างลางาน / ลาป่วยสุดเนียน</span>
                      </span>
                      <button onClick={handleGenExcuse} className="btn btn-sm btn-subtle" style={{ fontSize: '0.75rem', padding: '0.2rem 0.55rem' }}>
                        <RefreshCw size={12} style={{ marginRight: '0.25rem' }} /> สุ่มใหม่
                      </button>
                    </div>

                    <div style={{
                      background: 'var(--bg-subtle)',
                      padding: '0.75rem',
                      borderRadius: '8px',
                      fontSize: '0.82rem',
                      lineHeight: 1.5,
                      color: 'var(--text-main)',
                      minHeight: '48px',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      gap: '0.5rem'
                    }}>
                      <span>{generatedExcuse || 'กดปุ่ม "สุ่มใหม่" เพื่อให้ AI ช่วยแต่งข้ออ้างส่งหัวหน้า/กลุ่มงานแบบมืออาชีพ'}</span>
                      {generatedExcuse && (
                        <button
                          onClick={() => copyToClipboard(generatedExcuse, 'excuse')}
                          className="btn btn-sm btn-outline"
                          style={{ padding: '0.25rem 0.45rem', flexShrink: 0 }}
                          title="คัดลอกข้อความ"
                        >
                          {copiedId === 'excuse' ? <Check size={14} style={{ color: 'green' }} /> : <Copy size={14} />}
                        </button>
                      )}
                    </div>
                  </div>

                  {/* Corporate Jargon Generator */}
                  <div className="surface-card" style={{ padding: '1rem', borderRadius: '12px', border: '1px solid var(--border-subtle)' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                      <span style={{ fontWeight: 700, fontSize: '0.88rem', display: 'flex', alignItems: 'center', gap: '0.35rem', color: 'var(--primary-700)' }}>
                        <Zap size={16} />
                        <span>AI สุ่มสเตตัสการทำงานแบบดูโปร</span>
                      </span>
                      <button onClick={handleGenJargon} className="btn btn-sm btn-subtle" style={{ fontSize: '0.75rem', padding: '0.2rem 0.55rem' }}>
                        <RefreshCw size={12} style={{ marginRight: '0.25rem' }} /> สุ่มใหม่
                      </button>
                    </div>

                    <div style={{
                      background: 'var(--bg-subtle)',
                      padding: '0.75rem',
                      borderRadius: '8px',
                      fontSize: '0.82rem',
                      lineHeight: 1.5,
                      color: 'var(--text-main)',
                      minHeight: '48px',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      gap: '0.5rem'
                    }}>
                      <span>{generatedJargon || 'กดปุ่มสุ่ม เพื่อเอาข้อความหรูๆ ไปตอบเวลาโดนถามว่า "ตอนนี้ทำอะไรอยู่" 555'}</span>
                      {generatedJargon && (
                        <button
                          onClick={() => copyToClipboard(generatedJargon, 'jargon')}
                          className="btn btn-sm btn-outline"
                          style={{ padding: '0.25rem 0.45rem', flexShrink: 0 }}
                          title="คัดลอกข้อความ"
                        >
                          {copiedId === 'jargon' ? <Check size={14} style={{ color: 'green' }} /> : <Copy size={14} />}
                        </button>
                      )}
                    </div>
                  </div>

                  {/* Lunch Menu in Rayong Generator */}
                  <div className="surface-card" style={{ padding: '1rem', borderRadius: '12px', border: '1px solid var(--border-subtle)' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                      <span style={{ fontWeight: 700, fontSize: '0.88rem', display: 'flex', alignItems: 'center', gap: '0.35rem', color: 'var(--accent-gold-dark)' }}>
                        <Coffee size={16} />
                        <span>🍱 เที่ยงนี้กินไรดีในระยอง?</span>
                      </span>
                      <button onClick={handleGenFood} className="btn btn-sm btn-subtle" style={{ fontSize: '0.75rem', padding: '0.2rem 0.55rem' }}>
                        <RefreshCw size={12} style={{ marginRight: '0.25rem' }} /> สุ่มเมนู
                      </button>
                    </div>

                    {generatedFood ? (
                      <div style={{ background: 'var(--bg-subtle)', padding: '0.75rem', borderRadius: '8px', fontSize: '0.82rem' }}>
                        <div style={{ fontWeight: 700, color: 'var(--primary-800)', fontSize: '0.92rem' }}>{generatedFood.name}</div>
                        <div style={{ color: 'var(--text-muted)', marginTop: '0.2rem' }}>📍 {generatedFood.place}</div>
                        <div style={{ color: 'var(--accent-teal-dark)', marginTop: '0.2rem', fontStyle: 'italic' }}>{generatedFood.mood}</div>
                      </div>
                    ) : (
                      <div style={{ background: 'var(--bg-subtle)', padding: '0.75rem', borderRadius: '8px', fontSize: '0.82rem', color: 'var(--text-muted)', textAlign: 'center' }}>
                        กดสุ่มเมนูเพื่อตัดสินใจมื้อเที่ยงแบบไม่ต้องคิดเอง!
                      </div>
                    )}
                  </div>

                </div>
              )}

              {/* TAB 3: FORTUNE & LUCKY NUMBERS */}
              {activeTab === 'fortune' && (
                <div style={{ flex: 1, overflowY: 'auto', padding: '1.25rem', display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center', gap: '1rem' }}>
                  <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'var(--accent-gold-light)', color: 'var(--accent-gold-dark)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <Award size={26} />
                  </div>

                  <div>
                    <h4 style={{ fontSize: '1.05rem', margin: 0, color: 'var(--primary-900)', fontWeight: 800 }}>
                      เซียมซีดวงการเงิน & เลขเด็ดสหกรณ์
                    </h4>
                    <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '0.25rem', margin: 0 }}>
                      เสี่ยงดวงตรวจชะตาการเงิน ปันผล และความโชคดีประจำวัน
                    </p>
                  </div>

                  <button
                    onClick={handleGenFortune}
                    className="btn btn-gold"
                    style={{ padding: '0.65rem 1.5rem', fontWeight: 700, display: 'flex', alignItems: 'center', gap: '0.4rem' }}
                  >
                    <RefreshCw size={15} />
                    <span>เขย่าเซียมซีสุ่มดวง</span>
                  </button>

                  {generatedFortune && (
                    <div className="surface-card animate-fade-in" style={{
                      padding: '1.25rem',
                      borderRadius: '14px',
                      border: '2px dashed var(--accent-gold)',
                      background: 'rgba(217, 119, 6, 0.04)',
                      width: '100%',
                      textAlign: 'center'
                    }}>
                      <div style={{ fontWeight: 800, color: 'var(--accent-gold-dark)', fontSize: '1rem', marginBottom: '0.5rem' }}>
                        🎋 {generatedFortune.stick}
                      </div>
                      <p style={{ fontSize: '0.85rem', color: 'var(--text-main)', lineHeight: 1.6, margin: '0 0 0.75rem 0' }}>
                        {generatedFortune.text}
                      </p>
                      <div style={{ background: 'var(--bg-surface)', padding: '0.5rem', borderRadius: '8px', display: 'inline-block', border: '1px solid var(--border-subtle)' }}>
                        <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>เลขมงคลประจำวัน: </span>
                        <strong style={{ fontSize: '1.1rem', color: 'var(--accent-rose)' }}>{generatedFortune.luckyNo}</strong>
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* TAB 4: SLACKER CLICKER MINI-GAME */}
              {activeTab === 'game' && (
                <div style={{ flex: 1, overflowY: 'auto', padding: '1.25rem', display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center', gap: '0.85rem' }}>
                  <div style={{ background: 'var(--bg-subtle)', padding: '0.5rem 1rem', borderRadius: '20px', fontSize: '0.8rem', color: 'var(--primary-800)', fontWeight: 700 }}>
                    {slackerLevel}
                  </div>

                  <div>
                    <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>ยอดเงินหุ้นสะสมจากการแอบอู้:</div>
                    <div style={{ fontSize: '2.2rem', fontWeight: 900, color: 'var(--accent-emerald-dark)', fontFamily: 'var(--font-display)' }}>
                      {slackerCoins.toLocaleString()} <span style={{ fontSize: '1rem' }}>บาท</span>
                    </div>
                  </div>

                  {/* Big Click Button */}
                  <button
                    onClick={() => setSlackerCoins((c) => c + 10)}
                    className="hover-scale"
                    style={{
                      width: '110px',
                      height: '110px',
                      borderRadius: '50%',
                      background: 'radial-gradient(circle, #10b981 0%, #047857 100%)',
                      border: '4px solid #ffffff',
                      boxShadow: '0 10px 25px rgba(16, 185, 129, 0.4)',
                      color: '#ffffff',
                      display: 'flex',
                      flexDirection: 'column',
                      alignItems: 'center',
                      justifyContent: 'center',
                      cursor: 'pointer',
                      margin: '0.5rem 0'
                    }}
                  >
                    <TrendingUp size={32} />
                    <span style={{ fontSize: '0.75rem', fontWeight: 800, marginTop: '0.2rem' }}>+10 หุ้น</span>
                  </button>

                  <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', margin: 0 }}>
                    💡 จิ้มปุ่มเพื่อสะสมหุ้น ทุกคลิกคือเงินปันผลวัยเกษียณ ยิ่งคลิกเยอะยศยิ่งสูง!
                  </p>

                  <button
                    onClick={async () => {
                      const confirmed = await confirmDialog({
                        title: 'รีเซ็ตแต้มหุ้นสะสม',
                        message: 'ต้องการรีเซ็ตยอดหุ้นสะสมในมินิเกมเป็นค่าเริ่มต้น (100 หุ้น) หรือไม่?',
                        type: 'danger'
                      });
                      if (confirmed) {
                        setSlackerCoins(100);
                        toast.info('รีเซ็ตยอดหุ้นสะสมเรียบร้อยแล้ว');
                      }
                    }}
                    style={{ background: 'transparent', border: 'none', color: 'var(--text-muted)', fontSize: '0.72rem', cursor: 'pointer', textDecoration: 'underline' }}
                  >
                    รีเซ็ตแต้ม
                  </button>
                </div>
              )}
            </>
          )}

        </div>
      )}
    </>
  );
}
