function isEmpty(v) { return v === undefined || v === null || (typeof v === 'string' && v.trim() === ''); }
function isInt(n) { return Number.isInteger(n); }
function isDateStr(v) { return typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v); }
function isEmail(v) { return typeof v === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
function isRating(v) { return Number.isInteger(v) && v >= 1 && v <= 5; }

export function validateRegister(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (isEmpty(body?.email)) errors.push({ field: 'email', message: 'Email is required' });
  else if (!isEmail(body.email)) errors.push({ field: 'email', message: 'Email format invalid' });
  if (isEmpty(body?.password) || (body?.password || '').length < 6) errors.push({ field: 'password', message: 'Password must be at least 6 characters' });
  if (isEmpty(body?.display_name)) errors.push({ field: 'display_name', message: 'Display name is required' });
  return errors;
}

export function validateLogin(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (isEmpty(body?.email)) errors.push({ field: 'email', message: 'Email is required' });
  if (isEmpty(body?.password)) errors.push({ field: 'password', message: 'Password is required' });
  return errors;
}

export function validateRecovery(body) {
  const errors = [];
  if (isEmpty(body?.email)) errors.push({ field: 'email', message: 'Email is required' });
  else if (!isEmail(body.email)) errors.push({ field: 'email', message: 'Email format invalid' });
  return errors;
}

export function validateRoomSearch(body) {
  const errors = [];
  if (!isDateStr(body?.check_in)) errors.push({ field: 'check_in', message: 'check_in must be YYYY-MM-DD' });
  if (!isDateStr(body?.check_out)) errors.push({ field: 'check_out', message: 'check_out must be YYYY-MM-DD' });
  if (body?.check_in && body?.check_out && body.check_in >= body.check_out) errors.push({ field: 'check_out', message: 'check_out must be after check_in' });
  if (body?.guests !== undefined && (!isInt(Number(body.guests)) || Number(body.guests) < 1)) errors.push({ field: 'guests', message: 'guests must be a positive integer' });
  if (body?.max_price !== undefined && Number.isNaN(Number(body.max_price))) errors.push({ field: 'max_price', message: 'max_price must be numeric' });
  return errors;
}

export function validateBooking(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (!isInt(Number(body?.room_id))) errors.push({ field: 'room_id', message: 'room_id is required' });
  if (!isDateStr(body?.check_in)) errors.push({ field: 'check_in', message: 'check_in must be YYYY-MM-DD' });
  if (!isDateStr(body?.check_out)) errors.push({ field: 'check_out', message: 'check_out must be YYYY-MM-DD' });
  if (body?.check_in && body?.check_out && body.check_in >= body.check_out) errors.push({ field: 'check_out', message: 'check_out must be after check_in' });
  if (!isInt(Number(body?.guests)) || Number(body?.guests) < 1) errors.push({ field: 'guests', message: 'guests must be a positive integer' });
  if (isEmpty(body?.card_number) || (body?.card_number || '').length < 4) errors.push({ field: 'card_number', message: 'card_number is required (min 4 digits)' });
  if (isEmpty(body?.card_holder)) errors.push({ field: 'card_holder', message: 'card_holder is required' });
  return errors;
}

export function validateBookingAction(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (!body?.action || !['cancel', 'modify'].includes(body.action)) errors.push({ field: 'action', message: 'action must be cancel or modify' });
  if (body.action === 'modify') {
    if (body.check_in && !isDateStr(body.check_in)) errors.push({ field: 'check_in', message: 'check_in must be YYYY-MM-DD' });
    if (body.check_out && !isDateStr(body.check_out)) errors.push({ field: 'check_out', message: 'check_out must be YYYY-MM-DD' });
    if (body.check_in && body.check_out && body.check_in >= body.check_out) errors.push({ field: 'check_out', message: 'check_out must be after check_in' });
  }
  if (body.reason !== undefined && typeof body.reason !== 'string') errors.push({ field: 'reason', message: 'reason must be a string' });
  return errors;
}

export function validateStaffStatus(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (!isInt(Number(body?.booking_id))) errors.push({ field: 'booking_id', message: 'booking_id is required' });
  if (!body?.status || !['check_in', 'check_out'].includes(body.status)) errors.push({ field: 'status', message: 'status must be check_in or check_out' });
  return errors;
}

export function validateRoomInventoryUpsert(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (isEmpty(body?.code)) errors.push({ field: 'code', message: 'code is required' });
  if (isEmpty(body?.name)) errors.push({ field: 'name', message: 'name is required' });
  if (!isInt(Number(body?.capacity)) || Number(body.capacity) < 1) errors.push({ field: 'capacity', message: 'capacity must be positive integer' });
  if (!Number.isFinite(Number(body?.nightly_rate_cents)) || Number(body.nightly_rate_cents) < 0) errors.push({ field: 'nightly_rate_cents', message: 'nightly_rate_cents must be a non-negative integer' });
  if (body.status && !['active', 'maintenance', 'inactive'].includes(body.status)) errors.push({ field: 'status', message: 'invalid status' });
  return errors;
}

export function validateRoomBlock(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (!isInt(Number(body?.room_id))) errors.push({ field: 'room_id', message: 'room_id is required' });
  if (!isDateStr(body?.start_date)) errors.push({ field: 'start_date', message: 'start_date must be YYYY-MM-DD' });
  if (!isDateStr(body?.end_date)) errors.push({ field: 'end_date', message: 'end_date must be YYYY-MM-DD' });
  if (body?.start_date && body?.end_date && body.start_date >= body.end_date) errors.push({ field: 'end_date', message: 'end_date must be after start_date' });
  if (isEmpty(body?.reason)) errors.push({ field: 'reason', message: 'reason is required' });
  return errors;
}

export function validateMessage(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (!isInt(Number(body?.booking_id))) errors.push({ field: 'booking_id', message: 'booking_id is required' });
  if (isEmpty(body?.body)) errors.push({ field: 'body', message: 'body is required' });
  return errors;
}

export function validateReview(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (!isInt(Number(body?.booking_id))) errors.push({ field: 'booking_id', message: 'booking_id is required' });
  if (!isRating(Number(body?.rating))) errors.push({ field: 'rating', message: 'rating must be integer between 1 and 5' });
  if (isEmpty(body?.body)) errors.push({ field: 'body', message: 'body is required' });
  return errors;
}

export function validateReviewModeration(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (!isInt(Number(body?.review_id))) errors.push({ field: 'review_id', message: 'review_id is required' });
  if (!body?.decision || !['approve', 'reject'].includes(body.decision)) errors.push({ field: 'decision', message: 'decision must be approve or reject' });
  return errors;
}

export function validateInvoiceRequest(body) {
  const errors = [];
  if (!isInt(Number(body?.booking_id))) errors.push({ field: 'booking_id', message: 'booking_id is required' });
  return errors;
}

export function validateReceiptRequest(body) {
  const errors = [];
  if (!isInt(Number(body?.booking_id))) errors.push({ field: 'booking_id', message: 'booking_id is required' });
  return errors;
}

export function validateAdminReport(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (body?.start_date !== undefined && !isDateStr(body.start_date)) errors.push({ field: 'start_date', message: 'start_date must be YYYY-MM-DD' });
  if (body?.end_date !== undefined && !isDateStr(body.end_date)) errors.push({ field: 'end_date', message: 'end_date must be YYYY-MM-DD' });
  if (body?.start_date && body?.end_date && body.start_date > body.end_date) errors.push({ field: 'end_date', message: 'end_date must be on or after start_date' });
  if (body?.report && !['occupancy', 'revenue', 'cancellations'].includes(body.report)) errors.push({ field: 'report', message: 'report must be occupancy, revenue, or cancellations' });
  return errors;
}

export function validateApiErrorCheck(body) {
  const errors = [];
  if (!body || typeof body !== 'object') errors.push({ field: 'body', message: 'Body is required' });
  if (!body?.scenario || !['conflict', 'validation', 'unauthorized', 'network'].includes(body.scenario)) errors.push({ field: 'scenario', message: 'scenario must be conflict, validation, unauthorized, or network' });
  return errors;
}