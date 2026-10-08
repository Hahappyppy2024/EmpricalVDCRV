from flask import Blueprint, redirect, render_template, url_for

from ..auth import current_user, login_required
from ..services import workspace_service

pages_bp = Blueprint("pages", __name__)


@pages_bp.route("/")
def index():
    if current_user() is None:
        return redirect(url_for("accounts.login_page"))
    return redirect(url_for("pages.home"))


@pages_bp.route("/home")
@login_required
def home():
    data = workspace_service.list_for(current_user())
    return render_template("home.html", data=data)


@pages_bp.route("/about")
def about():
    return render_template("about.html")
