import { assetUrl, imageFallback } from "../../utils/environment.js";
import React from 'react';

export default function PageSkeletonLoader() {
  return (
    <div className="coop-page-loader" role="status" aria-live="polite" aria-label="กำลังโหลดหน้าเว็บ">
      <div className="coop-logo-spinner" aria-hidden="true">
        <span className="coop-logo-spinner__ring" />
        <span className="coop-logo-spinner__halo" />
        <img
          className="coop-logo-spinner__logo"
          src={assetUrl("/assets/img/logo.webp")}
          alt=""
          onError={imageFallback}
        />
      </div>
      <p className="coop-page-loader__text">กำลังโหลดข้อมูล</p>
      <span className="coop-page-loader__dots" aria-hidden="true">•••</span>
    </div>
  );
}
