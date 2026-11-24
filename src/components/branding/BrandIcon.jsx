import React from 'react';
import google from '../../assets/simple-icons/google.svg';
import facebook from '../../assets/simple-icons/facebook.svg';
import line from '../../assets/simple-icons/line.svg';

// Local Simple Icons vector assets, not remote CDN: stable and privacy-preserving.
// Brand marks are reserved for the corresponding service, never for generic actions.
const BRANDS = Object.freeze({google, facebook, line});
export default function BrandIcon({brand,size=18,className='',...props}) {
  const source = BRANDS[brand];
  if (!source) return null;
  return <img src={source} width={size} height={size}
    className={`ry-brand-icon ${className}`.trim()}
    alt="" aria-hidden="true" draggable="false" {...props}/>;
}
