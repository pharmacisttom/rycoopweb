// Historical terms remain selectable and newer terms can be prepared without code changes.
export const BOARD_TERMS = Array.from({ length: 50 }, (_, index) => `ชุดที่ ${50 - index}`);
export const BOARD_EXPECTED_COUNT = 15;
export const BOARD_POSITION_OPTIONS = Object.freeze([
 'ประธานกรรมการ',
 'รองประธานสหกรณ์',
 'กรรมการและเลขานุการ',
 'กรรมการและเหรัญญิก',
 'กรรมการ'
]);

export function auditBoardTerms(entries) {
 const values=Array.isArray(entries)?entries:[];
 const auditedTerms=[...new Set([...values.map(p=>p?.term).filter(Boolean), 'ชุดที่ 38', 'ชุดที่ 39'])]
   .sort((a,b)=>b.localeCompare(a,'th',{numeric:true}));
 return auditedTerms.map(term=>{
   const people=values.filter(p=>p && p.term===term && p.status!=='former');
   const assigned=BOARD_POSITION_OPTIONS.map(position=>({
     position,count:people.filter(p=>p.position===position).length
   }));
   const duplicateNames=people.map(p=>(p.name||'').trim()).filter((name,i,all)=>name&&all.indexOf(name)!==i);
   const issues=[];
   if (people.length!==BOARD_EXPECTED_COUNT) issues.push(`ชุดนี้มีกรรมการ ${people.length} จาก ${BOARD_EXPECTED_COUNT} คน`);
   for(const role of ['ประธานกรรมการ','รองประธานสหกรณ์','กรรมการและเลขานุการ','กรรมการและเหรัญญิก']){
     const count=people.filter(p=>p.position===role).length;
     if(count!==1)issues.push(`ตำแหน่ง ${role} ต้องมี 1 คน (ปัจจุบัน ${count})`);
   }
   const unknown=people.filter(p=>!BOARD_POSITION_OPTIONS.includes(p.position));
   if(unknown.length)issues.push(`มีตำแหน่งไม่ตรงมาตรฐาน ${unknown.length} คน`);
   if(duplicateNames.length)issues.push('ชื่อกรรมการซ้ำในชุดเดียวกัน');
   if(people.some(p=>!p.name?.trim()))issues.push('มีกรรมการที่ยังไม่ได้ระบุชื่อ');
   return {term,count:people.length,assigned,issues,complete:issues.length===0};
 });
}
