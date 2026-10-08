from flask import Blueprint

from .accounts import accounts_bp
from .attachments import attachments_bp
from .channel_management import channel_management_bp
from .connection_handling import connection_bp
from .direct_messages import dm_bp
from .errors_api import errors_bp
from .frontend_api import frontend_bp
from .history_search import history_bp
from .link_preview import link_preview_bp
from .membership import membership_bp
from .messaging import messaging_bp
from .pages import pages_bp
from .workspaces import workspaces_bp

ALL_BLUEPRINTS = [
    pages_bp,
    accounts_bp,
    workspaces_bp,
    membership_bp,
    messaging_bp,
    history_bp,
    dm_bp,
    attachments_bp,
    link_preview_bp,
    channel_management_bp,
    connection_bp,
    frontend_bp,
    errors_bp,
]
