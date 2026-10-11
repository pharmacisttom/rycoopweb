import React from 'react';
import { safeRichText, textToRichText } from '../../utils/richText';
import './RichTextEditor.css';

export default function RichTextContent({ value = '', className = '' }) {
  const html = /<\/?[a-z][\s\S]*>/i.test(value) ? value : textToRichText(value);
  return <div className={`rich-text-content ${className}`} dangerouslySetInnerHTML={{ __html: safeRichText(html) }} />;
}
