import {ApiError} from './errors.js';
export class Repository{
 constructor(db){this.db=db;}
 need(table,id){const row=this.db.prepare(`SELECT * FROM ${table} WHERE id=?`).get(id);if(!row)throw new ApiError(404,'not_found','Resource not found.');return row;}
 booking(id,user){const booking=this.need('bookings',id);if(!['staff','admin'].includes(user.role)&&booking.guest_id!==user.id)throw new ApiError(403,'forbidden','Booking access denied.');return booking;}
 roomType(id){return this.need('room_types',id);}
 availableCount(roomTypeId,checkIn,checkOut,excludeBookingId=0){return this.db.prepare(`SELECT COUNT(*) count FROM rooms r WHERE r.room_type_id=? AND r.status!='maintenance' AND NOT EXISTS(SELECT 1 FROM bookings b WHERE b.room_id=r.id AND b.id!=? AND b.status IN ('confirmed','checked_in') AND b.check_in<? AND b.check_out>?)`).get(roomTypeId,excludeBookingId,checkOut,checkIn).count;}
 rate(roomTypeId,checkIn,checkOut){const rate=this.db.prepare('SELECT rp.*,cp.name policy_name,cp.free_hours,cp.fee_percent FROM rate_plans rp JOIN cancellation_policies cp ON cp.id=rp.policy_id WHERE rp.room_type_id=? AND rp.valid_from<=? AND rp.valid_to>=? ORDER BY rp.nightly_rate_cents LIMIT 1').get(roomTypeId,checkIn,checkOut);if(!rate)throw new ApiError(409,'rate_unavailable','No rate covers the requested dates.');return rate;}
 audit(actor,action,details){this.db.prepare('INSERT INTO audit_events(actor_id,action,details,created_at) VALUES(?,?,?,?)').run(actor,action,details,new Date().toISOString());}
 publicBooking(item){return Object.fromEntries(['id','confirmation_code','guest_id','room_type_id','room_id','check_in','check_out','adults','children','total_cents','policy_name','status','version','created_at'].map(k=>[k,item[k]]));}
}
