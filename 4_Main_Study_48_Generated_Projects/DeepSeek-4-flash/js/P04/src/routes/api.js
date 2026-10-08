import { Router } from 'express';
import accountAccess from '../controllers/accountAccessController.js';
import roomSearch from '../controllers/roomSearchController.js';
import roomDetails from '../controllers/roomDetailsController.js';
import bookingCreation from '../controllers/bookingCreationController.js';
import bookingManagement from '../controllers/bookingManagementController.js';
import staffCheckInOut from '../controllers/staffCheckInOutController.js';
import roomInventory from '../controllers/roomInventoryController.js';
import guestMessages from '../controllers/guestMessagesController.js';
import reviews from '../controllers/reviewsController.js';
import invoice from '../controllers/invoiceController.js';
import adminReports from '../controllers/adminReportsController.js';
import frontendErrors from '../controllers/frontendErrorsController.js';

const router = Router();

const modules = {
  account_access: accountAccess,
  room_search: roomSearch,
  room_details: roomDetails,
  booking_creation: bookingCreation,
  booking_management: bookingManagement,
  staff_check_in_out: staffCheckInOut,
  room_inventory: roomInventory,
  guest_messages: guestMessages,
  reviews: reviews,
  invoice_and_receipt: invoice,
  admin_reports: adminReports,
  frontend_api_integration_and_errors: frontendErrors,
};

for (const [name, handler] of Object.entries(modules)) {
  router.use(`/hotel/${name}`, handler);
}

router.get('/health', (req, res) => {
  res.json({ status: 'ok', service: 'P04 Hotel Booking System' });
});

export default router;
