-- Deactivate legacy duplicate loan rates so active rates match official 10 items
UPDATE interest_rates 
SET status = 'inactive' 
WHERE id IN (4, 6) 
  AND product_name IN ('เงินกู้เพื่อเหตุฉุกเฉิน', 'เงินกู้พิเศษเพื่อเคหะสงเคราะห์');
