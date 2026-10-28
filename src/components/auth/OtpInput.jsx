import React, { useEffect, useRef, useState } from 'react';

export const OTP_LENGTH = 6;

/**
 * Sanitize raw input or clipboard paste into up to 6 numeric digits (0-9).
 * Never logs or persists OTP values.
 */
export function sanitizeOtpDigits(raw, maxLength = OTP_LENGTH) {
  if (typeof raw !== 'string' && typeof raw !== 'number') {
    return '';
  }
  return String(raw).replace(/\D+/g, '').slice(0, maxLength);
}

/**
 * Validate whether a string is an exact 6-digit numeric OTP code.
 */
export function isValidSixDigitOtp(value) {
  return typeof value === 'string' && /^[0-9]{6}$/.test(value);
}

/**
 * Accessible Six-Digit OTP Input Component (RYCOOPWEB 3.0)
 *
 * Features:
 * - Six visually separate digit boxes with a single accessible underlying input (#mfa-code)
 * - Numeric inputMode & mobile one-time-code autofill support
 * - Full paste handling (strips spaces, hyphens, and non-numeric characters)
 * - Keyboard navigation (ArrowLeft, ArrowRight, Backspace, Delete, Home, End)
 * - Explicit Confirm action required (never auto-submits on 6th digit)
 * - Zero storage or console logging of OTP secrets
 */
export default function OtpInput({
  id = 'mfa-code',
  value = '',
  onChange,
  disabled = false,
  invalid = false,
  autoFocus = true,
  describedBy,
  label = 'รหัสยืนยัน 6 หลักจากแอป Authenticator',
}) {
  const inputRef = useRef(null);
  const [isFocused, setIsFocused] = useState(false);
  const [activeSlot, setActiveSlot] = useState(0);

  const cleanValue = sanitizeOtpDigits(value, OTP_LENGTH);
  const digits = Array.from({ length: OTP_LENGTH }, (_, idx) => cleanValue[idx] || '');

  useEffect(() => {
    if (autoFocus && !disabled && inputRef.current && typeof inputRef.current.focus === 'function') {
      inputRef.current.focus();
    }
  }, [autoFocus, disabled]);

  useEffect(() => {
    setActiveSlot(Math.min(cleanValue.length, OTP_LENGTH - 1));
  }, [cleanValue.length]);

  const handleInputChange = (event) => {
    if (disabled) return;
    const nextClean = sanitizeOtpDigits(event.target.value, OTP_LENGTH);
    if (typeof onChange === 'function') {
      onChange(nextClean);
    }
    setActiveSlot(Math.min(nextClean.length, OTP_LENGTH - 1));
  };

  const handlePaste = (event) => {
    if (disabled) return;
    const pastedText = event.clipboardData?.getData('text') ?? '';
    const pastedDigits = sanitizeOtpDigits(pastedText, OTP_LENGTH);
    if (pastedDigits.length > 0) {
      event.preventDefault();
      if (typeof onChange === 'function') {
        onChange(pastedDigits);
      }
      setActiveSlot(Math.min(pastedDigits.length, OTP_LENGTH - 1));
    }
  };

  const handleKeyDown = (event) => {
    if (disabled) return;
    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      setActiveSlot((prev) => Math.max(0, prev - 1));
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      setActiveSlot((prev) => Math.min(OTP_LENGTH - 1, prev + 1));
    } else if (event.key === 'Home') {
      event.preventDefault();
      setActiveSlot(0);
    } else if (event.key === 'End') {
      event.preventDefault();
      setActiveSlot(Math.min(cleanValue.length, OTP_LENGTH - 1));
    } else if (event.key === 'Backspace' && cleanValue.length > 0) {
      // Allow native backspace or trim last digit cleanly
      setActiveSlot(Math.max(0, cleanValue.length - 1));
    }
  };

  const handleSlotClick = (index) => {
    if (disabled) return;
    setActiveSlot(Math.min(index, cleanValue.length));
    if (inputRef.current && typeof inputRef.current.focus === 'function') {
      inputRef.current.focus();
    }
  };

  return (
    <div className="mfa-otp-field">
      <label htmlFor={id} className="mfa-field-label">
        <span>{label}</span>
        <span aria-live="polite" style={{ fontSize: '0.76rem', color: 'var(--mfa-text-muted)', fontWeight: 600 }}>
          {cleanValue.length}/{OTP_LENGTH} หลัก
        </span>
      </label>

      <div className="mfa-otp-wrapper">
        <input
          ref={inputRef}
          id={id}
          name="code"
          type="text"
          inputMode="numeric"
          autoComplete="one-time-code"
          pattern="[0-9]{6}"
          maxLength={OTP_LENGTH}
          value={cleanValue}
          onChange={handleInputChange}
          onPaste={handlePaste}
          onKeyDown={handleKeyDown}
          onFocus={() => setIsFocused(true)}
          onBlur={() => setIsFocused(false)}
          disabled={disabled}
          required
          aria-label={label}
          aria-invalid={invalid ? 'true' : 'false'}
          aria-describedby={describedBy}
          className="mfa-otp-native-input"
        />

        <div className="mfa-otp-grid" aria-hidden="true">
          {digits.map((digit, index) => {
            const isSlotActive = isFocused && index === activeSlot;
            const isFilled = digit !== '';
            const slotClasses = [
              'mfa-otp-slot',
              isFilled ? 'filled' : '',
              isSlotActive ? 'active' : '',
              invalid ? 'invalid' : '',
              disabled ? 'disabled' : '',
            ]
              .filter(Boolean)
              .join(' ');

            return (
              <div
                key={index}
                data-slot-index={index}
                onClick={() => handleSlotClick(index)}
                className={slotClasses}
              >
                {digit || (isSlotActive ? <span className="mfa-otp-caret" /> : '')}
              </div>
            );
          })}
        </div>
      </div>

      <p id={describedBy || `${id}-hint`} className="mfa-otp-hint">
        รองรับการพิมพ์ตัวเลข 0–9 หรือวางรหัส (Paste) 6 หลักจากแอป Authenticator
      </p>
    </div>
  );
}

