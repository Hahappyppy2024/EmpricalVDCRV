export function env() {
  return {
    PORT: parseInt(process.env.PORT || '3000', 10),
    NODE_ENV: process.env.NODE_ENV || 'development',
    DATABASE_PATH: process.env.DATABASE_PATH || './data/hotel.db',
    SESSION_COOKIE_NAME: process.env.SESSION_COOKIE_NAME || 'hbs_sid',
    SESSION_TTL_HOURS: parseInt(process.env.SESSION_TTL_HOURS || '24', 10),
    APP_BASE_URL: process.env.APP_BASE_URL || 'http://localhost:3000',
    HOTEL_NAME: process.env.HOTEL_NAME || 'Benchmark Hotel',
    HOTEL_TIMEZONE: process.env.HOTEL_TIMEZONE || 'UTC',
  };
}