from flask import Blueprint, render_template

from utils.auth import get_current_user


page_bp = Blueprint('page', __name__)


@page_bp.route('/')
def home():
    return render_template('home.html', current_user=get_current_user())
