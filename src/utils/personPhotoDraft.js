/** Apply an uploaded staff/board image to exactly the person and section that initiated it.
 * Returns original state for cross-section or changed selection responses. */
export function applyPersonPhotoToDraft(rows, { currentSection, targetSection, targetId, targetIndex, url }) {
  if (!['staff','boards'].includes(targetSection) || currentSection !== targetSection ||
      !Array.isArray(rows) || typeof url !== 'string' || !url) return rows;
  if (!Number.isInteger(targetIndex) || targetIndex < 0 || targetIndex >= rows.length) return rows;
  const person=rows[targetIndex];
  if (!person || String(person.id) !== String(targetId)) return rows;
  return rows.map((item,index)=>index===targetIndex?{...item,image:url}:item);
}
