import os

class Config:
    SECRET_KEY = os.environ.get('SECRET_KEY', 'dev-secret-key-change-in-prod')
    DATABASE_PATH = 'lms.db'
    UPLOAD_FOLDER = '/uploads'

# config.py
BASE_DIR = os.path.abspath(os.path.dirname(__file__))
class TestConfig(Config):
    TESTING = True
    DATABASE_PATH = os.path.join(BASE_DIR, "lms2.db")
    UPLOAD_FOLDER = os.path.join(BASE_DIR, "/test_uploads")